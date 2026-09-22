<?php

namespace App\Services\Sdui;

class DashboardSduiService
{
    /**
     * Build the Server-Driven UI (SDUI) Amount Receivable Card structure
     * with server-side navigation action directives.
     */
    public static function buildAmountReceivableCard(float $totalAmount, float $overdueTotal, float $dueTodayTotal, int $outstandingInvoicesCount = 0): array
    {
        return [
            'type' => 'receivables_card',
            'total_amount' => round($totalAmount, 2),
            'outstanding_invoices_count' => $outstandingInvoicesCount,
            'rows' => [
                [
                    'id' => 'row_overdue',
                    'label' => 'Overdue Amount',
                    'amount' => round($overdueTotal, 2),
                    'color' => '#EF4444',
                    'action' => [
                        'type' => 'navigate',
                        'route' => '/sales/invoices',
                        'params' => [
                            'status' => 'overdue',
                            'sort'   => 'due_date_asc',
                        ],
                    ],
                ],
                [
                    'id' => 'row_due_today',
                    'label' => 'Due Today',
                    'amount' => round($dueTodayTotal, 2),
                    'color' => '#F59E0B',
                    'action' => [
                        'type' => 'navigate',
                        'route' => '/sales/invoices',
                        'params' => [
                            'status' => 'due_today',
                            'sort'   => 'amount_desc',
                        ],
                    ],
                ],
            ],
        ];
    }
}
