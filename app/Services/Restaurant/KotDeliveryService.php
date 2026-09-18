<?php

namespace App\Services\Restaurant;

use App\Models\KitchenTicket;

class KotDeliveryService
{
    public function find(mixed $companyId, mixed $id): KitchenTicket
    {
        return KitchenTicket::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query->where('id', $id)->orWhere('kot_number', (string) $id))
            ->with(['company', 'table', 'sale.customer'])
            ->firstOrFail();
    }

    public function message(KitchenTicket $ticket): string
    {
        $ticket->loadMissing(['company', 'sale.customer']);
        $table = $ticket->table_name ?: ucfirst(str_replace('_', ' ', (string) $ticket->service_type));
        $lines = [
            'KITCHEN ORDER TICKET #'.$ticket->kot_number,
            'Restaurant: '.($ticket->company?->display_name ?: $ticket->company?->name ?: 'Restaurant'),
            'Table / Order: '.$table,
            'Service: '.ucfirst(str_replace('_', ' ', (string) $ticket->service_type)),
            'Server: '.($ticket->server_name ?: 'Staff'),
            'Sent: '.($ticket->sent_to_kitchen_at ?? $ticket->created_at ?? now())->format('d M Y, H:i'),
            '',
        ];

        foreach ((array) $ticket->items as $item) {
            $lines[] = ($item['quantity'] ?? $item['qty'] ?? 1).' × '.($item['name'] ?? 'Item');
            if (! empty($item['variant'])) {
                $lines[] = '  Variant: '.$item['variant'];
            }
            foreach ((array) ($item['modifiers'] ?? []) as $modifier) {
                $name = is_array($modifier) ? ($modifier['name'] ?? '') : $modifier;
                if ($name !== '') {
                    $lines[] = '  + '.$name;
                }
            }
            if (! empty($item['spice_level'])) {
                $lines[] = '  Spice: '.(is_array($item['spice_level']) ? ($item['spice_level']['name'] ?? '') : $item['spice_level']);
            }
            if (! empty($item['note'])) {
                $lines[] = '  Note: '.$item['note'];
            }
            if (! empty($item['seat'])) {
                $lines[] = '  Seat: '.$item['seat'];
            }
        }

        if ($ticket->kitchen_notes) {
            $lines[] = 'Kitchen notes: '.$ticket->kitchen_notes;
        }
        if ($ticket->prep_minutes) {
            $lines[] = 'Estimated prep: '.$ticket->prep_minutes.' minutes';
        }
        if ($ticket->target_completion_at) {
            $lines[] = 'Target ready: '.$ticket->target_completion_at->format('H:i');
        }

        return implode("\n", $lines);
    }

    public function variables(KitchenTicket $ticket): array
    {
        $ticket->loadMissing(['company', 'sale.customer']);
        $sale = $ticket->sale;
        $phone = $sale?->customer?->phone ?: ($sale?->customer_phone ?: data_get($sale?->gst_invoice, 'customer_phone', ''));
        $email = $sale?->customer?->email ?: ($sale?->customer_email ?: data_get($sale?->gst_invoice, 'customer_email', ''));

        return [
            'document_type' => 'kot',
            'document_id' => (string) $ticket->id,
            'document_number' => $ticket->kot_number,
            'document_code' => '#'.$ticket->kot_number,
            'kot_id' => (string) $ticket->id,
            'kot_number' => $ticket->kot_number,
            'invoice_id' => $ticket->kot_number,
            'company_name' => $ticket->company?->display_name ?: $ticket->company?->name,
            'table_name' => $ticket->table_name,
            'service_type' => $ticket->service_type,
            'server_name' => $ticket->server_name,
            'customer_name' => $sale?->customer?->name ?: ($sale?->customer_name ?: $ticket->table_name),
            'customer_phone' => $phone,
            'customer_email' => $email,
            'phone' => $phone,
            'email' => $email,
            'items' => (array) $ticket->items,
            'kitchen_notes' => $ticket->kitchen_notes,
            'message' => $this->message($ticket),
            'total' => (float) ($sale?->total ?? 0),
            'amount' => number_format((float) ($sale?->total ?? 0), 2, '.', ''),
        ];
    }
}
