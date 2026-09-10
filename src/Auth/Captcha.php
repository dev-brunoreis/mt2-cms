<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

/**
 * Self-hosted SVG captcha stored in the PHP session (one-shot verify).
 */
final class Captcha
{
    private const CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LENGTH = 5;
    private const TTL_SECONDS = 300;

    public function __construct(private string $scope = 'public')
    {
    }

    public function svg(): string
    {
        $code = $this->generateCode();
        $expires = time() + self::TTL_SECONDS;
        $_SESSION[$this->sessionKey()] = [
            'hash' => $this->hashAnswer(strtolower($code), $expires),
            'expires' => $expires,
        ];

        return $this->renderSvg($code);
    }

    public function verify(?string $answer): bool
    {
        $key = $this->sessionKey();
        $stored = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        if (!is_array($stored)) {
            return false;
        }

        $expires = (int) ($stored['expires'] ?? 0);

        if ($expires < time()) {
            return false;
        }

        $answer = strtolower(trim((string) $answer));

        if ($answer === '' || strlen($answer) !== self::LENGTH) {
            return false;
        }

        $expected = (string) ($stored['hash'] ?? '');

        return hash_equals($expected, $this->hashAnswer($answer, $expires));
    }

    private function sessionKey(): string
    {
        return '_captcha_' . $this->scope;
    }

    private function generateCode(): string
    {
        $code = '';
        $max = strlen(self::CHARSET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARSET[random_int(0, $max)];
        }

        return $code;
    }

    private function hashAnswer(string $answer, int $expires): string
    {
        $pepper = (string) (session_id() ?: 'mt2cms-captcha');

        return hash_hmac('sha256', $answer . '|' . $expires . '|' . $this->scope, $pepper);
    }

    private function renderSvg(string $code): string
    {
        $width = 160;
        $height = 48;
        $chars = str_split($code);
        $elements = [];

        foreach ($chars as $index => $char) {
            $x = 16 + ($index * 28);
            $y = 32 + random_int(-4, 4);
            $rotate = random_int(-18, 18);
            $elements[] = sprintf(
                '<text x="%d" y="%d" transform="rotate(%d %d %d)" font-family="monospace" font-size="24" font-weight="700" fill="#1e293b">%s</text>',
                $x,
                $y,
                $rotate,
                $x,
                $y,
                htmlspecialchars($char, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            );
        }

        for ($i = 0; $i < 4; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $elements[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#cbd5e1" stroke-width="1"/>',
                $x1,
                $y1,
                $x2,
                $y2,
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="Captcha"><rect width="100%%" height="100%%" fill="#f8fafc"/>%s</svg>',
            $width,
            $height,
            $width,
            $height,
            implode('', $elements),
        );
    }
}
