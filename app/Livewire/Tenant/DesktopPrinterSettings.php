<?php

namespace App\Livewire\Tenant;

use App\Services\Printing\DesktopPrintService;
use Livewire\Component;

/**
 * Machine-local printer preferences for the desktop app — which installed
 * Windows printer to use for 58mm receipts, 80mm receipts, and A4 invoices/
 * quotations. Renders nothing on the regular web app. Stored via NativePHP's
 * local Settings store (see DesktopPrintService::defaultPrinterFor), not
 * synced — a printer choice belongs to this machine, not the tenant.
 */
class DesktopPrinterSettings extends Component
{
    public bool $isDesktop = false;

    public array $printers = [];

    public string $printer58mm = '';

    public string $printer80mm = '';

    public string $printerA4 = '';

    public function mount(DesktopPrintService $service): void
    {
        $this->isDesktop = $service->isDesktop();

        if (! $this->isDesktop) {
            return;
        }

        $this->printers = $service->availablePrinters();
        $this->printer58mm = (string) ($service->defaultPrinterFor('58mm') ?? '');
        $this->printer80mm = (string) ($service->defaultPrinterFor('80mm') ?? '');
        $this->printerA4 = (string) ($service->defaultPrinterFor('a4') ?? '');
    }

    public function save(DesktopPrintService $service): void
    {
        if (! $this->isDesktop) {
            return;
        }

        try {
            if (filled($this->printer58mm)) {
                $service->setDefaultPrinter('58mm', $this->printer58mm);
            }
            if (filled($this->printer80mm)) {
                $service->setDefaultPrinter('80mm', $this->printer80mm);
            }
            if (filled($this->printerA4)) {
                $service->setDefaultPrinter('a4', $this->printerA4);
            }

            session()->flash('status', 'Printer preferences saved for this device.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Could not save printer settings: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.tenant.desktop-printer-settings');
    }
}
