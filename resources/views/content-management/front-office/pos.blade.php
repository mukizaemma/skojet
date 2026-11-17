@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos-nav">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <h4 class="mb-3">POS</h4>
        <div class="desk-pos">
            <a class="desk-pos__card desk-pos__card--sales" href="{{ route('content-management.front-office.pos.sales') }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
                <strong>Restaurant sales</strong>
            </a>
            <a class="desk-pos__card desk-pos__card--invoices" href="{{ route('content-management.front-office.pos.invoices') }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                <strong>Restaurant invoices</strong>
            </a>
            <a class="desk-pos__card desk-pos__card--reservations" href="{{ route('content-management.restaurant-reservations') }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-utensils"></i></span>
                <strong>Restaurant reservations</strong>
            </a>
            <a class="desk-pos__card desk-pos__card--reports" href="{{ route('content-management.front-office.pos.reports') }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-chart-bar"></i></span>
                <strong>Restaurant reports</strong>
            </a>
            <a class="desk-pos__card desk-pos__card--menu" href="{{ route('content-management.front-office.settings.extras.index', ['section' => 'menu']) }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-book-open"></i></span>
                <strong>Menu management</strong>
            </a>
            <a class="desk-pos__card desk-pos__card--settings" href="{{ route('content-management.front-office.pos.settings') }}">
                <span class="desk-pos__icon" aria-hidden="true"><i class="fas fa-sliders-h"></i></span>
                <strong>Settings</strong>
            </a>
        </div>
    </div>
</div>
@endsection
