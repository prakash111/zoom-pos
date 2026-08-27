<?php

namespace App\Services\Sync;

use App\Models\Company;
use App\Models\ConsignmentItem;
use App\Models\Consignment;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\OrderPayment;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generic desktop offline-sync engine for the tenant modules NOT already
 * covered by PosSyncApiController (products/categories/customers/suppliers/
 * sales/quotations — those keep their existing, hand-tuned wire format).
 *
 * Every table here follows the same rule already established by the legacy
 * sync code: idempotent upsert keyed on (company_id, external_id). Rows
 * created through the normal web UI also carry an external_id (via
 * SyncableModel), so pull responses never need to fall back to a numeric
 * server id as a stand-in identifier.
 */
class DesktopSyncEngine
{
    /**
     * table_key => [
     *   'model' => Eloquent model class,
     *   'parents' => [local fk column => parent Eloquent model class],
     * ]
     *
     * `parents` entries are foreign keys whose value must be translated
     * between the client's external_id and the server's numeric id (and
     * vice versa) when crossing the wire — this is how relationships are
     * preserved across the sync boundary (e.g. a cash movement referencing
     * the register it belongs to).
     */
    public function registry(): array
    {
        return [
            'cash_registers' => [
                'model' => CashRegister::class,
                'parents' => [],
            ],
            'cash_register_transactions' => [
                'model' => CashRegisterTransaction::class,
                'parents' => ['cash_register_id' => CashRegister::class],
            ],
            'sales_targets' => [
                'model' => SalesTarget::class,
                'parents' => [],
            ],
            'consignments' => [
                'model' => Consignment::class,
                'parents' => ['sale_id' => Sale::class],
            ],
            'service_orders' => [
                'model' => ServiceOrder::class,
                'parents' => [],
            ],
            'vendor_bills' => [
                'model' => VendorBill::class,
                'parents' => [],
            ],
            'vendor_bill_payments' => [
                'model' => VendorBillPayment::class,
                'parents' => ['vendor_bill_id' => VendorBill::class],
            ],
            'order_payments' => [
                'model' => OrderPayment::class,
                'parents' => ['sale_id' => Sale::class],
            ],
        ];
    }

    /**
     * Pull every row (or the delta since $since) for every registered table.
     *
     * @return array<string, array>
     */
    public function pull(Company $company, ?Carbon $since = null): array
    {
        $result = [];

        foreach ($this->registry() as $key => $config) {
            $query = $config['model']::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id);

            if ($since) {
                $query->where('updated_at', '>=', $since);
            }

            $rows = $key === 'consignments' ? $query->with('items')->get() : $query->get();

            $result[$key] = $rows->map(fn (Model $row) => $this->toWire($key, $config, $row))->values()->all();
        }

        return $result;
    }

    /**
     * table_key => model class, for callers (DesktopSyncClient) that just
     * need to iterate the registry without the parent-fk metadata.
     */
    public function syncableModelClasses(): array
    {
        return collect($this->registry())->map(fn (array $config) => $config['model'])->all();
    }

    /**
     * Translate a single local row to its wire shape (parent fks resolved to
     * external_id). Shared by pull responses and by DesktopSyncClient when
     * pushing local rows up to the server — the translation direction is
     * identical either way: local numeric fk -> external_id on the wire.
     */
    public function rowToPushPayload(string $key, Model $row): array
    {
        return $this->toWire($key, $this->registry()[$key], $row);
    }

    /**
     * Upsert a single pulled row into the local database — the mirror image
     * of upsertRow(), reused as-is since "upsert by external_id, translate
     * parent fks via their external_id" is the same operation regardless of
     * which side of the wire is doing the writing.
     */
    public function upsertFromPull(string $key, Company $company, array $row): void
    {
        $this->upsertRow($key, $this->registry()[$key], $company, null, $row);
    }

    protected function toWire(string $key, array $config, Model $row): array
    {
        $wire = collect($row->getAttributes())
            ->only($row->getFillable())
            ->all();

        $wire['external_id'] = $row->external_id;
        $wire['created_at'] = $row->created_at?->toIso8601String();
        $wire['updated_at'] = $row->updated_at?->toIso8601String();

        foreach ($config['parents'] as $fkColumn => $parentModelClass) {
            if (! empty($row->{$fkColumn})) {
                $wire[$fkColumn] = $parentModelClass::query()
                    ->withoutGlobalScope('company')
                    ->whereKey($row->{$fkColumn})
                    ->value('external_id') ?: $row->{$fkColumn};
            }
        }

        if ($key === 'consignments') {
            $wire['items'] = $row->items->map(fn (ConsignmentItem $item) => collect($item->getAttributes())
                ->only($item->getFillable())
                ->all())->values()->all();
        }

        return $wire;
    }

    /**
     * Push a batch payload shaped like ['cash_registers' => [...], 'consignments' => [...], ...].
     * Unknown/absent keys are ignored so a client can push only what changed.
     *
     * @return array<string, array<int, string>> table_key => synced external_ids
     */
    public function push(Company $company, ?User $user, array $payload): array
    {
        $synced = [];

        DB::transaction(function () use ($company, $user, $payload, &$synced) {
            foreach ($this->registry() as $key => $config) {
                if (empty($payload[$key]) || ! is_array($payload[$key])) {
                    continue;
                }

                foreach ($payload[$key] as $rowData) {
                    if (! is_array($rowData)) {
                        continue;
                    }

                    $synced[$key][] = $this->upsertRow($key, $config, $company, $user, $rowData);
                }
            }
        });

        return $synced;
    }

    protected function upsertRow(string $key, array $config, Company $company, ?User $user, array $rowData): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $externalId = (string) ($rowData['external_id'] ?? $rowData['id'] ?? Str::uuid());

        $existing = $modelClass::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('external_id', $externalId)
            ->first();

        $fillable = (new $modelClass)->getFillable();
        $attributes = collect($rowData)->only($fillable)->all();
        $attributes['company_id'] = $company->id;
        $attributes['external_id'] = $externalId;

        foreach ($config['parents'] as $fkColumn => $parentModelClass) {
            if (! empty($rowData[$fkColumn])) {
                $attributes[$fkColumn] = $parentModelClass::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where(function ($q) use ($rowData, $fkColumn) {
                        $q->where('external_id', (string) $rowData[$fkColumn])
                            ->orWhere('id', $rowData[$fkColumn]);
                    })
                    ->value('id');
            }
        }

        if ($existing) {
            $existing->fill($attributes)->save();
            $row = $existing;
        } else {
            $row = $modelClass::create($attributes);
        }

        if ($key === 'consignments' && isset($rowData['items']) && is_array($rowData['items'])) {
            $itemFillable = (new ConsignmentItem)->getFillable();
            $row->items()->delete();
            foreach ($rowData['items'] as $itemData) {
                if (! is_array($itemData)) {
                    continue;
                }
                $row->items()->create(collect($itemData)->only($itemFillable)->all());
            }
            $row->recalculateTotals();
        }

        $row->markSynced();

        return $externalId;
    }
}
