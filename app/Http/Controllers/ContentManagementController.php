<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotelContact;
use App\Support\SyncHotelContact;
use App\Models\About;
use App\Models\TermsCondition;
use App\Models\SeoData;
use App\Models\User;
use App\Models\Role;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Models\Room;
use App\Models\Facility;
use App\Models\Amenity;
use App\Models\Gallery;
use App\Services\AggregatedGalleryService;
use App\Services\PublicWebsiteData;
use App\Models\Slide;
use App\Models\Setting;
use App\Models\ServiceImage;
use App\Models\Roomimage;
use App\Models\Facilityimage;
use App\Models\PageHero;
use App\Mail\BookingStatusUpdateGuestMail;
use App\Models\Booking;
use App\Models\BookingTrash;
use App\Services\SiteNotificationMail;
use App\Support\BookingReplyDrafts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ContentManagementController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'rooms' => Room::count(),
            'users' => User::count(),
        ];

        $contentReady = $stats['rooms'] > 0;

        return view('content-management.dashboard', compact('stats', 'contentReady'));
    }

    /**
     * Reservations overview for Content Manager.
     */
    public function reservations(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Booking::query()
            ->with(['room', 'assignedRoom', 'facility', 'tourActivity'])
            ->latest();

        if (filled($startDate)) {
            try {
                $start = Carbon::parse($startDate)->startOfDay();
                $query->where('created_at', '>=', $start);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if (filled($endDate)) {
            try {
                $end = Carbon::parse($endDate)->endOfDay();
                $query->where('created_at', '<=', $end);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        $bookings = $query->paginate(40)->withQueryString();

        $base = Booking::query();
        if (filled($startDate)) {
            try {
                $base->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            } catch (\Throwable) {
            }
        }
        if (filled($endDate)) {
            try {
                $base->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            } catch (\Throwable) {
            }
        }

        $allForStats = (clone $base)->get(['id', 'status', 'message']);
        $stats = [
            'total' => $allForStats->count(),
            'whatsapp' => $allForStats->filter(fn (Booking $b) => $this->bookingChannel($b) === 'WhatsApp')->count(),
            'email' => $allForStats->filter(fn (Booking $b) => $this->bookingChannel($b) === 'Email')->count(),
            'confirmed' => $allForStats->where('status', 'confirmed')->count(),
            'pending' => $allForStats->where('status', 'pending')->count(),
        ];

        return view('content-management.reservations.index', compact('bookings', 'stats', 'startDate', 'endDate'));
    }

    /**
     * Reservation detail + respond form.
     */
    public function showReservationPage($id)
    {
        $booking = Booking::with(['room', 'assignedRoom', 'facility', 'tourActivity'])->findOrFail($id);
        $drafts = BookingReplyDrafts::for($booking);
        $channel = $this->bookingChannel($booking);

        return view('content-management.reservations.show', compact('booking', 'drafts', 'channel'));
    }

    /**
     * JSON payload for modal/legacy callers.
     */
    public function showReservation($id)
    {
        $booking = Booking::with(['room', 'assignedRoom', 'facility', 'tourActivity'])->findOrFail($id);

        return response()->json($booking);
    }

    /**
     * Reply to reservation with status & custom message; optionally email guest.
     */
    public function replyReservation(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:confirmed,cancelled,rejected,full-booked,scam',
            'admin_reply' => 'nullable|string|max:5000',
            'email_guest' => 'nullable|boolean',
        ]);

        $booking = Booking::with(['room', 'facility', 'tourActivity'])->findOrFail($id);
        $status = $request->status === 'rejected' ? 'cancelled' : $request->status;
        $reply = trim((string) $request->input('admin_reply', ''));
        if ($reply === '') {
            $drafts = BookingReplyDrafts::for($booking);
            $reply = $status === 'confirmed'
                ? $drafts['confirmed']
                : ($drafts['cancelled'] ?? $drafts['confirmed']);
        }

        $booking->status = $status;
        $booking->admin_reply = $reply;
        $booking->admin_replied_at = now();

        $emailGuest = $request->boolean('email_guest', true);
        $guestEmail = $booking->email;
        if ($emailGuest && filled($guestEmail)) {
            $statusLabelMap = [
                'confirmed' => 'Confirmed',
                'cancelled' => 'Cancelled',
                'full-booked' => 'Full-booked',
                'scam' => 'Marked as Scam',
            ];
            $statusLabel = $statusLabelMap[$status] ?? ucfirst($status);
            $sent = SiteNotificationMail::sendToGuest(
                $guestEmail,
                new BookingStatusUpdateGuestMail($booking, $statusLabel, $reply)
            );
            if (! $sent) {
                $booking->save();

                return redirect()
                    ->route('content-management.reservations.show', $booking->id)
                    ->with('error', 'Status saved but email could not be sent. Check Resend configuration.');
            }
        }

        if ($status === 'scam') {
            BookingTrash::create([
                'original_booking_id' => $booking->id,
                'names' => $booking->names,
                'email' => $booking->email,
                'phone' => $booking->phone,
                'reservation_type' => $booking->reservation_type,
                'status' => $booking->status,
                'payload' => $booking->toArray(),
            ]);
            $booking->delete();

            return redirect()
                ->route('content-management.reservations')
                ->with('success', 'Reservation updated'.($emailGuest ? ' and guest notified.' : '.'));
        }

        $booking->save();

        return redirect()
            ->route('content-management.reservations.show', $booking->id)
            ->with('success', 'Reservation updated'.($emailGuest && filled($guestEmail) ? ' and guest notified by email.' : '.'));
    }

    public function destroyReservation($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()
            ->route('content-management.reservations')
            ->with('success', 'Reservation deleted.');
    }

    protected function bookingChannel(Booking $booking): string
    {
        $message = strtolower((string) ($booking->message ?? ''));
        if (str_contains($message, 'confirmation preference: whatsapp') || str_contains($message, 'via whatsapp')) {
            return 'WhatsApp';
        }

        return 'Email';
    }

    // Hotel Contacts Management
    public function contacts()
    {
        $contact = HotelContact::first();
        return view('content-management.contacts.index', compact('contact'));
    }

    public function updateContacts(Request $request)
    {
        SyncHotelContact::fromRequest($request);

        $setting = \App\Models\Setting::first();
        if ($setting) {
            foreach (['phone', 'email', 'address', 'facebook', 'instagram', 'linkedin', 'twitter'] as $field) {
                if ($request->has($field)) {
                    $setting->{$field} = $request->input($field);
                }
            }
            $setting->save();
        }

        return redirect()->to(route('setting').'#contacts')->with('success', 'Contacts updated successfully');
    }

    // About Us Management
    public function about()
    {
        $about = About::first();
        return view('content-management.about.index', compact('about'));
    }

    public function updateAbout(Request $request)
    {
        $about = About::firstOrNew();
        $about->fill($request->only(['title', 'subTitle', 'founderDescription', 'mission', 'vision', 'storyDescription', 'backImageText']));
        $about->user_id = auth()->id();
        
        // Handle image uploads
        if ($request->hasFile('image1')) {
            if ($about->image1) {
                \Storage::disk('public')->delete($about->image1);
            }
            $about->image1 = store_optimized_image($request->file('image1'), 'abouts');
        }
        if ($request->hasFile('image2')) {
            if ($about->image2) {
                \Storage::disk('public')->delete($about->image2);
            }
            $about->image2 = store_optimized_image($request->file('image2'), 'abouts');
        }
        if ($request->hasFile('image3')) {
            if ($about->image3) {
                \Storage::disk('public')->delete($about->image3);
            }
            $about->image3 = store_optimized_image($request->file('image3'), 'abouts');
        }
        if ($request->hasFile('image4')) {
            if ($about->image4) {
                \Storage::disk('public')->delete($about->image4);
            }
            $about->image4 = store_optimized_image($request->file('image4'), 'abouts');
        }
        if ($request->hasFile('storyImage')) {
            if ($about->storyImage) {
                \Storage::disk('public')->delete($about->storyImage);
            }
            $about->storyImage = store_optimized_image($request->file('storyImage'), 'abouts');
        }
        
        $about->save();

        return redirect()->to(route('setting').'#about')->with('success', 'About information updated successfully');
    }

    // Terms and Conditions
    public function termsConditions()
    {
        $terms = TermsCondition::first();
        return view('content-management.terms.index', compact('terms'));
    }

    public function updateTermsConditions(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
        ]);

        $terms = TermsCondition::query()->first() ?? new TermsCondition();
        $terms->content = (string) $request->input('content', '');
        $terms->status = 'active';
        $terms->updated_by = auth()->id();
        $terms->save();

        return redirect()->to(route('setting').'#tab-terms')->with('success', 'Terms and conditions updated successfully');
    }

    // SEO Data Management
    public function seoData()
    {
        $seoData = SeoData::all();
        return view('content-management.seo.index', compact('seoData'));
    }

    public function showSeoData($id)
    {
        $seo = SeoData::findOrFail($id);
        return response()->json($seo);
    }

    public function updateSeoData(Request $request)
    {
        $id = $request->input('id') ?? $request->query('id');
        
        if ($id) {
            $seo = SeoData::findOrFail($id);
        } else {
            $request->validate([
                'page_name' => 'required|unique:seo_data,page_name',
            ]);
            $seo = new SeoData();
        }
        
        $seo->fill($request->only(['page_name', 'meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description']));
        $seo->updated_by = auth()->id();
        
        if ($request->hasFile('og_image')) {
            if ($seo->og_image) {
                \Storage::disk('public')->delete($seo->og_image);
            }
            $seo->og_image = store_optimized_image($request->file('og_image'), 'seo');
        }
        
        $seo->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'SEO data saved successfully']);
        }

        return redirect()->back()->with('success', 'SEO data updated successfully');
    }

    // System Users Panel (Super Admin only)
    public function users()
    {
        $users = User::with('role')->latest()->get();
        $roles = Role::whereIn('slug', ['admin', 'guest'])->orderBy('name')->get();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        return view('content-management.users.index', compact('users', 'roles', 'superAdminRole'));
    }

    public function updateUserRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $allowedRoleIds = Role::whereIn('slug', ['admin', 'guest'])->pluck('id')->all();
        $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
        if ($superAdminRoleId && (int) $user->role_id === (int) $superAdminRoleId) {
            $allowedRoleIds[] = (int) $superAdminRoleId;
        }

        $request->validate([
            'role_id' => ['required', Rule::in($allowedRoleIds)],
        ]);

        $user->role_id = $request->role_id;
        $user->save();

        return redirect()->back()->with('success', 'User role updated successfully');
    }

    // Services Management
    public function services()
    {
        $services = Service::with('images')->latest()->get();
        return view('content-management.services.index', compact('services'));
    }

    public function storeService(Request $request)
    {
        $service = new Service();
        $service->fill($request->all());
        $service->slug = \Illuminate\Support\Str::slug($request->title);
        if ($request->hasFile('image')) {
            $service->image = store_optimized_image($request->file('image'), 'services');
        }
        $service->save();

        // Handle multiple images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                ServiceImage::create([
                    'service_id' => $service->id,
                    'image' => store_optimized_image($image, 'services'),
                    'order' => $index,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Service created successfully');
    }

    // Rooms Management
    public function rooms()
    {
        $rooms = Room::with(['amenities', 'images'])->latest()->get();
        $amenities = Amenity::all();
        return view('content-management.rooms.index', compact('rooms', 'amenities'));
    }

    public function storeRoom(Request $request)
    {
        $room = new Room();
        $room->fill($request->all());
        $room->slug = \Illuminate\Support\Str::slug($request->title);
        if ($request->hasFile('image')) {
            $room->image = store_optimized_image($request->file('image'), 'rooms');
        }
        $room->user_id = auth()->id();
        $room->save();

        // Attach amenities
        if ($request->has('amenities')) {
            $room->amenities()->sync($request->amenities);
        }

        // Handle multiple images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                Roomimage::create([
                    'room_id' => $room->id,
                    'image' => store_optimized_image($image, 'rooms'),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Room created successfully');
    }

    // Facilities Management
    public function facilities()
    {
        $facilities = Facility::with('images')->latest()->get();
        return view('content-management.facilities.index', compact('facilities'));
    }

    public function storeFacility(Request $request)
    {
        $facility = new Facility();
        $facility->fill($request->all());
        $facility->slug = \Illuminate\Support\Str::slug($request->title);
        if ($request->hasFile('image')) {
            $facility->image = store_optimized_image($request->file('image'), 'facilities');
        }
        $facility->save();

        // Handle multiple images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                Facilityimage::create([
                    'facility_id' => $facility->id,
                    'image' => store_optimized_image($image, 'facilities'),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Facility created successfully');
    }

    public function gallery()
    {
        Gallery::reconcileImagePaths();
        Gallery::syncExistingServerPhotos();

        $ordered = $this->orderedGalleryImages();
        $homeImages = $this->homeGalleryImages();
        $images = Gallery::query()
            ->photos()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->appends(['tab' => 'gallery']);

        return view('content-management.gallery.index', [
            'images' => $images,
            'homeImages' => $homeImages,
            'galleryTotal' => $ordered->count(),
            'firstHomeId' => $homeImages->first()?->id,
            'lastHomeId' => $homeImages->last()?->id,
            'nextSort' => ((int) $ordered->max('sort_order')) + 1,
            'firstGalleryId' => $ordered->first()?->id,
            'lastGalleryId' => $ordered->last()?->id,
            'activeTab' => request('tab') === 'gallery' ? 'gallery' : 'home',
        ]);
    }

    public function storeGalleryImage(Request $request)
    {
        $files = $request->file('images', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $files = array_values(array_filter($files));
        if ($files === [] && $request->hasFile('image')) {
            $files = [$request->file('image')];
        }
        $request->files->set('images', $files);
        $existingInput = array_values(array_filter(array_map('strval', (array) $request->input('existing_images', []))));

        $validated = $request->validate([
            'images' => ($existingInput === [] ? 'required|array|min:1|max:30' : 'nullable|array|max:30'),
            'images.*' => admin_image_validation_rule(true),
            'caption' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'show_on_home' => 'nullable|boolean',
            'return_tab' => 'nullable|in:home,gallery',
            'existing_images' => 'nullable|array|max:30',
            'existing_images.*' => 'nullable|string|max:255',
        ], [
            'images.required' => 'Choose at least one image to upload.',
            'images.*.image' => 'Each file must be a JPG, PNG, GIF, or WEBP photo.',
            'images.*.max' => 'Each image must be 15 MB or smaller.',
        ]);

        $wantHome = $request->boolean('show_on_home');
        $homeSlots = max(0, 6 - $this->homeGalleryCount());
        $homeFullNote = '';
        if ($wantHome && $homeSlots === 0) {
            $wantHome = false;
            $homeFullNote = ' The home page already has 6 images, so these were added to the gallery page only.';
        }

        Storage::disk('public')->makeDirectory('gallery');
        $galleryDir = storage_path('app/public/gallery');
        $canWriteGallery = is_dir($galleryDir) && is_writable($galleryDir);

        $sort = $request->filled('sort_order')
            ? (int) $validated['sort_order']
            : ((int) Gallery::query()->max('sort_order') + 1);
        $placedHome = 0;
        $created = 0;
        $existingPlaced = 0;
        $alreadyHome = 0;
        $alreadyGallery = 0;
        $failed = [];
        $touchedIds = [];
        $handledStems = [];
        $index = $this->galleryPhotoIndex();

        foreach ($existingInput as $relative) {
            $relative = $this->safePublicImagePath($relative);
            if ($relative === null) {
                continue;
            }

            $this->linkExistingGalleryPhoto(
                $relative,
                $wantHome,
                $homeSlots,
                $validated['caption'] ?? null,
                $sort,
                $placedHome,
                $created,
                $existingPlaced,
                $alreadyHome,
                $alreadyGallery,
                $touchedIds,
                $handledStems,
                $index
            );
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                $failed[] = ($file ? $file->getClientOriginalName() : 'a selected file').' (upload was rejected)';
                continue;
            }

            $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            if ($stem !== '' && isset($handledStems[$stem])) {
                continue;
            }

            $uploadHash = null;
            $tmp = $file->getRealPath();
            if (is_string($tmp) && is_file($tmp)) {
                $uploadHash = md5_file($tmp);
            }
            $matched = $this->matchingGalleryRows($index, $uploadHash, $file->getClientOriginalName());
            if ($matched !== []) {
                $this->offerExistingGalleryRows(
                    $matched,
                    $wantHome,
                    $homeSlots,
                    $placedHome,
                    $existingPlaced,
                    $alreadyHome,
                    $alreadyGallery,
                    $touchedIds
                );
                if ($stem !== '') {
                    $handledStems[$stem] = true;
                }
                continue;
            }

            $storedPath = $this->findPublicPathByBasename($file->getClientOriginalName());
            if ($storedPath !== null) {
                $this->linkExistingGalleryPhoto(
                    $storedPath,
                    $wantHome,
                    $homeSlots,
                    $validated['caption'] ?? null,
                    $sort,
                    $placedHome,
                    $created,
                    $existingPlaced,
                    $alreadyHome,
                    $alreadyGallery,
                    $touchedIds,
                    $handledStems,
                    $index
                );
                continue;
            }

            if (! $canWriteGallery) {
                $failed[] = $file->getClientOriginalName().' (the server cannot write a new file into the gallery folder)';
                continue;
            }

            try {
                $path = store_optimized_image($file, 'gallery');
            } catch (\Throwable $e) {
                $failed[] = $file->getClientOriginalName().' ('.$e->getMessage().')';
                continue;
            }

            if ($path === '' || $path === false || ! Storage::disk('public')->exists($path)) {
                $failed[] = $file->getClientOriginalName().' (could not write the file)';
                continue;
            }

            $storedAbsolute = storage_path('app/public/'.$path);
            $hash = is_file($storedAbsolute) ? md5_file($storedAbsolute) : null;
            $matched = $this->matchingGalleryRows($index, $hash, null);
            if ($matched !== []) {
                if (str_starts_with((string) $path, 'gallery/')) {
                    Storage::disk('public')->delete($path);
                }
                $this->offerExistingGalleryRows(
                    $matched,
                    $wantHome,
                    $homeSlots,
                    $placedHome,
                    $existingPlaced,
                    $alreadyHome,
                    $alreadyGallery,
                    $touchedIds
                );
                continue;
            }

            $onHome = $wantHome && $placedHome < $homeSlots;
            if ($onHome) {
                $placedHome++;
            }

            $gallery = new Gallery();
            $gallery->media_type = 'image';
            $gallery->caption = $validated['caption'] ?? null;
            $gallery->sort_order = $sort;
            $gallery->show_on_home = $onHome;
            $gallery->image = $path;
            $gallery->save();
            $this->rememberGalleryRow($index, $gallery, $hash);
            $touchedIds[$gallery->id] = true;
            $created++;
            $sort++;
        }

        $redirectTab = $validated['return_tab'] ?? 'gallery';
        if ($created === 0 && $existingPlaced === 0) {
            if ($failed !== []) {
                return redirect()
                    ->route('content-management.gallery', ['tab' => $redirectTab])
                    ->with('error', 'Could not save: '.implode(', ', $failed).'.');
            }
            if ($wantHome && $alreadyHome > 0) {
                return redirect()
                    ->route('content-management.gallery', ['tab' => 'home'])
                    ->with('success', 'Those photos are already on the home page.');
            }
            if ($alreadyGallery > 0) {
                $detail = $homeFullNote !== ''
                    ? ltrim($homeFullNote)
                    : 'Those photos are already in the gallery.';

                return redirect()
                    ->route('content-management.gallery', ['tab' => $redirectTab])
                    ->with('success', $detail);
            }

            return redirect()
                ->route('content-management.gallery', ['tab' => $redirectTab])
                ->with('error', 'No images were saved.');
        }

        PublicWebsiteData::flushCaches();

        $message = '';
        if ($created === 1) {
            $message = 'Gallery image added.';
        } elseif ($created > 1) {
            $message = $created.' gallery images added.';
        }
        if ($existingPlaced === 1) {
            $message = trim($message.' 1 existing photo is now on the home page.');
        } elseif ($existingPlaced > 1) {
            $message = trim($message.' '.$existingPlaced.' existing photos are now on the home page.');
        }
        if ($homeFullNote !== '') {
            $message .= $homeFullNote;
        } elseif ($wantHome && $alreadyGallery > 0 && $placedHome < ($created + $existingPlaced + $alreadyGallery)) {
            $message .= ' '.$placedHome.' '.($placedHome === 1 ? 'is' : 'are').' on the home page. The home page holds 6 images, so the rest stay on the gallery page.';
        }
        if ($failed !== []) {
            $message .= ' Could not save: '.implode(', ', $failed).'.';
        }

        $tab = $wantHome && $placedHome > 0 ? 'home' : $redirectTab;

        return redirect()
            ->route('content-management.gallery', ['tab' => $tab])
            ->with('success', $message);
    }

    public function moveGalleryImage(Request $request, Gallery $gallery)
    {
        $request->validate([
            'direction' => 'required|in:up,down',
            'scope' => 'nullable|in:home,gallery',
        ]);

        $scope = $request->input('scope') === 'home' ? 'home' : 'gallery';
        $images = ($scope === 'home' ? $this->homeGalleryImages() : $this->orderedGalleryImages())->values();
        $index = $images->search(fn (Gallery $row) => (int) $row->id === (int) $gallery->id);
        $swap = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $swap < 0 || $swap >= $images->count()) {
            return redirect()->route('content-management.gallery', [
                'tab' => $scope,
                'page' => $scope === 'gallery' ? $request->input('page') : null,
            ]);
        }

        $items = $images->all();
        if ($scope === 'home') {
            $this->swapSortOrder($items[$index], $items[$swap]);
        } else {
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            $this->writeGalleryOrder(collect($items));
        }
        PublicWebsiteData::flushCaches();

        if ($scope === 'home') {
            return redirect()->route('content-management.gallery', [
                'tab' => 'home',
            ])->with('success', 'Homepage order updated.');
        }

        $perPage = 15;
        $newPage = (int) floor($swap / $perPage) + 1;

        return redirect()->route('content-management.gallery', [
            'tab' => 'gallery',
            'page' => $newPage > 1 ? $newPage : null,
        ])->with('success', 'Gallery order updated.');
    }

    public function removeDuplicateGalleryImages()
    {
        Gallery::reconcileImagePaths();

        $groups = [];
        foreach ($this->orderedGalleryImages() as $image) {
            $full = $image->absoluteImagePath();
            $key = $full ? md5_file($full) : 'missing-'.$image->id;
            $groups[$key][] = $image;
        }

        $removed = 0;
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, function (Gallery $a, Gallery $b) {
                if ($a->show_on_home !== $b->show_on_home) {
                    return $a->show_on_home ? -1 : 1;
                }
                if ((int) $a->sort_order !== (int) $b->sort_order) {
                    return (int) $a->sort_order <=> (int) $b->sort_order;
                }

                return (int) $a->id <=> (int) $b->id;
            });

            array_shift($group);
            foreach ($group as $duplicate) {
                $path = $duplicate->image;
                $duplicate->delete();
                $this->deleteGalleryFile($path);
                $removed++;
            }
        }

        $this->writeGalleryOrder($this->orderedGalleryImages());
        PublicWebsiteData::flushCaches();

        return redirect()
            ->route('content-management.gallery', ['tab' => 'gallery'])
            ->with('success', $removed > 0
                ? $removed.' duplicate '.($removed === 1 ? 'image was' : 'images were').' removed.'
                : 'No duplicate images were found.');
    }

    public function updateGalleryImage(Request $request, Gallery $gallery)
    {
        $validated = $request->validate([
            'image' => admin_image_validation_rule(false),
            'caption' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'show_on_home' => 'nullable|boolean',
            'return_tab' => 'nullable|in:home,gallery',
        ]);

        $showOnHome = $request->boolean('show_on_home');
        if ($showOnHome && ! $gallery->show_on_home && $this->homeGalleryCount() >= 6) {
            return redirect()
                ->route('content-management.gallery', ['tab' => $request->input('return_tab', 'home')])
                ->with('error', 'The home page already shows 6 images. Remove one before adding another.');
        }

        $gallery->caption = $validated['caption'] ?? null;
        if ($request->filled('sort_order')) {
            $gallery->sort_order = (int) $validated['sort_order'];
        }
        $gallery->show_on_home = $showOnHome;

        $previousImage = (string) $gallery->image;
        if ($request->hasFile('image')) {
            Storage::disk('public')->makeDirectory('gallery');
            $path = store_optimized_image($request->file('image'), 'gallery');
            if ($path === '' || ! Storage::disk('public')->exists($path)) {
                return redirect()
                    ->route('content-management.gallery', ['tab' => $validated['return_tab'] ?? 'gallery'])
                    ->with('error', 'The replacement image could not be saved. Use a JPG, PNG, GIF, or WEBP file.');
            }
            $gallery->image = $path;
        }

        $gallery->save();
        if ($request->hasFile('image') && $previousImage !== (string) $gallery->image) {
            $this->deleteGalleryFile($previousImage);
        }
        PublicWebsiteData::flushCaches();

        return redirect()
            ->route('content-management.gallery', ['tab' => $validated['return_tab'] ?? 'gallery'])
            ->with('success', 'Gallery image updated.');
    }

    public function destroyGalleryImage(Request $request, Gallery $gallery)
    {
        $path = $gallery->image;
        $gallery->delete();
        $this->deleteGalleryFile($path);
        PublicWebsiteData::flushCaches();

        $tab = $request->input('return_tab') === 'home' ? 'home' : 'gallery';

        return redirect()
            ->route('content-management.gallery', ['tab' => $tab])
            ->with('success', 'Gallery image removed.');
    }

    private function homeGalleryImages()
    {
        return Gallery::query()
            ->where('show_on_home', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function homeGalleryCount(): int
    {
        return $this->homeGalleryImages()->count();
    }

    private function swapSortOrder(Gallery $current, Gallery $neighbor): void
    {
        $currentOrder = (int) $current->sort_order;
        $neighborOrder = (int) $neighbor->sort_order;
        if ($currentOrder === $neighborOrder) {
            $neighborOrder = $currentOrder + 1;
        }

        $current->sort_order = $neighborOrder;
        $neighbor->sort_order = $currentOrder;
        $current->save();
        $neighbor->save();
    }

    /**
     * @return array{byHash: array<string, list<Gallery>>, byName: array<string, list<Gallery>>}
     */
    private function galleryPhotoIndex(): array
    {
        $index = ['byHash' => [], 'byName' => []];
        foreach (Gallery::query()->photos()->get() as $row) {
            $absolute = $row->absoluteImagePath();
            $hash = $absolute !== null && is_file($absolute) ? md5_file($absolute) : null;
            $this->rememberGalleryRow($index, $row, $hash);
        }

        return $index;
    }

    /**
     * @param  array{byHash: array<string, list<Gallery>>, byName: array<string, list<Gallery>>}  $index
     */
    private function rememberGalleryRow(array &$index, Gallery $row, ?string $hash): void
    {
        if ($hash !== null && $hash !== '') {
            $index['byHash'][$hash][] = $row;
        }

        foreach (array_unique(array_filter([
            basename((string) $row->image),
            basename((string) ($row->storageRelativePath() ?? '')),
        ])) as $name) {
            $index['byName'][$name][] = $row;
        }
    }

    /**
     * @param  array{byHash: array<string, list<Gallery>>, byName: array<string, list<Gallery>>}  $index
     * @return list<Gallery>
     */
    private function matchingGalleryRows(array $index, ?string $hash, ?string $filename): array
    {
        if ($hash !== null && $hash !== '' && isset($index['byHash'][$hash])) {
            return $index['byHash'][$hash];
        }

        $filename = $filename !== null ? basename($filename) : '';
        if ($filename !== '' && isset($index['byName'][$filename])) {
            return $index['byName'][$filename];
        }

        $stem = $filename !== '' ? pathinfo($filename, PATHINFO_FILENAME) : '';
        if ($stem === '') {
            return [];
        }

        $stemMatches = [];
        foreach ($index['byName'] as $name => $rows) {
            if (pathinfo($name, PATHINFO_FILENAME) !== $stem) {
                continue;
            }
            foreach ($rows as $row) {
                $stemMatches[$row->id] = $row;
            }
        }

        return count($stemMatches) === 1 ? array_values($stemMatches) : [];
    }

    /**
     * Put an existing gallery photo on the home page when there is room.
     * A photo that is not a gallery row yet is linked by its current path.
     *
     * @param  array<int, true>  $touchedIds
     * @param  array<string, true>  $handledStems
     * @param  array{byHash: array<string, list<Gallery>>, byName: array<string, list<Gallery>>}  $index
     */
    private function linkExistingGalleryPhoto(
        string $relative,
        bool $wantHome,
        int $homeSlots,
        ?string $caption,
        int &$sort,
        int &$placedHome,
        int &$created,
        int &$existingPlaced,
        int &$alreadyHome,
        int &$alreadyGallery,
        array &$touchedIds,
        array &$handledStems,
        array &$index
    ): void {
        $absolute = storage_path('app/public/'.$relative);
        $hash = is_file($absolute) ? md5_file($absolute) : null;
        $matched = $this->matchingGalleryRows($index, $hash, basename($relative));
        if ($matched !== []) {
            $this->offerExistingGalleryRows(
                $matched,
                $wantHome,
                $homeSlots,
                $placedHome,
                $existingPlaced,
                $alreadyHome,
                $alreadyGallery,
                $touchedIds
            );
        } else {
            $onHome = $wantHome && $placedHome < $homeSlots;
            if ($onHome) {
                $placedHome++;
            }

            $gallery = new Gallery();
            $gallery->media_type = 'image';
            $gallery->caption = $caption;
            $gallery->sort_order = $sort;
            $gallery->show_on_home = $onHome;
            $gallery->image = $relative;
            $gallery->save();
            $this->rememberGalleryRow($index, $gallery, $hash);
            $touchedIds[$gallery->id] = true;
            $created++;
            $sort++;
        }

        $stem = pathinfo($relative, PATHINFO_FILENAME);
        if ($stem !== '') {
            $handledStems[$stem] = true;
        }
    }

    /**
     * @param  list<Gallery>  $rows
     * @param  array<int, true>  $touchedIds
     */
    private function offerExistingGalleryRows(
        array $rows,
        bool $wantHome,
        int $homeSlots,
        int &$placedHome,
        int &$existingPlaced,
        int &$alreadyHome,
        int &$alreadyGallery,
        array &$touchedIds
    ): void {
        foreach ($rows as $row) {
            if (isset($touchedIds[$row->id])) {
                return;
            }
        }

        foreach ($rows as $row) {
            if ($row->show_on_home) {
                $touchedIds[$row->id] = true;
                $alreadyHome++;

                return;
            }
        }

        $candidate = $rows[0] ?? null;
        if (! $candidate instanceof Gallery) {
            return;
        }

        if ($wantHome && $placedHome < $homeSlots) {
            $candidate->show_on_home = true;
            $candidate->save();
            $placedHome++;
            $existingPlaced++;
            $touchedIds[$candidate->id] = true;

            return;
        }

        $touchedIds[$candidate->id] = true;
        $alreadyGallery++;
    }

    private function findPublicPathByBasename(string $filename): ?string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, "\0")) {
            return null;
        }

        foreach ([
            'gallery/'.$filename,
            'images/gallery/'.$filename,
            'rooms/gallery/'.$filename,
            'rooms/'.$filename,
            'slides/'.$filename,
            'page-heroes/'.$filename,
            'images/restaurant/'.$filename,
        ] as $candidate) {
            if ($this->safePublicImagePath($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function safePublicImagePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    private function orderedGalleryImages()
    {
        return Gallery::query()
            ->photos()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function writeGalleryOrder($images): void
    {
        foreach ($images->values() as $index => $image) {
            $next = $index + 1;
            if ((int) $image->sort_order !== $next) {
                $image->sort_order = $next;
                $image->save();
            }
        }
    }

    private function deleteGalleryFile(?string $path): void
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || ! str_starts_with($path, 'gallery/')) {
            return;
        }

        $stillUsed = Gallery::query()->where('image', $path)->exists();
        if ($stillUsed) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function updateHomeGalleryFeatures(Request $request, AggregatedGalleryService $galleryService)
    {
        $request->validate([
            'featured_keys' => 'nullable|array|max:3',
            'featured_keys.*' => 'string|max:120',
        ]);

        try {
            $galleryService->syncHomeFeatured($request->input('featured_keys', []));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Home page gallery updated successfully.');
    }

    public function storeGallery(Request $request)
    {
        if ($request->media_type === 'image') {
            $files = $request->file('images');
            if (empty($files)) {
                $files = $request->hasFile('image') ? [$request->file('image')] : [];
            }
            if (!is_array($files)) {
                $files = [$files];
            }
            Storage::disk('public')->makeDirectory('gallery');
            $count = 0;
            $caption = $request->input('caption');
            $category = $request->input('category');
            $sort = (int) Gallery::query()->max('sort_order') + 1;
            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $path = store_optimized_image($file, 'gallery');
                if ($path === '' || ! Storage::disk('public')->exists($path)) {
                    continue;
                }
                $gallery = new Gallery();
                $gallery->media_type = 'image';
                $gallery->category = $category;
                $gallery->caption = $caption;
                $gallery->image = $path;
                $gallery->sort_order = $sort;
                $gallery->show_on_home = false;
                $gallery->save();
                $count++;
                $sort++;
            }
            if ($count === 0) {
                return redirect()->back()->with('error', 'Please select at least one JPG, PNG, GIF, or WEBP image.');
            }
            PublicWebsiteData::flushCaches();
            return redirect()->back()->with('success', $count === 1
                ? 'Gallery image added successfully.'
                : $count . ' gallery images added successfully.');
        }

        $gallery = new Gallery();
        $gallery->media_type = $request->media_type;
        $gallery->category = $request->category;
        $gallery->caption = $request->caption;

        if ($request->hasFile('video')) {
            $gallery->video_path = $request->file('video')->store('gallery/videos', 'public');
        }
        if ($request->youtube_link) {
            $gallery->youtube_link = $request->youtube_link;
        }
        if ($request->hasFile('thumbnail')) {
            $gallery->thumbnail = store_optimized_image($request->file('thumbnail'), 'gallery');
        }

        $gallery->save();

        return redirect()->back()->with('success', 'Gallery item added successfully');
    }

    // Slideshow Management
    public function slideshow()
    {
        $slides = Slide::orderBy('sort_order')->orderBy('created_at')->orderBy('id')->get();
        $setting = Setting::first();

        return view('content-management.slideshow.index', compact('slides', 'setting'));
    }

    public function updateHomeHero(Request $request)
    {
        $validated = $request->validate([
            'home_hero_text_mode' => 'required|in:global,per_slide',
            'home_hero_headline' => 'nullable|string|max:255',
            'home_hero_lead' => 'nullable|string|max:2000',
        ]);

        $setting = Setting::first();
        if (! $setting) {
            return redirect()->back()->with('error', 'No settings record found.');
        }

        $setting->home_hero_text_mode = $validated['home_hero_text_mode'];
        $setting->home_hero_headline = filled($validated['home_hero_headline'] ?? null)
            ? trim($validated['home_hero_headline'])
            : null;
        $setting->home_hero_lead = filled($validated['home_hero_lead'] ?? null)
            ? trim($validated['home_hero_lead'])
            : null;
        $setting->save();

        return redirect()->back()->with('success', 'Homepage hero text updated successfully.');
    }

    public function storeSlide(Request $request)
    {
        $request->validate([
            'caption' => 'nullable|string|max:500',
            'button' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:255',
            'image' => 'required|image|max:2048',
        ]);

        $slide = new Slide();
        $slide->heading = $request->input('caption');
        $slide->subheading = null;
        $slide->button = $request->filled('button') ? $request->input('button') : null;
        $slide->link = $request->filled('link') ? $request->input('link') : null;
        $slide->media_type = 'image';
        $slide->sort_order = (int) Slide::max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $slide->image = store_optimized_image($request->file('image'), 'slides');
        }

        $slide->save();

        return redirect()->back()->with('success', 'Slide added successfully');
    }

    public function reorderSlides(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        foreach (array_values($request->input('order')) as $position => $id) {
            Slide::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['success' => true, 'message' => 'Slide order updated']);
    }

    public function updateSlide(Request $request, Slide $slide)
    {
        $request->validate([
            'caption' => 'nullable|string|max:500',
            'button' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:255',
            'image' => 'nullable|image|max:2048',
        ]);

        $slide->heading = $request->input('caption');
        $slide->subheading = null;
        $slide->button = $request->filled('button') ? $request->input('button') : null;
        $slide->link = $request->filled('link') ? $request->input('link') : null;
        $slide->media_type = 'image';

        if ($request->hasFile('image')) {
            if ($slide->image) {
                Storage::disk('public')->delete($slide->image);
            }
            $slide->image = store_optimized_image($request->file('image'), 'slides');
        }

        $slide->save();

        return redirect()->back()->with('success', 'Slide updated successfully');
    }

    public function deleteSlide(Slide $slide)
    {
        if ($slide->image) {
            Storage::disk('public')->delete($slide->image);
        }
        if ($slide->video_file) {
            Storage::disk('public')->delete($slide->video_file);
        }

        $slide->delete();

        return redirect()->back()->with('success', 'Slide deleted successfully');
    }

    // Page Heroes Management (aligned with config/page_heroes.php + main navigation)
    public function pageHeroes()
    {
        $definitions = config('page_heroes', []);

        foreach ($definitions as $slug => $meta) {
            $hero = PageHero::firstOrNew(['page_slug' => $slug]);
            if (! $hero->exists) {
                $hero->page_name = $meta['label'];
                $hero->is_active = true;
                $hero->save();
            } elseif ($hero->page_name !== $meta['label']) {
                $hero->page_name = $meta['label'];
                $hero->save();
            }
        }

        $order = array_keys($definitions);
        $pageHeroes = PageHero::query()
            ->whereIn('page_slug', $order)
            ->get()
            ->sortBy(function ($h) use ($order) {
                $i = array_search($h->page_slug, $order, true);

                return $i === false ? 1000 + $h->id : $i;
            })
            ->values();

        $heroDefinitions = $definitions;

        return view('content-management.page-heroes.index', compact('pageHeroes', 'heroDefinitions'));
    }

    public function updatePageHero(Request $request, $id)
    {
        try {
            $request->validate([
                'background_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'caption' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
                // Remove is_active from validation - we'll handle it manually
            ]);

            $pageHero = PageHero::findOrFail($id);

            if ($request->boolean('remove_background_image') && $pageHero->background_image) {
                Storage::disk('public')->delete($pageHero->background_image);
                $pageHero->background_image = null;
            }

            if ($request->hasFile('background_image')) {
                // Delete old image if exists
                if ($pageHero->background_image) {
                    Storage::disk('public')->delete($pageHero->background_image);
                }

                // Ensure directory exists
                if (! Storage::disk('public')->exists('page-heroes')) {
                    Storage::disk('public')->makeDirectory('page-heroes');
                }

                // Store new image
                $imagePath = store_optimized_image($request->file('background_image'), 'page-heroes');
                $pageHero->background_image = $imagePath;
            }

            // Update other fields - always update even if empty
            $pageHero->caption = $request->input('caption', '');
            $pageHero->description = $request->input('description', '');
            
            // Handle checkbox - if not present in request, it's false
            $pageHero->is_active = $request->has('is_active') && $request->input('is_active') !== null ? true : false;
            
            $saved = $pageHero->save();
            
            // Log for debugging
            \Log::info('Page Hero Update', [
                'id' => $pageHero->id,
                'has_image' => $request->hasFile('background_image'),
                'image_path' => $pageHero->background_image,
                'caption' => $pageHero->caption,
                'saved' => $saved
            ]);

            if ($saved) {
                return redirect()->back()->with('success', 'Page hero updated successfully');
            } else {
                return redirect()->back()->with('error', 'Failed to update page hero. Please try again.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Validation failed. Please check your input.');
        } catch (\Exception $e) {
            \Log::error('Page Hero Update Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}
