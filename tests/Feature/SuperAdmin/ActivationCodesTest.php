<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\ActivationCodes\Index;
use App\Models\ActivationCode;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ActivationCodesTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_generating_a_code_shows_plaintext_once_and_stores_only_a_hash(): void
    {
        $this->actingAsSuperAdmin();
        Plan::create(['name' => 'starter', 'display_name' => 'Starter', 'billing_cycle' => 'monthly', 'price' => 19]);

        $component = Livewire::test(Index::class)
            ->set('planName', 'starter')
            ->set('maxUses', 1)
            ->set('validityDays', 30)
            ->call('generate');

        $plaintext = $component->get('justGeneratedCode');
        $this->assertNotEmpty($plaintext);

        $code = ActivationCode::firstOrFail();
        $this->assertNotSame($plaintext, $code->code_hash);
        $this->assertTrue(Hash::check($plaintext, $code->code_hash));
        $this->assertSame(substr($plaintext, 0, 7), $code->code_prefix);
    }

    public function test_cannot_revoke_a_redeemed_code(): void
    {
        $this->actingAsSuperAdmin();
        $code = ActivationCode::create([
            'code_hash' => Hash::make('ABCD-EFGH-IJKL'),
            'code_prefix' => 'ABCD-EF',
            'plan_name' => null,
            'max_uses' => 1,
            'current_uses' => 1,
        ]);

        Livewire::test(Index::class)->call('revoke', $code->id);

        $this->assertFalse($code->fresh()->revoked);
    }
}
