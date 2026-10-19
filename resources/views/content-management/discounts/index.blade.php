@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')

    <div class="container-fluid pt-4 px-4">
        <div class="bg-light rounded h-100 p-4">
            <div class="mb-4">
                <h4 class="mb-1">Suite discounts</h4>
                <p class="text-muted small mb-0">A discount can cover every suite or one suite. It applies only to nights inside the dates below. If two offers match the same night, the guest receives the larger saving.</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('content-management.discounts.store') }}" method="post" class="row g-3 align-items-end mb-4">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="120">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Suite</label>
                    <select name="room_id" class="form-select">
                        <option value="">All suites</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="percent" @selected(old('discount_type', 'percent') === 'percent')>Percent off each night</option>
                        <option value="amount" @selected(old('discount_type') === 'amount')>Amount off each night</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Value</label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">First night</label>
                    <input type="date" name="starts_on" class="form-control" value="{{ old('starts_on') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last night</label>
                    <input type="date" name="ends_on" class="form-control" value="{{ old('ends_on') }}" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Add discount</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Suite</th>
                            <th>Offer</th>
                            <th>Nights</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discounts as $discount)
                            <tr>
                                <td colspan="6" class="p-0 border-0">
                                    <form action="{{ route('content-management.discounts.update', $discount->id) }}" method="post" class="row g-2 align-items-center px-2 py-2 border-bottom">
                                        @csrf
                                        <div class="col-md-2">
                                            <input type="text" name="title" class="form-control form-control-sm" value="{{ $discount->title }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="room_id" class="form-select form-select-sm">
                                                <option value="">All suites</option>
                                                @foreach($rooms as $room)
                                                    <option value="{{ $room->id }}" @selected((int) $discount->room_id === (int) $room->id)>{{ $room->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="discount_type" class="form-select form-select-sm">
                                                <option value="percent" @selected($discount->discount_type === 'percent')>Percent</option>
                                                <option value="amount" @selected($discount->discount_type === 'amount')>Amount</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <input type="number" name="amount" class="form-control form-control-sm" min="0.01" step="0.01" value="{{ $discount->amount }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="starts_on" class="form-control form-control-sm" value="{{ $discount->starts_on?->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="ends_on" class="form-control form-control-sm" value="{{ $discount->ends_on?->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="Active" @selected($discount->status === 'Active')>Active</option>
                                                <option value="Inactive" @selected($discount->status === 'Inactive')>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </div>
                                    </form>
                                    <form action="{{ route('content-management.discounts.destroy', $discount->id) }}" method="post" class="px-2 pb-2" onsubmit="return confirm('Remove this discount?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">No discounts yet. Standard suite rates are shown on the booking page.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
