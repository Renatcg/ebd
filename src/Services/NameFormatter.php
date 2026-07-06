<?php

declare(strict_types=1);

final class NameFormatter
{
    public static function personName(mixed $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name) ?? (string) $name);

        if ($name === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
    }
}
