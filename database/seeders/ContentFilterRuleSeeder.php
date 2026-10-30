<?php

namespace Database\Seeders;

use App\Models\ContentFilterRule;
use Illuminate\Database\Seeder;

class ContentFilterRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['jancok', 'profanity', 'censor'], ['jancuk', 'profanity', 'censor'], ['bangsat', 'profanity', 'censor'],
            ['brengsek', 'profanity', 'censor'], ['bajingan', 'insult', 'censor'], ['goblok', 'insult', 'censor'],
            ['tolol', 'insult', 'censor'], ['fuck', 'profanity', 'censor'], ['motherfucker', 'profanity', 'censor'],
            ['kontol', 'sexual_18', 'censor'], ['memek', 'sexual_18', 'censor'], ['ngentot', 'sexual_18', 'censor'],
            ['ngewe', 'sexual_18', 'censor'], ['bokep', 'sexual_18', 'censor'], ['colmek', 'sexual_18', 'censor'],
            ['anj1ng', 'profanity', 'censor'], ['k0ntol', 'sexual_18', 'censor'], ['m3mek', 'sexual_18', 'censor'],
            ['aku akan membunuhmu', 'other', 'review'], ['akan membunuh kamu', 'other', 'review'],
            ['bom di kampus', 'other', 'review'], ['membakar kampus', 'other', 'review'],
            ['sebar alamat rumah', 'other', 'review'],
        ];

        foreach ($rules as [$phrase, $category, $action]) {
            ContentFilterRule::firstOrCreate(['phrase' => $phrase], [
                'category' => $category,
                'action' => $action,
                'is_active' => true,
            ]);
        }
    }
}
