@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-calendar">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="desk-cal__bar">
            <div>
                <h4 class="mb-1">Calendar</h4>
                <p class="text-muted mb-0">{{ $start->format('d M') }} – {{ $dates->last()->format('d M Y') }}. Click an open night to add a reservation.</p>
            </div>
            <div class="desk-cal__actions">
                <a href="{{ route('content-management.front-office.create') }}" class="btn btn-dark btn-sm">New reservation</a>
                <form method="GET" class="desk-cal__nav">
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => $prev]) }}">Previous</a>
                    <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control form-control-sm">
                    <button class="btn btn-dark btn-sm" type="submit">View</button>
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => now()->toDateString()]) }}">Today</a>
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => $next]) }}">Next</a>
                </form>
            </div>
        </div>
        <div class="desk-legend mb-3">
            <span class="desk-swatch--confirmed">Confirmed</span>
            <span class="desk-swatch--checked_in">Checked in</span>
            <span class="desk-swatch--checked_out">Checked out</span>
            <span class="desk-swatch--closure">Room closure</span>
            <span class="desk-swatch--unallocated">Unallocated</span>
            <span class="desk-swatch--incomplete">Incomplete payment</span>
        </div>
        <div class="desk-cal" style="--days: {{ $dates->count() }}">
            <div class="desk-cal__head">
                <div>Suite</div>
                @foreach($dates as $date)
                    <div class="{{ $date->isToday() ? 'is-today' : '' }} {{ $date->isWeekend() ? 'is-weekend' : '' }}">
                        <span class="desk-cal__dow">{{ $date->format('D') }}</span>
                        <span class="desk-cal__num">{{ $date->format('d') }}</span>
                        <span class="desk-cal__mon">{{ $date->format('M') }}</span>
                    </div>
                @endforeach
            </div>
            @foreach($rows as $row)
                <div class="desk-cal__row">
                    <div class="desk-cal__name">{{ $row['room']->title }}</div>
                    @include('content-management.front-office.partials.calendar-track', ['bars' => $row['bars'], 'room' => $row['room']])
                </div>
            @endforeach
            @if(count($unallocated))
                <div class="desk-cal__row">
                    <div class="desk-cal__name">Unallocated</div>
                    @include('content-management.front-office.partials.calendar-track', ['bars' => $unallocated, 'room' => null])
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
