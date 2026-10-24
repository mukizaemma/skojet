<div class="public-livewire-page terms-page">

@include('frontend.includes.page-hero-banner', [
    'defaultCaption' => site_trans('nav.terms'),
    'defaultDescription' => site_trans('legal.lead'),
])

<section class="rts__section section__padding pt-50 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                @php
                    $storedTerms = (string) ($terms->content ?? '');
                    $termsHtml = \App\Support\HotelTerms::isStale($storedTerms)
                        ? \App\Support\HotelTerms::html()
                        : terms_content_html($storedTerms);
                @endphp
                <div class="terms-page__body content-richtext">
                    {!! $termsHtml !!}
                </div>
            </div>
        </div>
    </div>
</section>

</div>
