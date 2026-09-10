<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

use Mt2Cms\Model\Env;

/**
 * Encrypts application secrets at rest using APP_KEY (32-byte hex).
 */
final class AppCrypto
{
    private const PREFIX = 'enc.v1.';
    private const KEY_BYTES = 32;
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(self::KEY_BYTES));
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::deriveKey();
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipher = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES,
        );

        if ($cipher === false || $tag === '') {
            throw new \RuntimeException('Encryption failed');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if (!self::isEncrypted($payload)) {
            throw new \RuntimeException('Invalid encrypted payload');
        }

        $encoded = substr($payload, strlen(self::PREFIX));
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < self::IV_BYTES + self::TAG_BYTES + 1) {
            throw new \RuntimeException('Invalid encrypted payload');
        }

        $iv = substr($raw, 0, self::IV_BYTES);
        $tag = substr($raw, self::IV_BYTES, self::TAG_BYTES);
        $cipher = substr($raw, self::IV_BYTES + self::TAG_BYTES);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::deriveKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plain;
    }

    public static function hasValidKey(): bool
    {
        try {
            self::deriveKey();

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private static function deriveKey(): string
    {
        $hex = Env::getInstance()->get('APP_KEY', '');

        if (!is_string($hex) || strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new \RuntimeException('APP_KEY is missing or invalid');
        }

        $key = hex2bin($hex);

        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            throw new \RuntimeException('APP_KEY is missing or invalid');
        }

        return $key;
    }
}
