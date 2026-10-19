@extends('layouts.adminBase')

@section('content')
<div class="admin-livewire-page d-flex w-100 align-items-stretch">
@include('content-management.includes.sidebar')
<div class="content">
    @include('admin.includes.navbar')

    <div class="container-fluid pt-4 px-4">
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session()->get('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session()->get('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session()->has('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session()->get('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="bg-light rounded h-100 p-4 mb-4">
            <h4 class="mb-1">Dining page</h4>
            <p class="text-muted small mb-4">The public restaurant page uses this title as the banner heading, and the gallery below. Offerings and the closing note are edited with the site text.</p>

            <form action="{{ route('saveResto', $data->id) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label">Page title</label>
                    <input type="text" name="title" class="form-control" id="title" value="{{ old('title', $data->title) }}" maxlength="255">
                </div>
                <button type="submit" class="btn btn-primary">Save title</button>
            </form>
        </div>

        <div class="bg-light rounded h-100 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">Gallery <span class="text-muted">({{ $totalImages }})</span></h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newImage">Add images</button>
            </div>
            <p class="text-muted small">These photos are the three-column gallery on the restaurant page. The caption is the image description.</p>

            @if($images->count() == 0)
                <p class="text-muted mb-0">No images yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white">
                        <thead>
                            <tr>
                                <th style="width:100px;">Preview</th>
                                <th>Caption</th>
                                <th style="width:120px;">Order</th>
                                <th style="width:200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($images as $image)
                            <tr>
                                <td>
                                    <img src="{{ asset('storage/images/restaurant/' . $image->image) }}" alt="" class="rounded" style="width: 88px; height: 56px; object-fit: cover;">
                                </td>
                                <td>
                                    <form action="{{ route('updateRestoImage', $image->id) }}" method="POST" class="d-flex flex-column gap-1">
                                        @csrf
                                        <input type="text" name="caption" value="{{ old('caption', $image->caption) }}" class="form-control form-control-sm" placeholder="Caption" maxlength="500">
                                        <button type="submit" class="btn btn-sm btn-primary align-self-start">Save caption</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="{{ route('reorderRestoImage', $image->id) }}" method="POST" class="d-inline">@csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move up">↑</button>
                                    </form>
                                    <form action="{{ route('reorderRestoImage', $image->id) }}" method="POST" class="d-inline">@csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move down">↓</button>
                                    </form>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#replaceRestoImageModal" data-image-id="{{ $image->id }}">Replace</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('{{ route('deleteRestoImage', $image->id) }}')">Delete</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="newImage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add gallery images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('addRestoImage', ['id' => $data->id]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="restaurant_id" value="{{ $data->id }}">
                    <label for="image" class="form-label">Images</label>
                    <input type="file" name="image[]" class="form-control" id="image" accept="image/*" multiple required>
                    <small class="text-muted">You can upload one or several photos.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">This removes the photo from the restaurant gallery.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="" id="deleteConfirmBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="replaceRestoImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Replace image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="replaceRestoImageForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <label class="form-label">New image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                    <small class="text-muted d-block mt-2">The caption stays as it is until you change it in the table.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmDelete(deleteUrl) {
        document.getElementById('deleteConfirmBtn').setAttribute('href', deleteUrl);
        var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        confirmModal.show();
    }

    document.getElementById('replaceRestoImageModal').addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        var id = btn && btn.getAttribute('data-image-id');
        var form = document.getElementById('replaceRestoImageForm');
        if (form && id) {
            form.action = "{{ url('/updateRestoImage') }}/" + id;
        }
    });
</script>
@endsection
