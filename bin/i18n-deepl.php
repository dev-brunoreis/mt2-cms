#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Offline DeepL Free translator for lang/*.json.
 *
 * Usage:
 *   DEEPL_AUTH_KEY=… php bin/i18n-deepl.php --only=fr
 *   DEEPL_AUTH_KEY=… php bin/i18n-deepl.php --public-only --only=it,hu,cs
 *   php bin/i18n-deepl.php --dry-run --only=de
 *
 * Requires --only=… (English is the source; no other packs ship by default).
 * Never commit DEEPL_AUTH_KEY. Free endpoint: api-free.deepl.com
 */

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/src/bootstrap/autoload.php';

use Mt2Cms\I18n\LocaleJsonTree;

const DEEPL_API = 'https://api-free.deepl.com/v2/translate';
const DEEPL_USAGE = 'https://api-free.deepl.com/v2/usage';
const BATCH_SIZE = 40;

/** @var array<string, array{target: string, name: string, formality: bool}> */
const LOCALES = [
    'pt' => ['target' => 'PT-BR', 'name' => 'Português', 'formality' => true],
    'de' => ['target' => 'DE', 'name' => 'Deutsch', 'formality' => true],
    'tr' => ['target' => 'TR', 'name' => 'Türkçe', 'formality' => false],
    'pl' => ['target' => 'PL', 'name' => 'Polski', 'formality' => true],
    'es' => ['target' => 'ES', 'name' => 'Español', 'formality' => true],
    'ru' => ['target' => 'RU', 'name' => 'Русский', 'formality' => true],
    'ro' => ['target' => 'RO', 'name' => 'Română', 'formality' => false],
    'fr' => ['target' => 'FR', 'name' => 'Français', 'formality' => true],
    'it' => ['target' => 'IT', 'name' => 'Italiano', 'formality' => true],
    'hu' => ['target' => 'HU', 'name' => 'Magyar', 'formality' => false],
    'cs' => ['target' => 'CS', 'name' => 'Čeština', 'formality' => false],
    'da' => ['target' => 'DA', 'name' => 'Dansk', 'formality' => false],
    'el' => ['target' => 'EL', 'name' => 'Ελληνικά', 'formality' => false],
    'nl' => ['target' => 'NL', 'name' => 'Nederlands', 'formality' => true],
];

$opts = getopt('', ['only:', 'public-only', 'dry-run', 'force', 'help']);

if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: DEEPL_AUTH_KEY=… php bin/i18n-deepl.php --only=fr[,de,…] [--public-only] [--dry-run] [--force]\n");
    exit(0);
}

$only = null;
if (isset($opts['only']) && is_string($opts['only']) && $opts['only'] !== '') {
    $only = array_values(array_filter(array_map('trim', explode(',', $opts['only']))));
}

if ($only === null || $only === []) {
    fwrite(STDERR, "Pass --only=code[,code…] (supported codes are listed in LOCALES).\n");
    exit(1);
}

$codes = $only;
$publicOnly = isset($opts['public-only']);
$dryRun = isset($opts['dry-run']);
$force = isset($opts['force']);

$authKeyEnv = getenv('DEEPL_AUTH_KEY');
$authKey = is_string($authKeyEnv) ? trim($authKeyEnv) : '';
if (!$dryRun && $authKey === '') {
    fwrite(STDERR, "Set DEEPL_AUTH_KEY (DeepL Free auth key). Do not commit it.\n");
    exit(1);
}

foreach ($codes as $code) {
    if (!isset(LOCALES[$code])) {
        fwrite(STDERR, "Unknown locale code: {$code}\n");
        exit(1);
    }
}

$enPath = BASE_DIR . '/lang/en.json';
$en = json_decode((string) file_get_contents($enPath), true);
if (!is_array($en)) {
    fwrite(STDERR, "Invalid lang/en.json\n");
    exit(1);
}

$leaves = LocaleJsonTree::flatten($en, $publicOnly);
$charCount = 0;
foreach ($leaves as $leaf) {
    $charCount += mb_strlen($leaf['text']);
}

fwrite(STDOUT, sprintf(
    "Source strings: %d (%s) · %d chars · targets: %s\n",
    count($leaves),
    $publicOnly ? 'public-only' : 'full',
    $charCount,
    implode(',', $codes),
));
fwrite(STDOUT, sprintf("Estimated usage: %d chars\n", $charCount * count($codes)));

if (!$dryRun) {
    $usage = deeplGet(DEEPL_USAGE, $authKey);
    $used = (int) ($usage['character_count'] ?? 0);
    $limit = (int) ($usage['character_limit'] ?? 0);
    fwrite(STDOUT, sprintf("DeepL usage before: %d / %d (remaining %d)\n", $used, $limit, max(0, $limit - $used)));
}

if ($dryRun) {
    fwrite(STDOUT, "Dry run — no API calls.\n");
    exit(0);
}

