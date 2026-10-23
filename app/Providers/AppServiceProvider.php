<?php

namespace App\Providers;

use App\Models\About;
use App\Models\Gallery;
use App\Models\HotelContact;
use App\Models\Restaurant;
use App\Models\Restoimage;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Slide;
use App\Models\WhyChooseUsItem;
use App\Services\PublicWebsiteData;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensures helpers load even when Composer autoload "files" is stale on deploy (hotel_price, terms_content_html).
        require_once base_path('app/helpers.php');
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer([
            'layouts.frontbase',
            'layouts.includes.site-footer',
            'frontend.includes.page-hero-banner',
        ], function ($view) {
            $view->with(PublicWebsiteData::layoutShared());
        });

        $flushPublicCache = static function () {
            PublicWebsiteData::flushCaches();
        };
        foreach ([
            Room::class,
            Setting::class,
            About::class,
            WhyChooseUsItem::class,
            HotelContact::class,
            Slide::class,
            Gallery::class,
            Restaurant::class,
            Restoimage::class,
        ] as $model) {
            $model::saved($flushPublicCache);
            $model::deleted($flushPublicCache);
        }
    }
}
