<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Drop;

class LocaleText
{
    public static function decode(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        foreach (['CP949', 'EUC-KR'] as $from) {
            if (function_exists('iconv')) {
                $converted = @iconv($from, 'UTF-8//IGNORE', $raw);

                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }

            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($raw, 'UTF-8', $from);

                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        return $raw;
    }
}
