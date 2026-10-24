<?php

namespace Database\Seeders;

use App\Models\WhyChooseUsItem;
use Illuminate\Database\Seeder;

class WhyChooseUsItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'title' => 'Four exclusive suites',
                'description' => 'Only eight guests at a time. Each suite has a lounge, private bathroom, balcony, outdoor shower and garden views.',
                'icon' => 'fa-solid fa-bed',
                'sort_order' => 1,
            ],
            [
                'title' => 'Facing the volcanoes',
                'description' => 'A quiet base near Kinigi, looking toward the peaks and a short drive from Volcanoes National Park.',
                'icon' => 'fa-solid fa-mountain',
                'sort_order' => 2,
            ],
            [
                'title' => 'Gardens and outdoor showers',
                'description' => 'Private outdoor space, balconies and gardens for unhurried mornings after a park day.',
                'icon' => 'fa-solid fa-leaf',
                'sort_order' => 3,
            ],
            [
                'title' => 'Coffee shop on the property',
                'description' => 'Specialty coffee, light bites and meals when you want them — or the kitchen, with help from our cook.',
                'icon' => 'fa-solid fa-mug-hot',
                'sort_order' => 4,
            ],
            [
                'title' => 'Personal care',
                'description' => 'A small team that looks after each stay from arrival to departure.',
                'icon' => 'fa-solid fa-heart',
                'sort_order' => 5,
            ],
            [
                'title' => 'Competitive rates',
                'description' => 'Clear nightly rates for each suite and meal plan, with offers shown when they are active.',
                'icon' => 'fa-solid fa-tag',
                'sort_order' => 6,
            ],
            [
                'title' => 'Made for privacy',
                'description' => 'A calm retreat for couples, honeymooners and guests who want space, quiet and a high-class stay.',
                'icon' => 'fa-solid fa-shield-heart',
                'sort_order' => 7,
            ],
        ];

        foreach ($items as $row) {
            WhyChooseUsItem::updateOrCreate(
                ['sort_order' => $row['sort_order']],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'icon' => $row['icon'],
                ]
            );
        }
    }
}
