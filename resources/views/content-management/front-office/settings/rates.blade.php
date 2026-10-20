@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
@php $currency = price_currency_label($setting); @endphp
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-2">Rates management</h4>
        <p class="text-muted">Set each suite’s nightly rate, board prices, and date discounts. Close a suite when it cannot be sold.</p>

        <section id="prices" class="mb-4">
            <h5 class="mb-1">Room prices</h5>
            <p class="text-muted small">Set a dollar rate and a franc rate for each suite. A reservation uses one of those currencies for the room and for every extra on the bill. Leave a price blank to keep that currency off the booking page. A percent discount applies to both currencies. A fixed discount needs its own dollar amount and franc amount.</p>
            @foreach($rooms as $room)
                @php $plans = $room->ratePlans(); @endphp
                <form method="POST" action="{{ route('content-management.front-office.settings.rates', $room) }}" class="bg-white border rounded p-4 mb-3">
                    @csrf
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">{{ $room->title }}</h6>
                        @if($room->status !== 'Active')
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Standard nightly rate ($)</label>
                            <input type="number" class="form-control" name="price" min="0" step="1" value="{{ $room->price }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standard nightly rate (RWF)</label>
                            <input type="number" class="form-control" name="price_rwf" min="0" step="1" value="{{ $room->price_rwf }}" placeholder="—">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Board</th>
                                    <th>Single ($)</th>
                                    <th>Single (RWF)</th>
                                    <th>Double ($)</th>
                                    <th>Double (RWF)</th>
                                    <th>Discount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    'bed_breakfast' => ['Bed & breakfast', 'Breakfast only'],
                                    'half_board' => ['Half board', 'Breakfast and dinner'],
                                    'full_board' => ['Full board', 'Breakfast, lunch and dinner'],
                                ] as $planKey => [$planLabel, $planNote])
                                    <tr>
                                        <td>
                                            <strong>{{ $planLabel }}</strong>
                                            <div class="small text-muted">{{ $planNote }}</div>
                                        </td>
                                        <td style="min-width: 7rem;">
                                            <input type="number" class="form-control" name="rates[{{ $planKey }}][single]" min="0" step="1" value="{{ $plans[$planKey]['single'] }}" placeholder="—">
                                        </td>
                                        <td style="min-width: 7rem;">
                                            <input type="number" class="form-control" name="rates[{{ $planKey }}][single_rwf]" min="0" step="1" value="{{ $plans[$planKey]['single_rwf'] }}" placeholder="—">
                                        </td>
                                        <td style="min-width: 7rem;">
                                            <input type="number" class="form-control" name="rates[{{ $planKey }}][double]" min="0" step="1" value="{{ $plans[$planKey]['double'] }}" placeholder="—">
                                        </td>
                                        <td style="min-width: 7rem;">
                                            <input type="number" class="form-control" name="rates[{{ $planKey }}][double_rwf]" min="0" step="1" value="{{ $plans[$planKey]['double_rwf'] }}" placeholder="—">
                                        </td>
                                        <td style="min-width: 16rem;">
                                            <div class="d-flex flex-column gap-2">
                                                <select class="form-select" name="rates[{{ $planKey }}][discount_type]">
                                                    <option value="">None</option>
                                                    <option value="percent" @selected($plans[$planKey]['discount_type'] === 'percent')>Percent</option>
                                                    <option value="amount" @selected($plans[$planKey]['discount_type'] === 'amount')>Amount</option>
                                                </select>
                                                <input type="number" class="form-control" name="rates[{{ $planKey }}][discount_value]" min="0" step="0.01" value="{{ $plans[$planKey]['discount_value'] }}" placeholder="% or $">
                                                <input type="number" class="form-control" name="rates[{{ $planKey }}][discount_value_rwf]" min="0" step="1" value="{{ $plans[$planKey]['discount_value_rwf'] }}" placeholder="RWF amount">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-dark mt-3" type="submit">Save {{ $room->title }} rates</button>
                </form>
            @endforeach
        </section>

        <section id="discounts" class="bg-white border rounded p-4 mb-4">
            <h5 class="mb-1">Date discounts</h5>
            <p class="text-muted small">A discount can cover every suite or one suite. It applies only to nights inside the dates below. If two offers match the same night, the guest receives the larger saving.</p>
            <form method="POST" action="{{ route('content-management.front-office.settings.discounts.store') }}" class="row g-3 align-items-end mb-4">
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
                    <label class="form-label">Value ($ or %)</label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Value (RWF)</label>
                    <input type="number" name="amount_rwf" class="form-control" min="0" step="1" value="{{ old('amount_rwf') }}" placeholder="Fixed amount">
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
                    <button class="btn btn-dark" type="submit">Add discount</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
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
                                <td colspan="6" class="p-2">
                                    <form method="POST" action="{{ route('content-management.front-office.settings.discounts.update', $discount) }}" class="row g-2 align-items-center">
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
                                        <div class="col-md-1">
                                            <input type="number" name="amount_rwf" class="form-control form-control-sm" min="0" step="1" value="{{ $discount->amount_rwf }}" placeholder="RWF">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="starts_on" class="form-control form-control-sm" value="{{ optional($discount->starts_on)->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="ends_on" class="form-control form-control-sm" value="{{ optional($discount->ends_on)->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <select name="status" class="form-select form-select-sm">
                                                <option value="Active" @selected($discount->status === 'Active')>Active</option>
                                                <option value="Inactive" @selected($discount->status === 'Inactive')>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button class="btn btn-sm btn-dark" type="submit">Save</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('content-management.front-office.settings.discounts.destroy', $discount) }}" class="mt-2" onsubmit="return confirm('Remove this discount?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No date discounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section id="closures">
            <h5 class="mb-1">Room closures</h5>
            <p class="text-muted small">Close a suite when it cannot be sold.</p>
            <form method="POST" action="{{ route('content-management.front-office.closures.store') }}" class="bg-white border rounded p-3 mb-4">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Suite</label>
                        <select name="room_id" class="form-select" required>
                            @foreach($rooms->where('status', 'Active') as $room)
                                <option value="{{ $room->id }}">{{ $room->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="starts_on" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="ends_on" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Maintenance">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-dark w-100" type="submit">Add closure</button>
                    </div>
                </div>
            </form>
            <div class="bg-white border rounded">
                <table class="table mb-0">
                    <thead><tr><th>Suite</th><th>From</th><th>To</th><th>Note</th><th></th></tr></thead>
                    <tbody>
                        @forelse($closures as $closure)
                            <tr>
                                <td>{{ $closure->room?->title }}</td>
                                <td>{{ $closure->starts_on->format('d M Y') }}</td>
                                <td>{{ $closure->ends_on->format('d M Y') }}</td>
                                <td>{{ $closure->note }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('content-management.front-office.closures.destroy', $closure) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No room closures.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
