<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class FileEncryptionService
{
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
        
        return base64_encode($iv . '::' . $encrypted);
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
        
        $iv = $parts[0];
        $encrypted = $parts[1];
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
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
        return hash('sha256', $password . env('APP_KEY', 'default-key'), true);
    }

    /**
     * Generate secure download token.
     */
    public function generateDownloadToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
