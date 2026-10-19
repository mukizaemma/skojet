<div class="card h-100">
    <img src="{{ $image->publicImageUrl() }}" alt="{{ $image->caption }}" class="card-img-top" style="height:180px;object-fit:cover;">
    <div class="card-body py-3">
        <p class="mb-1 fw-semibold">{{ $image->caption ?: 'Untitled' }}</p>
        <p class="small text-muted mb-2">Order {{ $image->sort_order }}@if($image->show_on_home) · On home page @endif</p>
        <div class="d-flex flex-wrap gap-2">
            @if($returnTab === 'gallery')
                <form action="{{ route('content-management.gallery.move', $image) }}" method="POST">
                    @csrf
                    <input type="hidden" name="direction" value="up">
                    <input type="hidden" name="page" value="{{ request('page') }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled((int) $image->id === (int) ($firstGalleryId ?? 0)) title="Move earlier">
                        <i class="fa fa-arrow-up"></i>
                    </button>
                </form>
                <form action="{{ route('content-management.gallery.move', $image) }}" method="POST">
                    @csrf
                    <input type="hidden" name="direction" value="down">
                    <input type="hidden" name="page" value="{{ request('page') }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled((int) $image->id === (int) ($lastGalleryId ?? 0)) title="Move later">
                        <i class="fa fa-arrow-down"></i>
                    </button>
                </form>
            @endif
            <button
                type="button"
                class="btn btn-sm btn-outline-primary js-edit-gallery"
                data-bs-toggle="modal"
                data-bs-target="#galleryEditModal"
                data-update-url="{{ route('content-management.gallery.update', $image) }}"
                data-caption="{{ $image->caption }}"
                data-sort="{{ $image->sort_order }}"
                data-home="{{ $image->show_on_home ? '1' : '0' }}"
                data-preview="{{ $image->publicImageUrl() }}"
                data-return-tab="{{ $returnTab }}"
            >Edit</button>
            <form action="{{ route('content-management.gallery.destroy', $image) }}" method="POST" onsubmit="return confirm('Remove this image?');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="return_tab" value="{{ $returnTab }}">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        </div>
    </div>
</div>
