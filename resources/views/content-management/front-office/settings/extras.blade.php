@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-session">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-1">POS Items</h4>
        <p class="text-muted mb-3">Each item has a dollar price and a franc price. A guest bill uses the currency chosen on the reservation, so the room and these items stay in the same currency. Food, drinks, and coffee post to restaurant sales. Laundry, massage, transport, and tour guide post to services sales.</p>
        <form method="POST" action="{{ route('content-management.front-office.settings.extras.store') }}" class="bg-white border rounded p-3 mb-4">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required maxlength="120">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="kind" class="form-select" required>
                        @foreach(\App\Models\ExtraItem::KINDS as $kind => $meta)
                            <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $meta['category'] === 'restaurant' ? 'Restaurant' : 'Service' }} · {{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">USD price</label>
                    <input type="number" name="price" min="0" step="0.01" value="{{ old('price', '0') }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">RWF price</label>
                    <input type="number" name="price_rwf" min="0" step="1" value="{{ old('price_rwf') }}" class="form-control" placeholder="—">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-dark w-100" type="submit">Add</button>
                </div>
            </div>
        </form>
        @forelse(\App\Models\ExtraItem::CATEGORIES as $category => $categoryLabel)
            <h6 class="mt-3">{{ $categoryLabel }}</h6>
            <div class="bg-white border rounded mb-3">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Item</th><th>Type</th><th>USD</th><th>RWF</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($items->where('category', $category) as $item)
                            <tr>
                                <td colspan="6" class="p-2">
                                    <form method="POST" action="{{ route('content-management.front-office.settings.extras.update', $item) }}" class="row g-2 align-items-center">
                                        @csrf
                                        <div class="col-md-3"><input type="text" name="name" value="{{ $item->name }}" class="form-control" required maxlength="120"></div>
                                        <div class="col-md-3">
                                            <select name="kind" class="form-select" required>
                                                @foreach(\App\Models\ExtraItem::KINDS as $kind => $meta)
                                                    <option value="{{ $kind }}" @selected($item->kind === $kind)>{{ $meta['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2"><input type="number" name="price" min="0" step="0.01" value="{{ $item->price }}" class="form-control" required></div>
                                        <div class="col-md-2"><input type="number" name="price_rwf" min="0" step="1" value="{{ $item->price_rwf }}" class="form-control" placeholder="RWF"></div>
                                        <div class="col-md-2">
                                            <select name="status" class="form-select">
                                                <option value="Active" @selected($item->status === 'Active')>Active</option>
                                                <option value="Inactive" @selected($item->status === 'Inactive')>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex gap-2">
                                            <button class="btn btn-dark btn-sm" type="submit">Save</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('content-management.front-office.settings.extras.destroy', $item) }}" class="mt-1">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No {{ strtolower($categoryLabel) }} items yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
@endsection