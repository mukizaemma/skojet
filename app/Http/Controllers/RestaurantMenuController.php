<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Support\ReservationQr;
use Illuminate\Http\Request;

class RestaurantMenuController extends Controller
{
    public function index()
    {
        return redirect()->route('content-management.front-office.settings.extras.index', ['section' => 'menu']);
    }

    public function qrCard()
    {
        $url = localized_route('dining.reserve');

        return view('content-management.restaurant.qr-card', [
            'url' => $url,
            'svg' => ReservationQr::svg($url),
            'hotel' => \App\Services\BookingSubmissionService::hotelName(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'minimum_amount' => 'required|numeric|min:0',
            'minimum_amount_local' => 'required|numeric|min:0',
            'momo_code' => 'nullable|string|max:40',
            'momo_account_name' => 'nullable|string|max:120',
        ]);

        $restaurant = Restaurant::access();
        $restaurant->requires_reservation = $request->boolean('requires_reservation');
        $restaurant->minimum_amount = round((float) $data['minimum_amount'], 2);
        $restaurant->minimum_amount_local = round((float) $data['minimum_amount_local'], 2);
        $restaurant->momo_code = filled($data['momo_code'] ?? null) ? trim($data['momo_code']) : null;
        $restaurant->momo_account_name = filled($data['momo_account_name'] ?? null) ? trim($data['momo_account_name']) : null;
        $restaurant->save();

        return redirect()
            ->route('content-management.front-office.pos.settings')
            ->with('success', 'Restaurant access settings saved.');
    }

    public function store(Request $request)
    {
        RestaurantMenuItem::query()->create($this->validatedItem($request));

        return redirect()->route('content-management.front-office.settings.extras.index', ['section' => 'menu'])->with('success', 'Menu item added.');
    }

    public function update(Request $request, RestaurantMenuItem $item)
    {
        $item->update($this->validatedItem($request));

        return redirect()->route('content-management.front-office.settings.extras.index', ['section' => 'menu'])->with('success', 'Menu item saved.');
    }

    public function destroy(RestaurantMenuItem $item)
    {
        $item->delete();

        return redirect()->route('content-management.front-office.settings.extras.index', ['section' => 'menu'])->with('success', 'Menu item removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        $data = $request->validate([
            'category' => 'required|in:food,beverages,coffee',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'price_international' => 'required|numeric|min:0',
            'price_local' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        return [
            'category' => $data['category'],
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'price_international' => round((float) $data['price_international'], 2),
            'price_local' => round((float) $data['price_local'], 2),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
