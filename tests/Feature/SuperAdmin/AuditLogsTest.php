<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\AuditLogs\Index;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_action_filter_narrows_the_list(): void
    {
        $this->actingAsSuperAdmin();
        AuditLog::record('tenant.suspended', 'emp_1');
        AuditLog::record('plan.created');

        Livewire::test(Index::class)
            ->set('action', 'tenant.suspended')
            ->assertSee('tenant.suspended')
            ->assertDontSee('plan.created');
    }

    public function test_actions_taken_elsewhere_in_this_milestone_are_actually_logged(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(\App\Livewire\SuperAdmin\Plans\Index::class)
            ->call('newPlan')
            ->set('name', 'x')->set('displayName', 'X')->set('price', 1)
            ->call('save');

        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.created']);
    }
}
