<?php

declare(strict_types=1);

use Remita\GravityForms\Support\PaymentStatusMapper;

require_once __DIR__ . '/TestCase.php';

final class PaymentStatusMapperTest extends TestCase
{
    public function testMapsApprovedQueryToSuccess(): void
    {
        $this->assertSame(PaymentStatusMapper::STATUS_SUCCESS, PaymentStatusMapper::mapQueryResponse([
            'status' => '00',
            'data' => ['paymentState' => 'APPROVED'],
        ]));
    }

    public function testMapsPendingQueryToPending(): void
    {
        $this->assertSame(PaymentStatusMapper::STATUS_PENDING, PaymentStatusMapper::mapQueryResponse([
            'status' => '09',
            'data' => ['paymentState' => 'PENDING'],
        ]));
    }
}
