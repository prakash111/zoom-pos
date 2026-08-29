<?php

namespace App\Services\Sync;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Delivery\MessageQueueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs INSIDE the NativePHP desktop app (against its local SQLite database)
 * and talks to the tenant's remote server as an HTTP client — this is the
 * "Local Database -> Sync Engine -> Server" leg of the offline architecture.
 * It is the only piece of code that makes outbound network calls for sync;
 * everything else in the app (POS, products, reports, ...) reads/writes the
 * local database directly and never waits on this class.
 *
 * Credentials/settings are stored per-company in the existing `configurations`
 * key-value table under the `desktop_sync.*` namespace — the device token is
 * encrypted at rest (Laravel's Crypt facade), never written in plaintext.
 */
class DesktopSyncClient
{
    protected const STATUS_OFFLINE = 'offline';

    protected const STATUS_ONLINE = 'online';

    protected const STATUS_SYNCING = 'syncing';

    protected const STATUS_SYNCED = 'synced';

    /**
     * Legacy tables PosSyncApiController owns, mapped for the generic push
     * loop below — the wire shape for creates matches sync-batch's existing
     * created_products/created_customers keys.
     *
     * Categories/brands/suppliers/units were previously only ever pulled
     * (see legacyPullableModels below) — a category or brand created or
     * edited on the desktop app had nowhere to go and was silently stuck
     * on that one device forever, never reaching the server or any other
     * device. Listing them here is enough: this class's generic push loop
     * (pushLegacyDirty) already builds the wire payload and marks rows
     * synced purely from fillable()+external_id, with no per-model code.
     */
    protected array $legacyPushableModels = [
        'created_products' => Product::class,
        'created_customers' => Customer::class,
        'created_categories' => Category::class,
        'created_brands' => Brand::class,
        'created_suppliers' => Supplier::class,
        'created_units' => Unit::class,
    ];

    /**
     * Read-only local mirrors: pulled from PosSyncApiController::syncPull,
     * upserted locally by external_id (falls back to the server's numeric id
     * for rows that predate SyncableModel).
     */
    protected array $legacyPullableModels = [
        'products' => Product::class,
        'categories' => Category::class,
        'customers' => Customer::class,
        'suppliers' => Supplier::class,
        'brands' => Brand::class,
        'units' => Unit::class,
        'sales' => Sale::class,
        'quotations' => Sale::class,
    ];

    /**
     * PosSyncApiController::syncPull() emits a hand-shaped wire format that
     * doesn't always match the local Eloquent column names (e.g. it calls a
     * product's sale price "price", not "sale_price") — a legacy of this
     * being the same payload shape the standalone POS terminal client reads.
     * Without translating these, upsertLocal()'s `only($fillable)` silently
     * drops every field whose wire name isn't also a column name, which is
     * exactly why pulled products used to land locally with a real name but
     * a zeroed price and stock. table_key => [wire_field => model_column].
     */
    protected array $legacyPullFieldAliases = [
        'products' => ['price' => 'sale_price', 'stock' => 'current_stock', 'min_stock' => 'minimum_stock'],
        'sales' => ['tax' => 'tax_amount'],
        'quotations' => ['tax' => 'tax_amount', 'quote_number' => 'sale_number', 'valid_until' => 'due_date'],
    ];

    public function __construct(protected Company $company, protected DesktopSyncEngine $engine)
    {
    }

    public function isConfigured(): bool
    {
        return filled($this->deviceToken());
    }

    public function configure(string $baseUrl, string $deviceToken): void
    {
        $this->setConfig('remote_base_url', rtrim($baseUrl, '/'));
        $this->setConfig('device_token', Crypt::encryptString($deviceToken));
    }

    public function status(): string
    {
        return $this->getConfig('status', self::STATUS_OFFLINE);
    }

    public function lastSyncedAt(): ?Carbon
    {
        $value = $this->getConfig('last_synced_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function checkConnectivity(): bool
    {
        if (! $this->isConfigured()) {
            $this->setStatus(self::STATUS_OFFLINE);

            return false;
        }

        try {
            $online = $this->http()->timeout(4)->get('/api/health')->successful();
        } catch (\Throwable) {
            $online = false;
        }

        $this->setStatus($online ? self::STATUS_ONLINE : self::STATUS_OFFLINE);

        return $online;
    }

    /**
     * Run one full sync cycle: push everything dirty, then pull everything
     * changed since the last successful pull. Safe to call repeatedly — a
     * failed cycle just leaves rows dirty/the cursor unmoved for the next try.
     */
    public function runCycle(): array
    {
        if (! $this->checkConnectivity()) {
            return ['status' => self::STATUS_OFFLINE];
        }

        $this->setStatus(self::STATUS_SYNCING);

        try {
            $pushed = $this->pushLegacyDirty() + $this->pushGenericDirty();
            $pulled = $this->pullLegacy() + $this->pullGeneric();

            // Connection is confirmed up — drain anything sitting in the
            // local email/WhatsApp delivery queue right now, same trigger
            // point as "When internet returns" in the sync engine itself.
            $queueResult = app(MessageQueueService::class)->processDue($this->company);

            $this->setConfig('last_synced_at', now()->toIso8601String());
            $this->setStatus(self::STATUS_SYNCED);

            return ['status' => self::STATUS_SYNCED, 'pushed' => $pushed, 'pulled' => $pulled, 'queue' => $queueResult];
        } catch (\Throwable $e) {
            Log::warning('Desktop sync cycle failed, will retry next cycle.', ['exception' => $e]);
            $this->setStatus(self::STATUS_ONLINE);

            return ['status' => self::STATUS_ONLINE, 'error' => $e->getMessage()];
        }
    }

    /**
     * Whether there is anything dirty right now — lets the caller shorten the
     * retry interval only when it's actually useful (see RunDesktopSyncCycle).
     */
    public function hasPendingWork(): bool
    {
        foreach ($this->legacyPullableModels as $modelClass) {
            if (method_exists($modelClass, 'scopeUnsynced') && $modelClass::query()->withoutGlobalScope('company')
                ->where('company_id', $this->company->id)->unsynced()->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function pushLegacyDirty(): int
    {
        $count = 0;
        $payload = [];
        $dirtyRows = [];

        foreach ($this->legacyPushableModels as $wireKey => $modelClass) {
            $dirty = $modelClass::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $this->company->id)
                ->unsynced()
                ->get();

            if ($dirty->isEmpty()) {
                continue;
            }

            $payload[$wireKey] = $dirty->map(fn ($row) => array_merge(
                collect($row->getAttributes())->only($row->getFillable())->all(),
                ['id' => $row->external_id, 'updated_at' => $row->updated_at?->toIso8601String()],
            ))->values()->all();

            $count += $dirty->count();
            $dirtyRows[] = $dirty;
        }

        if (empty($payload)) {
            return 0;
        }

        // Only mark rows synced once the server has actually acknowledged
        // them — if this throws, every row here stays dirty for the next cycle.
        $this->http()->post('/api/v1/pos/sync-batch', $payload)->throw();

        foreach ($dirtyRows as $dirty) {
            $dirty->each->markSynced();
        }

        return $count;
    }

    protected function pushGenericDirty(): int
    {
        $payload = [];
        $count = 0;

        foreach ($this->engine->syncableModelClasses() as $key => $modelClass) {
            $dirty = $modelClass::query()->withoutGlobalScope('company')
                ->where('company_id', $this->company->id)->unsynced()->get();

            if ($dirty->isEmpty()) {
                continue;
            }

            $payload[$key] = $dirty->map(fn ($row) => $this->engine->rowToPushPayload($key, $row))->values()->all();
            $count += $dirty->count();
        }

        if (empty($payload)) {
            return 0;
        }

        $this->http()->post('/api/v1/pos/desktop-sync/push', $payload)->throw();

        foreach ($this->engine->syncableModelClasses() as $key => $modelClass) {
            if (isset($payload[$key])) {
                $modelClass::query()->withoutGlobalScope('company')
                    ->where('company_id', $this->company->id)->unsynced()->get()->each->markSynced();
            }
        }

        return $count;
    }

    protected function pullLegacy(): int
    {
        $since = $this->getConfig('last_pull_at');
        $response = $this->http()->get('/api/v1/pos/sync-catalog', $since ? ['since' => $since] : [])->throw()->json();

        $count = 0;
        foreach ($this->legacyPullableModels as $wireKey => $modelClass) {
            foreach ($response[$wireKey] ?? [] as $row) {
                if ($wireKey === 'quotations') {
                    // The wire payload has no operation_type field of its own
                    // (it's implied by which top-level key the row arrived
                    // under) — without setting it explicitly here, a pulled
                    // quotation would land indistinguishable from a regular
                    // sale in every local query that filters on it.
                    $row['operation_type'] = 'quotation';
                }

                $this->upsertLocal($modelClass, $row, $this->legacyPullFieldAliases[$wireKey] ?? []);
                $count++;
            }
        }

        // Business/receipt settings changed in Settings on the web (or any
        // other device) previously never reached this device again after the
        // one-time bootstrap that created this local Company row — every
        // subsequent edit was silently stuck server-side. Read-only refresh
        // only: this deliberately never pushes a local edit back up, since
        // Company also carries plan/billing fields this sync surface must
        // never touch (see the field allowlist in PosSyncApiController::
        // syncPull()).
        if (! empty($response['company']) && is_array($response['company'])) {
            $this->company->fill($response['company'])->save();
        }

        $this->setConfig('last_pull_at', $response['server_time'] ?? now()->toIso8601String());

        return $count;
    }

    protected function pullGeneric(): int
    {
        $since = $this->getConfig('last_generic_pull_at');
        $response = $this->http()->get('/api/v1/pos/desktop-sync/pull', $since ? ['since' => $since] : [])->throw()->json();

        $count = 0;
        foreach ($this->engine->syncableModelClasses() as $key => $modelClass) {
            foreach ($response['data'][$key] ?? [] as $row) {
                $this->engine->upsertFromPull($key, $this->company, $row);
                $count++;
            }
        }

        $this->setConfig('last_generic_pull_at', $response['server_time'] ?? now()->toIso8601String());

        return $count;
    }

    protected function upsertLocal(string $modelClass, array $row, array $fieldAliases = []): void
    {
        $externalId = (string) ($row['id'] ?? $row['server_id'] ?? null);
        if ($externalId === '') {
            return;
        }

        foreach ($fieldAliases as $wireField => $column) {
            if (array_key_exists($wireField, $row) && ! array_key_exists($column, $row)) {
                $row[$column] = $row[$wireField];
            }
        }

        $existing = $modelClass::query()->withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->where(fn ($q) => $q->where('external_id', $externalId)->orWhere('id', $row['server_id'] ?? -1))
            ->first();

        $fillable = (new $modelClass)->getFillable();
        $attributes = collect($row)->only($fillable)->all();
        $attributes['company_id'] = $this->company->id;
        if (in_array('external_id', $fillable, true)) {
            $attributes['external_id'] = $existing?->external_id ?: $externalId;
        }

        $model = $existing ?: new $modelClass;
        $model->fill($attributes);
        $model->company_id = $this->company->id;
        $model->save();

        if (method_exists($model, 'markSynced')) {
            $model->markSynced();
        }
    }

    protected function deviceToken(): ?string
    {
        $encrypted = $this->getConfig('device_token');
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function http()
    {
        return Http::baseUrl($this->getConfig('remote_base_url', config('nativephp.website', 'https://saas.zoomnearby.com')))
            ->withToken($this->deviceToken())
            ->acceptJson()
            // Without a bound timeout, a slow/unresponsive server would hang
            // this indefinitely — and runCycle() is also called synchronously
            // right after login (see TenantLogin), so a hang here would hang
            // the login screen itself, not just a background job.
            ->timeout(20);
    }

    protected function setStatus(string $status): void
    {
        $this->setConfig('status', $status);
    }

    protected function getConfig(string $key, $default = null)
    {
        return Configuration::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('key', "desktop_sync.{$key}")
            ->value('value') ?? $default;
    }

    protected function setConfig(string $key, $value): void
    {
        Configuration::withoutGlobalScopes()->updateOrCreate([
            'company_id' => $this->company->id,
            'key' => "desktop_sync.{$key}",
        ], [
            'value' => $value,
        ]);
    }
}
