@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="desk-no-print mb-3 d-flex flex-wrap gap-2">
            <button class="btn btn-dark" type="button" onclick="window.print()">Print bill</button>
            <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.receipt.pdf', $booking) }}">Download PDF</a>
            <a class="btn btn-link" href="{{ route('content-management.front-office.show', $booking) }}">Back</a>
        </div>
        @include('bills.stay-document', ['booking' => $booking, 'setting' => $setting])
    </div>
</div>
@if(request()->boolean('print'))
<script>window.addEventListener('load', function () { window.print(); });</script>
@endif
@endsection
