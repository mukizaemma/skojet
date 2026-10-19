@php
    $brandLogoFallback = asset('assets/images/brand/skojet-logo-mono.png');
    $footerLogoUrl = filled(trim((string) ($setting?->donate ?? '')))
        ? asset('storage/images') . $setting->donate
        : (filled(trim((string) ($setting?->logo ?? '')))
            ? asset('storage/images') . $setting->logo
            : $brandLogoFallback);

    $ftHotel = \App\Models\HotelContact::first();
    $mainPhone = hotel_public_phone($setting) ?? '';
    $mainEmail = $ftHotel?->email ?? $setting?->email ?? '';

    $ftAddr = '';
    if ($ftHotel) {
        $ftAddr = trim(implode(' ', array_filter([
            $ftHotel->address,
            $ftHotel->city,
            $ftHotel->country,
            $ftHotel->postal_code,
        ])));
    }
    if ($ftAddr === '') {
        $ftAddr = $setting?->address ?? '';
    }

    $ftMapUrl = $ftAddr !== ''
        ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($ftAddr)
        : 'https://maps.app.goo.gl/HHpJhzWDsh4JCiVCA';

    $socialLinks = [];
    $socialRaw = [
        [$ftHotel?->facebook ?? $setting?->facebook, 'fab fa-facebook-f', 'Facebook'],
        [$ftHotel?->twitter ?? $setting?->twitter, 'fab fa-twitter', 'Twitter'],
        [$ftHotel?->instagram ?? $setting?->instagram, 'fab fa-instagram', 'Instagram'],
        [$ftHotel?->linkedin ?? $setting?->linkedin, 'fab fa-linkedin-in', 'LinkedIn'],
        [$setting?->youtube, 'fab fa-youtube', 'YouTube'],
    ];
    foreach ($socialRaw as $row) {
        if (filled(trim((string) ($row[0] ?? '')))) {
            $socialLinks[] = ['url' => trim((string) $row[0]), 'icon' => $row[1], 'label' => $row[2]];
        }
    }

    $footerMainMenuLinks = [
        ['route' => 'home', 'label' => site_trans('nav.home')],
        ['route' => 'rooms', 'label' => site_trans('nav.suites')],
        ['route' => 'activities', 'label' => site_trans('nav.experiences')],
        ['route' => 'dining', 'label' => site_trans('nav.dining')],
        ['route' => 'gallery', 'label' => site_trans('nav.gallery')],
        ['route' => 'contact', 'label' => site_trans('nav.contact')],
    ];
@endphp

<footer class="site-footer site-footer--kibeho site-footer--skojet" aria-label="Site footer">
    <div class="site-footer__accent" aria-hidden="true"></div>

    <div class="container site-footer__body">
        <div class="skojet-footer">
            <div class="skojet-footer__brand">
                <a wire:navigate href="{{ localized_route('home') }}" class="site-footer__logo-link skojet-footer__logo">
                    <img class="site-footer__logo" src="{{ $footerLogoUrl }}" alt="{{ $setting?->company ?? 'Skojet Boutique Hotel' }}" width="180" height="80" loading="lazy" decoding="async">
                </a>

                <p class="skojet-footer__mark">{{ site_trans('footer.tagline') }}</p>
                <h2 class="skojet-footer__title">{{ site_trans('footer.why_title') }}</h2>
                <p class="skojet-footer__line">{{ site_trans('footer.brand_line') }}</p>
                @if(count($socialLinks) > 0)
                    <div class="skojet-footer__social" aria-label="{{ site_trans('footer.follow_us') }}">
                        @foreach($socialLinks as $social)
                            <a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer">
                                <i class="{{ $social['icon'] }}" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

                <div class="skojet-footer__aside">
                    <div class="skojet-footer__panels">
                        <nav class="skojet-footer__nav" aria-label="{{ site_trans('footer.quick_links') }}">
                            <p class="skojet-footer__label">{{ site_trans('footer.quick_links') }}</p>
                            @foreach($footerMainMenuLinks as $item)
                                @if($item['route'] !== 'home')
                                    <a wire:navigate href="{{ localized_route($item['route']) }}">{{ $item['label'] }}</a>
                                @endif
                            @endforeach
                        </nav>

                        <div class="skojet-footer__meta">
                            <p class="skojet-footer__label">{{ site_trans('footer.contact_info') }}</p>
                            <div class="skojet-footer__contacts">
                                @if(filled($mainPhone))
                                    <a href="tel:{{ preg_replace('/\s+/', '', $mainPhone) }}"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>{{ $mainPhone }}</span></a>
                                @endif
                                @if(filled($mainEmail))
                                    <a href="mailto:{{ $mainEmail }}"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span>{{ $mainEmail }}</span></a>
                                @endif
                                @if(filled($ftAddr))
                                    <a href="{{ $ftMapUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>{{ $ftAddr }}</span></a>
                                @endif
                            </div>
                            @include('frontend.includes.language-switcher', ['variant' => 'site-lang-switcher--footer'])
                        </div>
                    </div>

                <div class="skojet-footer__book">
                    @include('frontend.includes.reservation-link', [
                        'style' => 'fill',
                        'class' => 'site-footer__book-btn footer__book-cta',
                        'label' => site_trans('buttons.book_your_stay'),
                    ])
                    <a href="{{ $ftMapUrl }}" class="theme-btn btn-style border skojet-footer__map" target="_blank" rel="noopener noreferrer">
                        <span>{{ site_trans('footer.get_here') }}</span>
                    </a>
                </div>
                </div>
        </div>
    </div>

    <div class="site-footer__copyright">
        <div class="container">
            <div class="site-footer__legal">
            <p class="site-footer__copyright-text mb-0">
                &copy; {{ date('Y') }} {{ $setting?->company ?? 'Skojet Boutique Hotel' }}. {{ site_trans('footer.all_rights') }}
            </p>
            @php
                $creditName = trim((string) ($setting->footer_delivered_by_company ?? ''));
                $creditUrl = trim((string) ($setting->footer_delivered_by_url ?? ''));
                $showCredit = (bool) ($setting?->footer_delivered_by_enabled ?? false) && $creditName !== '';
            @endphp
            @if($showCredit)
                <p class="site-footer__credit mb-0">
                    {{ site_trans('footer.delivered_by') }}
                    @if($creditUrl !== '' && filter_var($creditUrl, FILTER_VALIDATE_URL))
                        <a href="{{ $creditUrl }}" target="_blank" rel="noopener noreferrer">{{ $creditName }}</a>
                    @else
                        {{ $creditName }}
                    @endif
                </p>
            @endif
            </div>
        </div>
    </div>
</footer>
