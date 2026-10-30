<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Template;
use App\Models\TopupPackage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ContentFilterRuleSeeder::class);

        $categories = [
            ['bucin!', '#F6DDE8'], ['misuh!', '#F6E5D7'], ['maba!', '#DCEBFA'], ['bti!', '#E2F1E7'],
            ['curhat!', '#EEE5F6'], ['akademik!', '#DCEBFA'], ['organisasi!', '#E2F1E7'], ['umum!', '#F7ECCD'],
        ];

        foreach ($categories as $order => [$name, $color]) {
            Category::updateOrCreate(['slug' => str($name)->slug()], [
                'name' => $name,
                'badge_color' => $color,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }

        foreach ([['Starter', 5000, 10], ['Regular', 10000, 25], ['Creator', 25000, 70], ['Pro', 50000, 150]] as [$name, $price, $tokens]) {
            TopupPackage::updateOrCreate(['name' => $name], ['price_idr' => $price, 'tokens' => $tokens, 'is_active' => true]);
        }

        foreach (['instagram', 'x'] as $platform) {
            foreach (['text', 'photo'] as $kind) {
                Template::updateOrCreate(['name' => ucfirst($kind).' '.strtoupper($platform), 'platform' => $platform], [
                    'kind' => $kind,
                    'width' => $platform === 'x' ? 1200 : 1080,
                    'height' => $platform === 'x' ? 675 : 1350,
                    'theme' => [
                        'brand_name' => config('menfess.brand_name'),
                        'username' => config('menfess.social_username'),
                        'domain' => config('app.url'),
                        'background' => config('menfess.canvas_background'),
                        'accent' => config('menfess.canvas_accent'),
                    ],
                    'version' => 1,
                    'is_active' => true,
                ]);
            }
        }
    }
}
