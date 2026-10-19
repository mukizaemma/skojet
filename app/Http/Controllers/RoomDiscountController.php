<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomDiscount;
use Illuminate\Http\Request;

class RoomDiscountController extends Controller
{
    public function index()
    {
        $discounts = RoomDiscount::query()->with('room')->orderByDesc('starts_on')->get();
        $rooms = Room::query()->where('status', 'Active')->orderBy('title')->get();

        return view('content-management.discounts.index', compact('discounts', 'rooms'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        RoomDiscount::create($data);

        return redirect()->route('content-management.discounts')->with('success', 'Discount saved.');
    }

    public function update(Request $request, int $id)
    {
        $discount = RoomDiscount::findOrFail($id);
        $discount->update($this->validated($request));

        return redirect()->route('content-management.discounts')->with('success', 'Discount updated.');
    }

    public function destroy(int $id)
    {
        RoomDiscount::findOrFail($id)->delete();

        return redirect()->route('content-management.discounts')->with('success', 'Discount removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'room_id' => 'nullable|exists:rooms,id',
            'discount_type' => 'required|in:percent,amount',
            'amount' => 'required|numeric|min:0.01',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'status' => 'required|in:Active,Inactive',
        ]);

        if ($data['discount_type'] === 'percent' && (float) $data['amount'] > 100) {
            $data['amount'] = 100;
        }

        $data['room_id'] = $data['room_id'] !== null && $data['room_id'] !== ''
            ? (int) $data['room_id']
            : null;

        return $data;
    }
}
