<div class="public-livewire-page dining-reserve">
    <section class="section__padding">
        <div class="container">
            @if($submitted)
                <div class="dining-reserve__success">
                    <h1>{{ site_trans('dining.reserve_success_title') }}</h1>
                    <p>{{ site_trans('dining.reserve_success_lead') }}</p>
                    @if($guestEmailSent)
                        <p>{{ site_trans('dining.reserve_success_email', ['email' => $email]) }}</p>
                    @else
                        <p>{{ site_trans('dining.reserve_success_saved') }}</p>
                    @endif
                    @if($whatsappUrl)
                        <p><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">{{ site_trans('dining.reserve_open_whatsapp') }}</a></p>
                    @endif
                </div>
                @if($submittedReservation)
                    @if($submittedReservation->access_token)
                        <p class="dining-reserve__tools">
                            <a class="theme-btn btn-style fill" target="_blank" rel="noopener" href="{{ localized_route('dining.pass', ['token' => $submittedReservation->access_token]) }}?print=1"><span>{{ site_trans('dining.bill_print') }}</span></a>
                            <a class="theme-btn btn-style" href="{{ localized_route('dining.pass.pdf', ['token' => $submittedReservation->access_token]) }}"><span>{{ site_trans('dining.bill_pdf') }}</span></a>
                        </p>
                    @endif
                    <div class="dining-reserve__bill">
                        @include('restaurant.bill-document', ['reservation' => $submittedReservation])
                    </div>
                @endif
            @else
                @include('frontend.includes.reserve-choice', ['mode' => 'restaurant'])

                <header class="skojet-intro">
                    <span class="skojet-intro__eyebrow">{{ site_trans('dining.reserve_kicker') }}</span>
                    <h1 class="skojet-intro__title">{{ site_trans('dining.reserve_title') }}</h1>
                    <p class="skojet-intro__lead">{{ $restaurant->requiresReservation() ? site_trans('dining.reserve_required') : site_trans('dining.reserve_optional') }}</p>
                    <p class="skojet-intro__lead">{{ site_trans('dining.reserve_lead') }}</p>
                </header>

                @if($menu === [])
                    <p class="dining-reserve__empty">{{ site_trans('dining.reserve_no_menu') }}</p>
                    <a wire:navigate href="{{ localized_route('connect') }}" class="theme-btn btn-style fill"><span>{{ site_trans('dining.cta_room') }}</span></a>
                @else
                    <div class="dining-reserve__layout">
                        <div class="dining-reserve__menu">
                            <div class="dining-reserve__tiers" role="radiogroup" aria-label="{{ site_trans('dining.reserve_price_note') }}">
                                <button type="button" wire:click="setTier('international')" class="{{ $price_tier === 'international' ? 'is-current' : '' }}">{{ site_trans('dining.reserve_international') }}</button>
                                <button type="button" wire:click="setTier('local')" class="{{ $price_tier === 'local' ? 'is-current' : '' }}">{{ site_trans('dining.reserve_local') }}</button>
                            </div>
                            <p class="dining-reserve__note">{{ site_trans('dining.reserve_price_note') }}</p>

                            @foreach($menu as $category => $items)
                                <section class="dining-menu" aria-labelledby="reserve-{{ $category }}">
                                    <h2 id="reserve-{{ $category }}">{{ site_trans('dining.menu_'.$category) }}</h2>
                                    <ul>
                                        @foreach($items as $item)
                                            @php $qty = (int) ($quantities[$item->id] ?? 0); @endphp
                                            <li>
                                                <div>
                                                    <strong>{{ $item->name }}</strong>
                                                    @if(filled($item->description))
                                                        <span>{{ $item->description }}</span>
                                                    @endif
                                                </div>
                                                <div class="dining-menu__buy">
                                                    <em>{{ restaurant_money($item->priceFor($price_tier), $price_tier) }}</em>
                                                    @if($qty > 0)
                                                        <div class="dining-menu__qty">
                                                            <button type="button" wire:click="removeItem({{ $item->id }})" aria-label="{{ site_trans('dining.reserve_less', ['name' => $item->name]) }}">−</button>
                                                            <span>{{ $qty }}</span>
                                                            <button type="button" wire:click="addItem({{ $item->id }})" aria-label="{{ site_trans('dining.reserve_more', ['name' => $item->name]) }}">+</button>
                                                        </div>
                                                    @else
                                                        <button type="button" class="dining-menu__add" wire:click="addItem({{ $item->id }})">{{ site_trans('dining.reserve_add') }}</button>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>

                        <form class="dining-reserve__card" wire:submit.prevent="submit('email')">
                            <h2>{{ site_trans('dining.reserve_your_order') }}</h2>
                            @if($orderLines === [])
                                <p class="dining-reserve__note">{{ site_trans('dining.reserve_empty_order') }}</p>
                            @else
                                <ul class="dining-reserve__lines">
                                    @foreach($orderLines as $line)
                                        <li>
                                            <span>{{ $line['quantity'] }} × {{ $line['name'] }}</span>
                                            <strong>{{ restaurant_money($line['line'], $price_tier) }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <dl class="dining-reserve__totals">
                                <div>
                                    <dt>{{ site_trans('dining.reserve_minimum') }}</dt>
                                    <dd>{{ restaurant_money($minimum, $price_tier) }}</dd>
                                </div>
                                <div>
                                    <dt>{{ site_trans('dining.reserve_total') }}</dt>
                                    <dd>{{ restaurant_money($orderTotal, $price_tier) }}</dd>
                                </div>
                            </dl>

                            @if($shortfall >= 0.01)
                                <p class="dining-reserve__short">{{ site_trans('dining.reserve_short', ['amount' => restaurant_money($shortfall, $price_tier)]) }}</p>
                            @elseif($orderLines !== [])
                                <p class="dining-reserve__ready">{{ site_trans('dining.reserve_ready') }}</p>
                            @endif
                            @error('order') <p class="dining-reserve__short">{{ $message }}</p> @enderror

                            <label>
                                <span>{{ site_trans('dining.reserve_name') }}</span>
                                <input type="text" wire:model.live.debounce.400ms="names" autocomplete="name" required>
                            </label>
                            @error('names') <small>{{ $message }}</small> @enderror
                            <label>
                                <span>{{ site_trans('dining.reserve_phone') }}</span>
                                <input type="tel" wire:model.live.debounce.400ms="phone" autocomplete="tel" required>
                            </label>
                            @error('phone') <small>{{ $message }}</small> @enderror
                            <label>
                                <span>{{ site_trans('dining.reserve_email') }}</span>
                                <input type="email" wire:model.live.debounce.400ms="email" autocomplete="email" required>
                            </label>
                            @error('email') <small>{{ $message }}</small> @enderror
                            <div class="dining-reserve__when">
                                <label>
                                    <span>{{ site_trans('dining.reserve_date') }}</span>
                                    <input type="date" wire:model.live="reserved_date" min="{{ now()->toDateString() }}" required>
                                </label>
                                <label>
                                    <span>{{ site_trans('dining.reserve_time') }}</span>
                                    <input type="time" wire:model.live="reserved_time" required>
                                </label>
                                <label>
                                    <span>{{ site_trans('dining.reserve_guests') }}</span>
                                    <input type="number" wire:model.live="guests" min="1" max="40" required>
                                </label>
                            </div>
                            @error('reserved_date') <small>{{ $message }}</small> @enderror
                            @error('reserved_time') <small>{{ $message }}</small> @enderror
                            @error('guests') <small>{{ $message }}</small> @enderror
                            <label>
                                <span>{{ site_trans('dining.reserve_notes') }}</span>
                                <textarea wire:model="notes" rows="3" maxlength="1000"></textarea>
                            </label>

                            <div class="dining-reserve__pay">
                                <p>{{ site_trans('dining.momo_title') }}</p>
                                @if($restaurant->momoCode() !== '')
                                    <strong class="dining-reserve__momo">{{ $restaurant->momoCode() }}</strong>
                                    @if($restaurant->momoAccountName() !== '')
                                        <span class="dining-reserve__note">{{ $restaurant->momoAccountName() }}</span>
                                    @endif
                                    <p class="dining-reserve__note">{{ site_trans('dining.momo_pay') }}</p>
                                    <div class="dining-reserve__tiers">
                                        <button type="button" wire:click="setPayment('minimum')" class="{{ $payment_choice === 'minimum' ? 'is-current' : '' }}">
                                            {{ site_trans('dining.pay_minimum') }} · {{ restaurant_money($minimum, $price_tier) }}
                                        </button>
                                        <button type="button" wire:click="setPayment('full')" class="{{ $payment_choice === 'full' ? 'is-current' : '' }}">
                                            {{ site_trans('dining.pay_full') }} · {{ restaurant_money($orderTotal, $price_tier) }}
                                        </button>
                                    </div>
                                    <label class="dining-reserve__confirm">
                                        <input type="checkbox" wire:model.live="payment_confirmed">
                                        <span>{{ site_trans('dining.pay_confirm') }}</span>
                                    </label>
                                    @error('payment_confirmed') <small>{{ $message }}</small> @enderror
                                    <label>
                                        <span>{{ site_trans('dining.pay_name') }}</span>
                                        <input type="text" wire:model.live.debounce.400ms="payer_name" autocomplete="name">
                                    </label>
                                    @error('payer_name') <small>{{ $message }}</small> @enderror
                                @else
                                    <p class="dining-reserve__short">{{ site_trans('dining.momo_missing') }}</p>
                                @endif
                            </div>

                            <div class="dining-reserve__send">
                                <button type="submit" class="theme-btn btn-style fill" wire:loading.attr="disabled" @disabled(! $canSend)>
                                    <span>{{ site_trans('dining.reserve_send_email') }}</span>
                                </button>
                                <button type="button" class="theme-btn btn-style border" wire:click="submit('whatsapp')" wire:loading.attr="disabled" @disabled(! $canSend || ! $whatsappReady)>
                                    <span>{{ site_trans('dining.reserve_send_whatsapp') }}</span>
                                </button>
                            </div>
                            @if(! $whatsappReady)
                                <p class="dining-reserve__note">{{ site_trans('dining.reserve_whatsapp_unavailable') }}</p>
                            @endif
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </section>
</div>
