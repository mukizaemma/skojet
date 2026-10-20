@php
    $taken = [];
    foreach ($bars as $bar) {
        for ($offset = 0; $offset < (int) $bar['span']; $offset++) {
            $taken[(int) $bar['column'] + $offset] = true;
        }
    }
    $lanes = max(1, (int) collect($bars)->max('lane') + 1);
@endphp
<div class="desk-cal__track" style="--lanes: {{ $lanes }}">
    @foreach($dates as $index => $date)
        @php
            $column = $index + 1;
            $open = empty($taken[$column]) && ! empty($room);
            $checkout = $date->copy()->addDay()->toDateString();
            $cellClass = 'desk-cal__cell'
                .($date->isToday() ? ' is-today' : '')
                .($date->isWeekend() ? ' is-weekend' : '')
                .($open ? '' : ' is-taken');
        @endphp
        @if($open)
            <a class="{{ $cellClass }}" style="grid-column: {{ $column }}" href="{{ route('content-management.front-office.create', ['room' => $room->id, 'checkin' => $date->toDateString(), 'checkout' => $checkout]) }}" title="Add {{ $room->title }} on {{ $date->format('D, d M Y') }}">
                <span class="desk-cal__plus" aria-hidden="true">+</span>
                <span class="visually-hidden">Add {{ $room->title }} on {{ $date->format('D, d M Y') }}</span>
            </a>
        @else
            <span class="{{ $cellClass }}" style="grid-column: {{ $column }}"></span>
        @endif
    @endforeach
    @foreach($bars as $bar)
        @include('content-management.front-office.partials.bar', ['bar' => $bar])
    @endforeach
</div>
