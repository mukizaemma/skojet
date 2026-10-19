<div class="public-livewire-page">
@php
    $heroImage = '';
    $heroCaption = 'Gallery';
    $heroDescription = 'where every image tells a story of luxury, comfort, and unparalleled hospitality';

    if ($pageHero && !empty($pageHero->background_image)) {
        $heroImage = asset('storage/' . $pageHero->background_image);
        $heroCaption = $pageHero->caption ?? $heroCaption;
        $heroDescription = $pageHero->description ?? $heroDescription;
    } elseif ($about && $about?->image2) {
        if (strpos($about?->image2, '/') !== false || strpos($about?->image2, 'abouts') === 0) {
            $heroImage = asset('storage/' . $about?->image2);
        } else {
            $heroImage = asset('storage/images/about/' . $about?->image2);
        }
    } else {
        $heroImage = asset('storage/images/about/default.jpg');
    }
@endphp
    <div class="rts__section page__hero__height page__hero__bg" style="background-image: url({{ $heroImage }}); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-12">
                    <div class="page__hero__content">
                        <h1 class="wow fadeInUp">{{ $heroCaption }}</h1>
                        <p class="wow fadeInUp font-sm">{{ $heroDescription }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rts__section section__padding gallery-page__section">
        <div class="container">
            <div class="gallery-page__grid" id="galleryImagesRow" role="list">
                @forelse ($galleryImages as $index => $image)
                    <button type="button"
                        class="gallery-page__item gallery-image-trigger"
                        wire:key="gallery-img-{{ $image['key'] }}"
                        data-index="{{ $index }}"
                        aria-label="View image {{ $index + 1 }}">
                        <img src="{{ $image['url'] }}" alt="" loading="lazy" decoding="async">
                        <span class="gallery-page__item-overlay" aria-hidden="true">
                            <span class="gallery-page__item-icon"><i class="fa-solid fa-expand"></i></span>
                        </span>
                    </button>
                @empty
                    <p class="gallery-page__empty mb-0">No images in the gallery yet.</p>
                @endforelse
            </div>

            @if($galleryPaginator->hasPages())
                <nav class="gallery-page__pager" aria-label="Gallery pages">
                    {{ $galleryPaginator->links() }}
                </nav>
            @endif
        </div>
    </div>

    <div id="gallery-images-payload" class="d-none" aria-hidden="true">@json($galleryImages)</div>

    <div id="gallery-lightbox" class="gallery-lightbox" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="Image viewer" wire:ignore>
        <button type="button" class="gallery-lightbox__close" aria-label="Close gallery">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--prev" aria-label="Previous image">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
        <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--next" aria-label="Next image">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        <div class="gallery-lightbox__stage">
            <img class="gallery-lightbox__image" src="" alt="">
        </div>
        <p class="gallery-lightbox__counter" aria-live="polite"></p>
    </div>

    <script>
    (function () {
        var lightbox = document.getElementById('gallery-lightbox');
        if (!lightbox) {
            return;
        }

        var imageEl = lightbox.querySelector('.gallery-lightbox__image');
        var counterEl = lightbox.querySelector('.gallery-lightbox__counter');
        var closeBtn = lightbox.querySelector('.gallery-lightbox__close');
        var prevBtn = lightbox.querySelector('.gallery-lightbox__nav--prev');
        var nextBtn = lightbox.querySelector('.gallery-lightbox__nav--next');
        var stageEl = lightbox.querySelector('.gallery-lightbox__stage');
        var currentIndex = 0;

        function getGalleryImages() {
            var el = document.getElementById('gallery-images-payload');
            if (!el) {
                return [];
            }
            try {
                return JSON.parse(el.textContent || '[]');
            } catch (error) {
                return [];
            }
        }

        function updateLightboxImage() {
            var images = getGalleryImages();
            var item = images[currentIndex];
            if (!item || !imageEl) {
                return;
            }

            imageEl.src = item.url;
            imageEl.alt = '';
            if (counterEl) {
                counterEl.textContent = (currentIndex + 1) + ' / ' + images.length;
            }
            if (prevBtn) {
                prevBtn.style.visibility = images.length > 1 ? 'visible' : 'hidden';
            }
            if (nextBtn) {
                nextBtn.style.visibility = images.length > 1 ? 'visible' : 'hidden';
            }
        }

        function openLightbox(index) {
            var images = getGalleryImages();
            if (!images.length) {
                return;
            }

            currentIndex = ((index % images.length) + images.length) % images.length;
            updateLightboxImage();
            lightbox.hidden = false;
            lightbox.setAttribute('aria-hidden', 'false');
            lightbox.classList.add('is-open');
            document.body.classList.add('gallery-lightbox-open');
            if (closeBtn) {
                closeBtn.focus();
            }
        }

        function closeLightbox() {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('gallery-lightbox-open');
            window.setTimeout(function () {
                if (!lightbox.classList.contains('is-open')) {
                    lightbox.hidden = true;
                    if (imageEl) {
                        imageEl.removeAttribute('src');
                    }
                }
            }, 260);
        }

        function showPrevious() {
            var images = getGalleryImages();
            if (images.length < 2) {
                return;
            }
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            updateLightboxImage();
        }

        function showNext() {
            var images = getGalleryImages();
            if (images.length < 2) {
                return;
            }
            currentIndex = (currentIndex + 1) % images.length;
            updateLightboxImage();
        }

        function onKeydown(event) {
            if (!lightbox.classList.contains('is-open')) {
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeLightbox();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                showPrevious();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                showNext();
            }
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.gallery-image-trigger');
            if (trigger) {
                event.preventDefault();
                var index = parseInt(trigger.getAttribute('data-index'), 10);
                if (!isNaN(index)) {
                    openLightbox(index);
                }
                return;
            }

            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeLightbox();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                showPrevious();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                showNext();
            });
        }

        if (stageEl) {
            stageEl.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        document.addEventListener('keydown', onKeydown);
    })();
    </script>
</div>