foreach ($codes as $code) {
    $meta = LOCALES[$code];
    $outPath = BASE_DIR . '/lang/' . $code . '.json';

    if (is_file($outPath) && !$force) {
        $existing = json_decode((string) file_get_contents($outPath), true);
        if (is_array($existing) && LocaleJsonTree::countLeaves($existing) >= count($leaves)) {
            fwrite(STDOUT, "Skip {$code}: {$outPath} already complete (use --force to redo)\n");
            continue;
        }
    }

    $cacheDir = BASE_DIR . '/lang/.deepl-cache';
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        fwrite(STDERR, "Cannot create {$cacheDir}\n");
        exit(1);
    }
    $cachePath = $cacheDir . '/' . $code . '.json';
    $translations = [];
    if (!$force && is_file($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached)) {
            foreach ($cached as $path => $text) {
                if (is_string($path) && is_string($text)) {
                    $translations[$path] = $text;
                }
            }
            fwrite(STDOUT, sprintf("  resumed %d cached strings for {$code}\n", count($translations)));
        }
    }

    $pending = [];
    $pendingChars = 0;
    foreach ($leaves as $leaf) {
        if (!isset($translations[$leaf['path']])) {
            $pending[] = $leaf;
            $pendingChars += mb_strlen($leaf['text']);
        }
    }

    if ($pending !== []) {
        $usage = deeplGet(DEEPL_USAGE, $authKey);
        $used = (int) ($usage['character_count'] ?? 0);
        $limit = (int) ($usage['character_limit'] ?? 0);
        $remaining = $limit > 0 ? $limit - $used : PHP_INT_MAX;
        if ($pendingChars > $remaining) {
            fwrite(STDERR, sprintf(
                "Skip {$code}: need %d more chars, remaining %d. Re-run next month or --public-only.\n",
                $pendingChars,
                max(0, $remaining),
            ));
            continue;
        }
    }

    fwrite(STDOUT, "Translating → {$code} ({$meta['target']})…\n");

    $batches = array_chunk($pending, BATCH_SIZE);
    $done = count($translations);

    foreach ($batches as $batch) {
        $texts = [];
        foreach ($batch as $leaf) {
            $texts[] = LocaleJsonTree::protectPlaceholders($leaf['text']);
        }

        $params = [
            'target_lang' => $meta['target'],
            'source_lang' => 'EN',
            'tag_handling' => 'xml',
            'ignore_tags' => 'x',
            'text' => $texts,
        ];

        if ($meta['formality']) {
            $params['formality'] = 'prefer_less';
        }

        $result = deeplTranslate($params, $authKey);
        $items = $result['translations'] ?? null;

        if (!is_array($items) || count($items) !== count($batch)) {
            fwrite(STDERR, "Unexpected DeepL response for {$code} batch at {$done}\n");
            exit(1);
        }

        foreach ($batch as $i => $leaf) {
            $translated = (string) ($items[$i]['text'] ?? '');
            $clean = LocaleJsonTree::unprotectPlaceholders($translated);
            $translations[$leaf['path']] = LocaleJsonTree::sanitizeTranslation($leaf['text'], $clean);
        }

        file_put_contents(
            $cachePath,
            json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $done = count($translations);
        fwrite(STDOUT, sprintf("  %d / %d\n", $done, count($leaves)));
        usleep(120_000);
    }

    if (count($translations) !== count($leaves)) {
        fwrite(STDERR, "Incomplete translation for {$code}: " . count($translations) . '/' . count($leaves) . "\n");
        exit(1);
    }

    if ($publicOnly) {
        $tree = LocaleJsonTree::apply($en, $translations);
        if (isset($en['admin'])) {
            $tree['admin'] = $en['admin'];
        }
    } else {
        $tree = LocaleJsonTree::apply($en, $translations);
    }

    if (!isset($tree['locale']) || !is_array($tree['locale'])) {
        $tree['locale'] = [];
    }
    $tree['locale']['name'] = $meta['name'];

    $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "JSON encode failed for {$code}\n");
        exit(1);
    }

    file_put_contents($outPath, $json . "\n");
    fwrite(STDOUT, "Wrote {$outPath}\n");
}

$usageAfter = deeplGet(DEEPL_USAGE, $authKey);
fwrite(STDOUT, sprintf(
    "DeepL usage after: %d / %d\n",
    (int) ($usageAfter['character_count'] ?? 0),
    (int) ($usageAfter['character_limit'] ?? 0),
));
fwrite(STDOUT, "Done.\n");

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function deeplTranslate(array $params, string $authKey): array
{
    $body = [];
    foreach ($params as $key => $value) {
        if ($key === 'text' && is_array($value)) {
            foreach ($value as $text) {
                $body[] = 'text=' . rawurlencode((string) $text);
            }
            continue;
        }
        $body[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return deeplRequest('POST', DEEPL_API, $authKey, implode('&', $body));
}

/**
 * @return array<string, mixed>
 */
function deeplGet(string $url, string $authKey): array
{
    return deeplRequest('GET', $url, $authKey, null);
}

/**
 * @return array<string, mixed>
 */
function deeplRequest(string $method, string $url, string $authKey, ?string $body): array
{
    $attempts = 0;

    while (true) {
        $attempts++;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        $headers = [
            'Authorization: DeepL-Auth-Key ' . $authKey,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            if ($attempts < 5) {
                sleep(2 * $attempts);
                continue;
            }
            throw new RuntimeException('DeepL request failed: ' . $err);
        }

        if ($status === 429 || $status >= 500) {
            if ($attempts < 8) {
                sleep(2 * $attempts);
                continue;
            }
        }

        if ($status === 456) {
            fwrite(STDERR, "DeepL quota exhausted (456).\n");
            exit(1);
        }

        $data = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            fwrite(STDERR, "DeepL HTTP {$status}: {$raw}\n");
            exit(1);
        }

        return $data;
    }
}
