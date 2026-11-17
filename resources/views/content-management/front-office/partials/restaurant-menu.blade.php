<div id="restaurant-menu">
    <h4 class="mb-1">Restaurant menu</h4>
    <p class="text-muted mb-3">These dishes, drinks, and coffees are what a guest orders when reserving a table. Room bills use the extras on the other tab.</p>
    <form method="POST" action="{{ route('content-management.restaurant-menu.store') }}" class="bg-white border rounded p-3 mb-4">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input class="form-control" type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Description or accompaniments</label>
                <input class="form-control" type="text" name="description" value="{{ old('description') }}" maxlength="500">
            </div>
            <div class="col-md-1">
                <label class="form-label">USD</label>
                <input class="form-control" type="number" name="price_international" min="0" step="0.01" value="{{ old('price_international', '0') }}" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">RWF</label>
                <input class="form-control" type="number" name="price_local" min="0" step="1" value="{{ old('price_local', '0') }}" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">Order</label>
                <input class="form-control" type="number" name="sort_order" min="0" value="{{ old('sort_order', '0') }}">
            </div>
            <div class="col-md-1">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                    <label class="form-check-label">Shown</label>
                </div>
                <button class="btn btn-dark w-100" type="submit">Add</button>
            </div>
        </div>
    </form>

    @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $category => $label)
        <h6 class="mt-3">{{ $label }}</h6>
        <div class="bg-white border rounded mb-3">
            @forelse($menuItems->get($category, collect()) as $item)
                <div class="p-3 border-bottom">
                    <form method="POST" action="{{ route('content-management.restaurant-menu.update', $item) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-2">
                            <select name="category" class="form-select" required>
                                @foreach(\App\Models\RestaurantMenuItem::CATEGORIES as $key => $categoryLabel)
                                    <option value="{{ $key }}" @selected($item->category === $key)>{{ $categoryLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" type="text" name="name" value="{{ $item->name }}" maxlength="120" required>
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" type="text" name="description" value="{{ $item->description }}" maxlength="500" placeholder="Description or accompaniments">
                        </div>
                        <div class="col-md-1">
                            <input class="form-control" type="number" name="price_international" min="0" step="0.01" value="{{ $item->price_international }}" required aria-label="USD">
                        </div>
                        <div class="col-md-1">
                            <input class="form-control" type="number" name="price_local" min="0" step="1" value="{{ $item->price_local }}" required aria-label="RWF">
                        </div>
                        <div class="col-md-1">
                            <input class="form-control" type="number" name="sort_order" min="0" value="{{ $item->sort_order }}" aria-label="Order">
                        </div>
                        <div class="col-md-1">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                <label class="form-check-label">Shown</label>
                            </div>
                            <button class="btn btn-dark btn-sm" type="submit">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('content-management.restaurant-menu.destroy', $item) }}" class="mt-1" onsubmit="return confirm('Remove this item?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger p-0" type="submit">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-muted p-3 mb-0">No {{ strtolower($label) }} items yet.</p>
            @endforelse
        </div>
    @endforeach
</div>
