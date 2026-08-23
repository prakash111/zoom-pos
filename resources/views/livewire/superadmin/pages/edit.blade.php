<div class="space-y-6">

    <div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
            <span>📄 Edit Page</span>
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">/pages/{{ $slug }}</p>
    </div>

    @include('livewire.superadmin.pages._form', ['submitLabel' => 'Save Changes'])

</div>
