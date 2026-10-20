<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraItem extends Model
{
    public const KINDS = [
        'food' => ['label' => 'Food', 'category' => 'restaurant'],
        'drink' => ['label' => 'Drink', 'category' => 'restaurant'],
        'coffee' => ['label' => 'Coffee', 'category' => 'restaurant'],
        'laundry' => ['label' => 'Laundry', 'category' => 'service'],
        'massage' => ['label' => 'Massage', 'category' => 'service'],
        'transport' => ['label' => 'Transport', 'category' => 'service'],
        'tour_guide' => ['label' => 'Tour guide', 'category' => 'service'],
    ];

    public const CATEGORIES = [
        'restaurant' => 'Restaurant',
        'service' => 'Services',
    ];

    protected $fillable = [
        'name',
        'category',
        'kind',
        'price',
        'price_rwf',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'price_rwf' => 'float',
    ];

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind]['label'] ?? ucfirst((string) $this->kind);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}
