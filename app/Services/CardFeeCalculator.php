<?php

namespace App\Services;

class CardFeeCalculator
{
    /**
     * Calculate dynamic credit/debit installments and merchant fee breakdown.
     */
    public function calculateInstallments(float $totalAmount, string $cardType = 'credit', int $selectedInstallment = 1): array
    {
        $debitFee = (float) tenant_setting('card_fee_debit', 1.5);
        $credit1xFee = (float) tenant_setting('card_fee_credit_1x', 3.2);

        $installmentsConfig = tenant_setting('card_fee_credit_installments', 4.5);
        $multiFeeBase = is_numeric($installmentsConfig)
            ? (float) $installmentsConfig
            : (is_array($installmentsConfig) ? (float) ($installmentsConfig['base'] ?? 4.5) : 4.5);

        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $feeRate = ($i === 1) ? $credit1xFee : ($multiFeeBase + (($i - 2) * 0.4));
            $installmentValue = $i > 0 ? ($totalAmount / $i) : $totalAmount;
            $feeAmount = ($totalAmount * $feeRate) / 100;
            $netReceivable = $totalAmount - $feeAmount;

            $formattedTotal = number_format($totalAmount, 2);
            $formattedInstallment = number_format($installmentValue, 2);

            $options[$i] = [
                'installments' => $i,
                'installment_amount' => round($installmentValue, 2),
                'fee_rate' => round($feeRate, 2),
                'fee_amount' => round($feeAmount, 2),
                'net_receivable' => round($netReceivable, 2),
                'label' => ($i === 1)
                    ? "1x (\${$formattedTotal}) - ".__('Single Installment')
                    : "{$i}x (\${$formattedInstallment}/".__('mo').')',
            ];
        }

        return $options;
    }
}
