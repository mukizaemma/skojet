@php
    $menuCategories = \App\Models\RestaurantMenuItem::CATEGORIES;
    $requestedCategory = (string) request('category', '');
    $activeCategory = $requestedCategory === '' ? 'all' : $requestedCategory;
    if ($activeCategory !== 'all' && ! array_key_exists($activeCategory, $menuCategories)) {
        $activeCategory = 'all';
    }
    $menuList = collect($menuCategories)->flatMap(fn ($label, $key) => $menuItems->get($key, collect()));
    $menuPayload = $menuList->map(fn ($item) => [
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
            <button class="menu-tabs__btn {{ $activeCategory === 'all' ? 'is-active' : '' }}" type="button" role="tab" data-menu-tab="all" aria-selected="{{ $activeCategory === 'all' ? 'true' : 'false' }}">
                All
                <span data-menu-count="all">{{ $menuList->count() }}</span>
            </button>
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
    <div class="bg-white border rounded" id="menu-list">
        @foreach($menuList as $item)
            <button class="menu-row" type="button" data-menu-open="{{ $item->id }}" data-menu-category="{{ $item->category }}" data-menu-text="{{ strtolower($item->name.' '.($menuCategories[$item->category] ?? '').' '.$item->description.' '.$item->price_international.' '.$item->price_local) }}">
                <span>
                    <strong>{{ $item->name }}</strong>
                    <small>{{ $menuCategories[$item->category] ?? '' }}@unless($item->is_active) · Hidden @endunless @if(filled($item->description)) · {{ $item->description }} @endif</small>
                </span>
                <span class="menu-row__prices">{{ restaurant_money($item->price_international, 'international') }} · {{ restaurant_money($item->price_local, 'local') }}</span>
            </button>
        @endforeach
        <p class="text-muted p-3 mb-0" data-menu-empty @if($menuList->isNotEmpty()) hidden @endif>No menu items yet.</p>
        <p class="text-muted p-3 mb-0" data-menu-none hidden>No items match this search.</p>
    </div>
    <nav class="menu-pager" id="menu-pager" aria-label="Menu pages" hidden></nav>
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
        const pager = document.getElementById('menu-pager');
        const pageSize = 25;
        let active = @json($activeCategory);
        let page = 1;

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
            document.getElementById('menu-category').value = item ? item.category : (active === 'all' ? 'food' : active);
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

        function matching(query) {
            return Array.prototype.filter.call(document.querySelectorAll('[data-menu-open]'), function (row) {
                const category = row.getAttribute('data-menu-category');
                const text = row.getAttribute('data-menu-text') || '';
                const inTab = active === 'all' || category === active;
                return inTab && (query === '' || text.indexOf(query) !== -1);
            });
        }

        function renderPager(pages) {
            pager.innerHTML = '';
            if (pages < 2) {
                pager.hidden = true;
                return;
            }
            pager.hidden = false;
            function add(label, target, disabled, current) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'menu-pager__btn' + (current ? ' is-active' : '');
                button.textContent = label;
                button.disabled = disabled;
                if (!disabled && !current) {
                    button.addEventListener('click', function () {
                        page = target;
                        applyFilter(false);
                        document.getElementById('menu-list').scrollIntoView({ block: 'nearest' });
                    });
                }
                pager.appendChild(button);
            }
            add('Previous', page - 1, page <= 1, false);
            for (let number = 1; number <= pages; number += 1) {
                add(String(number), number, false, number === page);
            }
            add('Next', page + 1, page >= pages, false);
        }

        function applyFilter(resetPage) {
            const query = search.value.trim().toLowerCase();
            if (resetPage !== false) {
                page = 1;
            }
            const rows = matching(query);
            const pages = Math.max(1, Math.ceil(rows.length / pageSize));
            if (page > pages) {
                page = pages;
            }
            const start = (page - 1) * pageSize;
            const visible = {};
            rows.forEach(function (row, index) {
                visible[row.getAttribute('data-menu-open')] = index >= start && index < start + pageSize;
            });
            document.querySelectorAll('[data-menu-open]').forEach(function (row) {
                row.hidden = !visible[row.getAttribute('data-menu-open')];
            });
            const empty = document.querySelector('[data-menu-empty]');
            const none = document.querySelector('[data-menu-none]');
            const totalRows = document.querySelectorAll('[data-menu-open]').length;
            if (empty) {
                empty.hidden = totalRows !== 0;
            }
            if (none) {
                none.hidden = rows.length !== 0 || totalRows === 0;
            }
            document.querySelectorAll('[data-menu-count]').forEach(function (count) {
                const key = count.getAttribute('data-menu-count');
                let matches = 0;
                document.querySelectorAll('[data-menu-open]').forEach(function (row) {
                    const text = row.getAttribute('data-menu-text') || '';
                    const category = row.getAttribute('data-menu-category');
                    const inBucket = key === 'all' || category === key;
                    if (inBucket && (query === '' || text.indexOf(query) !== -1)) {
                        matches += 1;
                    }
                });
                count.textContent = matches;
            });
            document.querySelectorAll('[data-menu-tab]').forEach(function (tab) {
                const on = tab.getAttribute('data-menu-tab') === active;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            renderPager(rows.length === 0 ? 0 : pages);
        }

        document.querySelectorAll('[data-menu-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                active = tab.getAttribute('data-menu-tab');
                applyFilter(true);
            });
        });
        search.addEventListener('input', function () {
            applyFilter(true);
        });
        applyFilter(true);

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
