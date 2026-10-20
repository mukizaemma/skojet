@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-menu">
<div class="desk-app">
@include('content-management.front-office.partials.header')
<div class="desk-app__body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h4 class="mb-0">Payments</h4>
            <a href="{{ route('content-management.front-office.create') }}" class="btn btn-dark">New reservation</a>
        </div>
        <div class="bg-white border rounded">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Guest</th>
                            <th>Method</th>
                            <th>Note</th>
                            <th>Received by</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($payment->booking)
                                        <a href="{{ route('content-management.front-office.show', $payment->booking) }}">{{ $payment->booking->names }}</a>
                                    @endif
                                </td>
                                <td>{{ $payment->methodLabel() }}</td>
                                <td>{{ $payment->note }}</td>
                                <td>{{ $payment->receiver?->name }}</td>
                                <td class="text-end">{{ hotel_price($payment->amount, $setting ?? null, $payment->booking?->currencyKey() ?? 'usd') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted p-3">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
