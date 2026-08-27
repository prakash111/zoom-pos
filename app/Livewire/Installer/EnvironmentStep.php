<?php

namespace App\Livewire\Installer;

use App\Services\Installer\EnvironmentWriterService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.installer', ['step' => 2])]
class EnvironmentStep extends Component
{
    public string $appUrl = '';

    public string $dbConnection = 'mysql';

    public string $dbHost = '127.0.0.1';

    public string $dbPort = '3306';

    public string $dbDatabase = '';

    public string $dbUsername = '';

    public string $dbPassword = '';

    public ?bool $connectionOk = null;

    public string $connectionMessage = '';

    public function mount(): void
    {
        $this->appUrl = config('app.url');
    }

    protected function rules(): array
    {
        return [
            'appUrl' => ['required', 'url'],
            'dbConnection' => ['required', 'in:mysql,sqlite'],
            'dbHost' => ['required_if:dbConnection,mysql'],
            'dbPort' => ['required_if:dbConnection,mysql'],
            'dbDatabase' => ['required'],
            'dbUsername' => ['required_if:dbConnection,mysql'],
            'dbPassword' => ['nullable'],
        ];
    }

    public function testConnection(): void
    {
        $this->validate();

        if ($this->dbConnection === 'sqlite') {
            $this->connectionOk = true;
            $this->connectionMessage = 'SQLite selected — no server connection needed.';

            return;
        }

        try {
            new \PDO(
                "mysql:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbDatabase}",
                $this->dbUsername,
                $this->dbPassword,
                [\PDO::ATTR_TIMEOUT => 5]
            );
            $this->connectionOk = true;
            $this->connectionMessage = 'Connection successful.';
        } catch (\PDOException $e) {
            $this->connectionOk = false;
            $this->connectionMessage = 'Connection failed: '.$e->getMessage();
        }
    }

    public function save()
    {
        $this->validate();

        $writer = new EnvironmentWriterService;
        $writer->write([
            'APP_URL' => $this->appUrl,
            'DB_CONNECTION' => $this->dbConnection,
            'DB_HOST' => $this->dbHost,
            'DB_PORT' => $this->dbPort,
            'DB_DATABASE' => $this->dbDatabase,
            'DB_USERNAME' => $this->dbUsername,
            'DB_PASSWORD' => $this->dbPassword,
        ]);

        Artisan::call('config:clear');

        return redirect()->route('install.migrate');
    }

    public function render()
    {
        return view('livewire.installer.environment-step');
    }
}
