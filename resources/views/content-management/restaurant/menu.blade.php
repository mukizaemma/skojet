@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="bg-light rounded p-4 mb-4">
            <h4 class="mb-1">Restaurant access</h4>
            <p class="text-muted small">Guests see the international price unless they switch to the local price. A reservation can be sent only when the order reaches the minimum for the prices they are using.</p>
            <form method="POST" action="{{ route('content-management.restaurant-menu.settings') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="requires_reservation" id="requires_reservation" value="1" @checked(old('requires_reservation', $restaurant->requiresReservation()))>
                        <label class="form-check-label" for="requires_reservation">Require a reservation before dining</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="minimum_amount">Minimum, international (USD)</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="minimum_amount" id="minimum_amount" value="{{ old('minimum_amount', number_format((float) $restaurant->minimum_amount, 2, '.', '')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="minimum_amount_local">Minimum, local (RWF)</label>
                    <input class="form-control" type="number" min="0" step="1" name="minimum_amount_local" id="minimum_amount_local" value="{{ old('minimum_amount_local', (int) $restaurant->minimum_amount_local) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="momo_code">MoMo code</label>
                    <input class="form-control" type="text" name="momo_code" id="momo_code" maxlength="40" value="{{ old('momo_code', $restaurant->momoCode()) }}" placeholder="Merchant code">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="momo_account_name">Name on the MoMo account</label>
                    <input class="form-control" type="text" name="momo_account_name" id="momo_account_name" maxlength="120" value="{{ old('momo_account_name', $restaurant->momoAccountName()) }}">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-dark" type="submit">Save access rules</button>
                </div>
            </form>
        </div>

        <div class="bg-light rounded p-4 mb-4">
            <h4 class="mb-1">Reservation link and QR code</h4>
            <p class="text-muted small mb-3">Share this link, or print the code and place it where guests can scan it. It opens the reservation page, where they choose a room or restaurant access.</p>
            <div class="input-group mb-3" style="max-width: 720px;">
                <input class="form-control" id="reserve-url" type="text" readonly value="{{ $reserveUrl }}">
                <button class="btn btn-outline-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reserve-url').value)">Copy link</button>
                <a class="btn btn-dark" href="{{ route('content-management.restaurant-menu.qr') }}" target="_blank" rel="noopener">Print QR code</a>
            </div>
        </div>

        <div class="bg-white border rounded p-4 mb-4">
            <h5 class="mb-3">Add a menu item</h5>
            <form method="POST" action="{{ route('content-management.restaurant-menu.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Description or accompaniments</label>
                    <input class="form-control" type="text" name="description" value="{{ old('description') }}" maxlength="500">
                </div>
                <div class="col-md-1">
                    <label class="form-label">USD</label>
                    <input class="form-control" type="number" name="price_international" min="0" step="0.01" value="{{ old('price_international', '0') }}" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">RWF</label>
                    <input class="form-control" type="number" name="price_local" min="0" step="1" value="{{ old('price_local', '0') }}" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <input class="form-control" type="number" name="sort_order" min="0" value="{{ old('sort_order', '0') }}">
                </div>
                <div class="col-md-1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="form-check-label">Shown</label>
                    </div>
                    <button class="btn btn-dark w-100" type="submit">Add</button>
                </div>
            </form>
        </div>

        @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $category => $label)
            <h5 class="mt-2">{{ $label }}</h5>
            <div class="bg-white border rounded mb-4">
                @forelse($items->get($category, collect()) as $item)
                    <div class="p-3 border-bottom">
                        <form method="POST" action="{{ route('content-management.restaurant-menu.update', $item) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $categoryLabel)
                                        <option value="{{ $key }}" @selected($item->category === $key)>{{ $categoryLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Name</label>
                                <input class="form-control" type="text" name="name" value="{{ $item->name }}" maxlength="120" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Description or accompaniments</label>
                                <input class="form-control" type="text" name="description" value="{{ $item->description }}" maxlength="500">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">USD</label>
                                <input class="form-control" type="number" name="price_international" min="0" step="0.01" value="{{ $item->price_international }}" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">RWF</label>
                                <input class="form-control" type="number" name="price_local" min="0" step="1" value="{{ $item->price_local }}" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Order</label>
                                <input class="form-control" type="number" name="sort_order" min="0" value="{{ $item->sort_order }}">
                            </div>
                            <div class="col-md-1">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                    <label class="form-check-label">Shown</label>
                                </div>
                                <button class="btn btn-dark btn-sm" type="submit">Save</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('content-management.restaurant-menu.destroy', $item) }}" class="mt-1" onsubmit="return confirm('Remove this item?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted p-3 mb-0">No {{ strtolower($label) }} items yet.</p>
                @endforelse
            </div>
        @endforeach
    </div>
</div>
</div>
@endsection
