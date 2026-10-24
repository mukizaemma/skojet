<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')

    <div class="container-fluid pt-4 px-4">
        <div class="bg-light rounded h-100 p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">Gallery</h4>
                    <p class="text-muted small mb-0">Home shows the first 6 images in a 3-column grid. The gallery page shows each photo already on the server, without duplicate copies, 15 per page.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'home' ? 'active' : '' }}" id="home-gallery-tab" data-bs-toggle="tab" data-bs-target="#home-gallery-pane" type="button" role="tab" aria-controls="home-gallery-pane" aria-selected="{{ $activeTab === 'home' ? 'true' : 'false' }}">
                        Home page <span class="badge bg-secondary ms-1">{{ $homeImages->count() }}/6</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'gallery' ? 'active' : '' }}" id="site-gallery-tab" data-bs-toggle="tab" data-bs-target="#site-gallery-pane" type="button" role="tab" aria-controls="site-gallery-pane" aria-selected="{{ $activeTab === 'gallery' ? 'true' : 'false' }}">
                        Gallery page <span class="badge bg-secondary ms-1">{{ $galleryTotal }}</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade {{ $activeTab === 'home' ? 'show active' : '' }}" id="home-gallery-pane" role="tabpanel" aria-labelledby="home-gallery-tab">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <p class="text-muted small mb-0">These are the homepage images, in the order visitors see them. Use the arrows to rearrange them. The homepage shows the first 6.</p>
                        @if($homeImages->count() < 6)
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#galleryAddModal" data-show-home="1" data-return-tab="home">
                                <i class="fa fa-plus me-2"></i>Add home image
                            </button>
                        @endif
                    </div>

                    @if($homeImages->isEmpty())
                        <div class="alert alert-info mb-0">No homepage images yet. Add up to 6.</div>
                    @else
                        <div class="row g-3">
                            @foreach($homeImages as $image)
                                <div class="col-md-4">
                                    @include('content-management.gallery.card', [
                                        'image' => $image,
                                        'returnTab' => 'home',
                                        'homePosition' => $loop->iteration,
                                    ])
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade {{ $activeTab === 'gallery' ? 'show active' : '' }}" id="site-gallery-pane" role="tabpanel" aria-labelledby="site-gallery-tab">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <p class="text-muted small mb-0">Photos already stored on the server are listed here, 15 per page, with copies of the same photo left out of the public page. Move them to change the public order, or remove the extra copies.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <form action="{{ route('content-management.gallery.remove-duplicates') }}" method="POST" onsubmit="return confirm('Remove duplicate copies and keep one of each photo?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">Remove duplicates</button>
                            </form>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#galleryAddModal" data-show-home="0" data-return-tab="gallery">
                                <i class="fa fa-plus me-2"></i>Add images
                            </button>
                        </div>
                    </div>

                    @if($images->isEmpty())
                        <div class="alert alert-info mb-0">No gallery images yet.</div>
                    @else
                        <div class="row g-3">
                            @foreach($images as $image)
                                <div class="col-md-4 col-xl-3">
                                    @include('content-management.gallery.card', ['image' => $image, 'returnTab' => 'gallery'])
                                </div>
                            @endforeach
                        </div>
                        @if($images->hasPages())
                            <div class="mt-4">{{ $images->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="galleryAddModal" tabindex="-1" aria-labelledby="galleryAddModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('content-management.gallery.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="return_tab" id="galleryAddReturnTab" value="home">
                <div class="modal-header">
                    <h5 class="modal-title" id="galleryAddModalLabel">Add gallery images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="galleryAddImage">Images</label>
                        <input type="file" class="form-control" id="galleryAddImage" name="images[]" accept="image/*" multiple required>
                        <p class="text-muted small mb-0 mt-1">Select one image or several at once. Up to 30 per upload.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="galleryAddCaption">Caption</label>
                        <input type="text" class="form-control" id="galleryAddCaption" name="caption" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="galleryAddSort">Sort order</label>
                        <input type="number" class="form-control" id="galleryAddSort" name="sort_order" min="0" max="9999" value="{{ $nextSort }}">
                        <p class="text-muted small mb-0 mt-1">Starting order. Extra images continue from this number.</p>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="galleryAddShowHome" name="show_on_home" value="1" {{ $homeImages->count() >= 6 ? 'disabled' : '' }}>
                        <label class="form-check-label" for="galleryAddShowHome">Show on the home page (6 maximum)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save images</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="galleryEditModal" tabindex="-1" aria-labelledby="galleryEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="galleryEditForm" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="return_tab" id="galleryEditReturnTab" value="gallery">
                <div class="modal-header">
                    <h5 class="modal-title" id="galleryEditModalLabel">Edit gallery image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img id="galleryEditPreview" src="" alt="" class="rounded mb-3 w-100" style="height:180px;object-fit:cover;">
                    <div class="mb-3">
                        <label class="form-label" for="galleryEditImage">Replace image</label>
                        <input type="file" class="form-control" id="galleryEditImage" name="image" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="galleryEditCaption">Caption</label>
                        <input type="text" class="form-control" id="galleryEditCaption" name="caption" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="galleryEditSort">Sort order</label>
                        <input type="number" class="form-control" id="galleryEditSort" name="sort_order" min="0" max="9999">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="galleryEditShowHome" name="show_on_home" value="1">
                        <label class="form-check-label" for="galleryEditShowHome">Show on the home page (6 maximum)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var homeFull = {{ $homeImages->count() >= 6 ? 'true' : 'false' }};
    var addModal = document.getElementById('galleryAddModal');
    if (addModal) {
        addModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }
            var showHome = trigger.getAttribute('data-show-home') === '1';
            var box = document.getElementById('galleryAddShowHome');
            var returnTab = document.getElementById('galleryAddReturnTab');
            if (box && !box.disabled) {
                box.checked = showHome;
            }
            if (returnTab) {
                returnTab.value = trigger.getAttribute('data-return-tab') || 'gallery';
            }
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.js-edit-gallery');
        if (!trigger) {
            return;
        }
        var form = document.getElementById('galleryEditForm');
        var caption = document.getElementById('galleryEditCaption');
        var sort = document.getElementById('galleryEditSort');
        var returnTab = document.getElementById('galleryEditReturnTab');
        var preview = document.getElementById('galleryEditPreview');
        var box = document.getElementById('galleryEditShowHome');
        var onHome = trigger.getAttribute('data-home') === '1';
        if (form) form.action = trigger.getAttribute('data-update-url') || '';
        if (caption) caption.value = trigger.getAttribute('data-caption') || '';
        if (sort) sort.value = trigger.getAttribute('data-sort') || '';
        if (returnTab) returnTab.value = trigger.getAttribute('data-return-tab') || 'gallery';
        if (preview) preview.src = trigger.getAttribute('data-preview') || '';
        if (box) {
            box.checked = onHome;
            box.disabled = homeFull && !onHome;
        }
    });
});
</script>
@endpush
