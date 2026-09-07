<?php

declare(strict_types=1);

namespace Remita\GravityForms\Support;

final class PaymentIdentifier
{
    public static function build(int $entryId, ?int $timestamp = null, ?int $random = null): string
    {
        return sprintf(
            'gf-%d-%d-%06d',
            $entryId,
            $timestamp ?? time(),
            $random ?? wp_rand(100000, 999999)
        );
    }

    public static function extractEntryId(string $paymentIdentifier): ?int
    {
        if (preg_match('/^gf-(\d+)-\d+-\d{6}$/', $paymentIdentifier, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
