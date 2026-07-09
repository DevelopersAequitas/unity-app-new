<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/cities.json');

        if (! File::exists($path)) {
            $this->command->error("Cities data file not found at: {$path}");

            return;
        }

        $json = File::get($path);
        $cities = json_decode($json, true);

        if (! is_array($cities)) {
            $this->command->error("Invalid JSON format in {$path}");

            return;
        }

        $this->command->info('Seeding '.count($cities).' cities...');

        // Chunk inserts to prevent memory issues or query limits
        $chunks = array_chunk($cities, 500);

        foreach ($chunks as $chunk) {
            $dataToInsert = [];

            foreach ($chunk as $cityData) {
                // Check if city ID already exists to avoid duplication
                if (City::where('id', $cityData['id'])->exists()) {
                    continue;
                }

                $dataToInsert[] = [
                    'id' => $cityData['id'],
                    'name' => $cityData['name'],
                    'state' => $cityData['state'],
                    'district' => $cityData['district'],
                    'country' => $cityData['country'],
                    'country_code' => $cityData['country_code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($dataToInsert)) {
                City::insert($dataToInsert);
            }
        }

        $this->command->info('Cities seeded successfully!');
    }
}
