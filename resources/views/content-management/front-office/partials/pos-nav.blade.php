@php $current = $current ?? ''; @endphp
<nav class="pos-nav" aria-label="POS">
    <a class="{{ $current === 'sales' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.sales') }}">Sales</a>
    <a class="{{ $current === 'invoices' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.invoices') }}">Invoices</a>
    <a class="{{ $current === 'reservations' ? 'is-current' : '' }}" href="{{ route('content-management.restaurant-reservations') }}">Reservations</a>
    <a class="{{ $current === 'reports' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.reports') }}">POS Reports</a>
</nav>
