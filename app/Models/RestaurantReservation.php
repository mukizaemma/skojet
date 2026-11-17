<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantReservation extends Model
{
    protected $fillable = [
        'reference',
        'names',
        'phone',
        'email',
        'guests',
        'reserved_for',
        'notes',
        'submit_via',
        'price_tier',
        'locale',
        'status',
        'payment_status',
        'paid_at',
        'minimum_amount',
        'total_amount',
        'access_token',
        'payer_name',
        'payment_choice',
        'claimed_amount',
        'paid_amount',
        'momo_code',
        'momo_account_name',
    ];

    protected $casts = [
        'guests' => 'integer',
        'reserved_for' => 'datetime',
        'paid_at' => 'datetime',
        'minimum_amount' => 'float',
        'total_amount' => 'float',
        'claimed_amount' => 'float',
        'paid_amount' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantReservationItem::class)->orderBy('id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' && $this->accessGranted();
    }

    public function accessGranted(): bool
    {
        if ($this->payment_status !== 'paid') {
            return false;
        }

        if ((float) $this->paid_amount > 0.001) {
            return true;
        }

        return (float) $this->minimum_amount < 0.001;
    }

    public function passUrl(): ?string
    {
        if (! filled($this->access_token)) {
            return null;
        }

        return localized_route('dining.pass', ['token' => $this->access_token]);
    }

    public function usesLocalPrices(): bool
    {
        return $this->price_tier === 'local';
    }

    public function money(float $amount): string
    {
        return restaurant_money($amount, $this->price_tier);
    }

    public function recalculate(): void
    {
        $this->total_amount = round((float) $this->items()->sum('line_total'), 2);
        $this->save();
    }

    /**
     * @return list<string>
     */
    public function billLines(): array
    {
        $invoice = hotel_invoice();
        $when = $this->reserved_for?->format('D, M j, Y · H:i') ?? '';
        $thanks = trim((string) ($invoice['footer'] ?? ''));
        if ($thanks === '') {
            $thanks = site_trans('dining.bill_thanks');
        }

        $lines = array_values(array_filter([
            $invoice['name'],
            $invoice['address'],
            collect([$invoice['phone'], $invoice['email']])->filter()->implode(' · '),
            $invoice['tax_id'] !== '' ? site_trans('dining.bill_tax', ['id' => $invoice['tax_id']]) : null,
            '',
            site_trans('dining.bill_title'),
            site_trans('dining.bill_reference', ['ref' => $this->reference]),
            $this->accessGranted() ? site_trans('dining.access_paid') : site_trans('dining.access_unpaid'),
            site_trans('dining.bill_paid_amount', ['amount' => $this->money((float) $this->paid_amount)]),
            $this->accessGranted() ? site_trans('dining.access_enter') : site_trans('dining.access_denied'),
            '',
            $this->names,
            $this->phone.' · '.$this->email,
            site_trans('dining.bill_when', ['when' => $when, 'guests' => (string) $this->guests]),
            '',
        ], fn ($line) => $line !== null));

        foreach ($this->items as $item) {
            $lines[] = $item->quantity.' × '.$item->name.' — '.$this->money((float) $item->line_total);
            if (filled($item->description)) {
                $lines[] = '   '.$item->description;
            }
        }

        $lines[] = '';
        $lines[] = site_trans('dining.reserve_total').': '.$this->money((float) $this->total_amount);
        $lines[] = site_trans('dining.bill_momo', ['code' => (string) $this->momo_code]);
        if (filled($this->payer_name)) {
            $lines[] = site_trans('dining.bill_payer', ['name' => $this->payer_name]);
        }
        $lines[] = site_trans('dining.bill_claimed', ['amount' => $this->money((float) $this->claimed_amount)]);
        if ($this->passUrl()) {
            $lines[] = '';
            $lines[] = $this->passUrl();
        }
        $lines[] = '';
        $lines[] = $thanks;

        return $lines;
    }

    public function billText(): string
    {
        return implode("\n", $this->billLines());
    }
}
