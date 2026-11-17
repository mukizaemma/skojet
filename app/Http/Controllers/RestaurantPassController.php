<?php

namespace App\Http\Controllers;

use App\Models\RestaurantReservation;

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
}
