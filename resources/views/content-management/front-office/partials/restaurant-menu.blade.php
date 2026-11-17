@php
    $menuCategories = \App\Models\RestaurantMenuItem::CATEGORIES;
    $activeCategory = request('category', old('category', 'food'));
    if (! array_key_exists($activeCategory, $menuCategories)) {
        $activeCategory = 'food';
    }
    $menuPayload = $menuItems->flatten(1)->map(fn ($item) => [
        'id' => $item->id,
        'category' => $item->category,
        'name' => $item->name,
        'description' => $item->description,
        'price_international' => $item->price_international,
        'price_local' => $item->price_local,
        'sort_order' => $item->sort_order,
        'is_active' => (bool) $item->is_active,
    ])->values();
@endphp
<div id="restaurant-menu">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="menu-tabs" role="tablist">
            @foreach($menuCategories as $key => $label)
                <button class="menu-tabs__btn {{ $activeCategory === $key ? 'is-active' : '' }}" type="button" role="tab" data-menu-tab="{{ $key }}" aria-selected="{{ $activeCategory === $key ? 'true' : 'false' }}">
                    {{ $label }}
                    <span data-menu-count="{{ $key }}">{{ $menuItems->get($key, collect())->count() }}</span>
                </button>
            @endforeach
        </div>
        <button class="btn btn-dark" type="button" data-menu-add>Add item</button>
    </div>
    <input class="form-control mb-3" id="menu-search" type="search" placeholder="Search the menu" autocomplete="off">
    <div class="bg-white border rounded">
        @foreach($menuCategories as $category => $label)
            <div data-menu-panel="{{ $category }}" @if($activeCategory !== $category) hidden @endif>
                @forelse($menuItems->get($category, collect()) as $item)
                    <button class="menu-row" type="button" data-menu-open="{{ $item->id }}" data-menu-category="{{ $item->category }}" data-menu-text="{{ strtolower($item->name.' '.$item->description.' '.$item->price_international.' '.$item->price_local) }}">
                        <span>
                            <strong>{{ $item->name }}</strong>
                            @unless($item->is_active)<em>Hidden</em>@endunless
                            @if(filled($item->description))<small>{{ $item->description }}</small>@endif
                        </span>
                        <span class="menu-row__prices">{{ restaurant_money($item->price_international, 'international') }} · {{ restaurant_money($item->price_local, 'local') }}</span>
                    </button>
                @empty
                    <p class="text-muted p-3 mb-0" data-menu-empty>No {{ strtolower($label) }} yet.</p>
                @endforelse
                <p class="text-muted p-3 mb-0" data-menu-none hidden>No items match this search.</p>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="menu-item-modal" tabindex="-1" aria-labelledby="menu-item-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="menu-item-form" method="POST" action="{{ route('content-management.restaurant-menu.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="menu-item-title">Add menu item</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="menu-category">Category</label>
                        <select class="form-select" id="menu-category" name="category" required>
                            @foreach($menuCategories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="menu-name">Name</label>
                        <input class="form-control" id="menu-name" name="name" maxlength="120" required value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="menu-description">Description or accompaniments</label>
                        <textarea class="form-control" id="menu-description" name="description" maxlength="500" rows="3">{{ old('description') }}</textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="menu-usd">USD</label>
                            <input class="form-control" id="menu-usd" type="number" name="price_international" min="0" step="0.01" required value="{{ old('price_international', '0') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="menu-rwf">RWF</label>
                            <input class="form-control" id="menu-rwf" type="number" name="price_local" min="0" step="1" required value="{{ old('price_local', '0') }}">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label" for="menu-order">Order</label>
                            <input class="form-control" id="menu-order" type="number" name="sort_order" min="0" value="{{ old('sort_order', '0') }}">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" id="menu-shown" type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))>
                                <label class="form-check-label" for="menu-shown">Shown on the menu</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button class="btn btn-outline-danger" id="menu-remove" type="submit" form="menu-item-delete" hidden>Remove</button>
                    <div class="d-flex gap-2 ms-auto">
                        <button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal" data-dismiss="modal">Cancel</button>
                        <button class="btn btn-dark" type="submit">Save</button>
                    </div>
                </div>
            </form>
            <form id="menu-item-delete" method="POST" hidden onsubmit="return confirm('Remove this item from the menu?')">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
<script type="application/json" id="menu-item-data">@json($menuPayload)</script>
<script>
    (function () {
        const items = JSON.parse(document.getElementById('menu-item-data').textContent || '[]');
        const byId = {};
        items.forEach(function (item) { byId[item.id] = item; });
        const form = document.getElementById('menu-item-form');
        const removeForm = document.getElementById('menu-item-delete');
        const removeButton = document.getElementById('menu-remove');
        const modalEl = document.getElementById('menu-item-modal');
        const storeUrl = @json(route('content-management.restaurant-menu.store'));
        const updateUrl = @json(route('content-management.restaurant-menu.update', ['item' => '__ID__']));
        const destroyUrl = @json(route('content-management.restaurant-menu.destroy', ['item' => '__ID__']));
        const search = document.getElementById('menu-search');
        let active = @json($activeCategory);

        function showModal() {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }
            if (window.jQuery) {
                window.jQuery(modalEl).modal('show');
            }
        }

        function fill(item) {
            const editing = Boolean(item);
            document.getElementById('menu-item-title').textContent = editing ? 'Edit menu item' : 'Add menu item';
            form.action = editing ? updateUrl.replace('__ID__', item.id) : storeUrl;
            removeForm.action = editing ? destroyUrl.replace('__ID__', item.id) : '';
            removeButton.hidden = !editing;
            document.getElementById('menu-category').value = item ? item.category : active;
            document.getElementById('menu-name').value = item ? item.name : '';
            document.getElementById('menu-description').value = item && item.description ? item.description : '';
            document.getElementById('menu-usd').value = item ? item.price_international : '0';
            document.getElementById('menu-rwf').value = item ? item.price_local : '0';
            document.getElementById('menu-order').value = item ? item.sort_order : '0';
            document.getElementById('menu-shown').checked = item ? item.is_active : true;
            showModal();
        }

        document.querySelector('[data-menu-add]').addEventListener('click', function () {
            fill(null);
        });
        document.querySelectorAll('[data-menu-open]').forEach(function (row) {
            row.addEventListener('click', function () {
                fill(byId[row.getAttribute('data-menu-open')] || null);
            });
        });

        function applyFilter() {
            const query = search.value.trim().toLowerCase();
            document.querySelectorAll('[data-menu-panel]').forEach(function (panel) {
                const category = panel.getAttribute('data-menu-panel');
                const showPanel = query !== '' || category === active;
                panel.hidden = !showPanel;
                let visible = 0;
                panel.querySelectorAll('[data-menu-open]').forEach(function (row) {
                    const match = query === '' || row.getAttribute('data-menu-text').indexOf(query) !== -1;
                    const inTab = query !== '' || row.getAttribute('data-menu-category') === active;
                    const on = match && (query !== '' || inTab);
                    row.hidden = !on;
                    if (on) {
                        visible += 1;
                    }
                });
                const empty = panel.querySelector('[data-menu-empty]');
                const none = panel.querySelector('[data-menu-none]');
                if (empty) {
                    empty.hidden = query !== '' || visible !== 0 || panel.querySelector('[data-menu-open]');
                }
                if (none) {
                    none.hidden = query === '' || visible !== 0;
                }
                const count = document.querySelector('[data-menu-count="' + category + '"]');
                if (count) {
                    const total = panel.querySelectorAll('[data-menu-open]').length;
                    let matches = 0;
                    panel.querySelectorAll('[data-menu-open]').forEach(function (row) {
                        if (query === '' || row.getAttribute('data-menu-text').indexOf(query) !== -1) {
                            matches += 1;
                        }
                    });
                    count.textContent = query === '' ? total : matches;
                }
            });
            document.querySelectorAll('[data-menu-tab]').forEach(function (tab) {
                const on = query === '' && tab.getAttribute('data-menu-tab') === active;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }

        document.querySelectorAll('[data-menu-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                active = tab.getAttribute('data-menu-tab');
                search.value = '';
                applyFilter();
            });
        });
        search.addEventListener('input', applyFilter);

        @if($errors->any())
            fill({
                id: @json(old('item_id')),
                category: @json(old('category', $activeCategory)),
                name: @json(old('name', '')),
                description: @json(old('description', '')),
                price_international: @json(old('price_international', '0')),
                price_local: @json(old('price_local', '0')),
                sort_order: @json(old('sort_order', '0')),
                is_active: @json((bool) old('is_active'))
            });
            if (!@json(old('item_id'))) {
                document.getElementById('menu-item-title').textContent = 'Add menu item';
                form.action = storeUrl;
                removeButton.hidden = true;
            }
        @endif
    })();
</script>
