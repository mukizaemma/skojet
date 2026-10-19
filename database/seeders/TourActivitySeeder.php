<?php

namespace Database\Seeders;

use App\Models\TourActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class TourActivitySeeder extends Seeder
{
    public function run(): void
    {
        $addedBy = User::query()->where('email', 'admin@iremetech.com')->value('id');

        $activities = [
            [
                'title' => 'Gorilla trekking',
                'slug' => 'gorilla-trekking',
                'sort_order' => 1,
                'cover_image' => 'slides/9zB0RhHZlXjATRoRZ2F2jzKymuCgFVZSuN4iBCqq.jpg',
                'description' => '<p>A morning with the mountain gorillas in Volcanoes National Park, and a private transfer from the hotel to Kinigi.</p><p>Permits are issued by the Rwanda Development Board and should be booked ahead. We arrange the drive so you leave after breakfast and come back to the suite when the trek is finished.</p>',
            ],
            [
                'title' => 'Golden monkey trekking',
                'slug' => 'golden-monkey-trekking',
                'sort_order' => 2,
                'cover_image' => 'slides/Wf50U9F5z7vpUrWV0KPptvqP0nTfUSsGnCqjX48c.jpg',
                'description' => '<p>A shorter trek through the bamboo of Volcanoes National Park, often paired with a quiet afternoon at the hotel.</p><p>Golden monkey permits are booked through the Rwanda Development Board. The walk is usually easier than a gorilla trek, and we time the transfer from Kinigi around your group.</p>',
            ],
            [
                'title' => 'Volcano hiking',
                'slug' => 'volcano-hiking',
                'sort_order' => 3,
                'cover_image' => 'slides/WVeDzFWNoVlMwn03xgoZoRhKuRiSG8eLIXfizLiT.jpg',
                'description' => '<p>A day on the peaks above Kinigi. Mount Bisoke, with its crater lake, is the usual hike from Musanze.</p><p>Longer routes, including Mount Karisimbi, can be planned on request. We arrange the transfer to the trailhead and leave the evening free in the garden.</p>',
            ],
            [
                'title' => 'Twin Lakes',
                'slug' => 'twin-lakes',
                'sort_order' => 4,
                'cover_image' => 'slides/NpwOE7XzRxgMY7ynzurS4KaHuqI39vTabd0xMQya.jpg',
                'description' => '<p>Lake Burera and Lake Ruhondo sit in the hills beyond Musanze, an easy scenic drive from the hotel.</p><p>Viewpoints, villages and still water make a gentle day between park visits, with dinner back at Skojet.</p>',
            ],
            [
                'title' => 'Musanze Caves',
                'slug' => 'musanze-caves',
                'sort_order' => 5,
                'cover_image' => 'slides/jsqqTUtvEPctbkMR0dZFLivv0ThqbEkSBqNA0nbx.jpg',
                'description' => '<p>Lava caves on the edge of Musanze, a short outing between days in the park.</p><p>The walk is guided and does not need a gorilla permit. We can arrange the transfer there and back.</p>',
            ],
            [
                'title' => 'Cultural village',
                'slug' => 'cultural-village',
                'sort_order' => 6,
                'cover_image' => 'slides/9zB0RhHZlXjATRoRZ2F2jzKymuCgFVZSuN4iBCqq.jpg',
                'description' => '<p>A community cultural visit near Kinigi, with dance, crafts and a look at life beside Volcanoes National Park.</p><p>It pairs well with the afternoon after a trek, or as its own unhurried day. We arrange the transfer from the hotel.</p>',
            ],
        ];

        foreach ($activities as $activity) {
            TourActivity::query()->firstOrCreate(
                ['slug' => $activity['slug']],
                array_merge($activity, [
                    'status' => 'Active',
                    'added_by' => $addedBy,
                ])
            );
        }
    }
}
