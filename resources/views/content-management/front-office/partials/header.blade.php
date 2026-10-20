@php
    $deskRoute = (string) request()->route()?->getName();
    $deskNav = [
        ['content-management.front-office.calendar', 'fa-calendar-alt', 'Calendar', str_contains($deskRoute, 'front-office.calendar')],
        ['content-management.front-office.reservations', 'fa-list', 'Reservations', str_contains($deskRoute, 'front-office.reservations') || str_contains($deskRoute, 'front-office.show') || str_contains($deskRoute, 'front-office.create') || str_contains($deskRoute, 'front-office.receipt') || str_contains($deskRoute, 'front-office.cancel') || str_contains($deskRoute, 'front-office.check-in') || str_contains($deskRoute, 'front-office.check-out')],
        ['content-management.front-office.payments', 'fa-money-bill', 'Payments', str_contains($deskRoute, 'front-office.payments')],
        ['content-management.front-office.inventory', 'fa-th', 'Room inventory', str_contains($deskRoute, 'front-office.inventory')],
        ['content-management.front-office.reports', 'fa-chart-bar', 'Reports', str_contains($deskRoute, 'front-office.reports')],
        ['content-management.front-office.settings.extras.index', 'fa-cash-register', 'POS Items', str_contains($deskRoute, 'front-office.settings.extras')],
    ];
    $settingsActive = str_contains($deskRoute, 'front-office.settings') && ! str_contains($deskRoute, 'front-office.settings.extras');
    $ratesActive = $deskRoute === 'content-management.front-office.settings.rates.index';
    $invoiceActive = $deskRoute === 'content-management.front-office.settings.invoice.edit';
    $extrasActive = str_contains($deskRoute, 'front-office.settings.extras');
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
            <a class="desk-top__link {{ $settingsActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.rates.index') }}">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                <span>Settings</span>
            </a>
            <div class="desk-top__sub">
                <div class="desk-top__sub-panel" role="menu">
                    <a class="desk-top__sublink {{ $ratesActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.rates.index') }}" role="menuitem">Rates management</a>
                    <a class="desk-top__sublink {{ $invoiceActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.invoice.edit') }}" role="menuitem">Invoice details</a>
                    <a class="desk-top__sublink {{ $extrasActive ? 'is-active' : '' }}" href="{{ route('content-management.front-office.settings.extras.index') }}" role="menuitem">POS Items</a>
                </div>
            </div>
        </div>
    </nav>
    <a class="desk-top__back" href="{{ route('content-management.dashboard') }}">Website</a>
</header>
