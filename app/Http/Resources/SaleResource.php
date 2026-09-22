<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = is_array($this->items) ? $this->items : (json_decode($this->items ?? '', true) ?: []);

        return [
            'id' => (string) ($this->external_id ?: $this->id),
            'server_id' => $this->id,
            'sale_number' => $this->sale_number,
            'invoice_number' => $this->sale_number,
            'customer_id' => $this->customer_id ? (string) $this->customer_id : null,
            'customer_name' => $this->customer_name ?: ($this->customer?->name ?? 'Walk-in'),
            'store_id' => $this->store_id,
            'company_id' => $this->company_id,
            'items' => $items,
            'total' => (float) ($this->total ?? 0),
            'total_amount' => (float) ($this->total_amount ?? $this->total ?? 0),
            'net_amount' => (float) ($this->net_amount ?? $this->total ?? 0),
            'discount' => (float) ($this->discount ?? 0),
            'tax' => (float) ($this->tax_amount ?? 0),
            'tax_amount' => (float) ($this->tax_amount ?? 0),
            'payment_method' => $this->payment_method ?: 'cash',
            'payment_status' => $this->payment_status ?: 'paid',
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'due_amount' => (float) ($this->due_amount ?? 0),
            'due_date' => $this->due_date ? (is_string($this->due_date) ? $this->due_date : $this->due_date->toDateString()) : null,
            'status' => $this->status ?: 'completed',
            'notes' => $this->notes ?? '',
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
