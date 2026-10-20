@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')
    <div class="container-fluid pt-4 px-4">
        @include('content-management.front-office.partials.alerts')
        <h4 class="mb-2">Front Office settings</h4>
        <p class="text-muted">Close a suite when it cannot be sold. Rates stay on the Rooms page.</p>
        <form method="POST" action="{{ route('content-management.front-office.closures.store') }}" class="bg-white border rounded p-3 mb-4">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Suite</label>
                    <select name="room_id" class="form-select" required>
                        @foreach($rooms as $room)
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
    </div>
</div>
</div>
@endsection
