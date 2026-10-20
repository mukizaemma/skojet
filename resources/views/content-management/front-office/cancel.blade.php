@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <div class="bg-white border rounded p-4" style="max-width: 640px;">
            <h4 class="mb-1">Cancel this reservation?</h4>
            <p class="text-muted">Nothing is cancelled until you confirm below. This is a second check.</p>
            <dl class="row mb-4">
                <dt class="col-sm-4">Guest</dt>
                <dd class="col-sm-8">{{ $booking->names }}</dd>
                <dt class="col-sm-4">Suite</dt>
                <dd class="col-sm-8">{{ $booking->stayRoom()?->title ?? 'Unallocated' }}</dd>
                <dt class="col-sm-4">Stay</dt>
                <dd class="col-sm-8">{{ optional($booking->checkin_date)->format('d M Y') }} – {{ optional($booking->checkout_date)->format('d M Y') }}</dd>
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8 text-capitalize">{{ str_replace('_', ' ', $booking->deskState()) }}</dd>
            </dl>
            <form method="POST" action="{{ route('content-management.front-office.cancel', $booking) }}">
                @csrf
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_cancel" value="1" id="confirm_cancel" required>
                    <label class="form-check-label" for="confirm_cancel">Yes, cancel {{ $booking->names }}’s reservation</label>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-danger" type="submit">Cancel reservation</button>
                    <a class="btn btn-outline-dark" href="{{ route('content-management.front-office.show', $booking) }}">Keep reservation</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
