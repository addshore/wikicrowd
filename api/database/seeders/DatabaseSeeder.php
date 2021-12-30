<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        \App\Models\QuDepicts::create([
            'mediainfo_id' => 'M44206487', // https://commons.wikimedia.org/wiki/File:Fog_near_Baden,_Austria.JPG
            'depicts_id' => 'Q37477', // fog
            'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/50/Fog_near_Baden%2C_Austria.JPG/800px-Fog_near_Baden%2C_Austria.JPG',
        ]);
        \App\Models\QuDepicts::create([
            'mediainfo_id' => 'M1530829', // https://commons.wikimedia.org/wiki/File:Bakweri_house_at_foot_of_Fako.jpg
            'depicts_id' => 'Q37477', // fog
            'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/Bakweri_house_at_foot_of_Fako.jpg/800px-Bakweri_house_at_foot_of_Fako.jpg',
        ]);
        \App\Models\QuDepicts::create([
            'mediainfo_id' => 'M91768744', // https://commons.wikimedia.org/wiki/File:Foggy_morning_-_Flickr_-_Muffet.jpg
            'depicts_id' => 'Q37477', // fog
            'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e2/Foggy_morning_-_Flickr_-_Muffet.jpg/800px-Foggy_morning_-_Flickr_-_Muffet.jpg',
        ]);
    }
}
