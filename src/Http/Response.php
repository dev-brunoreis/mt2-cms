<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
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

        $headers = $this->headers;
        $headers['Set-Cookie'] = implode('; ', $parts);

        return new self($this->body, $this->status, $headers);
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
