<?php

namespace App\Services\Printing;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Support\Desktop;
use Native\Desktop\DataObjects\Printer;
use Native\Desktop\Facades\System;

/**
 * Native, dialog-free printing for the desktop app via NativePHP's
 * System::print() (Electron's webContents.print() under the hood) — renders
 * the exact same `documents.receipt` / `documents.template` Blade views used
 * for the browser print-dialog path, so desktop and web produce identical
 * output; only the delivery mechanism differs (silent vs. OS dialog).
 *
 * On the regular web app (no NativePHP runtime) every method here is a
 * no-op/empty result — callers fall back to the existing print-dialog links,
 * which is the only "printing" path that exists on the web anyway.
 */
class DesktopPrintService
{
    public function __construct(protected InvoiceDeliveryService $delivery)
    {
    }

    public function isDesktop(): bool
    {
        return Desktop::isRunning();
    }

    /**
     * @return array<int, array{name: string, label: string}>
     */
    public function availablePrinters(): array
    {
        if (! $this->isDesktop()) {
            return [];
        }

        try {
            return collect(System::printers())
                ->map(fn (Printer $p) => ['name' => $p->name, 'label' => $p->displayName ?: $p->name])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Silently print a thermal receipt (58mm/80mm) to a specific Windows printer.
     */
    public function printReceipt(Sale $sale, string $printerName, string $format = '80mm'): bool
    {
        if (! $this->isDesktop() || empty($printerName)) {
            return false;
        }

        $sale->loadMissing(['payments', 'user']);
        $company = $sale->company ?? Company::find($sale->company_id);
        $is58mm = $format === '58mm';
        $qr = $this->delivery->generateReceiptQrCode($sale, $is58mm);

        $html = $this->renderWithFormat('documents.receipt', $format, [
            'sale' => $sale,
            'company' => $company,
            'whatsAppUrl' => '',
            'backRoute' => null,
            'qrCodeSvg' => $qr['svg'],
            'qrCodeDataUri' => $qr['data_uri'],
            'verificationUrl' => $qr['url'],
        ]);

        return $this->dispatchPrint($html, $printerName, [
            'silent' => true,
            'printBackground' => true,
            'margins' => ['marginType' => 'none'],
            'pageSize' => $is58mm
                ? ['width' => 58000, 'height' => 297000] // microns; height is a generous max, printer trims to content
                : ['width' => 80000, 'height' => 297000],
        ]);
    }

    /**
     * Silently print an A4 invoice/quotation to a specific Windows printer.
     */
    public function printA4Document(Sale $sale, string $printerName): bool
    {
        if (! $this->isDesktop() || empty($printerName)) {
            return false;
        }

        $sale->loadMissing(['payments', 'user']);
        $company = $sale->company ?? Company::find($sale->company_id);
        $isQuotation = $sale->operation_type === 'quotation';

        $html = view('documents.template', [
            'sale' => $sale,
            'company' => $company,
            'documentTitle' => $isQuotation ? 'Quotation Proposal' : 'Cash Receipt / Invoice',
            'isQuotation' => $isQuotation,
            'whatsAppUrl' => '',
            'backRoute' => null,
        ])->render();

        return $this->dispatchPrint($html, $printerName, [
            'silent' => true,
            'printBackground' => true,
            'pageSize' => 'A4',
        ]);
    }

    /**
     * Reads the machine-local printer preference — deliberately stored via
     * NativePHP's local Settings facade, not the tenant `configurations`
     * table, since which printer is plugged into this machine is a property
     * of this device, not tenant business data (two terminals at the same
     * store may have different printers).
     */
    public function defaultPrinterFor(string $kind): ?string
    {
        if (! $this->isDesktop()) {
            return null;
        }

        try {
            return \Native\Desktop\Facades\Settings::get("printer_{$kind}") ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function setDefaultPrinter(string $kind, string $printerName): void
    {
        \Native\Desktop\Facades\Settings::set("printer_{$kind}", $printerName);
    }

    /**
     * Print using whichever printer is configured for this format — returns
     * false (does nothing) if desktop printing isn't set up, so callers can
     * fall back to the browser print-dialog route unchanged.
     */
    public function printReceiptAuto(Sale $sale, string $format = '80mm'): bool
    {
        $printer = $this->defaultPrinterFor($format);

        return $printer ? $this->printReceipt($sale, $printer, $format) : false;
    }

    public function printA4DocumentAuto(Sale $sale): bool
    {
        $printer = $this->defaultPrinterFor('a4');

        return $printer ? $this->printA4Document($sale, $printer) : false;
    }

    protected function dispatchPrint(string $html, string $printerName, array $settings): bool
    {
        try {
            $printer = collect(System::printers())->first(fn (Printer $p) => $p->name === $printerName);
            System::print($html, $printer, $settings);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * documents.receipt reads its paper size from the current request's
     * `format` query parameter (shared with the browser print-dialog route),
     * so temporarily set it on the bound request to force the right size
     * without duplicating the view.
     */
    protected function renderWithFormat(string $view, string $format, array $data): string
    {
        $request = request();
        $original = $request->query('format');

        $request->query->set('format', $format);

        try {
            return view($view, $data)->render();
        } finally {
            if ($original === null) {
                $request->query->remove('format');
            } else {
                $request->query->set('format', $original);
            }
        }
    }
}
