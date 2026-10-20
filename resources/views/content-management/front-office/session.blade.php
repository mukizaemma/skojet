@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-pos">
@php
    $isCheckIn = $mode === 'checkin';
    $booked = $isCheckIn ? $booking->booked_checkin_date : $booking->booked_checkout_date;
    $bookedLabel = optional($booked)->toDateString();
    $defaultDate = old('stay_date', $suggested->toDateString());
    $parts = preg_split('/\s+/', trim((string) $booking->names), 2) ?: [];
@endphp
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-1">{{ $isCheckIn ? 'Check in' : 'Check out' }} {{ $booking->names }}</h4>
        <p class="text-muted mb-3">{{ $booking->stayRoom()?->title }} · Booked {{ optional($booking->booked_checkin_date)->format('d M Y') }} – {{ optional($booking->booked_checkout_date)->format('d M Y') }}</p>
        <div class="desk-book">
            <form id="desk-session" method="POST" action="{{ route($isCheckIn ? 'content-management.front-office.check-in' : 'content-management.front-office.check-out', $booking) }}" class="desk-book__form bg-white border rounded p-4">
                @csrf
                <h6 class="mb-3">{{ $isCheckIn ? 'Arrival' : 'Departure' }}</h6>
                @if($isCheckIn && $suggested->toDateString() > now()->toDateString())
                    <div class="alert alert-warning">This guest is booked to arrive {{ $suggested->format('d M Y') }}, which is still ahead. Change the date and record why, or wait until the guest is here before checking in. Checking in now would mark the suite occupied before the guest arrives.</div>
                @elseif(! $isCheckIn && optional($booking->checkout_date)->toDateString() > now()->toDateString())
                    <div class="alert alert-warning">The booked departure is {{ optional($booking->checkout_date)->format('d M Y') }}. Set the checkout date to the day the guest actually leaves. Leaving the later date would keep the suite booked after the guest has gone.</div>
                @elseif($isCheckIn && $suggested->toDateString() < optional($booking->booked_checkin_date)->toDateString())
                    <div class="alert alert-warning">This is earlier than the booked arrival. The suite will be held from the new date so it is not shown as available while the guest is here.</div>
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ $isCheckIn ? 'Check-in date' : 'Check-out date' }}</label>
                        <input type="date" name="stay_date" value="{{ $defaultDate }}" class="form-control" required>
                        <div class="form-text">Booked {{ $booked ? $booked->format('d M Y') : '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Why the date changed</label>
                        <input type="text" name="reason" value="{{ old('reason', $isCheckIn ? $booking->checkin_note : $booking->checkout_note) }}" class="form-control" maxlength="500" placeholder="Required when this date is not the booked date">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">First name</label>
                        <input type="text" name="first_name" value="{{ old('first_name', $parts[0] ?? '') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last name</label>
                        <input type="text" name="last_name" value="{{ old('last_name', $parts[1] ?? '') }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $booking->phone) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $booking->email) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adults</label>
                        <input type="number" name="adults" min="1" max="6" value="{{ old('adults', $booking->adults ?? 1) }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Children</label>
                        <input type="number" name="children" min="0" max="4" value="{{ old('children', $booking->children ?? 0) }}" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Comment</label>
                        <textarea name="comment" class="form-control" rows="3">{{ old('comment', $booking->message) }}</textarea>
                    </div>
                </div>
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="confirm_session" value="1" id="confirm_session">
                    <label class="form-check-label" for="confirm_session">{{ $isCheckIn ? 'The guest is here, and these dates should be used for the stay and the reports.' : 'The guest has left, and the checkout date above is the last day the suite should stay booked.' }}</label>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn btn-outline-dark" type="submit" name="intent" value="revise">Save date change</button>
                    <button class="btn btn-dark" type="submit" name="intent" value="confirm">{{ $isCheckIn ? 'Confirm check-in' : 'Confirm check-out' }}</button>
                    <a class="btn btn-link" href="{{ route('content-management.front-office.show', $booking) }}">Back to reservation</a>
                </div>
            </form>
            <div class="desk-book__side">
                @include('content-management.front-office.partials.charges')
                @include('content-management.front-office.partials.payment-box', ['sessionForm' => 'desk-session', 'mode' => $mode])
            </div>
        </div>
    </div>
</div>
@endsection
