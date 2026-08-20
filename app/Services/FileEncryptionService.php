<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class FileEncryptionService
{
    /** Penanda format terenkripsi versi 2 (IV disimpan dalam hex). */
    private const PREFIX = 'v2:';

    /**
     * Encrypt file content.
     */
    public function encrypt(string $content, string $password): string
    {
        $key = $this->deriveKey($password);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($content, 'AES-256-CBC', $key, 0, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt file');
        }

        // IV di-hex-kan supaya pemisah '::' tidak pernah bentrok dengan byte acak IV.
        return base64_encode(self::PREFIX . bin2hex($iv) . '::' . $encrypted);
    }

    /**
     * Decrypt file content.
     */
    public function decrypt(string $encryptedContent, string $password): string
    {
        $key = $this->deriveKey($password);
        $decoded = base64_decode($encryptedContent);
        
        if ($decoded === false) {
            throw new \RuntimeException('Invalid encrypted content');
        }
        
        $parts = explode('::', $decoded, 2);

        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid encrypted content format');
        }

        // Format baru: "v2:<iv hex>::<ciphertext>". Format lama menyimpan IV mentah
        // pada bagian pertama — tetap didukung agar file lama bisa dibuka.
        if (str_starts_with($parts[0], self::PREFIX)) {
            $iv = hex2bin(substr($parts[0], strlen(self::PREFIX)));
        } else {
            $iv = $parts[0];
        }

        $encrypted = $parts[1];
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        // Fallback untuk file yang dienkripsi sebelum perbaikan kunci (env() vs config()).
        if ($decrypted === false) {
            foreach ($this->legacyKeys($password) as $legacyKey) {
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $legacyKey, 0, $iv);
                if ($decrypted !== false) {
                    break;
                }
            }
        }

        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt file - invalid password');
        }

        return $decrypted;
    }

    /**
     * Encrypt file and store it.
     */
    public function encryptAndStore(string $filePath, string $password): string
    {
        $content = file_get_contents($filePath);
        $encrypted = $this->encrypt($content, $password);
        
        $encryptedPath = $filePath . '.encrypted';
        file_put_contents($encryptedPath, $encrypted);
        
        // Remove original file
        unlink($filePath);
        
        return $encryptedPath;
    }

    /**
     * Decrypt file and return content.
     */
    public function decryptFile(string $filePath, string $password): string
    {
        $encryptedContent = file_get_contents($filePath);
        return $this->decrypt($encryptedContent, $password);
    }

    /**
     * Derive encryption key from password.
     */
    private function deriveKey(string $password): string
    {
        // NOTE: config('app.key') — jangan pakai env() di sini. Saat `config:cache`
        // aktif (umum di cPanel) env() mengembalikan null sehingga kunci berubah
        // dan file lama tidak bisa didekripsi lagi.
        return hash('sha256', $password . (string) config('app.key'), true);
    }

    /**
     * Kunci-kunci lama yang pernah dipakai sebelum perbaikan deriveKey().
     *
     * @return array<int, string>
     */
    private function legacyKeys(string $password): array
    {
        $candidates = array_filter([
            env('APP_KEY'),
            'default-key',
        ]);

        return array_map(
            fn ($secret) => hash('sha256', $password . $secret, true),
            array_values(array_unique($candidates))
        );
    }

    /**
     * Generate secure download token.
     */
    public function generateDownloadToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
