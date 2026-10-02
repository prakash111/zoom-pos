{{--
    Additive vertical-operations category for the expanded sidebar layout.
    Shown only for pharmacy / salon / repair stores (see TenantNavigationComposer).
--}}
@if ($hasRestaurant && ! $isRestaurantMode)
    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
            {{ __('Restaurant Operations') }}
        </div>
        <x-nav.expanded-item :route="route('tenant.restaurant.pos')" :active="request()->routeIs('tenant.restaurant.pos')" item-key="restaurant_pos" title="{{ __('Restaurant POS') }}" subtitle="{{ __('Dine-In, Tables, KOT') }}">
            <span class="text-base shrink-0">🍽️</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.restaurant.tables')" :active="request()->routeIs('tenant.restaurant.tables')" item-key="tables" title="{{ __('Floor Plan & Tables') }}" subtitle="{{ __('Live tables') }}">
            <span class="text-base shrink-0">🪑</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.restaurant.kds')" :active="request()->routeIs('tenant.restaurant.kds')" item-key="kds" title="{{ __('Kitchen Display (KDS)') }}" subtitle="{{ __('KOT queue') }}">
            <span class="text-base shrink-0">🍳</span>
        </x-nav.expanded-item>
    </div>
@endif

@if ($isPharmacy)
    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
            {{ __('Pharmacy Operations') }}
        </div>
        <x-nav.expanded-item :route="route('tenant.pharmacy.dashboard')" :active="request()->routeIs('tenant.pharmacy.dashboard')" item-key="pharmacy_dashboard" title="{{ __('Pharmacy Dashboard') }}" subtitle="{{ __('Batches, expiry & Rx') }}">
            <span class="text-base shrink-0">💊</span>
        </x-nav.expanded-item>
        @if ($canProducts)
            <x-nav.expanded-item :route="route('tenant.pharmacy.batches')" :active="request()->routeIs('tenant.pharmacy.batches')" item-key="pharmacy_batches" title="{{ __('Drug Batches & Expiry') }}" subtitle="{{ __('FEFO stock & returns') }}">
                <span class="text-base shrink-0">📦</span>
            </x-nav.expanded-item>
        @endif
        @if ($canSales)
            <x-nav.expanded-item :route="route('tenant.pharmacy.prescriptions')" :active="request()->routeIs('tenant.pharmacy.prescriptions')" item-key="pharmacy_prescriptions" title="{{ __('Prescriptions & Queue') }}" subtitle="{{ __('Intake & dispensing') }}">
                <span class="text-base shrink-0">📝</span>
            </x-nav.expanded-item>
        @endif
    </div>
@endif

@if ($isSalon)
    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
            {{ __('Salon & Bookings') }}
        </div>
        <x-nav.expanded-item :route="route('tenant.salon.calendar')" :active="request()->routeIs('tenant.salon.calendar')" item-key="salon_calendar" title="{{ __('Booking Calendar') }}" subtitle="{{ __('Day view & bookings') }}">
            <span class="text-base shrink-0">📅</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.salon.services')" :active="request()->routeIs('tenant.salon.services')" item-key="salon_services" title="{{ __('Service Catalog') }}" subtitle="{{ __('Rates & duration') }}">
            <span class="text-base shrink-0">✂️</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.salon.stylists')" :active="request()->routeIs('tenant.salon.stylists')" item-key="salon_stylists" title="{{ __('Stylists & Staff') }}" subtitle="{{ __('Bookable specialists') }}">
            <span class="text-base shrink-0">🧑‍🎨</span>
        </x-nav.expanded-item>
    </div>
@endif

@if ($isRepair && $canRepair)
    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
            {{ __('Repair Operations') }}
        </div>
        <x-nav.expanded-item :route="route('tenant.repair.dashboard')" :active="request()->routeIs('tenant.repair.dashboard')" item-key="repair_dashboard" title="{{ __('Repair Workbench') }}" subtitle="{{ __('Status board') }}">
            <span class="text-base shrink-0">🛠️</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.repair.tickets')" :active="request()->routeIs('tenant.repair.tickets') || request()->routeIs('tenant.repair.ticket')" item-key="repair_tickets" title="{{ __('Ticket Register') }}" subtitle="{{ __('Intake & workbench') }}">
            <span class="text-base shrink-0">🧾</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.repair.categories')" :active="request()->routeIs('tenant.repair.categories')" item-key="repair_categories" title="{{ __('Device Categories') }}" subtitle="{{ __('Brands & checklists') }}">
            <span class="text-base shrink-0">📱</span>
        </x-nav.expanded-item>
    </div>
@endif

@if ($canLeads)
    <div :class="{ 'space-y-1': position === 'left' || position === 'right', 'flex flex-row items-center gap-1.5 shrink-0': position === 'top' || position === 'bottom', 'space-y-1': position === 'floating' }">
        <div x-show="position === 'left' || position === 'right'" class="text-[10px] font-black uppercase tracking-wider text-white/50 px-2.5">
            {{ __('Lead Management') }}
        </div>
        <x-nav.expanded-item :route="route('tenant.leads.dashboard')" :active="request()->routeIs('tenant.leads.dashboard') || (request()->routeIs('tenant.leads.index') && request('tab') === 'pipeline')" item-key="lead_dashboard" title="{{ __('Leads Dashboard') }}" subtitle="{{ __('Overview, metrics & conversion KPIs') }}">
            <span class="text-base shrink-0">📊</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.leads.create')" :active="request()->routeIs('tenant.leads.create') || (request()->routeIs('tenant.leads.index') && request('tab') === 'capture')" item-key="lead_create" title="{{ __('Capture Lead') }}" subtitle="{{ __('Intake prospect & contact details') }}">
            <span class="text-base shrink-0">➕</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.leads.pipeline')" :active="request()->routeIs('tenant.leads.pipeline') || (request()->routeIs('tenant.leads.index') && (request('tab') === 'all' || !request('tab')))" item-key="lead_pipeline" title="{{ __('Leads Pipeline') }}" subtitle="{{ __('Stage tracker & Kanban board') }}">
            <span class="text-base shrink-0">🗂️</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.leads.activities')" :active="request()->routeIs('tenant.leads.activities') || (request()->routeIs('tenant.leads.index') && request('tab') === 'activities')" item-key="lead_activities" title="{{ __('Follow-ups & Activities') }}" subtitle="{{ __('Scheduled tasks, calls & reminders') }}">
            <span class="text-base shrink-0">🔔</span>
        </x-nav.expanded-item>
        <x-nav.expanded-item :route="route('tenant.leads.sources')" :active="request()->routeIs('tenant.leads.sources') || (request()->routeIs('tenant.leads.index') && request('tab') === 'sources')" item-key="lead_sources" title="{{ __('Lead Sources') }}" subtitle="{{ __('Inbound channels & marketing origins') }}">
            <span class="text-base shrink-0">🏷️</span>
        </x-nav.expanded-item>
    </div>
@endif
