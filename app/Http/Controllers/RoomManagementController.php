<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\Roomimage;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RoomManagementController extends Controller
{
    public function index()
    {
        $rooms = Room::with(['amenities', 'images'])->latest()->get();
        $amenities = Amenity::all();
        $setting = Setting::first();

        return view('content-management.rooms.index', compact('rooms', 'amenities', 'setting'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'bed_type' => 'nullable|in:twin,king,double,deluxe',
            'rates' => 'nullable|array',
            'rates.*.single' => 'nullable|numeric|min:0',
            'rates.*.double' => 'nullable|numeric|min:0',
            'rates.*.discount_type' => 'nullable|in:amount,percent',
            'rates.*.discount_value' => 'nullable|numeric|min:0|max:1000000',
            'number_of_rooms' => 'required|integer|min:1|max:200',
            'max_occupancy' => 'required|integer|min:1|max:20',
            'cover_image' => admin_image_validation_rule(),
            'status' => 'required|in:Active,Inactive',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'images.*' => admin_image_validation_rule(),
        ]);

        $room = new Room();
        $room->title = $request->title;
        $room->slug = Str::slug($request->title);
        $room->description = $request->description;
        $room->room_type = 'room';
        $room->price = $request->price;
        $room->couplePrice = null;
        $room->bed_type = $request->filled('bed_type') ? $request->bed_type : null;
        $room->rate_plans = $this->ratePlansFromRequest($request);
        $room->number_of_rooms = (int) $request->number_of_rooms;
        $room->guests_included_in_price = min(2, (int) $request->max_occupancy);
        $room->extra_adult_price = null;
        $room->extra_child_price = null;
        $room->extra_bed_price = null;
        $room->max_occupancy = (int) $request->max_occupancy;
        $room->bed_count = 1;
        $room->status = $request->status;
        $room->room_status = 'available';
        $room->user_id = auth()->id();

        $coverPath = $this->resolveCoverPath($request);
        if ($coverPath !== null) {
            $room->cover_image = $coverPath;
        }

        $room->save();

        // Attach amenities
        if ($request->has('amenities')) {
            $room->amenities()->sync($request->amenities);
        }

        $this->attachGalleryImages($request, $room);

        return response()->json(['success' => true, 'message' => 'Room created successfully']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'bed_type' => 'nullable|in:twin,king,double,deluxe',
            'rates' => 'nullable|array',
            'rates.*.single' => 'nullable|numeric|min:0',
            'rates.*.double' => 'nullable|numeric|min:0',
            'rates.*.discount_type' => 'nullable|in:amount,percent',
            'rates.*.discount_value' => 'nullable|numeric|min:0|max:1000000',
            'number_of_rooms' => 'required|integer|min:1|max:200',
            'max_occupancy' => 'required|integer|min:1|max:20',
            'cover_image' => admin_image_validation_rule(),
            'status' => 'required|in:Active,Inactive',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'images.*' => admin_image_validation_rule(),
        ]);

        $room = Room::findOrFail($id);

        $room->title = $request->title;
        $room->slug = Str::slug($request->title);
        $room->description = $request->description;
        $room->room_type = 'room';
        $room->price = $request->price;
        $room->bed_type = $request->filled('bed_type') ? $request->bed_type : null;
        $room->rate_plans = $this->ratePlansFromRequest($request, $room);
        $room->number_of_rooms = (int) $request->number_of_rooms;
        $room->max_occupancy = (int) $request->max_occupancy;
        $room->guests_included_in_price = min((int) $room->guests_included_in_price ?: 2, (int) $request->max_occupancy);
        $room->status = $request->status;

        $coverPath = $this->resolveCoverPath($request, $room->cover_image);
        if ($coverPath !== null && $coverPath !== $room->cover_image) {
            if ($room->cover_image && str_starts_with((string) $room->cover_image, 'rooms/')) {
                Storage::disk('public')->delete($room->cover_image);
            }
            $room->cover_image = $coverPath;
        }

        $room->save();

        // Sync amenities
        if ($request->has('amenities')) {
            $room->amenities()->sync($request->amenities);
        } else {
            $room->amenities()->detach();
        }

        $this->attachGalleryImages($request, $room);

        return response()->json(['success' => true, 'message' => 'Room updated successfully']);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        
        // Delete cover image
        if ($room->cover_image) {
            Storage::disk('public')->delete($room->cover_image);
        }

        // Delete gallery images
        foreach ($room->images as $image) {
            Storage::disk('public')->delete($image->image);
            $image->delete();
        }

        $room->delete();

        return response()->json(['success' => true, 'message' => 'Room deleted successfully']);
    }

    public function show($id)
    {
        $room = Room::with(['amenities', 'images'])->findOrFail($id);

        return response()->json([
            'id' => $room->id,
            'title' => $room->title,
            'description' => $room->description,
            'price' => $room->price,
            'bed_type' => $room->bed_type,
            'rate_plans' => $room->ratePlans(),
            'number_of_rooms' => $room->number_of_rooms,
            'max_occupancy' => $room->max_occupancy,
            'status' => $room->status,
            'cover_image' => $room->cover_image,
            'cover_url' => $room->cover_image
                ? asset('storage/'.$room->cover_image)
                : $room->publicThumbnailUrl(),
            'amenities' => $room->amenities->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
            ])->values(),
            'images' => $room->images->map(fn ($img) => [
                'id' => $img->id,
                'image' => $img->image,
                'url' => asset('storage/'.$img->image),
            ])->values(),
        ]);
    }

    public function deleteImage($id)
    {
        $image = Roomimage::findOrFail($id);
        Storage::disk('public')->delete($image->image);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
    }

    public function addImages(Request $request, $id)
    {
        $request->validate([
            'images.*' => 'required|image|max:2048',
        ]);

        $room = Room::findOrFail($id);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                Roomimage::create([
                    'room_id' => $room->id,
                    'image' => store_optimized_image($image, 'rooms/gallery'),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Images added successfully']);
    }

    /**
     * @return array<string, array{single: ?float, double: ?float, discount_type: string, discount_value: ?float}>|null
     */
    protected function ratePlansFromRequest(Request $request, ?Room $room = null): ?array
    {
        $incoming = $request->input('rates', []);
        $existing = is_array($room?->rate_plans) ? $room->rate_plans : [];
        $plans = [];
        $any = false;

        foreach (array_keys(Room::BOARD_PLANS) as $key) {
            $row = is_array($incoming[$key] ?? null) ? $incoming[$key] : [];
            $stored = is_array($existing[$key] ?? null) ? $existing[$key] : [];
            $single = $this->rateMoney($row['single'] ?? null);
            $double = $this->rateMoney($row['double'] ?? null);
            $singleRwf = array_key_exists('single_rwf', $row) ? $this->rateMoney($row['single_rwf']) : $this->rateMoney($stored['single_rwf'] ?? null);
            $doubleRwf = array_key_exists('double_rwf', $row) ? $this->rateMoney($row['double_rwf']) : $this->rateMoney($stored['double_rwf'] ?? null);
            $type = in_array($row['discount_type'] ?? '', ['amount', 'percent'], true) ? $row['discount_type'] : '';
            $value = $this->rateMoney($row['discount_value'] ?? null);
            $valueRwf = array_key_exists('discount_value_rwf', $row)
                ? $this->rateMoney($row['discount_value_rwf'])
                : $this->rateMoney($stored['discount_value_rwf'] ?? null);
            if ($type === 'percent' && $value !== null) {
                $value = min(100, $value);
            }
            $priced = $single !== null || $double !== null || $singleRwf !== null || $doubleRwf !== null;
            if (! $priced || $type === '' || ($type === 'percent' && ($value === null || $value <= 0)) || ($type === 'amount' && ($value === null || $value <= 0) && ($valueRwf === null || $valueRwf <= 0))) {
                $type = '';
                $value = null;
                $valueRwf = null;
            }
            if ($type !== 'amount') {
                $valueRwf = null;
            }
            if ($priced) {
                $any = true;
            }
            $plans[$key] = [
                'single' => $single,
                'double' => $double,
                'single_rwf' => $singleRwf,
                'double_rwf' => $doubleRwf,
                'discount_type' => $type,
                'discount_value' => $value,
                'discount_value_rwf' => $valueRwf,
            ];
        }

        return $any ? $plans : null;
    }

    protected function rateMoney(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return $number < 0 ? null : $number;
    }

    private function resolveCoverPath(Request $request, ?string $current = null): ?string
    {
        if ($request->hasFile('cover_image')) {
            $stored = store_optimized_image($request->file('cover_image'), 'rooms');
            if (is_string($stored) && $stored !== '' && Storage::disk('public')->exists($stored)) {
                return $stored;
            }
        }

        $existing = $this->existingPublicImage($request->input('existing_cover'));
        if ($existing !== null && $existing !== $current) {
            return $existing;
        }

        return null;
    }

    private function attachGalleryImages(Request $request, Room $room): void
    {
        $linked = [];
        foreach ((array) $request->input('existing_images', []) as $relative) {
            $path = $this->existingPublicImage($relative);
            if ($path === null || isset($linked[$path])) {
                continue;
            }
            $linked[$path] = true;
            $already = $room->images()->where('image', $path)->exists();
            if ($already) {
                continue;
            }
            Roomimage::create([
                'room_id' => $room->id,
                'image' => $path,
            ]);
        }

        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $image) {
            if (! $image) {
                continue;
            }
            $stem = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            $alreadyLinked = false;
            foreach (array_keys($linked) as $path) {
                if (pathinfo($path, PATHINFO_FILENAME) === $stem) {
                    $alreadyLinked = true;
                    break;
                }
            }
            if ($alreadyLinked) {
                continue;
            }
            $stored = store_optimized_image($image, 'rooms/gallery');
            if (! is_string($stored) || $stored === '' || ! Storage::disk('public')->exists($stored)) {
                continue;
            }
            Roomimage::create([
                'room_id' => $room->id,
                'image' => $stored,
            ]);
        }
    }

    private function existingPublicImage(mixed $path): ?string
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
