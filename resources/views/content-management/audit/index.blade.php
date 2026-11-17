@extends('layouts.adminBase')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/front-desk.css') }}?v=desk-nav-clear">
<div class="container-fluid pt-4 px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            @include('content-management.front-office.partials.back', ['href' => route('content-management.dashboard'), 'label' => 'Dashboard'])
            <h4 class="mb-0">{{ $title }}</h4>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm {{ $archived ? 'btn-outline-dark' : 'btn-dark' }}" href="{{ route('content-management.audit.activities', request()->only('q')) }}">System activities</a>
            <a class="btn btn-sm {{ $archived ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ route('content-management.audit.archived', request()->only('q')) }}">Archived records</a>
        </div>
    </div>
    <form method="GET" class="bg-white border rounded p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-6">
                <label class="form-label" for="audit-q">Search</label>
                <input class="form-control" id="audit-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="User, email, or what was removed">
            </div>
            <div class="col-auto d-flex flex-wrap gap-2">
                <button class="btn btn-dark" type="submit">Search</button>
                @include('content-management.front-office.partials.clear-filters', ['href' => route($archived ? 'content-management.audit.archived' : 'content-management.audit.activities')])
            </div>
        </div>
    </form>
    <div class="bg-white border rounded">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Record</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ optional($log->created_at)->format('d M Y H:i') }}</td>
                        <td>
                            {{ $log->user?->name ?: 'Unknown user' }}
                            <div class="text-muted small">{{ $log->user?->email }}</div>
                        </td>
                        <td>{{ $log->actionLabel() }}</td>
                        <td>
                            {{ $log->summary }}
                            @if(is_array($log->payload) && $log->payload !== [])
                                <div class="text-muted small">
                                    @foreach($log->payload as $key => $value)
                                        @if(is_scalar($value) && $value !== '')
                                            {{ ucwords(str_replace('_', ' ', (string) $key)) }}: {{ $value }}@if(! $loop->last) · @endif
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted p-3">Nothing is recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
