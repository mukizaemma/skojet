@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <h4 class="mb-1">POS</h4>
        <p class="text-muted mb-4">Restaurant sales, reservations, reports, menu, and access settings.</p>
        <div class="desk-pos">
            <a class="desk-pos__card" href="{{ route('content-management.front-office.pos.sales') }}">
                <i class="fas fa-receipt" aria-hidden="true"></i>
                <strong>Sales</strong>
                <span>Dining bills and food, drink, and coffee posted to a room.</span>
            </a>
            <a class="desk-pos__card" href="{{ route('content-management.restaurant-reservations') }}">
                <i class="fas fa-utensils" aria-hidden="true"></i>
                <strong>Reservations</strong>
                <span>Open a bill, add items, and confirm the payment.</span>
            </a>
            <a class="desk-pos__card" href="{{ route('content-management.front-office.pos.reports') }}">
                <i class="fas fa-chart-bar" aria-hidden="true"></i>
                <strong>Reports</strong>
                <span>What the restaurant sold, and which reservations were paid.</span>
            </a>
            <a class="desk-pos__card" href="{{ route('content-management.front-office.settings.extras.index', ['section' => 'menu']) }}">
                <i class="fas fa-book-open" aria-hidden="true"></i>
                <strong>Menu management</strong>
                <span>Restaurant menu, and the extras that can be added to a room bill.</span>
            </a>
            <a class="desk-pos__card" href="{{ route('content-management.front-office.pos.settings') }}">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                <strong>Settings</strong>
                <span>Reservation requirement, minimums, MoMo, and the guest link.</span>
            </a>
        </div>
    </div>
</div>
@endsection
