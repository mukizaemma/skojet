@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h4 class="mb-0">Calendar</h4>
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => $prev]) }}">Previous</a>
                <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control form-control-sm">
                <button class="btn btn-dark btn-sm" type="submit">View</button>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => now()->toDateString()]) }}">Today</a>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.calendar', ['start' => $next]) }}">Next</a>
            </form>
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
                    <div class="{{ $date->isToday() ? 'fw-bold text-dark' : '' }}">
                        {{ $date->format('D') }}<br>{{ $date->format('d M') }}
                    </div>
                @endforeach
            </div>
            @foreach($rows as $row)
                <div class="desk-cal__row">
                    <div class="desk-cal__name">{{ $row['room']->title }}</div>
                    <div class="desk-cal__track">
                        @foreach($row['bars'] as $bar)
                            @include('content-management.front-office.partials.bar', ['bar' => $bar])
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if(count($unallocated))
                <div class="desk-cal__row">
                    <div class="desk-cal__name">Unallocated</div>
                    <div class="desk-cal__track">
                        @foreach($unallocated as $bar)
                            @include('content-management.front-office.partials.bar', ['bar' => $bar])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</div>
@endsection
