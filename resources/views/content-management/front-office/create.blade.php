@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.reservations'), 'label' => 'Room reservations'])
            <h4 class="mb-0">New room reservation</h4>
        </div>
        <div class="desk-book">
            <form id="desk-stay" method="POST" action="{{ route('content-management.front-office.store') }}" class="desk-book__form bg-white border rounded p-4">
                @csrf
                @include('content-management.front-office.partials.stay-fields', ['booking' => $booking])
            </form>
            <div class="desk-book__side">
                @include('content-management.front-office.partials.cart')
                @include('content-management.front-office.partials.stay-next')
            </div>
        </div>
    </div>
</div>
@endsection
