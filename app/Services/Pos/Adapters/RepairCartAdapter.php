<?php

namespace App\Services\Pos\Adapters;

use App\Models\Company;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Services\Pos\SduiPosAdapterInterface;

final class RepairCartAdapter implements SduiPosAdapterInterface
{
    /** @param list<array<string, mixed>> $lineItems */
    public function __construct(
        private readonly Company $company,
        private readonly ?RepairTicket $ticket = null,
        private readonly array $lineItems = [],
        private readonly array $context = [],
    ) {}

    public function getLineItems(): array
    {
        if ($this->lineItems !== []) {
            return $this->lineItems;
        }

        if (! $this->ticket) {
            return [];
        }

        $this->ticket->loadMissing('items.product');
        $lines = $this->ticket->items
            ->where('billed_to_customer', true)
            ->map(fn ($item) => [
                'id' => $item->product_id,
                'product_id' => $item->product_id,
                'title' => $item->item_name,
                'name' => $item->item_name,
                'price' => (float) $item->unit_price,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'qty' => (float) $item->quantity,
                'line_type' => $item->item_type,
                'tax_id' => $item->tax_id,
            ])
            ->values()
            ->all();

        if ($lines === [] && (float) $this->ticket->estimated_cost > 0) {
            $lines[] = [
                'id' => null,
                'product_id' => null,
                'title' => trim("Repair Service — {$this->ticket->brand} {$this->ticket->model}"),
                'name' => trim("Repair Service — {$this->ticket->brand} {$this->ticket->model}"),
                'subtitle' => 'Ticket #'.$this->ticket->ticket_number,
                'price' => (float) $this->ticket->estimated_cost,
                'unit_price' => (float) $this->ticket->estimated_cost,
                'quantity' => 1,
                'qty' => 1,
                'line_type' => 'service_labor',
            ];
        }

        if ((float) $this->ticket->diagnostic_fee > 0) {
            $lines[] = [
                'id' => null,
                'product_id' => null,
                'title' => 'Upfront Diagnostic Fee',
                'name' => 'Upfront Diagnostic Fee',
                'price' => (float) $this->ticket->diagnostic_fee,
                'unit_price' => (float) $this->ticket->diagnostic_fee,
                'quantity' => 1,
                'qty' => 1,
                'line_type' => 'diagnostic_fee',
            ];
        }

        return $lines;
    }

    public function getCustomer(): ?Customer
    {
        return $this->ticket?->customer;
    }

    public function getPrepaidDeposit(): float
    {
        return max(0, (float) ($this->ticket?->advance_deposit ?? 0));
    }

    public function getModuleContext(): array
    {
        return array_merge([
            'company' => $this->company,
            'module' => 'repair',
            'form_submit_endpoint' => $this->ticket
                ? "/api/tenant/repair/tickets/{$this->ticket->id}/settle"
                : '/api/tenant/repair/checkout',
            'ticket_field_label' => 'Repair Ticket',
            'customer_field_label' => 'Customer Name',
            'selected_ticket_id' => $this->ticket?->id,
            'preview_includes_ticket' => $this->ticket !== null,
            'default_customer_name' => $this->ticket?->customer_name,
        ], $this->context);
    }
}
