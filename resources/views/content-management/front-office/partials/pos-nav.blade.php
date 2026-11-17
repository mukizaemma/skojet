@php $current = $current ?? ''; @endphp
<nav class="pos-nav" aria-label="POS">
    <a class="{{ $current === 'sales' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.sales') }}">New POS Sale</a>
    <a class="{{ $current === 'invoices' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.invoices') }}">Restaurant invoices</a>
    <a class="{{ $current === 'reservations' ? 'is-current' : '' }}" href="{{ route('content-management.restaurant-reservations') }}">Restaurant reservations</a>
    <a class="{{ $current === 'reports' ? 'is-current' : '' }}" href="{{ route('content-management.front-office.pos.reports') }}">Restaurant reports</a>
</nav>
