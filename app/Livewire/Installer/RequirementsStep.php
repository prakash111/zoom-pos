<?php

namespace App\Livewire\Installer;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.installer', ['step' => 1])]
class RequirementsStep extends Component
{
    public array $checks = [];

    public bool $allPassed = false;

    public function mount(): void
    {
        $checks = [];

        // 1. PHP Version >= 8.2
        $checks['php_version'] = [
            'label' => 'PHP Version >= 8.2.0 (running '.PHP_VERSION.')',
            'pass' => version_compare(PHP_VERSION, '8.2.0', '>='),
        ];

        // 2. Memory Limit >= 256M
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseMemoryBytes($memoryLimit);
        $minMemoryBytes = 256 * 1024 * 1024;
        $checks['memory_limit'] = [
            'label' => 'PHP Memory Limit >= 256M (current: '.($memoryLimit === '-1' ? 'Unlimited' : $memoryLimit).')',
            'pass' => ($memoryLimit === '-1' || $memoryBytes >= $minMemoryBytes),
        ];

        // 3. Mandatory PHP Extensions
        $extensions = [
            'bcmath' => 'BCMath Extension',
            'ctype' => 'Ctype Extension',
            'curl' => 'cURL Extension',
            'dom' => 'DOM XML Extension',
            'fileinfo' => 'Fileinfo Extension',
            'json' => 'JSON Extension',
            'mbstring' => 'Mbstring Extension',
            'openssl' => 'OpenSSL Extension',
            'pcre' => 'PCRE Extension',
            'pdo' => 'PDO Extension',
            'pdo_mysql' => 'PDO MySQL Driver',
            'tokenizer' => 'Tokenizer Extension',
            'xml' => 'XML Extension',
        ];

        foreach ($extensions as $ext => $label) {
            $checks["ext:{$ext}"] = [
                'label' => "PHP extension: {$label} ({$ext})",
                'pass' => extension_loaded($ext),
            ];
        }

        // GD or Imagick for receipts / barcode rendering
        $hasGdOrImagick = extension_loaded('gd') || extension_loaded('imagick');
        $checks['ext:graphics'] = [
            'label' => 'PHP Graphics Extension: GD or Imagick (for QR codes & Receipts)',
            'pass' => $hasGdOrImagick,
        ];

        // 4. Directory & File Write Permissions
        $writablePaths = [
            'storage' => storage_path(),
            'storage/framework' => storage_path('framework'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            '.env' => base_path('.env'),
        ];

        foreach ($writablePaths as $label => $path) {
            $exists = file_exists($path);
            $isWritable = $exists ? is_writable($path) : is_writable(dirname($path));
            $checks["writable:{$label}"] = [
                'label' => "Directory/File Writable: {$label}",
                'pass' => $isWritable,
            ];
        }

        $this->checks = $checks;
        $this->allPassed = collect($checks)->every(fn ($c) => $c['pass']);
    }

    protected function parseMemoryBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '-1') {
            return PHP_INT_MAX;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) substr($val, 0, -1);
        switch ($last) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return (int) $val;
        }
    }

    public function render()
    {
        return view('livewire.installer.requirements-step');
    }
}
