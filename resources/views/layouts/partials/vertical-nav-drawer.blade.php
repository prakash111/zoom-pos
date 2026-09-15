{{--
    Additive vertical-operations group for the slide-out drawer. Shown only for
    a store whose registered business category is pharmacy, salon or repair
    (see TenantNavigationComposer: $isPharmacy / $isSalon / $isRepair). The
    standard retail nav still renders above this — a pharmacy still sells at the
    POS, keeps inventory, and has customers.
--}}
@if ($hasRestaurant && ! $isRestaurantMode)
    <div data-section-key="restaurant_operations">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-lime-600 dark:text-lime-400 mb-2 px-3">{{ __('Restaurant Operations') }}</div>
        <div class="space-y-1">
            <x-nav.drawer-item item-key="restaurant_pos" :route="route('tenant.restaurant.pos')" hover="lime" highlighted title="{{ __('Restaurant POS Terminal') }}" subtitle="{{ __('Dine-In, Takeaway & Delivery') }}">🍽️</x-nav.drawer-item>
            <x-nav.drawer-item item-key="floor_plan" :route="route('tenant.restaurant.tables')" hover="lime" title="{{ __('Floor Plan & Tables') }}" subtitle="{{ __('Live Table Status & QR Menus') }}">🪑</x-nav.drawer-item>
            <x-nav.drawer-item item-key="kitchen_display" :route="route('tenant.restaurant.kds')" hover="lime" title="{{ __('Kitchen Display (KDS)') }}" subtitle="{{ __('Live KOT preparation queue') }}">🍳</x-nav.drawer-item>
        </div>
    </div>
@endif

@if ($isPharmacy)
    <div data-section-key="pharmacy_management">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-2 px-3">{{ __('Pharmacy Operations') }}</div>
        <div class="space-y-1">
            <x-nav.drawer-item item-key="pharmacy_dashboard" :route="route('tenant.pharmacy.dashboard')" title="{{ __('Pharmacy Dashboard') }}" subtitle="{{ __('Batches, expiry & Rx counters') }}">💊</x-nav.drawer-item>
            @if ($canProducts)
                <x-nav.drawer-item item-key="pharmacy_batches" :route="route('tenant.pharmacy.batches')" title="{{ __('Drug Batches & Expiry') }}" subtitle="{{ __('FEFO stock, expiry tracker & returns') }}">📦</x-nav.drawer-item>
            @endif
            @if ($canSales)
                <x-nav.drawer-item item-key="pharmacy_prescriptions" :route="route('tenant.pharmacy.prescriptions')" title="{{ __('Prescriptions & Queue') }}" subtitle="{{ __('Intake, patient queue & dispensing') }}">📝</x-nav.drawer-item>
            @endif
        </div>
    </div>
@endif

@if ($isSalon)
    <div data-section-key="salon_bookings">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-violet-600 dark:text-violet-400 mb-2 px-3">{{ __('Salon & Bookings') }}</div>
        <div class="space-y-1">
            <x-nav.drawer-item item-key="salon_calendar" :route="route('tenant.salon.calendar')" title="{{ __('Service Booking Calendar') }}" subtitle="{{ __('Day view, bookings & status') }}">📅</x-nav.drawer-item>
            <x-nav.drawer-item item-key="salon_services" :route="route('tenant.salon.services')" title="{{ __('Service Catalog & Rates') }}" subtitle="{{ __('Bookable services, price & duration') }}">✂️</x-nav.drawer-item>
            <x-nav.drawer-item item-key="salon_stylists" :route="route('tenant.salon.stylists')" title="{{ __('Stylists & Staff') }}" subtitle="{{ __('Who can be booked as a specialist') }}">🧑‍🎨</x-nav.drawer-item>
        </div>
    </div>
@endif

@if ($isRepair)
    <div data-section-key="repair_service">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-sky-600 dark:text-sky-400 mb-2 px-3">{{ __('Repair Operations') }}</div>
        <div class="space-y-1">
            @if ($canRepair)
                <x-nav.drawer-item item-key="repair_dashboard" :route="route('tenant.repair.dashboard')" title="{{ __('Repair Workbench') }}" subtitle="{{ __('Status board & open tickets') }}">🛠️</x-nav.drawer-item>
                <x-nav.drawer-item item-key="repair_tickets" :route="route('tenant.repair.tickets')" title="{{ __('Repair Ticket Register') }}" subtitle="{{ __('Intake, parts, labor & checklist') }}">🧾</x-nav.drawer-item>
                <x-nav.drawer-item item-key="repair_categories" :route="route('tenant.repair.categories')" title="{{ __('Device Categories') }}" subtitle="{{ __('Brands & default checklists') }}">📱</x-nav.drawer-item>
            @endif
        </div>
    </div>
@endif
