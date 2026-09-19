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
        $detectedUrl = rtrim((string) (request()->root() ?: request()->getSchemeAndHttpHost()), '/');
        $configuredUrl = rtrim((string) config('app.url'), '/');

        if (! empty($detectedUrl) && ! str_contains($detectedUrl, 'yourdomain.com')) {
            $this->appUrl = $detectedUrl;
        } elseif (! empty($configuredUrl) && ! str_contains($configuredUrl, 'yourdomain.com')) {
            $this->appUrl = $configuredUrl;
        } else {
            $this->appUrl = $detectedUrl ?: 'http://localhost';
        }

        $host = env('DB_HOST') ?: config('database.connections.mysql.host');
        if (! empty($host) && ! in_array($host, ['your_database_host', 'your_db_host'], true)) {
            $this->dbHost = $host;
        } else {
            $this->dbHost = '127.0.0.1';
        }

        $port = env('DB_PORT') ?: config('database.connections.mysql.port');
        $this->dbPort = ! empty($port) ? (string) $port : '3306';
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
        $this->connectionOk = null;
        $this->connectionMessage = '';

        if ($this->dbConnection === 'sqlite') {
            $this->connectionOk = true;
            $this->connectionMessage = 'SQLite selected — no server connection needed.';

            return;
        }

        $host = trim($this->dbHost);
        $port = trim($this->dbPort) ?: '3306';
        $user = trim($this->dbUsername);
        $database = trim($this->dbDatabase);

        if ($host === '') {
            $this->connectionOk = false;
            $this->connectionMessage = 'Database Host is required to test MySQL connection.';

            return;
        }

        if ($user === '') {
            $this->connectionOk = false;
            $this->connectionMessage = 'Database Username is required to test MySQL connection.';

            return;
        }

        try {
            $dsn = "mysql:host={$host};port={$port}";
            if ($database !== '') {
                $dsn .= ";dbname={$database}";
            }

            new \PDO(
                $dsn,
                $user,
                $this->dbPassword,
                [
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]
            );

            $this->connectionOk = true;
            $this->connectionMessage = $database === ''
                ? 'Success: Connected to MySQL server successfully!'
                : "Success: Connected to database '{$database}' on {$host} successfully!";
        } catch (\Throwable $e) {
            $this->connectionOk = false;
            $this->connectionMessage = 'Connection failed: '.$e->getMessage();
        }
    }

    public function save()
    {
        $this->validate();

        if ($this->dbConnection === 'mysql') {
            try {
                $dsn = "mysql:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbDatabase}";
                new \PDO($dsn, $this->dbUsername, $this->dbPassword, [
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (\Throwable $e) {
                $this->connectionOk = false;
                $this->connectionMessage = 'Cannot proceed: Database connection failed. '.$e->getMessage();

                return;
            }
        }

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
