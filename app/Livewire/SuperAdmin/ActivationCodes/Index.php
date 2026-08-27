<?php

namespace App\Livewire\SuperAdmin\ActivationCodes;

use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'Activation Codes'])]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $planName = '';

    public ?int $maxUses = 1;

    public ?int $validityDays = 30;

    public string $notes = '';

    public ?string $justGeneratedCode = null;

    protected function rules(): array
    {
        return [
            'planName' => ['required', 'string', 'exists:plans,name'],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'validityDays' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function generate(): void
    {
        $data = $this->validate();

        $plaintext = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $code = ActivationCode::create([
            'code_hash' => Hash::make($plaintext),
            'code_prefix' => substr($plaintext, 0, 7),
            'plan_name' => $data['planName'],
            'max_uses' => $data['maxUses'],
            'validity_days' => $data['validityDays'] ?: null,
            'expires_at' => $data['validityDays'] ? now()->addDays($data['validityDays']) : null,
            'notes' => $data['notes'] ?: null,
            'created_by' => auth('platform_web')->id(),
        ]);

        AuditLog::record('activation_code.generated', null, auth('platform_web')->id(), ['id' => $code->id, 'plan' => $code->plan_name]);

        $this->justGeneratedCode = $plaintext;
        $this->reset(['planName', 'notes']);
        $this->maxUses = 1;
        $this->validityDays = 30;
    }

    public function revoke(string $id): void
    {
        $code = ActivationCode::findOrFail($id);
        if ($code->current_uses > 0) {
            session()->flash('error', 'Cannot revoke a code that has already been redeemed.');

            return;
        }

        $code->update(['revoked' => true]);
        AuditLog::record('activation_code.revoked', null, auth('platform_web')->id(), ['id' => $code->id]);
        session()->flash('status', 'Code revoked.');
    }

    public function render()
    {
        return view('livewire.superadmin.activation-codes.index', [
            'codes' => ActivationCode::query()->orderByDesc('created_at')->paginate(15),
            'plans' => Plan::query()->where('active', true)->orderBy('display_name')->get(),
        ]);
    }
}
