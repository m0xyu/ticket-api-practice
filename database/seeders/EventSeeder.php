<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Event::create([
            'name' => 'プレミアムライブ',
            'total_seats' => 100,
            'start_at' => now()->addDays(10),
            'end_at' => now()->addDays(10)->addHours(2),
        ]);
    }
}
