<?php

namespace App\Services\Sync;

use App\Models\Company;
use App\Models\KvStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Backs MysqlSyncController. Resolves the calling tenant strictly from the
 * validated bearer token (never from client-supplied ids), then reads/writes
 * through a real Eloquent model when config/sync_tables.php has a mapping
 * for the requested table, falling back to the generic of_kv_store for
 * anything not yet mapped — which is what lets save_table/load_all work for
 * the entire legacy action surface starting in Milestone 1.
 */
class SyncCompatService
{
    public function __construct(protected FieldAliasMap $aliases) {}

    public function resolveTenant(Request $request): Company
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant_api')->user();

        if (! $user) {
            abort(response()->json(['success' => false, 'error' => 'Unauthorized'], 401));
        }

        return $user->company;
    }

    public function saveTable(Company $company, string $table, array $row): array
    {
        $modelClass = config("sync_tables.{$table}");
        $row = $this->aliases->normalizeForWrite($table, $row);

        if ($modelClass) {
            $externalId = $row['id'] ?? $row['external_id'] ?? null;
            /** @var Model $record */
            $record = $modelClass::query()->forCompany($company->id)
                ->when($externalId, fn ($q) => $q->where('external_id', $externalId))
                ->first() ?? new $modelClass;

            $record->company_id = $company->id;
            if ($externalId) {
                $record->external_id = $externalId;
            }
            $record->fill(array_intersect_key($row, array_flip($record->getFillable())));
            $record->save();

            return ['success' => true, 'id' => $record->external_id ?? $record->getKey()];
        }

        $blob = KvStore::query()->forCompany($company->id)->where('store_key', $table)->first();
        $rows = $blob ? (json_decode($blob->value, true) ?: []) : [];

        $id = $row['id'] ?? null;
        $matched = false;
        if ($id !== null) {
            foreach ($rows as $i => $existing) {
                if (($existing['id'] ?? null) == $id) {
                    $rows[$i] = array_merge($existing, $row);
                    $matched = true;
                    break;
                }
            }
        }
        if (! $matched) {
            $rows[] = $row;
        }

        KvStore::query()->updateOrCreate(
            ['company_id' => $company->id, 'store_key' => $table],
            ['value' => json_encode($rows)]
        );

        return ['success' => true, 'id' => $id];
    }

    public function loadAll(Company $company): array
    {
        $result = [];

        foreach (config('sync_tables', []) as $table => $modelClass) {
            $result[$table] = $modelClass::query()->forCompany($company->id)->get()
                ->map(fn ($row) => $this->aliases->expandForRead($table, $this->presentForClient($row)))
                ->all();
        }

        $kvBlobs = KvStore::query()->forCompany($company->id)->get();
        foreach ($kvBlobs as $blob) {
            if (isset($result[$blob->store_key])) {
                continue; // a real model mapping takes precedence over stale KV data
            }
            $rows = json_decode($blob->value, true) ?: [];
            $result[$blob->store_key] = array_map(
                fn ($row) => $this->aliases->expandForRead($blob->store_key, $row),
                $rows
            );
        }

        return $result;
    }

    /**
     * Round-trip stability for the legacy client: it minted "id" itself
     * offline and matches its own local rows against whatever "id" comes
     * back, with no concept of an internal auto-increment key — so a mapped
     * model's outward-facing "id" must be its external_id, not the real PK.
     */
    protected function presentForClient(Model $row): array
    {
        $attributes = $row->toArray();

        if (array_key_exists('external_id', $attributes) && $attributes['external_id']) {
            $attributes['id'] = $attributes['external_id'];
        }

        return $attributes;
    }
}
