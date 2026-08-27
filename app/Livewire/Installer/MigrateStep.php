<?php

namespace App\Livewire\Installer;

use Database\Seeders\PlatformDefaultsSeeder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.installer', ['step' => 3])]
class MigrateStep extends Component
{
    public bool $ran = false;

    public bool $failed = false;

    public string $output = '';

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->output = Artisan::output();

            (new PlatformDefaultsSeeder)->run();

            $this->ran = true;
        } catch (\Throwable $e) {
            $this->failed = true;
            $this->output = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.installer.migrate-step');
    }
}
