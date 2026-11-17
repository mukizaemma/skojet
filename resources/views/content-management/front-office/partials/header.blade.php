@php
    $deskRoute = (string) request()->route()?->getName();
    $deskNav = [
        ['content-management.front-office.calendar', 'fa-calendar-alt', 'Calendar', str_contains($deskRoute, 'front-office.calendar')],
        ['content-management.front-office.reservations', 'fa-list', 'Room reservations', str_contains($deskRoute, 'front-office.reservations') || str_contains($deskRoute, 'front-office.show') || str_contains($deskRoute, 'front-office.create') || str_contains($deskRoute, 'front-office.receipt') || str_contains($deskRoute, 'front-office.cancel') || str_contains($deskRoute, 'front-office.check-in') || str_contains($deskRoute, 'front-office.check-out')],
        ['content-management.front-office.payments', 'fa-money-bill', 'Room payments', str_contains($deskRoute, 'front-office.payments')],
        ['content-management.front-office.inventory', 'fa-th', 'Room inventory', str_contains($deskRoute, 'front-office.inventory')],
        ['content-management.front-office.pos', 'fa-cash-register', 'POS', (str_contains($deskRoute, 'front-office.pos') && ! str_contains($deskRoute, 'front-office.pos.reports')) || str_contains($deskRoute, 'front-office.settings.extras') || str_contains($deskRoute, 'restaurant-reservations') || str_contains($deskRoute, 'restaurant-menu')],
    ];
    $reportsActive = $deskRoute === 'content-management.front-office.reports' || $deskRoute === 'content-management.front-office.pos.reports';
    $settingsActive = str_contains($deskRoute, 'front-office.settings') && ! str_contains($deskRoute, 'front-office.settings.extras');
    $ratesActive = $deskRoute === 'content-management.front-office.settings.rates.index';
    $invoiceActive = $deskRoute === 'content-management.front-office.settings.invoice.edit';
@endphp
<header class="desk-top">
    <a class="desk-top__brand {{ $deskRoute === 'content-management.front-office.home' ? 'is-active' : '' }}" href="{{ route('content-management.front-office.home') }}">Front Office</a>
    <nav class="desk-top__nav" aria-label="Front Office">
        @foreach($deskNav as [$routeName, $icon, $label, $active])
            <a class="desk-top__link {{ $active ? 'is-active' : '' }}" href="{{ route($routeName) }}">
                <i class="fas {{ $icon }}" aria-hidden="true"></i>
                <span>{{ $label }}</span>
            </a>
        @endforeach
        <div class="desk-top__menu">
            <a class="desk-top__link {{ $reportsActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.reports') }}">
                <i class="fas fa-chart-bar" aria-hidden="true"></i>
                <span>Reports</span>
            </a>
            <div class="desk-top__sub">
                <div class="desk-top__sub-panel" role="menu">
                    <a class="desk-top__sublink {{ $deskRoute === 'content-management.front-office.reports' ? 'is-active' : '' }}" href="{{ route('content-management.front-office.reports') }}" role="menuitem">Accommodation Reports</a>
                    <a class="desk-top__sublink {{ $deskRoute === 'content-management.front-office.pos.reports' ? 'is-active' : '' }}" href="{{ route('content-management.front-office.pos.reports') }}" role="menuitem">Restaurant Reports</a>
                </div>
            </div>
        </div>
        <div class="desk-top__menu">
            <a class="desk-top__link {{ $settingsActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.rates.index') }}">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                <span>Settings</span>
            </a>
            <div class="desk-top__sub">
                <div class="desk-top__sub-panel" role="menu">
                    <a class="desk-top__sublink {{ $ratesActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.rates.index') }}" role="menuitem">Rates management</a>
                    <a class="desk-top__sublink {{ $invoiceActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.invoice.edit') }}" role="menuitem">Invoice details</a>
                </div>
            </div>
        </div>
    </nav>
    <a class="desk-top__back" href="{{ route('content-management.dashboard') }}">Website</a>
</header>
