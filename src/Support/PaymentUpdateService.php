<?php

declare(strict_types=1);

namespace Remita\GravityForms\Support;

final class PaymentUpdateService
{
    public function applyQueryResponse(array $entry, array $response, callable $markSuccess, callable $markFailure, callable $addNote): string
    {
        $entryId = (int) ($entry['id'] ?? 0);
        $mappedStatus = PaymentStatusMapper::mapQueryResponse($response);
        $numericStatus = (string) ($response['status'] ?? '');
        $paymentState = (string) ($response['data']['paymentState'] ?? '');
        $rrr = (string) ($response['data']['rrr'] ?? $response['data']['formattedRRR'] ?? '');
        $paymentStatus = strtolower((string) ($entry['payment_status'] ?? ''));

        gform_update_meta($entryId, 'remita_last_numeric_status', $numericStatus);
        gform_update_meta($entryId, 'remita_last_payment_state', $paymentState);
        gform_update_meta($entryId, 'remita_last_rrr', $rrr);
        gform_update_meta($entryId, 'remita_last_mapped_status', $mappedStatus);
        gform_update_meta($entryId, 'remita_last_query_payload', wp_json_encode($response));

        if ($this->shouldPreventRegression($paymentStatus, $mappedStatus)) {
            $addNote('Ignored a regressive Remita query update after successful confirmation.');
            return PaymentStatusMapper::STATUS_SUCCESS;
        }

        if ($mappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            if ($paymentStatus !== 'paid') {
                $markSuccess();
            }
            $addNote(sprintf('Payment verified successfully with Remita. RRR: %s.', $rrr !== '' ? $rrr : 'n/a'));
            return $mappedStatus;
        }

        if ($mappedStatus === PaymentStatusMapper::STATUS_PENDING) {
            $addNote('Payment is still pending with Remita.');
            return $mappedStatus;
        }

        $markFailure();
        $addNote('Payment verification failed with Remita.');
        return $mappedStatus;
    }

    private function shouldPreventRegression(string $currentPaymentStatus, string $nextMappedStatus): bool
    {
        if ($nextMappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            return false;
        }

        return $currentPaymentStatus === 'paid';
    }
}
