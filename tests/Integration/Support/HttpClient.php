<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Integration\Support;

/**
 * Minimal curl GET helper for live-stack smoke tests (no Guzzle).
 */
final class HttpClient
{
    /**
     * @return array{status: int, body: string, content_type: string}
     */
    public static function get(string $url, int $timeoutSeconds = 5): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_HEADER => true,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);

            throw new \RuntimeException('curl GET failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        $body = substr((string) $raw, $headerSize);

        return [
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
        ];
    }

    public static function baseUrl(): string
    {
        $fromEnv = getenv('MT2CMS_BASE_URL');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        return 'http://127.0.0.1:8000';
    }

    public static function isReachable(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['host'])) {
            return false;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, (int) $port, $errno, $errstr, 1.0);
        if ($fp === false) {
            return false;
        }
        fclose($fp);

        return true;
    }
}
