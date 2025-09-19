<?php

namespace Database\Seeders;

use App\Models\BoardingHouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BoardingHouseSeeder extends Seeder
{
    public function run(): void
    {
        /*$items = [
            ['Kosan Mawar',  -6.200500, 106.816700, 'Jakarta Pusat', 1200000, ['wifi','ac']],
            ['Kosan Melati', -6.208800, 106.845600, 'Gambir',       1500000, ['wifi']],
            ['Kosan Kenanga',-6.220300, 106.802000, 'Slipi',        1000000, ['parkir','kipas']],
        ];
        foreach ($items as [$name,$lat,$lng,$addr,$price,$fac]) {
            BoardingHouse::create([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $addr,
                'price_month' => $price,
                'facilities' => $fac,
            ]);
        }*/
    }
}
