<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RestaurantMenuItem extends Model
{
    public const CATEGORIES = [
        'food' => 'Food',
        'beverages' => 'Beverages',
        'coffee' => 'Coffee',
    ];

    protected $fillable = [
        'category',
        'name',
        'description',
        'price_international',
        'price_local',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_international' => 'float',
        'price_local' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function reservationItems(): HasMany
    {
        return $this->hasMany(RestaurantReservationItem::class, 'menu_item_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function priceFor(string $tier): float
    {
        return $tier === 'local' ? (float) $this->price_local : (float) $this->price_international;
    }

    /**
     * Active items grouped in menu order. Empty categories are omitted.
     *
     * @return array<string, Collection<int, self>>
     */
    public static function publicGroups(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('restaurant_menu_items')) {
            return [];
        }

        $items = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $groups = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $group = $items->where('category', $category)->values();
            if ($group->isNotEmpty()) {
                $groups[$category] = $group;
            }
        }

        return $groups;
    }
}
