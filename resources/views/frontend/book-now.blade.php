<div class="public-livewire-page book-now-page">
    @php
        $setting = $setting ?? \App\Models\Setting::first();
        $guestCount = (int) $adults + (int) $children;
    @endphp

    <section class="book-channel section__padding">
        <div class="container">
            @if($submitted)
                <div class="book-channel__success text-center">
                    <div class="book-channel__success-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
                    <h1>{{ site_trans('booking.wizard_success_title') }}</h1>
                    <p>{{ site_trans('booking.wizard_success_lead') }}</p>
                    @if($submittedBookingId)
                        <p class="book-channel__ref">{{ site_trans('booking.wizard_success_ref', ['id' => $submittedBookingId]) }}</p>
                    @endif
                    @if($guestEmailSent)
                        <p>A confirmation email was sent to <strong>{{ $email }}</strong>.</p>
                    @else
                        <p>We saved your request. If you do not receive an email shortly, contact us and quote your reference.</p>
                    @endif
                    @if($whatsappUrl)
                        <p><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Open WhatsApp with this reservation</a></p>
                    @endif
                    <a wire:navigate href="{{ localized_route('home') }}" class="theme-btn btn-style fill">
                        <span>{{ site_trans('nav.home') }}</span>
                    </a>
                </div>
            @elseif($step === 2 && $selectedRoom)
                @php $mealRequired = $selectedRoom->publicRatePlans(hotel_currency($setting)) !== []; @endphp
                <div
                    class="book-channel__checkout"
                    x-data="{
                        first: @entangle('first_name').live,
                        last: @entangle('last_name').live,
                        phone: @entangle('phone').live,
                        email: @entangle('email').live,
                        country: @entangle('country').live,
                        terms: @entangle('agree_terms').live,
                        board: @entangle('board').live,
                        mealRequired: @js($mealRequired),
                        copy: @js([
                            'emailTitle' => site_trans('booking.channel_confirm_email_title'),
                            'emailText' => site_trans('booking.channel_confirm_email_text'),
                            'whatsappTitle' => site_trans('booking.channel_confirm_whatsapp_title'),
                            'whatsappText' => site_trans('booking.channel_confirm_whatsapp_text'),
                            'confirm' => site_trans('booking.channel_confirm'),
                            'cancel' => site_trans('booking.channel_cancel'),
                        ]),
                        get ready() {
                            const contactReady = [this.first, this.last, this.phone, this.email, this.country].every((value) => String(value || '').trim() !== '') && !!this.terms;
                            const mealReady = !this.mealRequired || String(this.board || '').trim() !== '';
                            return contactReady && mealReady;
                        },
                        ask(via) {
                            if (!this.ready || typeof Swal === 'undefined') {
                                return;
                            }
                            const whatsapp = via === 'whatsapp';
                            Swal.fire({
                                title: whatsapp ? this.copy.whatsappTitle : this.copy.emailTitle,
                                text: whatsapp ? this.copy.whatsappText : this.copy.emailText,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: this.copy.confirm,
                                cancelButtonText: this.copy.cancel,
                                confirmButtonColor: '#111111',
                                cancelButtonColor: '#8a847a',
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    this.$wire.submitReservation(via);
                                }
                            });
                        },
                    }"
                >
                    <button type="button" class="book-channel__back" wire:click="backToRooms">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ site_trans('booking.channel_back_suites') }}
                    </button>
                    <h1>{{ site_trans('booking.channel_checkout') }}</h1>

                    <div class="book-channel__checkout-grid">
                        <div class="book-channel__panel">
                            <h2>{{ site_trans('booking.channel_contact') }}</h2>
                            <p class="book-channel__note">{{ site_trans('booking.channel_email_note') }}</p>

                            <div class="book-channel__fields">
                                <label>
                                    <span>{{ site_trans('booking.channel_first_name') }} *</span>
                                    <input type="text" x-model="first" autocomplete="given-name">
                                    @error('first_name') <small>{{ $message }}</small> @enderror
                                </label>
                                <label>
                                    <span>{{ site_trans('booking.channel_last_name') }} *</span>
                                    <input type="text" x-model="last" autocomplete="family-name">
                                    @error('last_name') <small>{{ $message }}</small> @enderror
                                </label>
                                <label>
                                    <span>{{ site_trans('booking.form_phone') }} *</span>
                                    <input type="tel" x-model="phone" autocomplete="tel">
                                    @error('phone') <small>{{ $message }}</small> @enderror
                                </label>
                                <label>
                                    <span>{{ site_trans('booking.form_email') }} *</span>
                                    <input type="email" x-model="email" autocomplete="email">
                                    @error('email') <small>{{ $message }}</small> @enderror
                                </label>
                                <div class="book-channel__address-row">
                                    <label>
                                        <span>{{ site_trans('booking.form_country') }} *</span>
                                        <input type="text" x-model="country" autocomplete="country-name">
                                        @error('country') <small>{{ $message }}</small> @enderror
                                    </label>
                                    <label>
                                        <span>{{ site_trans('booking.channel_address') }}</span>
                                        <input type="text" wire:model="address" autocomplete="street-address">
                                    </label>
                                    <label>
                                        <span>{{ site_trans('booking.channel_postal') }}</span>
                                        <input type="text" wire:model="postal_code" autocomplete="postal-code">
                                    </label>
                                </div>
                                @php
                                    $mealPlans = $selectedRoom->publicRatePlans(hotel_currency($setting));
                                @endphp
                                @if($mealPlans !== [])
                                    <div class="book-channel__room is-wide">
                                        <p class="book-channel__meal-heading">{{ site_trans('booking.channel_your_room') }}</p>
                                        <p class="book-channel__room-name">{{ $selectedRoom->title }}</p>
                                    </div>
                                    <div class="book-channel__meals is-wide" role="radiogroup" aria-label="{{ site_trans('booking.channel_meal_plan') }}">
                                        <p class="book-channel__meal-heading">{{ site_trans('booking.channel_meal_plan') }}</p>
                                        @foreach($mealPlans as $plan)
                                            <article class="book-channel__meal {{ $board === $plan['key'] ? 'is-selected' : '' }}">
                                                <strong>{{ site_trans('room.board_'.$plan['key']) }}</strong>
                                                <span>{{ site_trans('room.board_note_'.$plan['key']) }}</span>
                                                <div class="book-channel__meal-occs">
                                                    @foreach(['single', 'double'] as $occ)
                                                        @continue($plan[$occ.'_sale'] === null)
                                                        @php
                                                            $occQuote = app(\App\Services\RoomDiscountService::class)->quote(
                                                                $selectedRoom,
                                                                (string) $checkin,
                                                                (string) $checkout,
                                                                (int) $adults,
                                                                (int) $children,
                                                                max(1, (int) $room_count),
                                                                0,
                                                                $plan['key'],
                                                                $occ
                                                            );
                                                            $occList = (float) $occQuote['nightly'];
                                                            $occSale = (float) (($occQuote['has_discount'] ?? false) ? $occQuote['sale_nightly'] : $occList);
                                                            $occSelected = $board === $plan['key'] && $occupancy === $occ;
                                                        @endphp
                                                        <button
                                                            type="button"
                                                            class="{{ $occSelected ? 'is-selected' : '' }}"
                                                            role="radio"
                                                            aria-checked="{{ $occSelected ? 'true' : 'false' }}"
                                                            wire:click="selectMealPlan('{{ $plan['key'] }}', '{{ $occ }}')"
                                                        >
                                                            <span>{{ site_trans('room.occ_'.$occ) }}</span>
                                                            <strong>
                                                                @if(($occQuote['has_discount'] ?? false) && $occSale + 0.5 < $occList)
                                                                    <s>{{ hotel_price($occList, $setting) }}</s>
                                                                @endif
                                                                {{ hotel_price($occSale, $setting) }}
                                                            </strong>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                    @error('board') <small class="book-channel__error">{{ $message }}</small> @enderror
                                @endif
                                <label class="is-wide">
                                    <span>{{ site_trans('booking.channel_requests') }}</span>
                                    <textarea wire:model="message" rows="2"></textarea>
                                </label>
                            </div>

                            <p class="book-channel__policy-line">{{ site_trans('booking.channel_policy') }}</p>

                            <label class="book-channel__terms">
                                <input type="checkbox" x-model="terms">
                                <span>{{ site_trans('booking.channel_terms') }} *</span>
                            </label>
                            @error('agree_terms') <small class="book-channel__error">{{ $message }}</small> @enderror
                            @error('room_id') <small class="book-channel__error">{{ $message }}</small> @enderror
                            @error('room_count') <small class="book-channel__error">{{ $message }}</small> @enderror

                            <div class="book-channel__submit">
                                <button type="button" class="theme-btn btn-style fill" :disabled="!ready" @click="ask('email')" wire:loading.attr="disabled" wire:target="submitReservation">
                                    <span><i class="fa-solid fa-envelope" aria-hidden="true"></i> {{ site_trans('booking.channel_send_email') }}</span>
                                </button>
                                @if($hasWhatsapp)
                                    <button type="button" class="theme-btn btn-style border book-channel__whatsapp" :disabled="!ready" @click="ask('whatsapp')" wire:loading.attr="disabled" wire:target="submitReservation">
                                        <span><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> {{ site_trans('booking.channel_send_whatsapp') }}</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <aside class="book-channel__panel book-channel__price">
                            <h2>{{ site_trans('booking.channel_price') }}</h2>
                            <p class="book-channel__suite">{{ $selectedRoom->title }}</p>
                            @if(isset(\App\Models\Room::BOARD_PLANS[$board]) && in_array($occupancy, ['single', 'double'], true))
                                <p class="book-channel__meta">{{ site_trans('room.board_'.$board) }} · {{ site_trans('room.occ_'.$occupancy) }}</p>
                            @endif
                            <div class="book-channel__stay-dates">
                                <label>
                                    <span>{{ site_trans('booking.form_checkin') }}</span>
                                    <input type="date" wire:model.live="checkin" min="{{ now()->toDateString() }}" required>
                                </label>
                                <label>
                                    <span>{{ site_trans('booking.form_checkout') }}</span>
                                    <input type="date" wire:model.live="checkout" min="{{ $checkin }}" required>
                                </label>
                            </div>
                            @error('checkin') <small class="book-channel__error">{{ $message }}</small> @enderror
                            @error('checkout') <small class="book-channel__error">{{ $message }}</small> @enderror
                            @if($datesReady && (int) $roomsAvailable < (int) $room_count)
                                <small class="book-channel__error">{{ site_trans('booking.wizard_rooms_dates_inventory', ['max' => $roomsAvailable, 'inventory' => $roomInventory]) }}</small>
                            @endif
                            <p class="book-channel__meta">{{ $nights }} {{ $nights === 1 ? 'night' : 'nights' }} · {{ site_trans('booking.channel_guests_line', ['count' => $guestCount]) }}</p>

                            @if($mealRequired && ! isset(\App\Models\Room::BOARD_PLANS[$board]))
                                <p class="book-channel__meta">{{ site_trans('booking.channel_meal_required') }}</p>
                            @else
                            <dl>
                                <div>
                                    <dt>{{ isset(\App\Models\Room::BOARD_PLANS[$board]) ? site_trans('room.board_'.$board) : site_trans('booking.channel_standard') }}</dt>
                                    <dd>{{ hotel_price($selectedQuote['list_total'] ?? 0, $setting) }}</dd>
                                </div>
                                @if(!empty($selectedQuote['has_discount']))
                                    <div>
                                        <dt>{{ site_trans('booking.channel_discount') }}@if(filled($selectedQuote['label'] ?? '')) ({{ $selectedQuote['label'] }})@endif</dt>
                                        <dd>-{{ hotel_price($selectedQuote['discount_total'] ?? 0, $setting) }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt>{{ site_trans('booking.channel_taxes') }}</dt>
                                    <dd>{{ hotel_price(0, $setting) }}</dd>
                                </div>
                            </dl>
                            <div class="book-channel__total">
                                <span>{{ site_trans('booking.channel_total') }}</span>
                                <strong>{{ hotel_price($selectedQuote['total'] ?? $estimatedTotal, $setting) }}</strong>
                            </div>
                            @endif
                        </aside>
                    </div>
                </div>
            @else
                <div class="book-channel__pick">
                <div
                    class="book-channel__bar"
                    x-data="bookingStayBar(@js($checkin), @js($checkout))"
                    @click.outside="open = null"
                    @keydown.escape.window="open = null"
                >
                    <button type="button" class="book-channel__field" :class="{ 'is-open': open === 'guests' }" @click="open = open === 'guests' ? null : 'guests'">
                        <span>{{ site_trans('booking.channel_guests') }}</span>
                        <strong>{{ $guestCount }} {{ $guestCount === 1 ? site_trans('booking.channel_guest') : site_trans('booking.channel_guests_word') }}, {{ (int) $room_count }} {{ (int) $room_count === 1 ? site_trans('booking.channel_room') : site_trans('booking.channel_rooms') }}</strong>
                    </button>
                    <button type="button" class="book-channel__field" :class="{ 'is-open': open === 'dates' }" @click="openCalendar()">
                        <span>{{ site_trans('booking.form_checkin') }}</span>
                        <strong>{{ \Carbon\Carbon::parse($checkin)->format('D, M j, Y') }}</strong>
                    </button>
                    <button type="button" class="book-channel__field" :class="{ 'is-open': open === 'dates' }" @click="openCalendar()">
                        <span>{{ site_trans('booking.form_checkout') }}</span>
                        <strong>{{ \Carbon\Carbon::parse($checkout)->format('D, M j, Y') }}</strong>
                    </button>

                    <div class="book-channel__bar-foot">
                        <p>
                            <strong>{{ (int) $nights === 1 ? site_trans('booking.channel_nights_one', ['count' => $nights]) : site_trans('booking.channel_nights_many', ['count' => $nights]) }}</strong>
                            <span>·</span>
                            @if(!empty($selectedQuote['has_discount']))
                                <s>{{ hotel_price($selectedQuote['list_total'] ?? 0, $setting) }}</s>
                            @endif
                            <strong>{{ hotel_price($selectedQuote['total'] ?? 0, $setting) }}</strong>
                            @if($selectedRoom)
                                <span>{{ $selectedRoom->title }}</span>
                            @endif
                        </p>
                        <button type="button" class="book-channel__cancel" wire:click="cancelStay">{{ site_trans('booking.channel_cancel') }}</button>
                    </div>

                    <div class="book-channel__popover book-channel__guests" x-show="open === 'guests'" x-cloak>
                        <p class="book-channel__popover-title">{{ site_trans('booking.channel_guests') }}</p>
                        <p class="book-channel__room-label">{{ site_trans('booking.channel_room') }} {{ (int) $room_count > 1 ? '1–'.(int) $room_count : '1' }}</p>
                        <div class="book-channel__stepper">
                            <span>{{ site_trans('booking.channel_adults_ages') }}</span>
                            <strong>{{ (int) $adults }}</strong>
                            <button type="button" wire:click="adjustGuests('adults', -1)" @disabled((int) $adults <= 1) aria-label="Fewer adults">−</button>
                            <button type="button" wire:click="adjustGuests('adults', 1)" @disabled((int) $adults >= 20) aria-label="More adults">+</button>
                        </div>
                        <div class="book-channel__stepper">
                            <span>{{ site_trans('booking.channel_children_ages') }}</span>
                            <strong>{{ (int) $children }}</strong>
                            <button type="button" wire:click="adjustGuests('children', -1)" @disabled((int) $children <= 0) aria-label="Fewer children">−</button>
                            <button type="button" wire:click="adjustGuests('children', 1)" @disabled((int) $children >= 20) aria-label="More children">+</button>
                        </div>
                        <button type="button" class="book-channel__add-room" wire:click="adjustGuests('room_count', 1)" @disabled((int) $room_count >= 20)>{{ site_trans('booking.channel_add_room') }}</button>
                        @if((int) $room_count > 1)
                            <button type="button" class="book-channel__add-room" wire:click="adjustGuests('room_count', -1)">{{ site_trans('booking.channel_remove_room') }}</button>
                        @endif
                        <div class="book-channel__popover-actions">
                            <button type="button" class="theme-btn btn-style fill" @click="open = null"><span>{{ site_trans('booking.channel_done') }}</span></button>
                        </div>
                    </div>

                    <div class="book-channel__popover book-channel__calendar" x-show="open === 'dates'" x-cloak>
                        <div class="book-channel__months">
                            <template x-for="(month, monthIndex) in months" :key="month.key">
                                <div class="book-channel__month">
                                    <div class="book-channel__month-head">
                                        <button type="button" x-show="monthIndex === 0" @click="shiftMonth(-1)" aria-label="Previous month">‹</button>
                                        <strong x-text="month.label"></strong>
                                        <button type="button" x-show="monthIndex === 1" @click="shiftMonth(1)" aria-label="Next month">›</button>
                                    </div>
                                    <div class="book-channel__weekdays">
                                        <template x-for="dayName in weekdayLabels" :key="dayName">
                                            <span x-text="dayName"></span>
                                        </template>
                                    </div>
                                    <div class="book-channel__days">
                                        <template x-for="(day, dayIndex) in month.days" :key="month.key + '-' + dayIndex">
                                            <button
                                                type="button"
                                                class="book-channel__day"
                                                :class="{
                                                    'is-empty': !day,
                                                    'is-disabled': day && day.disabled,
                                                    'is-start': day && day.iso === checkin,
                                                    'is-end': day && day.iso === checkout,
                                                    'is-range': day && day.inRange
                                                }"
                                                :disabled="!day || day.disabled"
                                                @click="day && pick(day.iso)"
                                            >
                                                <span x-text="day ? day.number : ''"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="book-channel__calendar-foot">
                            <p>
                                <strong>{{ (int) $nights === 1 ? site_trans('booking.channel_nights_one', ['count' => $nights]) : site_trans('booking.channel_nights_many', ['count' => $nights]) }}</strong>
                                · {{ hotel_price($selectedQuote['total'] ?? 0, $setting) }}
                            </p>
                            <button type="button" class="book-channel__cancel" wire:click="cancelStay" @click="open = null">{{ site_trans('booking.channel_cancel') }}</button>
                            <button type="button" class="theme-btn btn-style fill" @click="open = null"><span>{{ site_trans('booking.channel_done') }}</span></button>
                        </div>
                    </div>
                </div>

                <aside class="book-channel__panel book-channel__offers">
                    <h2>{{ site_trans('booking.channel_offers') }}</h2>
                    @forelse($activeDiscounts as $offer)
                        <article class="book-channel__offer">
                            <strong>{{ $offer->title }}</strong>
                            <span>
                                @if($offer->discount_type === 'percent')
                                    {{ rtrim(rtrim(number_format((float) $offer->amount, 2), '0'), '.') }}%
                                @else
                                    {{ hotel_price($offer->amount, $setting) }}
                                @endif
                                {{ site_trans('booking.channel_offer_night') }}
                            </span>
                            <em>{{ $offer->room?->title ?: site_trans('booking.channel_offer_all') }}</em>
                            <small>{{ $offer->starts_on?->format('M j, Y') }} – {{ $offer->ends_on?->format('M j, Y') }}</small>
                        </article>
                    @empty
                        <p class="book-channel__meta">{{ site_trans('booking.channel_no_offer') }}</p>
                    @endforelse
                </aside>

                <div class="book-channel__rooms">
                @foreach($errors->all() as $error)
                    <small class="book-channel__error">{{ $error }}</small>
                @endforeach

                <div class="book-channel__heading">
                    <h1>{{ site_trans('booking.channel_title') }}</h1>
                    <p>{{ site_trans('booking.channel_lead') }}</p>
                </div>

                <div class="book-channel__list">
                    @forelse($availableRooms as $room)
                        @php
                            $stay = $availabilityByRoom[$room->id] ?? [];
                            $quote = $quotes[$room->id] ?? [];
                            $sleeps = max(1, (int) ($room->max_occupancy ?? 2));
                            $canBook = ($stay['dates_ready'] ?? false)
                                && (int) ($stay['available'] ?? 0) >= 1
                                && $guestCount <= $sleeps
                                && $nights >= 1;
                            $image = $room->cover_image ?: $room->image;
                            $bedType = trim((string) ($room->bed_type ?? ''));
                            $bedCount = (int) ($room->bed_count ?? 0);
                            $beds = $bedType !== ''
                                ? trim(($bedCount > 0 ? $bedCount.' ' : '').$bedType)
                                : ($bedCount > 1 ? $bedCount.' beds' : '');
                        @endphp
                        <article class="book-channel__card {{ (int) $room_id === (int) $room->id ? 'is-selected' : '' }}" wire:click="chooseRoom({{ $room->id }})">
                            <div class="book-channel__photo">
                                @if($image)
                                    <img src="{{ asset('storage/'.$image) }}" alt="{{ $room->title }}">
                                @endif
                            </div>
                            <div class="book-channel__copy">
                                <h2>{{ $room->title }}</h2>
                                <p class="book-channel__facts">
                                    @if($room->bed_type && isset(\App\Models\Room::BED_TYPES[$room->bed_type]))
                                        <span>{{ site_trans('room.bed_'.$room->bed_type) }}</span>
                                    @elseif($beds !== '')
                                        <span>{{ $beds }}</span>
                                    @endif
                                    <span>{{ site_trans('booking.channel_sleeps', ['count' => $sleeps]) }}</span>
                                </p>
                                <p>{{ \Illuminate\Support\Str::words(strip_tags($room->description ?? ''), 22, '…') }}</p>
                                @if(filled($room->slug))
                                    <a wire:navigate href="{{ localized_route('room', ['slug' => $room->slug]) }}" class="book-channel__details" onclick="event.stopPropagation()">
                                        {{ site_trans('booking.channel_details') }}
                                    </a>
                                @endif
                            </div>
                            <div class="book-channel__rate">
                                @if(!empty($quote['has_discount']))
                                    <span class="book-channel__was">{{ hotel_price($quote['nightly'] ?? 0, $setting) }}</span>
                                    <strong>{{ hotel_price($quote['sale_nightly'] ?? 0, $setting) }}</strong>
                                    <span>{{ site_trans('booking.channel_per_night') }}</span>
                                    <em>{{ $quote['label'] ?: site_trans('booking.channel_offer') }}</em>
                                @else
                                    <strong>{{ hotel_price($quote['nightly'] ?? $room->price ?? 0, $setting) }}</strong>
                                    <span>{{ site_trans('booking.channel_per_night') }}</span>
                                @endif
                                @if($canBook)
                                    <button type="button" class="theme-btn btn-style fill" wire:click.stop="selectRoom({{ $room->id }})">
                                        <span>{{ site_trans('booking.channel_book') }}</span>
                                    </button>
                                @else
                                    <button type="button" class="theme-btn btn-style border" disabled>
                                        <span>{{ site_trans('booking.channel_unavailable') }}</span>
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="text-center">{{ site_trans('home.rooms_coming_soon') }}</p>
                    @endforelse
                </div>
                </div>

                <aside class="book-channel__panel book-channel__stay">
                    <h2>{{ site_trans('booking.channel_cart') }}</h2>
                    <p class="book-channel__meta">{{ \Carbon\Carbon::parse($checkin)->format('D, M j, Y') }} – {{ \Carbon\Carbon::parse($checkout)->format('D, M j, Y') }}</p>
                    <p class="book-channel__meta">{{ $nights }} {{ $nights === 1 ? 'night' : 'nights' }} · {{ site_trans('booking.channel_guests_line', ['count' => $guestCount]) }}</p>
                    @if($selectedRoom)
                        <p class="book-channel__suite">{{ $selectedRoom->title }}</p>
                        @if(isset(\App\Models\Room::BOARD_PLANS[$board]) && in_array($occupancy, ['single', 'double'], true))
                            <p class="book-channel__meta">{{ site_trans('room.board_'.$board) }} · {{ site_trans('room.occ_'.$occupancy) }}</p>
                        @endif
                        @if(!empty($selectedQuote['has_discount']))
                            <p class="book-channel__meta"><span class="book-channel__was">{{ hotel_price($selectedQuote['list_total'] ?? 0, $setting) }}</span> {{ hotel_price($selectedQuote['total'] ?? 0, $setting) }}</p>
                        @else
                            <p class="book-channel__suite">{{ hotel_price($selectedQuote['total'] ?? 0, $setting) }}</p>
                        @endif
                        <button type="button" class="book-channel__cancel" wire:click="cancelStay">{{ site_trans('booking.channel_cancel') }}</button>
                    @else
                        <p class="book-channel__meta">{{ site_trans('booking.channel_cart_empty') }}</p>
                    @endif
                </aside>
                </div>
            @endif
        </div>
    </section>
