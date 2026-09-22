<?php

namespace App\Http\Resources;

use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $documentType = str_starts_with(strtoupper((string) $this->sale_number), 'POS-') ? 'sale' : 'invoice';
        $postSaleData = SchemaResponse::postSaleActionData($this->resource);
        $postSaleData['actions_endpoint'] = "/api/v1/tenant/receivables/{$this->id}/reminder-sheet?document_type={$documentType}";

        $nativeSheetAction = [
            'type' => 'show_post_sale_sheet',
            'action_type' => 'show_post_sale_sheet',
            'data' => $postSaleData,
        ];

        return [
            'id' => (string) $this->id,
            'invoice_id' => (string) $this->id,
            'sale_id' => (string) ($this->external_id ?: $this->id),
            'document_id' => (string) $this->id,
            'document_type' => $documentType,
            'sale_number' => $this->sale_number,
            'invoice_number' => $this->sale_number,
            'store_id' => $this->store_id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? $this->customer_name ?? 'Walk-in',
            'phone' => $this->customer?->phone,
            'email' => $this->customer?->email,
            'customer' => $this->relationLoaded('customer') ? $this->customer : null,
            'date' => $this->created_at?->toIso8601String(),
            'due_date' => $this->due_date ? (is_string($this->due_date) ? $this->due_date : $this->due_date->toDateString()) : null,
            'due_reminder_at' => $this->due_reminder_at?->toIso8601String(),
            'due_reminder_sent_at' => $this->due_reminder_sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'total' => (float) ($this->total ?? 0),
            'total_amount' => (float) ($this->total_amount ?? $this->total ?? 0),
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'due_amount' => (float) ($this->due_amount ?? 0),
            'balance_due' => (float) ($this->due_amount ?? 0),
            'status' => $this->payment_status ?: $this->status,
            'payment_status' => $this->payment_status ?: 'pending',
            'action' => $nativeSheetAction,
            'on_tap' => $nativeSheetAction,
            'modal_endpoint' => "/api/v1/tenant/documents/{$documentType}/{$this->id}/actions-sheet",
            'actions_endpoint' => "/api/v1/tenant/receivables/{$this->id}/reminder-sheet?document_type={$documentType}",
        ];
    }
}
