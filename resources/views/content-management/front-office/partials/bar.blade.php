@if($bar['href'])
    <a class="desk-bar desk-bar--{{ $bar['state'] }}" href="{{ $bar['href'] }}" style="grid-column: {{ $bar['column'] }} / span {{ $bar['span'] }}; grid-row: {{ $bar['lane'] + 1 }};">{{ $bar['label'] }}</a>
@else
    <span class="desk-bar desk-bar--{{ $bar['state'] }}" style="grid-column: {{ $bar['column'] }} / span {{ $bar['span'] }}; grid-row: {{ $bar['lane'] + 1 }};">{{ $bar['label'] }}</span>
@endif
