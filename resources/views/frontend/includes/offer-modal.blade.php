@php
    $todayOffers = hotel_offers_today();
@endphp
@if($todayOffers->isNotEmpty())
    <div class="modal fade skojet-offer-modal" id="skojetOfferModal" tabindex="-1" aria-labelledby="skojetOfferModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="skojet-offer-modal__x" data-bs-dismiss="modal" aria-label="{{ site_trans('offers.close') }}">&times;</button>
                <p class="skojet-offer-modal__kicker">{{ site_trans('offers.modal_kicker') }}</p>
                <h2 id="skojetOfferModalTitle">{{ site_trans('offers.modal_title') }}</h2>
                <p class="skojet-offer-modal__lead">{{ site_trans('offers.modal_lead') }}</p>
                <ul class="skojet-offer-modal__list">
                    @foreach($todayOffers as $offer)
                        <li>
                            <strong>{{ $offer->title }}</strong>
                            <span>{{ hotel_offer_badge($offer, $setting ?? null) }}</span>
                            <span>{{ $offer->room?->title ?: site_trans('offers.all_suites') }}</span>
                            <span>{{ site_trans('offers.until', ['date' => $offer->ends_on?->format('M j, Y')]) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="skojet-offer-modal__actions">
                    @include('frontend.includes.reservation-link', ['style' => 'fill', 'class' => 'skojet-offer-modal__book'])
                    <button type="button" class="skojet-offer-modal__dismiss" data-bs-dismiss="modal">{{ site_trans('offers.close') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var KEY = 'skojet_discount_modal_seen';

            function storage() {
                try {
                    var probe = '__skojet_offer_probe';
                    window.localStorage.setItem(probe, '1');
                    window.localStorage.removeItem(probe);
                    return window.localStorage;
                } catch (e) {
                    try { return window.sessionStorage; } catch (err) { return null; }
                }
            }

            function seen() {
                var store = storage();
                return !store || store.getItem(KEY) === '1';
            }

            function markSeen() {
                var store = storage();
                if (store) store.setItem(KEY, '1');
            }

            function forceUnlock(el) {
                el.classList.remove('show');
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
                el.removeAttribute('aria-modal');
                if (document.querySelector('.modal.show')) return;
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
                document.querySelectorAll('.modal-backdrop').forEach(function (node) { node.remove(); });
            }

            function bind(el) {
                if (el.dataset.offerBound === '1') return;
                el.dataset.offerBound = '1';
                var closing = false;

                function dismiss() {
                    markSeen();
                    if (!el.classList.contains('show') && el.getAttribute('aria-hidden') === 'true' && el.style.display === 'none') {
                        return;
                    }
                    if (closing) return;
                    closing = true;
                    var ModalApi = window.bootstrap && window.bootstrap.Modal;
                    if (!ModalApi) {
                        forceUnlock(el);
                        closing = false;
                        return;
                    }
                    var instance = ModalApi.getOrCreateInstance(el, { backdrop: true, keyboard: true, focus: true });
                    var finished = false;
                    function done() {
                        if (finished) return;
                        finished = true;
                        closing = false;
                        if (!document.querySelector('.modal.show')) {
                            document.body.classList.remove('modal-open');
                            document.body.style.removeProperty('padding-right');
                            document.body.style.removeProperty('overflow');
                            document.querySelectorAll('.modal-backdrop').forEach(function (node) { node.remove(); });
                        }
                    }
                    el.addEventListener('hidden.bs.modal', done, { once: true });
                    instance.hide();
                    window.setTimeout(function () {
                        if (el.classList.contains('show')) forceUnlock(el);
                        done();
                    }, 500);
                }

                el.addEventListener('click', function (event) {
                    if (event.target.closest('[data-bs-dismiss="modal"]')) {
                        event.preventDefault();
                        dismiss();
                        return;
                    }
                    if (event.target === el) {
                        dismiss();
                        return;
                    }
                    var link = event.target.closest('a[href]');
                    if (link && el.contains(link)) {
                        markSeen();
                        forceUnlock(el);
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape' || !el.classList.contains('show')) return;
                    event.preventDefault();
                    dismiss();
                });

                el.addEventListener('shown.bs.modal', function () {
                    document.querySelectorAll('.modal-backdrop').forEach(function (node) {
                        node.classList.add('skojet-offer-backdrop');
                    });
                    var closeBtn = el.querySelector('.skojet-offer-modal__x');
                    if (closeBtn) closeBtn.focus();
                });

                window.addEventListener('pagehide', function () {
                    if (el.classList.contains('show')) {
                        markSeen();
                        forceUnlock(el);
                    }
                });

                var attempts = 0;
                function openOnce() {
                    if (!el.isConnected || seen() || el.classList.contains('show')) return;
                    var ModalApi = window.bootstrap && window.bootstrap.Modal;
                    if (!ModalApi) {
                        if (attempts < 10) {
                            attempts += 1;
                            window.setTimeout(openOnce, 200);
                        }
                        return;
                    }
                    markSeen();
                    var instance = ModalApi.getOrCreateInstance(el, { backdrop: true, keyboard: true, focus: true });
                    instance.show();
                }

                var delay = document.documentElement.classList.contains('site-seen') ? 300 : 700;
                window.setTimeout(function () {
                    if (el.isConnected) openOnce();
                }, delay);
            }

            function boot() {
                var el = document.getElementById('skojetOfferModal');
                if (!el) return;
                bind(el);
            }

            if (!window.__skojetOfferModalBoot) {
                window.__skojetOfferModalBoot = true;
                document.addEventListener('livewire:navigating', function () {
                    var open = document.getElementById('skojetOfferModal');
                    if (!open || !open.classList.contains('show')) return;
                    markSeen();
                    forceUnlock(open);
                });
                document.addEventListener('livewire:navigated', boot);
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            } else {
                boot();
            }
        })();
    </script>
@endif
