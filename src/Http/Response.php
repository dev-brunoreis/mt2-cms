<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

class Response
{
    /** @var array<string, string> */
    private const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: blob:; font-src 'self' https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'",
    ];

    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
        bool $withDefaults = true,
    ) {
        if ($withDefaults) {
            $this->headers = array_merge(
                self::SECURITY_HEADERS,
                ['Content-Type' => 'text/html; charset=UTF-8'],
                $headers,
            );
        } else {
            $this->headers = $headers;
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return new self($body, 404);
    }

    public static function png(string $body): self
    {
        return new self($body, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $status,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    public static function download(string $absolutePath, string $downloadName, string $mime): self
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return self::notFound();
        }

        $body = file_get_contents($absolutePath);

        if ($body === false) {
            return self::notFound();
        }

        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'download';
        $disposition = 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName);

        return new self($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->body, $this->status, $headers, false);
    }

    public function withCookie(string $name, string $value, int $maxAge = 31_536_000): self
    {
        $parts = [
            rawurlencode($name) . '=' . rawurlencode($value),
            'Max-Age=' . $maxAge,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        $https = $_SERVER['HTTPS'] ?? '';

        if ($https !== '' && $https !== 'off') {
            $parts[] = 'Secure';
        }

        return $this->withHeader('Set-Cookie', implode('; ', $parts));
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
