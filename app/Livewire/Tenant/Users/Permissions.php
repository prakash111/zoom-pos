<?php

namespace App\Livewire\Tenant\Users;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Permissions Matrix'])]
class Permissions extends Component
{
    public mixed $selectedUserId = null;

    public ?User $targetUser = null;

    /** @var array<string, array<string, bool>> */
    public array $grid = [];

    public function mount(?User $user = null): void
    {
        abort_unless(auth('web')->user()->isPrivilegedRole(), 403);

        if ($user && $user->exists) {
            $this->selectedUserId = $user->id;
        } else {
            $firstNonAdmin = User::whereNotIn('role', User::PRIVILEGED_ROLES)->first() ?? User::first();
            $this->selectedUserId = $firstNonAdmin?->id;
        }

        $this->loadUserPermissions();
    }

    public function updatedSelectedUserId(): void
    {
        $this->loadUserPermissions();
    }

    public function loadUserPermissions(): void
    {
        if (! $this->selectedUserId) {
            $this->targetUser = null;
            $this->grid = [];

            return;
        }

        $this->targetUser = User::find($this->selectedUserId);
        if (! $this->targetUser) {
            $this->grid = [];

            return;
        }

        // Initialize empty grid per module actions
        $modules = array_keys(PermissionChecker::MODULES);

        foreach ($modules as $module) {
            $actions = array_keys(PermissionChecker::getActionsForModule($module));
            foreach ($actions as $action) {
                $this->grid[$module][$action] = false;
            }
        }

        if ($this->targetUser->isPrivilegedRole()) {
            // Privileged role gets all checked by default
            foreach ($modules as $module) {
                $actions = array_keys(PermissionChecker::getActionsForModule($module));
                foreach ($actions as $action) {
                    $this->grid[$module][$action] = true;
                }
            }

            return;
        }

        // Fetch explicit permissions from DB
        $explicitPerms = Permission::where('user_id', $this->targetUser->id)->get();

        if ($explicitPerms->isNotEmpty()) {
            foreach ($explicitPerms as $p) {
                if (isset($this->grid[$p->module][$p->action])) {
                    $this->grid[$p->module][$p->action] = (bool) $p->allowed;
                }
            }
        } else {
            // Apply role default preset
            $roleDefaults = PermissionChecker::getRoleDefaults($this->targetUser->role);
            foreach ($roleDefaults as $module => $allowedActions) {
                foreach ($allowedActions as $action) {
                    if (isset($this->grid[$module][$action])) {
                        $this->grid[$module][$action] = true;
                    }
                }
            }
        }
    }

    public function applyPreset(string $preset): void
    {
        $modules = array_keys(PermissionChecker::MODULES);

        if ($preset === 'full') {
            foreach ($modules as $module) {
                $actions = array_keys(PermissionChecker::getActionsForModule($module));
                foreach ($actions as $action) {
                    $this->grid[$module][$action] = true;
                }
            }

            return;
        }

        if ($preset === 'view_only') {
            foreach ($modules as $module) {
                $actions = array_keys(PermissionChecker::getActionsForModule($module));
                foreach ($actions as $action) {
                    $this->grid[$module][$action] = ($action === 'view');
                }
            }

            return;
        }

        if ($preset === 'clear') {
            foreach ($modules as $module) {
                $actions = array_keys(PermissionChecker::getActionsForModule($module));
                foreach ($actions as $action) {
                    $this->grid[$module][$action] = false;
                }
            }

            return;
        }

        // Role-based profile preset
        $roleDefaults = PermissionChecker::getRoleDefaults($preset);
        foreach ($modules as $module) {
            $actions = array_keys(PermissionChecker::getActionsForModule($module));
            $allowedForModule = $roleDefaults[$module] ?? [];
            foreach ($actions as $action) {
                $this->grid[$module][$action] = in_array($action, $allowedForModule, true);
            }
        }
    }

    public function toggleRow(string $module): void
    {
        $actions = array_keys(PermissionChecker::getActionsForModule($module));
        $allChecked = collect($actions)->every(fn ($a) => ! empty($this->grid[$module][$a]));

        foreach ($actions as $action) {
            $this->grid[$module][$action] = ! $allChecked;
        }
    }

    public function toggleColumn(string $action): void
    {
        $modules = array_keys(PermissionChecker::MODULES);
        $allChecked = true;

        foreach ($modules as $module) {
            $modActions = array_keys(PermissionChecker::getActionsForModule($module));
            if (in_array($action, $modActions, true)) {
                if (empty($this->grid[$module][$action])) {
                    $allChecked = false;
                    break;
                }
            }
        }

        foreach ($modules as $module) {
            $modActions = array_keys(PermissionChecker::getActionsForModule($module));
            if (in_array($action, $modActions, true)) {
                $this->grid[$module][$action] = ! $allChecked;
            }
        }
    }

    public function save(): void
    {
        if (! $this->targetUser) {
            return;
        }

        $modules = array_keys(PermissionChecker::MODULES);

        foreach ($modules as $module) {
            $actions = array_keys(PermissionChecker::getActionsForModule($module));
            foreach ($actions as $action) {
                $allowed = (bool) ($this->grid[$module][$action] ?? false);
                $existing = Permission::where('user_id', $this->targetUser->id)
                    ->where('module', $module)
                    ->where('action', $action)
                    ->first();

                if ($allowed) {
                    if (! $existing) {
                        Permission::create([
                            'user_id' => $this->targetUser->id,
                            'company_id' => $this->targetUser->company_id,
                            'module' => $module,
                            'action' => $action,
                            'allowed' => true,
                        ]);
                    } elseif (! $existing->allowed) {
                        $existing->update(['allowed' => true]);
                    }
                } else {
                    if ($existing) {
                        $existing->delete();
                    }
                }
            }
        }

        AuditLog::record('user.permissions_updated', $this->targetUser->company_id, auth('web')->id(), [
            'target_user_id' => $this->targetUser->id,
            'target_user_name' => $this->targetUser->name,
        ]);

        session()->flash('status', "Permissions successfully updated for {$this->targetUser->name}.");
    }

    public function render()
    {
        return view('livewire.tenant.users.permissions', [
            'users' => User::orderBy('name')->get(),
            'modules' => PermissionChecker::MODULES,
            'roles' => User::ROLES,
        ]);
    }
}
