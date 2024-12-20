<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // For SQLite, we need to handle migrations differently
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Drop all tables
            DB::statement('PRAGMA foreign_keys=OFF');
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            foreach ($tables as $table) {
                if ($table->name !== 'sqlite_sequence') {
                    DB::statement("DROP TABLE IF EXISTS {$table->name}");
                }
            }
            
            // Run migrations
            Artisan::call('migrate');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            Artisan::call('migrate:fresh');
        }
    }
}
