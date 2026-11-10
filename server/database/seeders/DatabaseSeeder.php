<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed pure baseline vocabulary in strict foreign-key dependency order.
     */
    public function run(): void
    {
        $this->call([
            BaselineReferenceVocabularySeeder::class,
            InstitutionalCredentialSeeder::class,
        ]);
    }
}
