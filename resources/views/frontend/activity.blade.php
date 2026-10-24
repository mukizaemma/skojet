<div class="public-livewire-page activity-show">
@php
    $coverUrl = filled($activity->cover_image) ? asset('storage/'.$activity->cover_image) : null;
    $selectedActivityIds = array_map('intval', (array) old('activity_ids', [$activity->id]));
    $imageUrl = function (?string $path): ?string {
        if (! filled($path)) {
            return null;
        }
        if (str_contains($path, '/')) {
            return asset('storage/'.$path);
        }

        return asset('storage/images/tour-activities/'.$path);
    };
@endphp

    <header class="activity-show__intro">
        <div class="container">
            <p class="activity-show__eyebrow">{{ site_trans('nav.experiences') }}</p>
            <h1>{{ $activity->title }}</h1>
        </div>
    </header>

    @if($coverUrl)
        <figure class="activity-show__cover">
            <img src="{{ $coverUrl }}" alt="{{ $activity->title }}">
        </figure>
    @endif

    @if(filled(trim(strip_tags($activity->description ?? ''))))
        <section class="activity-show__story">
            <div class="container">
                <div class="activity-show__copy">
                    {!! $activity->description !!}
                </div>
            </div>
        </section>
    @endif

    @if($images->isNotEmpty())
        <section class="activity-show__gallery" aria-label="{{ site_trans('booking.activity_gallery') }}">
            <div class="container">
                <h2>{{ site_trans('booking.activity_gallery') }}</h2>
                <div class="activity-show__grid">
                    @foreach($images as $img)
                        @php $imgUrl = $imageUrl($img->image); @endphp
                        @if($imgUrl)
                            <figure>
                                <img src="{{ $imgUrl }}" alt="{{ $img->caption ?: $activity->title }}" loading="lazy">
                                @if(filled($img->caption))
                                    <figcaption>{{ $img->caption }}</figcaption>
                                @endif
                            </figure>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="activity-show__enquiry" id="activity-enquiry">
        <div class="container">
            <div class="activity-show__form">
                <h2>{{ site_trans('booking.activity_enquiry_title') }}</h2>
                <p>{{ site_trans('booking.activity_enquiry_lead') }}</p>

                @if($errors->any())
                    <div class="activity-show__errors" role="alert">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('enquiry.submit') }}" method="POST" data-no-spa-navigate>
                    @csrf
                    <input type="hidden" name="enquiry_type" value="activity">
                    <input type="hidden" name="activity_id" value="{{ $activity->id }}">

                    <div class="activity-show__fields">
                        <label>
                            <span>{{ site_trans('booking.form_name') }} *</span>
                            <input type="text" name="names" value="{{ old('names') }}" required autocomplete="name">
                        </label>
                        <label>
                            <span>{{ site_trans('booking.form_phone') }} *</span>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">
                        </label>
                        <label class="is-wide">
                            <span>{{ site_trans('booking.form_email') }} *</span>
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        </label>
                        <fieldset class="is-wide">
                            <legend>{{ site_trans('booking.activity_dates') }} *</legend>
                            <div class="activity-show__dates">
                                <label>
                                    <span>{{ site_trans('booking.activity_date_start') }}</span>
                                    <input type="date" name="activity_start" value="{{ old('activity_start') }}" min="{{ now()->toDateString() }}" required>
                                </label>
                                <label>
                                    <span>{{ site_trans('booking.activity_date_end') }}</span>
                                    <input type="date" name="activity_end" value="{{ old('activity_end') }}" min="{{ old('activity_start', now()->toDateString()) }}" required>
                                </label>
                            </div>
                        </fieldset>
                        <fieldset class="is-wide">
                            <legend>{{ site_trans('booking.activity_choices') }} *</legend>
                            <div class="activity-show__choices">
                                @foreach($enquiryActivities as $option)
                                    <label>
                                        <input type="checkbox" name="activity_ids[]" value="{{ $option->id }}" @checked(in_array((int) $option->id, $selectedActivityIds, true))>
                                        <span>{{ $option->title }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="is-wide">
                            <span>{{ site_trans('booking.activity_expectations') }} *</span>
                            <textarea name="expectations" rows="5" required placeholder="{{ site_trans('booking.activity_expectations_hint') }}">{{ old('expectations') }}</textarea>
                        </label>
                    </div>

                    <button type="submit" class="theme-btn btn-style fill">
                        <span>{{ site_trans('booking.activity_submit') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    @if($allActivities->isNotEmpty())
        <section class="activity-show__more">
            <div class="container">
                <h2>{{ site_trans('booking.activity_more') }}</h2>
                <div class="activity-show__more-grid">
                    @foreach($allActivities as $other)
                        <a wire:navigate href="{{ localized_route('activity', ['slug' => $other->slug]) }}">{{ $other->title }}</a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
