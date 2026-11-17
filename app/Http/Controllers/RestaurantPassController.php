<?php

namespace App\Http\Controllers;

use App\Models\RestaurantReservation;
use App\Support\PdfFile;

class RestaurantPassController extends Controller
{
    public function show(string $token)
    {
        $reservation = RestaurantReservation::query()
            ->where('access_token', $token)
            ->firstOrFail();
        $reservation->load('items');

        return view('frontend.restaurant-pass', [
            'reservation' => $reservation,
        ]);
    }

    public function pdf(string $token)
    {
        $reservation = RestaurantReservation::query()
            ->where('access_token', $token)
            ->firstOrFail();
        $reservation->load('items');

        return PdfFile::download(
            view('bills.pdf', [
                'title' => $reservation->reference,
                'body' => view('restaurant.bill-document', ['reservation' => $reservation])->render(),
            ])->render(),
            $reservation->reference.'.pdf'
        );
    }
}
