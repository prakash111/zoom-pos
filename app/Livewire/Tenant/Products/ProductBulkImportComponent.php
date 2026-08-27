<?php

namespace App\Livewire\Tenant\Products;

use App\Jobs\GenerateProductImage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

class ProductBulkImportComponent extends Component
{
    use WithFileUploads;

    public $importFile = null;

    public bool $autoGenerateAiImages = true;

    public bool $showImportModal = false;

    public function downloadDemoData(string $format = 'csv')
    {
        abort_unless(in_array($format, ['csv', 'txt'], true), 404);
        $rows = [
            ['name', 'category', 'item_code', 'barcode', 'cost_price', 'sale_price', 'stock'],
            ['Organic Italian Espresso', 'Beverages', 'BEV-001', '890123456789', '4.50', '9.50', '50'],
            ['Artisan Sourdough Loaf', 'Bakery', 'BAK-002', '890987654321', '1.80', '4.50', '25'],
            ['Fresh Hass Avocado', 'Produce', 'PRD-003', '890456123789', '0.90', '2.50', '100'],
        ];
        $separator = $format === 'csv' ? ',' : ' | ';
        $body = collect($rows)->map(fn ($row) => implode($separator, $row))->implode("\n")."\n";

        return response()->streamDownload(fn () => print ($body), "demo_products.{$format}", ['Content-Type' => $format === 'csv' ? 'text/csv' : 'text/plain']);
    }

    public function processImport(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'max:10240', 'extensions:csv,txt,docx']]);
        $extension = strtolower($this->importFile->getClientOriginalExtension());
        $rows = $this->parse($this->importFile->getRealPath(), $extension);
        $companyId = (string) auth('web')->user()->company_id;
        $createdIds = [];

        DB::transaction(function () use ($rows, &$createdIds) {
            foreach ($rows as $row) {
                if (count($row) < 2 || blank($row[0] ?? null)) {
                    continue;
                }
                $categoryName = trim($row[1] ?: 'General');
                $category = Category::firstOrCreate(['name' => $categoryName], ['active' => true]);
                $product = Product::create([
                    'name' => trim($row[0]), 'category_id' => $category->id, 'category_name' => $category->name,
                    'code' => filled($row[2] ?? null) ? trim($row[2]) : 'SKU-'.random_int(100000, 999999),
                    'barcode' => filled($row[3] ?? null) ? trim($row[3]) : null,
                    'cost_price' => (float) ($row[4] ?? 0), 'sale_price' => (float) ($row[5] ?? 0),
                    'current_stock' => (float) ($row[6] ?? 0), 'minimum_stock' => 0, 'active' => true, 'taxable' => true,
                ]);
                $createdIds[] = $product->id;
            }
        });

        if ($this->autoGenerateAiImages) {
            foreach ($createdIds as $id) {
                GenerateProductImage::dispatch($id, $companyId);
            }
        }

        $count = count($createdIds);
        $this->reset(['importFile', 'showImportModal']);
        $this->dispatch('products-imported');
        session()->flash('status', trans_choice(':count product imported.|:count products imported.', $count, ['count' => $count]).($this->autoGenerateAiImages ? ' '.__('AI images were queued.') : ''));
    }

    private function parse(string $path, string $extension): array
    {
        if ($extension === 'docx') {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
                throw ValidationException::withMessages(['importFile' => __('Unable to read this DOCX file.')]);
            }
            $zip->close();
            $xml = str_contains($xml, '<w:tbl')
                ? str_replace(['</w:p>', '</w:tc>', '</w:tr>'], ['', ' | ', "\n"], $xml)
                : str_replace('</w:p>', "\n", $xml);
            $contents = html_entity_decode(strip_tags($xml));
        } else {
            $contents = (string) file_get_contents($path);
        }

        $lines = preg_split('/\R/', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rows = array_map(fn ($line) => $extension === 'csv' ? str_getcsv($line) : array_map('trim', explode('|', $line)), $lines);
        if ($rows && strtolower(trim((string) ($rows[0][0] ?? ''))) === 'name') {
            array_shift($rows);
        }

        return $rows;
    }

    public function render()
    {
        return view('livewire.tenant.products.product-bulk-import-component');
    }
}
