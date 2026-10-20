<div class="bg-white border rounded p-3 mb-3" id="charges">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <h6 class="mb-0">Guest bill</h6>
        @if($items->isNotEmpty())
            <button class="btn btn-dark btn-sm" type="button" data-pos-open>Add extra charges</button>
        @endif
    </div>
    @forelse($booking->charges as $charge)
        <div class="d-flex justify-content-between gap-2 py-2 border-bottom">
            <div>
                <strong>{{ $charge->name }}</strong>
                <div class="text-muted small">
                    {{ $charge->categoryLabel() }} · {{ $charge->quantity }} × {{ hotel_price($charge->sale_price, $setting ?? null, $booking->currencyKey()) }}
                    @if($charge->included)
                        · Included in the room
                    @elseif($charge->sale_price + 0.001 < $charge->unit_price)
                        · Discounted from {{ hotel_price($charge->unit_price, $setting ?? null, $booking->currencyKey()) }}
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div>{{ $charge->included ? 'Included' : hotel_price($charge->line_total, $setting ?? null, $booking->currencyKey()) }}</div>
                <form method="POST" action="{{ route('content-management.front-office.charges.destroy', [$booking, $charge]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-muted small mb-2">No extra charges yet.</p>
    @endforelse
    @php $charged = $booking->charges->where('included', false)->sum('line_total'); @endphp
    @if($charged >= 0.5)
        <div class="d-flex justify-content-between pt-2">
            <span>Extras to pay</span>
            <strong>{{ hotel_price($charged, $setting ?? null, $booking->currencyKey()) }}</strong>
        </div>
    @endif
    @if($items->isEmpty())
        <p class="text-muted small mb-0 mt-2">Add items on the <a href="{{ route('content-management.front-office.settings.extras.index') }}">POS Items</a> tab before they can be added to a bill.</p>
    @else
        <div class="desk-pos mt-3" data-pos data-currency="{{ $booking->currencyKey() }}" hidden>
            <p class="small text-muted mb-2">Choose items, then mark each one included in the room or charged. Charged items are added to the balance and can be paid together.</p>
            <div class="desk-pos__items">
                @foreach($items->groupBy('category') as $category => $group)
                    <div class="desk-pos__group">
                        <div class="desk-pos__label">{{ \App\Models\ExtraItem::CATEGORIES[$category] ?? ucfirst($category) }}</div>
                        <div class="desk-pos__choices">
                            @foreach($group as $item)
                                @php $menuPrice = $booking->currencyKey() === 'rwf' ? $item->price_rwf : $item->price; @endphp
                                <button type="button" class="desk-pos__item" data-pos-add data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ $menuPrice }}" @disabled($menuPrice === null)>
                                    <span>{{ $item->name }}</span>
                                    <strong>{{ $menuPrice === null ? 'No '.($booking->currencyKey() === 'rwf' ? 'RWF' : 'USD').' price' : hotel_price($menuPrice, $setting ?? null, $booking->currencyKey()) }}</strong>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('content-management.front-office.charges.store', $booking) }}" data-pos-form>
                @csrf
                <div class="desk-pos__ticket" data-pos-ticket></div>
                <button class="btn btn-dark btn-sm mt-2" type="submit" data-pos-save disabled>Add to bill</button>
            </form>
        </div>
    @endif
</div>
<script>
    document.querySelectorAll('[data-pos]').forEach(function (panel) {
        const open = panel.parentElement.querySelector('[data-pos-open]');
        const form = panel.querySelector('[data-pos-form]');
        const ticket = panel.querySelector('[data-pos-ticket]');
        const save = panel.querySelector('[data-pos-save]');
        const lines = [];
        if (open) {
            open.addEventListener('click', function () {
                panel.hidden = !panel.hidden;
                open.textContent = panel.hidden ? 'Add extra charges' : 'Close POS';
            });
        }
        function paint() {
            ticket.innerHTML = '';
            lines.forEach(function (line, index) {
                const row = document.createElement('div');
                row.className = 'desk-pos__line';
                const name = document.createElement('strong');
                name.textContent = line.name;
                const qty = document.createElement('input');
                qty.type = 'number';
                qty.min = '1';
                qty.max = '20';
                qty.value = String(line.quantity);
                qty.className = 'form-control form-control-sm';
                qty.addEventListener('input', function () {
                    line.quantity = Math.max(1, Number(qty.value) || 1);
                });
                const price = document.createElement('input');
                price.type = 'number';
                price.min = '0';
                price.step = '0.01';
                price.value = String(line.sale);
                price.className = 'form-control form-control-sm';
                price.addEventListener('input', function () {
                    line.sale = Math.max(0, Number(price.value) || 0);
                });
                const mode = document.createElement('select');
                mode.className = 'form-select form-select-sm';
                [['0', 'Charge'], ['1', 'Included in room']].forEach(function (choice) {
                    const option = document.createElement('option');
                    option.value = choice[0];
                    option.textContent = choice[1];
                    option.selected = (line.included ? '1' : '0') === choice[0];
                    mode.appendChild(option);
                });
                mode.addEventListener('change', function () {
                    line.included = mode.value === '1';
                });
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-link btn-sm text-danger p-0';
                remove.textContent = 'Remove';
                remove.addEventListener('click', function () {
                    lines.splice(index, 1);
                    paint();
                });
                ['extra_item_id', 'quantity', 'sale_price', 'included'].forEach(function (field) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'lines[' + index + '][' + field + ']';
                    input.value = field === 'extra_item_id' ? line.id : (field === 'quantity' ? line.quantity : (field === 'sale_price' ? line.sale : (line.included ? '1' : '0')));
                    row.appendChild(input);
                });
                row.append(name, qty, price, mode, remove);
                ticket.appendChild(row);
            });
            save.disabled = lines.length === 0;
        }
        panel.querySelectorAll('[data-pos-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                const existing = lines.find(function (line) { return String(line.id) === button.dataset.id && !line.included; });
                if (existing) {
                    existing.quantity += 1;
                } else {
                    if (button.dataset.price === '') {
                    return;
                }
                lines.push({
                        id: button.dataset.id,
                        name: button.dataset.name,
                        sale: Number(button.dataset.price || 0),
                        quantity: 1,
                        included: false,
                    });
                }
                paint();
            });
        });
        if (form) {
            form.addEventListener('submit', function () {
                paint();
            });
        }
    });
</script>
