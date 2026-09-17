<?php

namespace App\Services\Backup;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function __construct(
        private readonly BackupFileCipher $cipher
    ) {
    }

    public function create(string $type = 'manual', ?int $createdBy = null): DatabaseBackup
    {
        $this->assertType($type);
        $connection = $this->connectionConfiguration();
        $disk = (string) config('backup.disk');
        $uuid = (string) Str::uuid();
        $timestamp = now('Asia/Manila')->format('Y-m-d_H-i-s');
        $filename = "maps-{$type}-{$timestamp}-{$uuid}.sql.gz.enc";
        $directory = trim((string) config('backup.directory'), '/');
        $path = ($directory !== '' ? $directory.'/' : '')
            .now('Asia/Manila')->format('Y/m').'/'.$filename;

        $backup = DatabaseBackup::create([
            'uuid' => $uuid,
            'type' => $type,
            'status' => 'processing',
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'database_name' => $connection['database'],
            'created_by' => $createdBy,
            'started_at' => now(),
            'metadata' => [
                'application' => config('app.name'),
                'environment' => app()->environment(),
                'format' => 'maps-secretstream-v1',
                'compression' => 'gzip',
            ],
        ]);

        $temporaryDirectory = $this->temporaryDirectory($uuid);
        $sqlPath = $temporaryDirectory.'/database.sql';
        $gzipPath = $temporaryDirectory.'/database.sql.gz';
        $encryptedPath = $temporaryDirectory.'/'.$filename;
        $credentialsPath = $temporaryDirectory.'/mysql-client.cnf';

        try {
            $this->writeCredentialsFile($credentialsPath, $connection);
            $this->dumpDatabase($credentialsPath, $connection['database'], $sqlPath);
            $this->gzip($sqlPath, $gzipPath);
            $this->cipher->encrypt($gzipPath, $encryptedPath);

            $sha256 = hash_file('sha256', $encryptedPath);

            if ($sha256 === false) {
                throw new RuntimeException('Unable to calculate the backup checksum.');
            }

            $stream = fopen($encryptedPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the completed backup for upload.');
            }

            try {
                $stored = Storage::disk($disk)->put($path, $stream, [
                    'visibility' => 'private',
                    'ContentType' => 'application/octet-stream',
                ]);
            } finally {
                fclose($stream);
            }

            if (! $stored || ! Storage::disk($disk)->exists($path)) {
                throw new RuntimeException('The encrypted backup could not be uploaded.');
            }

            $backup->update([
                'status' => 'completed',
                'size_bytes' => filesize($encryptedPath) ?: null,
                'sha256' => $sha256,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            return $backup->fresh(['creator']);
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 65000, ''),
                'completed_at' => now(),
            ]);

            throw $exception;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    public function verify(DatabaseBackup $backup, ?int $verifiedBy = null): bool
    {
        $this->assertUsable($backup);
        $temporaryDirectory = $this->temporaryDirectory($backup->uuid.'-verify');
        $encryptedPath = $temporaryDirectory.'/'.$backup->filename;
        $gzipPath = $temporaryDirectory.'/verified.sql.gz';

        try {
            $this->downloadToPath($backup, $encryptedPath);
            $actualHash = hash_file('sha256', $encryptedPath);

            if ($actualHash === false || ! hash_equals((string) $backup->sha256, $actualHash)) {
                throw new RuntimeException('Backup checksum verification failed.');
            }

            $this->cipher->decrypt($encryptedPath, $gzipPath);
            $this->assertValidGzip($gzipPath);

            $backup->update([
                'verified_at' => now(),
                'verified_by' => $verifiedBy,
                'error_message' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            $backup->update([
                'error_message' => Str::limit($exception->getMessage(), 65000, ''),
            ]);

            throw $exception;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    public function restore(DatabaseBackup $backup, ?int $restoredBy = null): void
    {
        $this->verify($backup, $restoredBy);
        $connection = $this->connectionConfiguration();
        $temporaryDirectory = $this->temporaryDirectory($backup->uuid.'-restore');
        $encryptedPath = $temporaryDirectory.'/'.$backup->filename;
        $gzipPath = $temporaryDirectory.'/restore.sql.gz';
        $sqlPath = $temporaryDirectory.'/restore.sql';
        $credentialsPath = $temporaryDirectory.'/mysql-client.cnf';

        try {
            $this->downloadToPath($backup, $encryptedPath);
            $this->cipher->decrypt($encryptedPath, $gzipPath);
            $this->gunzip($gzipPath, $sqlPath);
            $this->writeCredentialsFile($credentialsPath, $connection);

            Artisan::call('down', [
                '--retry' => 60,
                '--refresh' => 15,
                '--secret' => Str::random(40),
            ]);

            try {
                $this->importDatabase(
                    $credentialsPath,
                    $connection['database'],
                    $sqlPath
                );
            } finally {
                Artisan::call('up');
            }

            $this->preserveRecordAfterRestore($backup, $restoredBy);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    public function preserveRecordAfterRestore(
        DatabaseBackup $backup,
        ?int $restoredBy = null,
        bool $markRestored = true
    ): DatabaseBackup {
        return DatabaseBackup::updateOrCreate(
            ['uuid' => $backup->uuid],
            [
                'type' => $backup->type,
                'status' => 'completed',
                'disk' => $backup->disk,
                'path' => $backup->path,
                'filename' => $backup->filename,
                'size_bytes' => $backup->size_bytes,
                'sha256' => $backup->sha256,
                'database_name' => $backup->database_name,
                'created_by' => null,
                'started_at' => $backup->started_at,
                'completed_at' => $backup->completed_at,
                'verified_at' => now(),
                'verified_by' => null,
                'restored_at' => $markRestored ? now() : $backup->restored_at,
                'restored_by' => $markRestored ? $restoredBy : $backup->restored_by,
                'error_message' => null,
                'metadata' => $backup->metadata,
            ]
        );
    }

    public function delete(DatabaseBackup $backup): void
    {
        if ($backup->path !== null) {
            Storage::disk($backup->disk)->delete($backup->path);
        }

        $backup->delete();
    }

    public function downloadStream(DatabaseBackup $backup)
    {
        $this->assertUsable($backup);
        $stream = Storage::disk($backup->disk)->readStream((string) $backup->path);

        if ($stream === false) {
            throw new RuntimeException('The backup file could not be downloaded.');
        }

        return $stream;
    }

    public function prune(): int
    {
        $scheduled = DatabaseBackup::query()
            ->where('type', 'scheduled')
            ->where('status', 'completed')
            ->oldest('created_at')
            ->get();

        $keep = [];
        $daily = max(0, (int) config('backup.retention.daily'));
        $weekly = max(0, (int) config('backup.retention.weekly'));
        $monthly = max(0, (int) config('backup.retention.monthly'));

        foreach ($scheduled->sortByDesc('created_at') as $backup) {
            $created = $backup->created_at->copy()->timezone('Asia/Manila');

            if ($created->greaterThanOrEqualTo(now('Asia/Manila')->subDays($daily))) {
                $keep[$backup->id] = true;
            }
        }

        foreach ($scheduled->sortByDesc('created_at')->groupBy(
            fn (DatabaseBackup $backup) => $backup->created_at->format('o-W')
        )->take($weekly) as $group) {
            $keep[$group->first()->id] = true;
        }

        foreach ($scheduled->sortByDesc('created_at')->groupBy(
            fn (DatabaseBackup $backup) => $backup->created_at->format('Y-m')
        )->take($monthly) as $group) {
            $keep[$group->first()->id] = true;
        }

        $deleted = 0;

        foreach ($scheduled as $backup) {
            if (isset($keep[$backup->id])) {
                continue;
            }

            $this->delete($backup);
            $deleted++;
        }

        return $deleted;
    }

    private function dumpDatabase(
        string $credentialsPath,
        string $database,
        string $destinationPath
    ): void {
        $output = fopen($destinationPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Unable to create the SQL dump file.');
        }

        $process = new Process([
            (string) config('backup.dump_binary'),
            '--defaults-extra-file='.$credentialsPath,
            '--quick',
            '--skip-lock-tables',
            '--skip-add-locks',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--no-tablespaces',
            $database,
        ]);
        $process->setTimeout((int) config('backup.timeout_seconds'));

        try {
            $process->run(function (string $type, string $buffer) use ($output): void {
                if ($type === Process::OUT) {
                    fwrite($output, $buffer);
                }
            });
        } finally {
            fclose($output);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Database dump failed: '.trim($process->getErrorOutput())
            );
        }

        if (! is_file($destinationPath) || filesize($destinationPath) === 0) {
            throw new RuntimeException('The database dump was empty.');
        }
    }

    private function importDatabase(
        string $credentialsPath,
        string $database,
        string $sqlPath
    ): void {
        $input = fopen($sqlPath, 'rb');

        if ($input === false) {
            throw new RuntimeException('Unable to open the SQL restore file.');
        }

        $process = new Process([
            (string) config('backup.mysql_binary'),
            '--defaults-extra-file='.$credentialsPath,
            '--default-character-set=utf8mb4',
            $database,
        ]);
        $process->setTimeout((int) config('backup.timeout_seconds'));
        $process->setInput($input);

        try {
            $process->run();
        } finally {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Database restore failed: '.trim($process->getErrorOutput())
            );
        }
    }

    private function connectionConfiguration(): array
    {
        $name = config('database.default');
        $connection = config("database.connections.{$name}");

        if (! is_array($connection)
            || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backups require a MySQL-compatible connection.');
        }

        foreach (['host', 'port', 'database', 'username'] as $key) {
            if (! isset($connection[$key]) || $connection[$key] === '') {
                throw new RuntimeException("Database connection value [{$key}] is missing.");
            }
        }

        return $connection;
    }

    private function writeCredentialsFile(string $path, array $connection): void
    {
        $lines = [
            '[client]',
            'host='.$this->iniValue((string) $connection['host']),
            'port='.$this->iniValue((string) $connection['port']),
            'user='.$this->iniValue((string) $connection['username']),
            'password='.$this->iniValue((string) ($connection['password'] ?? '')),
        ];

        $sslCa = collect($connection['options'] ?? [])
            ->first(fn (mixed $value) => is_string($value) && $value !== '');

        if (is_string($sslCa) && $sslCa !== '') {
            $lines[] = 'ssl-ca='.$this->iniValue($sslCa);
        }

        if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            throw new RuntimeException('Unable to create temporary database credentials.');
        }

        chmod($path, 0600);
    }

    private function iniValue(string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new RuntimeException('A database credential contains an invalid line break.');
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function gzip(string $sourcePath, string $destinationPath): void
    {
        $input = fopen($sourcePath, 'rb');
        $output = gzopen($destinationPath, 'wb9');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('Unable to open the backup compression stream.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1048576);

                if ($chunk === false || gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Unable to compress the database backup.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function gunzip(string $sourcePath, string $destinationPath): void
    {
        $input = gzopen($sourcePath, 'rb');
        $output = fopen($destinationPath, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                gzclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('Unable to open the backup decompression stream.');
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1048576);

                if ($chunk === false || fwrite($output, $chunk) === false) {
                    throw new RuntimeException('Unable to decompress the database backup.');
                }
            }
        } finally {
            gzclose($input);
            fclose($output);
        }
    }

    private function assertValidGzip(string $path): void
    {
        $stream = gzopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The decrypted backup is not valid gzip data.');
        }

        try {
            $sample = gzread($stream, 8192);
        } finally {
            gzclose($stream);
        }

        if ($sample === false || trim($sample) === '') {
            throw new RuntimeException('The decrypted SQL backup is empty.');
        }
    }

    private function downloadToPath(DatabaseBackup $backup, string $destinationPath): void
    {
        $input = $this->downloadStream($backup);
        $output = fopen($destinationPath, 'wb');

        if ($output === false) {
            fclose($input);
            throw new RuntimeException('Unable to create a temporary backup file.');
        }

        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('Unable to download the backup file.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function temporaryDirectory(string $suffix): string
    {
        $path = storage_path(
            'app/backup-temp/'.$suffix.'-'.Str::lower(Str::random(10))
        );
        File::ensureDirectoryExists($path, 0700, true);

        return $path;
    }

    private function assertUsable(DatabaseBackup $backup): void
    {
        if (! $backup->isCompleted() || $backup->path === null || $backup->sha256 === null) {
            throw new RuntimeException('Only completed backups can be used.');
        }

        if (! Storage::disk($backup->disk)->exists($backup->path)) {
            throw new RuntimeException('The backup file is missing from storage.');
        }
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, ['manual', 'scheduled', 'pre_restore'], true)) {
            throw new RuntimeException('Invalid database backup type.');
        }
    }
}
