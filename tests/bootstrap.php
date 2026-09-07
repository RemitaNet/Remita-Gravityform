<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/AmountNormalizer.php';
require_once __DIR__ . '/../src/Support/PaymentIdentifier.php';
require_once __DIR__ . '/../src/Support/PaymentStatusMapper.php';
require_once __DIR__ . '/../src/Support/PaymentUpdateService.php';

if (!function_exists('gform_update_meta')) {
    function gform_update_meta(int $entryId, string $key, mixed $value): void
    {
        $GLOBALS['gf_test_meta'][$entryId][$key] = $value;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false
    {
        return json_encode($value);
    }
}
