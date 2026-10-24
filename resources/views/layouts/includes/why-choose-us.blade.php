@if(isset($whyChooseUsItems) && $whyChooseUsItems->isNotEmpty())
@php
    $wcuLayout = $whyChooseUsLayout ?? 'full';
    $wcuSlide = \App\Models\Slide::query()
        ->whereNotNull('image')
        ->where('image', '!=', '')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->first();
    $wcuBgUrl = $wcuSlide
        ? asset('storage/' . $wcuSlide->image)
        : asset('storage/images/about/default.jpg');
    if ($about && filled($about->image2)) {
        if (strpos($about->image2, '/') !== false || strpos($about->image2, 'abouts') === 0) {
            $wcuBgUrl = asset('storage/' . $about->image2);
        } else {
            $wcuBgUrl = asset('storage/images/about/' . $about->image2);
        }
    }
    $wcuCardColClass = $wcuLayout === 'meetings' ? 'col-6' : 'col-12 col-sm-6 col-xl-3';
@endphp
@if($wcuLayout === 'meetings')
<div class="site-why-choose-parallax-wrap site-why-choose-parallax-wrap--meetings">
@else
<div
    class="site-why-choose-parallax-wrap jarallax"
    data-speed="0.5"
    style="--wcu-bg-fallback: url('{{ $wcuBgUrl }}')"
>
    <img class="jarallax-img" src="{{ $wcuBgUrl }}" alt="" loading="lazy" decoding="async" width="1920" height="1080">
    <div class="site-why-choose__veil" aria-hidden="true"></div>
@endif

    <section class="site-why-choose {{ $wcuLayout === 'meetings' ? 'site-why-choose--meetings' : '' }} rts__section {{ $wcuLayout === 'meetings' ? 'py-4 py-lg-4' : 'section__padding' }}" aria-labelledby="site-why-choose-heading">
        <div class="{{ $wcuLayout === 'meetings' ? 'w-100 position-relative' : 'container position-relative' }}">
            @if($wcuLayout === 'meetings')
                <div class="row text-center text-lg-start mb-3 mb-lg-4">
                    <div class="col-12">
                        <h2 id="site-why-choose-heading" class="site-why-choose__heading section__title mb-2">{{ site_trans('home.why_choose_title') }}</h2>
                        <p class="site-why-choose__lead font-sm mb-0 site-why-choose__lead--meetings">
                            {{ site_trans('home.why_choose_lead') }}
                        </p>
                    </div>
                </div>
            @else
                <div class="row justify-content-center text-center mb-50 mb-lg-60">
                    <div class="col-lg-8">
                        <h2 id="site-why-choose-heading" class="site-why-choose__heading section__title">{{ site_trans('home.why_choose_title') }}</h2>
                        <p class="site-why-choose__lead font-sm mb-0">
                            {{ site_trans('home.why_choose_lead') }}
                        </p>
                    </div>
                </div>
            @endif
            <div class="row g-4 g-xl-4 justify-content-center align-items-stretch site-why-choose__grid {{ $wcuLayout === 'meetings' ? 'justify-content-start' : '' }}">
                @foreach($whyChooseUsItems as $item)
                @php
                    $wcuTitle = $item->title;
                    $wcuDescription = $item->description;
                    $wcuIcon = filled($item->icon) ? $item->icon : 'fa-solid fa-circle-dot';
                    $legacyTransfer = in_array(mb_strtolower(trim((string) $item->title)), [
                        'private transfers',
                        'private transfer',
                        'transferts privés',
                        'transfert privé',
                    ], true);
                    if ($legacyTransfer) {
                        $wcuTitle = site_trans('home.stat_rates');
                        $wcuDescription = app()->getLocale() === 'fr'
                            ? 'Des tarifs clairs pour chaque suite et chaque formule repas, avec les offres lorsqu’elles sont actives.'
                            : 'Clear nightly rates for each suite and meal plan, with offers shown when they are active.';
                        $wcuIcon = 'fa-solid fa-tag';
                    }
                    if (! str_contains($wcuIcon, ' ')) {
                        $wcuIcon = 'fa-solid '.$wcuIcon;
                    }
                @endphp
                <div class="{{ $wcuCardColClass }} d-flex">
                    <article
                        class="site-why-choose__card h-100 w-100 {{ $wcuLayout === 'meetings' ? 'site-why-choose__card--meetings' : '' }}"
                        tabindex="0"
                        @if(filled($wcuDescription))
                            aria-label="{{ $wcuTitle }}. {{ Str::limit(strip_tags($wcuDescription), 200) }}"
                        @else
                            aria-label="{{ $wcuTitle }}"
                        @endif
                    >
                        <div class="site-why-choose__peek">
                            <div class="site-why-choose__icon-wrap" aria-hidden="true">
                                <span class="site-why-choose__icon-ring"></span>
                                <i class="{{ $wcuIcon }} site-why-choose__icon"></i>
                            </div>
                            <h3 class="site-why-choose__title">{{ $wcuTitle }}</h3>
                        </div>
                        @if(filled($wcuDescription))
                        <div class="site-why-choose__reveal" role="region" aria-label="Details: {{ $wcuTitle }}">
                            <div class="site-why-choose__reveal-inner">
                                {!! nl2br(e($wcuDescription)) !!}
                            </div>
                        </div>
                        @endif
                    </article>
                </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endif
