<?php

namespace Tests\Unit;

use App\Services\Backup\BackupFileCipher;
use RuntimeException;
use Tests\TestCase;

class BackupFileCipherTest extends TestCase
{
    public function test_it_encrypts_and_decrypts_a_backup_stream(): void
    {
        $this->requireSodium();
        config()->set('backup.encryption_key', base64_encode(random_bytes(32)));
        $source = tempnam(sys_get_temp_dir(), 'maps-source-');
        $encrypted = tempnam(sys_get_temp_dir(), 'maps-encrypted-');
        $decrypted = tempnam(sys_get_temp_dir(), 'maps-decrypted-');
        $content = random_bytes(2500000);

        try {
            file_put_contents($source, $content);
            $cipher = app(BackupFileCipher::class);
            $cipher->encrypt($source, $encrypted);
            $cipher->decrypt($encrypted, $decrypted);

            $this->assertNotSame($content, file_get_contents($encrypted));
            $this->assertSame($content, file_get_contents($decrypted));
        } finally {
            @unlink($source);
            @unlink($encrypted);
            @unlink($decrypted);
        }
    }

    public function test_it_rejects_a_modified_encrypted_backup(): void
    {
        $this->requireSodium();
        config()->set('backup.encryption_key', base64_encode(random_bytes(32)));
        $source = tempnam(sys_get_temp_dir(), 'maps-source-');
        $encrypted = tempnam(sys_get_temp_dir(), 'maps-encrypted-');
        $decrypted = tempnam(sys_get_temp_dir(), 'maps-decrypted-');

        try {
            file_put_contents($source, 'protected M.A.P.S. backup data');
            $cipher = app(BackupFileCipher::class);
            $cipher->encrypt($source, $encrypted);
            $handle = fopen($encrypted, 'r+b');
            fseek($handle, -1, SEEK_END);
            $lastByte = fread($handle, 1);
            fseek($handle, -1, SEEK_END);
            fwrite($handle, chr(ord($lastByte) ^ 0xff));
            fclose($handle);

            $this->expectException(RuntimeException::class);
            $cipher->decrypt($encrypted, $decrypted);
        } finally {
            @unlink($source);
            @unlink($encrypted);
            @unlink($decrypted);
        }
    }

    private function requireSodium(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('The sodium extension is unavailable.');
        }
    }
}
