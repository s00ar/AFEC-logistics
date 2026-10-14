<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function units(mixed $value): string
{
    // Keep BIGINT/DECIMAL values as strings; never round a quantity through a JS/PHP float.
    $text = (string) $value;
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $text) ?? $text;
}