</div>

<script>
    window.bookingStayBar = function (checkin, checkout) {
        const weekdayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        const parse = (iso) => {
            if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                return null;
            }
            const [year, month, day] = iso.split('-').map(Number);
            return new Date(year, month - 1, day);
        };
        const isoOf = (date) => {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return date.getFullYear() + '-' + month + '-' + day;
        };
        const today = isoOf(new Date());

        return {
            open: null,
            checkin,
            checkout,
            awaitingEnd: false,
            cursor: parse(checkin) || new Date(),
            weekdayLabels,
            init() {
                this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), 1);
                this.$watch('$wire.checkin', (value) => { this.checkin = value; });
                this.$watch('$wire.checkout', (value) => { this.checkout = value; });
            },
            openCalendar() {
                this.open = this.open === 'dates' ? null : 'dates';
                const start = parse(this.checkin) || new Date();
                this.cursor = new Date(start.getFullYear(), start.getMonth(), 1);
            },
            shiftMonth(step) {
                this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + step, 1);
            },
            get months() {
                return [0, 1].map((offset) => {
                    const start = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + offset, 1);
                    const count = new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate();
                    const days = [];
                    for (let i = 0; i < start.getDay(); i++) {
                        days.push(null);
                    }
                    for (let day = 1; day <= count; day++) {
                        const date = new Date(start.getFullYear(), start.getMonth(), day);
                        const iso = isoOf(date);
                        days.push({
                            iso,
                            number: day,
                            disabled: iso < today,
                            inRange: this.checkin && this.checkout && iso > this.checkin && iso < this.checkout,
                        });
                    }
                    return {
                        key: isoOf(start),
                        label: start.toLocaleString(undefined, { month: 'long', year: 'numeric' }),
                        days,
                    };
                });
            },
            pick(iso) {
                if (!iso || iso < today) {
                    return;
                }
                if (!this.awaitingEnd || !this.checkin || iso <= this.checkin) {
                    const start = parse(iso);
                    start.setDate(start.getDate() + 1);
                    this.checkin = iso;
                    this.checkout = isoOf(start);
                    this.awaitingEnd = true;
                } else {
                    this.checkout = iso;
                    this.awaitingEnd = false;
                }
                this.$wire.setStayDates(this.checkin, this.checkout);
            },
        };
    };
</script>
@script
<script>
    $wire.on('reservation-submitted', (event) => {
        const detail = event && event.via ? event : (event && event[0] ? event[0] : {});
        const via = detail.via || 'email';
        const url = detail.whatsappUrl || '';
        const whatsapp = via === 'whatsapp' && url;
        const guestSent = detail.guestSent ?? detail.guest_sent;
        const adminSent = detail.adminSent ?? detail.admin_sent;
        const mailed = guestSent === true && adminSent === true;
        if (typeof Swal === 'undefined') {
            if (whatsapp) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
            return;
        }
        Swal.fire({
            icon: mailed ? 'success' : 'warning',
            title: @js(site_trans('booking.channel_sent_title')),
            text: !mailed
                ? @js(site_trans('booking.channel_sent_mail_failed'))
                : (whatsapp ? @js(site_trans('booking.channel_sent_whatsapp')) : @js(site_trans('booking.channel_sent_email'))),
            confirmButtonText: whatsapp ? @js(site_trans('booking.channel_open_whatsapp')) : @js(site_trans('booking.channel_done')),
            confirmButtonColor: '#111111',
        }).then(() => {
            if (whatsapp) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        });
    });
</script>
@endscript
