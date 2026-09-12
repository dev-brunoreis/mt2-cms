<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Service\SettingsService;
use Mt2Cms\Support\Log;

class DiscordWebhookService
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function notifyNewsPublished(int $newsId, string $title): void
    {
        $url = $this->settings->siteUrl() . '/news/' . $newsId;
        $this->send(
            '**News published:** ' . $title,
            [
                'title' => $title,
                'url' => $url,
                'description' => 'A new article is live on the site.',
            ],
        );
    }

    public function notifyNewTicket(int $ticketId, string $subject, string $accountLogin): void
    {
        $this->send(
            '**New support ticket #' . $ticketId . '** from `' . $accountLogin . '`',
            [
                'title' => $subject,
                'description' => 'Open the admin panel to reply.',
            ],
        );
    }

    public function notifyPaymentCredited(string $accountLogin, int $cashAmount): void
    {
        $this->send(
            '**Payment credited** — `' . $accountLogin . '` received **' . number_format($cashAmount) . '** cash.',
        );
    }

    public function notifyEventPublished(int $eventId, string $title): void
    {
        $url = $this->settings->siteUrl() . '/events/' . $eventId;
        $this->send(
            '**Event published:** ' . $title,
            [
                'title' => $title,
                'url' => $url,
                'description' => 'A new event is listed on the site.',
            ],
        );
    }

    /**
     * Digest of new economy alerts (one webhook, not per item).
     *
     * @param list<array{vnum: int, kind: string, change: float}> $alerts
     */
    public function notifyEconomyAlerts(array $alerts): void
    {
        if ($alerts === []) {
            return;
        }

        $lines = [];
        $max = min(15, count($alerts));

        for ($i = 0; $i < $max; $i++) {
            $a = $alerts[$i];
            $pct = round(((float) $a['change']) * 100.0, 1);
            $sign = $pct >= 0 ? '+' : '';
            $lines[] = '• vnum `' . (int) $a['vnum'] . '` **' . (string) $a['kind'] . '** (' . $sign . $pct . '%)';
        }

        if (count($alerts) > $max) {
            $lines[] = '… and ' . (count($alerts) - $max) . ' more';
        }

        $this->send(
            '**Economy alerts** (' . count($alerts) . ')',
            [
                'title' => 'Economy health',
                'url' => rtrim($this->settings->siteUrl(), '/') . '/admin/game/economy',
                'description' => implode("\n", $lines),
            ],
        );
    }

    /**
     * @param array{title?: string, url?: string, description?: string}|null $embed
     */
    private function send(string $content, ?array $embed = null): void
    {
        $webhookUrl = $this->settings->discordWebhookUrl();

        if ($webhookUrl === '') {
            return;
        }

        $payload = ['content' => mb_substr($content, 0, 2000)];

        if ($embed !== null) {
            $entry = [];

            if (($embed['title'] ?? '') !== '') {
                $entry['title'] = mb_substr((string) $embed['title'], 0, 256);
            }

            if (($embed['url'] ?? '') !== '') {
                $entry['url'] = (string) $embed['url'];
            }

            if (($embed['description'] ?? '') !== '') {
                $entry['description'] = mb_substr((string) $embed['description'], 0, 4096);
            }

            if ($entry !== []) {
                $payload['embeds'] = [$entry];
            }
        }

        try {
            $this->postJson($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::error('discord', 'Webhook delivery failed', $e);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('discord.curl_init_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('discord.webhook_http_' . $code . ': ' . (string) $raw);
        }
    }
}
