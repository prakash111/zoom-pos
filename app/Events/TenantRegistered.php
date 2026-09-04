<?php

namespace App\Events;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Company $company,
        public User $user,
        public string $posMode = 'general'
    ) {}
}
