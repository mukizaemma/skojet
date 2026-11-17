@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="pos-head">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @include('content-management.front-office.partials.back', ['href' => route('content-management.front-office.pos'), 'label' => 'POS'])
                <h4>Settings</h4>
            </div>
            @include('content-management.front-office.partials.pos-nav')
        </div>
        <h5 class="mb-3">Restaurant access</h5>
        <p class="text-muted mb-3">Guests see the international price unless they choose the local price. They can send a reservation when the order reaches the minimum for that price.</p>
        @if(! $ready)
            <div class="bg-white border rounded p-3 text-muted">Restaurant settings appear after the dining tables are created. Run the database migration, then open this page again.</div>
        @else
            <form method="POST" action="{{ route('content-management.restaurant-menu.settings') }}" class="bg-white border rounded p-4">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_reservation" id="requires_reservation" value="1" @checked(old('requires_reservation', $restaurant->requiresReservation()))>
                            <label class="form-check-label" for="requires_reservation">Require a reservation before dining</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="minimum_amount">Minimum, international (USD)</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="minimum_amount" id="minimum_amount" value="{{ old('minimum_amount', number_format((float) $restaurant->minimum_amount, 2, '.', '')) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="minimum_amount_local">Minimum, local (RWF)</label>
                        <input class="form-control" type="number" min="0" step="1" name="minimum_amount_local" id="minimum_amount_local" value="{{ old('minimum_amount_local', (int) $restaurant->minimum_amount_local) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="momo_code">MoMo code</label>
                        <input class="form-control" type="text" name="momo_code" id="momo_code" maxlength="40" value="{{ old('momo_code', $restaurant->momoCode()) }}" placeholder="Merchant code">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="momo_account_name">Name on the MoMo account</label>
                        <input class="form-control" type="text" name="momo_account_name" id="momo_account_name" maxlength="120" value="{{ old('momo_account_name', $restaurant->momoAccountName()) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reserve-url">Reservation link</label>
                        <div class="input-group">
                            <input class="form-control" id="reserve-url" type="text" readonly value="{{ $reserveUrl }}">
                            <button class="btn btn-outline-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reserve-url').value)">Copy</button>
                            <a class="btn btn-outline-dark" href="{{ route('content-management.restaurant-menu.qr') }}" target="_blank" rel="noopener">QR code</a>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark" type="submit">Save access rules</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
