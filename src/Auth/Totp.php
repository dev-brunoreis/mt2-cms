<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * RFC 6238 TOTP (SHA-1, 30s step, 6 digits) compatible with common authenticator apps.
 */
final class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const SECRET_BYTES = 20;

    /** @var string */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public static function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $issuerParam = rawurlencode($issuer);
        $secret = rawurlencode($secret);

        return 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . $issuerParam . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * QR code as a data URI (SVG) for Google Authenticator and similar apps.
     */
    public static function qrDataUri(string $data): string
    {
        $options = new QROptions([
            'scale' => 5,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', trim($code)) ?? '';

        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)));
        }

        return $codes;
    }

    private static function codeForCounter(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);
        $encoded = '';

        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
        $binary = '';

        foreach (str_split($secret) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);

            if ($pos === false) {
                continue;
            }

            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = str_split(substr($binary, 0, intdiv(strlen($binary), 8) * 8), 8);
        $decoded = '';

        foreach ($bytes as $byte) {
            $decoded .= chr(bindec($byte));
        }

        return $decoded;
    }
}
