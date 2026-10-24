<?php

namespace App\Support;

class HotelTerms
{
    public static function html(): string
    {
        return <<<'HTML'
<p class="terms-doc__address">Musanze District<br>Cyuve Sector<br>Kabeza Cell<br>Bukuzi Village</p>
<h2>Meals</h2>
<ul>
<li>Half Board: <strong>USD 30/adult</strong></li>
<li>Full Board: <strong>USD 60/adult</strong></li>
<li>Packed lunch: Available on request</li>
</ul>
<h2>Children</h2>
<ul>
<li>0–5 years: <strong>Free</strong> when sharing with 1–2 paying adults</li>
<li>5.1–12 years: <strong>USD 60/child/night</strong> with an extra bed</li>
</ul>
<h2>Check-in / Check-out</h2>
<ul>
<li>Check-in: <strong>1:00 p.m.</strong></li>
<li>Check-out: <strong>10:00 a.m.</strong></li>
<li>Early check-in: Subject to availability</li>
<li>Guaranteed early check-in: Previous night must be booked at <strong>100% of the daily rate</strong></li>
<li>Late check-out: Subject to availability and additional charges</li>
</ul>
<h2>Payment</h2>
<ul>
<li>3+ rooms: <strong>50% advance payment</strong></li>
<li>Short-notice bookings: Payment required immediately</li>
<li>Group rooming list: Required <strong>4 days before arrival</strong></li>
</ul>
<h2>Cancellation</h2>
<ul>
<li><strong>Up to 1 day before arrival:</strong> 50% charge</li>
<li><strong>Arrival date/no-show:</strong> 100% charge</li>
<li>Unused services: <strong>Non-refundable</strong></li>
</ul>
<h2>Other terms</h2>
<ul>
<li>Rates include applicable <strong>VAT</strong> and may change without prior notice.</li>
<li>If the reserved room is unavailable, suitable alternative accommodation may be arranged, <strong>subject to availability</strong>.</li>
<li>Guests are responsible for their personal belongings; the resort is <strong>not liable for loss or damage</strong>.</li>
<li>All cancellations, changes, and additions must be made <strong>in writing</strong>.</li>
<li><strong>Jurisdiction:</strong> Musanze Sector, Musanze District, Rwanda.</li>
</ul>
<p class="terms-doc__disclaimer"><strong>Disclaimer:</strong> Terms &amp; Conditions are subject to change without prior notice.</p>
<p class="terms-doc__sign">Skojet Boutique Hotel Management</p>
<p class="terms-doc__contacts"><a href="https://skojethotel.com">skojethotel.com</a><a href="mailto:skojetboutikhotel@gmail.com">skojetboutikhotel@gmail.com</a><a href="tel:+250782669161">+250782669161</a></p>
HTML;
    }
}
