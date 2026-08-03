<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\Crypt;

class SecretEncryptionService
{
    public function encrypt(string $plaintext): string
    {
        return Crypt::encryptString($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return Crypt::decryptString($ciphertext);
    }

    public function mask(): string
    {
        return '********';
    }
}
