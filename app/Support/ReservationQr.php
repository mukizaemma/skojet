<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ReservationQr
{
    public static function svg(string $url): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($url);
    }
}
