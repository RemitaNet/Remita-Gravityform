<?php

declare(strict_types=1);

use Remita\GravityForms\Support\PaymentIdentifier;

require_once __DIR__ . '/TestCase.php';

final class PaymentIdentifierTest extends TestCase
{
    public function testBuildsGravityFormsScopedIdentifier(): void
    {
        $this->assertSame('gf-42-1234567890-654321', PaymentIdentifier::build(42, 1234567890, 654321));
    }

    public function testExtractsEntryIdFromIdentifier(): void
    {
        $this->assertSame(42, PaymentIdentifier::extractEntryId('gf-42-1234567890-654321'));
        $this->assertNull(PaymentIdentifier::extractEntryId('mepr-42-1234567890-654321'));
    }
}
