@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-3">New reservation</h4>
        <form method="POST" action="{{ route('content-management.front-office.store') }}" class="bg-white border rounded p-4">
            @csrf
            @include('content-management.front-office.partials.stay-fields', ['booking' => new \App\Models\Booking()])
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="check_in_now" value="1" id="check_in_now">
                <label class="form-check-label" for="check_in_now">Check this guest in now</label>
            </div>
            <button class="btn btn-dark mt-3" type="submit">Save reservation</button>
        </form>
    </div>
</div>
</div>
@endsection
