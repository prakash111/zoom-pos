<?php

namespace App\Services\Documents;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tenant-scoped resolver for documents stored in the shared sales table.
 *
 * Invoices and POS receipts are two presentations of a Sale row in this
 * application. Keeping their identifier handling here prevents callers from
 * falling back to an unscoped record belonging to another tenant.
 */
class TenantDocumentResolver
{
    /**
     * @return array{document: Sale, type: 'invoice'|'sale'}
     */
    public function resolveSaleOrInvoice(mixed $tenantId, mixed $identifier, ?string $typeHint = null): array
    {
        if ($tenantId === null || $tenantId === '' || $identifier === null || $identifier === '') {
            throw new NotFoundHttpException('Document not found.');
        }

        $baseQuery = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $tenantId)
            ->where(function (Builder $query) {
                $query->whereNull('operation_type')
                    ->orWhere('operation_type', 'sale');
            });

        $document = (clone $baseQuery)
            ->where(fn (Builder $query) => $this->applyExactIdentifier($query, $identifier))
            ->with(['customer', 'company'])
            ->first();

        // Older clients sometimes send only the numeric suffix from a
        // formatted INV-/POS- document number.
        if (! $document && preg_match('/(\d+)$/', (string) $identifier, $matches)) {
            $numericSuffix = (int) $matches[1];
            $paddedSuffix = str_pad((string) $numericSuffix, 4, '0', STR_PAD_LEFT);

            $document = (clone $baseQuery)
                ->where(function (Builder $query) use ($numericSuffix, $paddedSuffix) {
                    $query->where('id', $numericSuffix)
                        ->orWhere('sale_number', 'like', "%{$paddedSuffix}")
                        ->orWhere('sale_number', 'like', "%-{$numericSuffix}");
                })
                ->with(['customer', 'company'])
                ->latest('id')
                ->first();
        }

        if (! $document) {
            throw new NotFoundHttpException('Invoice or POS sale not found.');
        }

        return [
            'document' => $document,
            'type' => $this->resolvePresentationType($document, $typeHint),
        ];
    }

    private function applyExactIdentifier(Builder $query, mixed $identifier): void
    {
        $identifier = (string) $identifier;

        $query->where('external_id', $identifier)
            ->orWhere('sale_number', $identifier);

        if (ctype_digit($identifier)) {
            $query->orWhere('id', (int) $identifier);
        }
    }

    /** @return 'invoice'|'sale' */
    private function resolvePresentationType(Sale $document, ?string $typeHint): string
    {
        $normalizedHint = strtolower(trim((string) $typeHint));
        if (in_array($normalizedHint, ['sale', 'receipt', 'pos', 'pos_sale'], true)) {
            return 'sale';
        }
        if ($normalizedHint === 'invoice') {
            return 'invoice';
        }

        $number = strtoupper((string) $document->sale_number);

        return str_starts_with($number, 'POS-') ? 'sale' : 'invoice';
    }
}
