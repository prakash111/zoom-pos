<!-- Quick Customer Modal -->
<x-modal wire:model="showQuickCustomerModal" maxWidth="md" :title="__('Quick Add Customer')" :subtitle="__('Create and immediately attach a new customer to this POS sale.')" icon="👤">
    <form id="quickCustomerForm" wire:submit.prevent="createQuickCustomer" class="space-y-3.5 text-xs">
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Customer Full Name *") }}</label>
            <input type="text"
                   wire:model="newCustomerName"
                   placeholder="{{ __("e.g. Michael Scott") }}"
                   class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
            @error('newCustomerName') <p class="text-rose-600 text-[10px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Phone Number") }}</label>
            <input type="text"
                   wire:model="newCustomerPhone"
                   placeholder="+1 555 0199"
                   class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 font-mono">
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __("Email Address") }}</label>
            <input type="email"
                   wire:model="newCustomerEmail"
                   placeholder="client@example.com"
                   class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
        </div>
    </form>

    <x-slot:footer>
        <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
            {{ __("Cancel") }}
        </button>
        <x-ui.button wire:click="createQuickCustomer" wire:loading.attr="disabled" class="!font-black">
            <span wire:loading.remove>{{ __("Save & Select Customer") }}</span>
            <span wire:loading>{{ __("Saving...") }}</span>
        </x-ui.button>
    </x-slot:footer>
</x-modal>
