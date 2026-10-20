@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @include('content-management.front-office.partials.alerts')
        <div class="bg-white rounded shadow-sm border p-4" style="max-width: 760px;">
            <h4 class="mb-2">Reservations Manager</h4>
            <p class="text-muted">Turn this on when the hotel’s Front Office access is current. This menu stays with the super admin. Website content and the guest reservations list stay available to the hotel admin either way.</p>
            <form method="POST" action="{{ route('content-management.reservations-manager.update') }}">
                @csrf
                <input type="hidden" name="reservations_manager_enabled" value="0">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="reservations_manager_enabled" name="reservations_manager_enabled" value="1" @checked($enabled)>
                    <label class="form-check-label" for="reservations_manager_enabled">Reservations Manager is active</label>
                </div>
                <button type="submit" class="btn btn-dark">Save</button>
                @if($enabled)
                    <a href="{{ route('content-management.front-office.home') }}" class="btn btn-outline-dark ms-2">Open Front Office</a>
                @endif
            </form>
        </div>
    </div>
</div>
</div>
@endsection
