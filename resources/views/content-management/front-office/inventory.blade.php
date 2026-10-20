@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}">
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h4 class="mb-1">Room inventory</h4>
                <p class="text-muted mb-0">Availability and the rates already set on each suite.</p>
            </div>
            <form method="GET" class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.inventory', ['start' => $prev]) }}">Previous</a>
                <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control form-control-sm">
                <button class="btn btn-dark btn-sm">View</button>
                <a class="btn btn-outline-dark btn-sm" href="{{ route('content-management.front-office.inventory', ['start' => $next]) }}">Next</a>
            </form>
        </div>
        <div class="bg-white border rounded desk-inv">
            <table>
                <thead>
                    <tr>
                        <th>Suite / rate</th>
                        @foreach($dates as $date)
                            <th class="{{ $date->isWeekend() ? 'text-danger' : '' }}">{{ $date->format('D') }}<br>{{ $date->format('d') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($blocks as $block)
                        <tr class="desk-room">
                            <td>{{ $block['room']->title }}</td>
                            @foreach($block['availability'] as $open)
                                <td><span class="desk-pill {{ $open ? '' : 'desk-pill--off' }}">{{ $open }}</span></td>
                            @endforeach
                        </tr>
                        @forelse($block['plans'] as $plan)
                            @foreach(['single' => 'Single', 'double' => 'Double'] as $key => $label)
                                @if($plan[$key.'_sale'] !== null)
                                    <tr>
                                        <td>{{ \App\Models\Room::BOARD_PLANS[$plan['key']] ?? $plan['key'] }} · {{ $label }}</td>
                                        @foreach($dates as $unused)
                                            <td>{{ hotel_price($plan[$key.'_sale'], $setting ?? null) }}</td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endforeach
                        @empty
                            <tr>
                                <td>Standard rate</td>
                                @foreach($dates as $unused)
                                    <td>{{ hotel_price($block['room']->price, $setting ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
