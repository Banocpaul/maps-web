<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupFileCipher
{
    private const MAGIC = "MAPSBK01";

    private const CHUNK_SIZE = 1048576;

    public function encrypt(string $sourcePath, string $destinationPath): void
    {
        $this->ensureSodiumIsAvailable();
        $key = $this->key();
        $input = fopen($sourcePath, 'rb');
        $output = fopen($destinationPath, 'wb');

        if ($input === false || $output === false) {
            $this->closeResources($input, $output);
            throw new RuntimeException('Unable to open a backup encryption stream.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            $this->writeAll($output, self::MAGIC.$header);

            do {
                $plain = fread($input, self::CHUNK_SIZE);

                if ($plain === false) {
                    throw new RuntimeException('Unable to read the compressed backup.');
                }

                $isFinal = feof($input);
                $tag = $isFinal
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $plain,
                    '',
                    $tag
                );

                $this->writeAll($output, pack('N', strlen($encrypted)).$encrypted);
            } while (! $isFinal);
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    public function decrypt(string $sourcePath, string $destinationPath): void
    {
        $this->ensureSodiumIsAvailable();
        $key = $this->key();
        $input = fopen($sourcePath, 'rb');
        $output = fopen($destinationPath, 'wb');

        if ($input === false || $output === false) {
            $this->closeResources($input, $output);
            throw new RuntimeException('Unable to open a backup decryption stream.');
        }

        try {
            $magic = $this->readExact($input, strlen(self::MAGIC));

            if (! hash_equals(self::MAGIC, $magic)) {
                throw new RuntimeException('The file is not a valid M.A.P.S. backup.');
            }

            $header = $this->readExact(
                $input,
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
            );
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $foundFinalTag = false;

            while (! feof($input)) {
                $lengthBytes = fread($input, 4);

                if ($lengthBytes === '' && feof($input)) {
                    break;
                }

                if ($lengthBytes === false || strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('The encrypted backup is truncated.');
                }

                $length = unpack('Nlength', $lengthBytes)['length'];
                $maximumLength = self::CHUNK_SIZE
                    + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                    || $length > $maximumLength) {
                    throw new RuntimeException('The encrypted backup has an invalid frame.');
                }

                $encrypted = $this->readExact($input, $length);
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull(
                    $state,
                    $encrypted
                );

                if ($result === false) {
                    throw new RuntimeException('Backup authentication failed.');
                }

                [$plain, $tag] = $result;
                $this->writeAll($output, $plain);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $foundFinalTag = true;

                    if (! feof($input)) {
                        $extra = fread($input, 1);

                        if ($extra !== '' && $extra !== false) {
                            throw new RuntimeException('Unexpected data follows the backup.');
                        }
                    }

                    break;
                }
            }

            if (! $foundFinalTag) {
                throw new RuntimeException('The encrypted backup is missing its final authentication tag.');
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    private function key(): string
    {
        $encoded = (string) config('backup.encryption_key');

        if ($encoded === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is not configured.');
        }

        try {
            $key = sodium_base642bin(
                $encoded,
                SODIUM_BASE64_VARIANT_ORIGINAL,
                ''
            );
        } catch (\SodiumException $exception) {
            throw new RuntimeException(
                'BACKUP_ENCRYPTION_KEY must be a valid base64 value.',
                previous: $exception
            );
        }

        if (strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must decode to exactly 32 bytes.');
        }

        return $key;
    }

    private function ensureSodiumIsAvailable(): void
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The PHP sodium extension is required for encrypted backups.');
        }
    }

    private function readExact($stream, int $length): string
    {
        $result = '';

        while (strlen($result) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($result));

            if ($chunk === false) {
                throw new RuntimeException('Unable to read the encrypted backup.');
            }

            $result .= $chunk;
        }

        if (strlen($result) !== $length) {
            throw new RuntimeException('The encrypted backup is truncated.');
        }

        return $result;
    }

    private function writeAll($stream, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($stream, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the encrypted backup.');
            }

            $offset += $written;
        }
    }

    private function closeResources(mixed ...$resources): void
    {
        foreach ($resources as $resource) {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}
