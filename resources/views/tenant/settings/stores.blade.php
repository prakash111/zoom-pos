@extends('layouts.tenant', ['title' => 'Stores & Branches'])

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <a href="{{ route('tenant.dashboard') }}" class="text-sm text-emerald-600 hover:underline">← {{ __('Back to dashboard') }}</a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Stores & Branches</h1>
        <p class="mt-1 text-sm text-slate-500">Switch locations or manage the branches under your subscription.</p>
        <p class="mt-2 text-sm font-medium">{{ $meta['total_stores'] }} stores · {{ $meta['max_allowed_stores'] === -1 ? 'Unlimited stores' : 'Plan limit: '.$meta['max_allowed_stores'] }}</p>
    </div>
    @if (session('status'))
        <div role="status" class="rounded-xl bg-emerald-50 text-emerald-800 p-4">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-xl bg-red-50 text-red-800 p-4">{{ $errors->first() }}</div>
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($branches as $branch)
            <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-lg">{{ $branch['name'] }}</h2>
                        <p class="text-sm text-slate-500">{{ $branch['code'] }}{{ $branch['is_primary'] ? ' · Main branch' : '' }}</p>
                        <p class="mt-1 text-sm">{{ $branch['address'] }}</p>
                        <p class="text-sm">{{ $branch['phone'] }}</p>
                    </div>
                    <span class="text-sm font-semibold {{ $branch['is_current'] ? 'text-emerald-600' : 'text-slate-500' }}">{{ $branch['is_current'] ? '✓ Selected' : ($branch['is_active'] ? 'Active' : 'Inactive') }}</span>
                </div>
                @if ($branch['is_active'] && ! $branch['is_current'])
                    <form method="POST" action="{{ route('tenant.stores.switch', $branch['id']) }}">
                        @csrf
                        <button class="rounded-lg px-4 py-2 bg-emerald-600 text-white font-semibold">Switch to this store</button>
                    </form>
                @endif
                @if ($meta['can_manage'])
                    <details class="border-t border-slate-200 dark:border-slate-700 pt-3">
                        <summary class="cursor-pointer font-semibold">Edit branch</summary>
                        <form method="POST" action="{{ route('tenant.stores.update', $branch['id']) }}" class="mt-3 space-y-3">
                            @csrf @method('PUT')
                            @foreach (['name' => 'Store name', 'code' => 'Branch code', 'phone' => 'Phone (with country code)', 'address' => 'Street address & city', 'tax_id' => 'Tax number'] as $field => $label)
                                <label class="block text-sm">{{ $label }}
                                    <input name="{{ $field }}" value="{{ $branch[$field] }}" @required($field === 'name') class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                                </label>
                            @endforeach
                            @if (! $branch['is_primary'])
                                <input type="hidden" name="is_active" value="0" />
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($branch['is_active']) /> Branch is active</label>
                            @endif
                            <button class="rounded-lg px-4 py-2 bg-emerald-600 text-white font-semibold">Save branch</button>
                        </form>
                    </details>
                @endif
            </section>
        @endforeach
    </div>
    @if ($meta['can_create_more'])
        <section id="create-store" class="scroll-mt-6 rounded-2xl border border-emerald-300 dark:border-emerald-800 bg-white dark:bg-slate-900 p-5">
            <h2 class="text-lg font-bold">Add New Store / Branch</h2>
            <form method="POST" action="{{ route('tenant.stores.create') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                @foreach (['name' => 'Store / Branch name', 'code' => 'Branch code (optional)', 'phone' => 'Phone (with country code)', 'address' => 'Street address & city', 'tax_id' => 'Tax number (optional)'] as $field => $label)
                    <label class="block text-sm">{{ $label }}
                        <input name="{{ $field }}" value="{{ old($field) }}" @required($field === 'name') class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                    </label>
                @endforeach
                <div class="md:col-span-2"><button class="rounded-xl px-5 py-3 bg-emerald-600 text-white font-bold">Create store and switch</button></div>
            </form>
        </section>
    @elseif ($meta['can_create'])
        <p class="rounded-xl bg-slate-100 dark:bg-slate-800 p-4">Your store limit is reached. <a class="font-semibold text-emerald-600 underline" href="{{ route('tenant.billing.index') }}">Upgrade your plan</a> to add another branch.</p>
    @endif
</div>
@endsection
