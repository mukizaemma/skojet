<div class="card h-100">
    <img src="{{ asset('storage/'.$image->image) }}" alt="{{ $image->caption }}" class="card-img-top" style="height:180px;object-fit:cover;">
    <div class="card-body py-3">
        <p class="mb-1 fw-semibold">{{ $image->caption ?: 'Untitled' }}</p>
        <p class="small text-muted mb-2">Order {{ $image->sort_order }}@if($image->show_on_home) · On home page @endif</p>
        <div class="d-flex gap-2">
            <button
                type="button"
                class="btn btn-sm btn-outline-primary js-edit-gallery"
                data-bs-toggle="modal"
                data-bs-target="#galleryEditModal"
                data-update-url="{{ route('content-management.gallery.update', $image) }}"
                data-caption="{{ $image->caption }}"
                data-sort="{{ $image->sort_order }}"
                data-home="{{ $image->show_on_home ? '1' : '0' }}"
                data-preview="{{ asset('storage/'.$image->image) }}"
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
