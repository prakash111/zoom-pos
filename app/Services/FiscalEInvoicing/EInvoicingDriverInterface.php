<?php

namespace App\Services\FiscalEInvoicing;

use App\Models\Sale;

interface EInvoicingDriverInterface
{
    /**
     * Generate the standardized fiscal/tax compliance invoice payload.
     */
    public function generatePayload(Sale $sale): array|string;

    /**
     * Validate regulatory compliance against national tax requirements.
     *
     * @return array{compliant: bool, errors: array<string>}
     */
    public function validateCompliance(Sale $sale): array;

    /**
     * Generate the fiscal QR code content (e.g. TLV Base64 or signed e-Invoice URI).
     */
    public function generateQrCodeData(Sale $sale): string;

    /**
     * Simulate or perform electronic invoice clearance submission to tax portal.
     *
     * @return array{status: string, irn: string, qr: string, signed_payload: string, message: string}
     */
    public function submitInvoice(Sale $sale): array;
}
