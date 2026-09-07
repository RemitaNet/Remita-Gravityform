<?php

declare(strict_types=1);

use Remita\GravityForms\Support\PaymentStatusMapper;
use Remita\GravityForms\Support\PaymentUpdateService;

require_once __DIR__ . '/TestCase.php';

final class PaymentUpdateServiceTest extends TestCase
{
    public function testLeavesPendingPaymentPendingWithoutFailure(): void
    {
        $service = new PaymentUpdateService();
        $entry = [
            'id' => 15,
            'payment_status' => 'Processing',
        ];

        $completed = false;
        $failed = false;
        $notes = [];

        $result = $service->applyQueryResponse(
            $entry,
            [
                'status' => '09',
                'data' => ['paymentState' => 'PENDING'],
            ],
            function () use (&$completed): void {
                $completed = true;
            },
            function () use (&$failed): void {
                $failed = true;
            },
            function (string $note) use (&$notes): void {
                $notes[] = $note;
            }
        );

        $this->assertSame(PaymentStatusMapper::STATUS_PENDING, $result);
        $this->assertSame(false, $completed);
        $this->assertSame(false, $failed);
        $this->assertSame('Payment is still pending with Remita.', $notes[0] ?? null);
    }

    public function testIgnoresRegressiveFailedUpdateAfterSuccess(): void
    {
        $service = new PaymentUpdateService();
        $entry = [
            'id' => 18,
            'payment_status' => 'Paid',
        ];

        $completed = false;
        $failed = false;
        $notes = [];

        $result = $service->applyQueryResponse(
            $entry,
            [
                'status' => '99',
                'data' => ['paymentState' => 'FAILED'],
            ],
            function () use (&$completed): void {
                $completed = true;
            },
            function () use (&$failed): void {
                $failed = true;
            },
            function (string $note) use (&$notes): void {
                $notes[] = $note;
            }
        );

        $this->assertSame(PaymentStatusMapper::STATUS_SUCCESS, $result);
        $this->assertSame(false, $completed);
        $this->assertSame(false, $failed);
        $this->assertSame('Ignored a regressive Remita query update after successful confirmation.', $notes[0] ?? null);
    }
}
