@php
    $overbooks = $overbooks ?? collect();
    $lanes = max(1, (int) collect($bars)->max('lane') + 1);
@endphp
<div class="desk-cal__track" style="--lanes: {{ $lanes }}">
    @foreach($dates as $index => $date)
        @php
            $column = $index + 1;
            $occupied = 0;
            $closed = false;
            foreach ($bars as $bar) {
                $covers = $column >= (int) $bar['column'] && $column < (int) $bar['column'] + (int) $bar['span'];
                if (! $covers) {
                    continue;
                }
                if (($bar['state'] ?? '') === 'closure') {
                    $closed = true;
                } else {
                    $occupied++;
                }
            }
            $extra = (int) $overbooks->filter(function ($overbook) use ($date) {
                return $date->gte($overbook->starts_on) && $date->lte($overbook->ends_on);
            })->sum('extra');
            $open = ! $closed && $occupied < (1 + $extra) && ! empty($room);
            $checkout = $date->copy()->addDay()->toDateString();
            $cellClass = 'desk-cal__cell'
                .($date->isToday() ? ' is-today' : '')
                .($date->isWeekend() ? ' is-weekend' : '')
                .($extra > 0 ? ' is-overbook' : '')
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
