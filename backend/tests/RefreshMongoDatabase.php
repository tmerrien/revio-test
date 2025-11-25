<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Refresh MongoDB Database Trait
 *
 * Alternative to RefreshDatabase for MongoDB (which doesn't support transactions)
 */
trait RefreshMongoDatabase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    /**
     * Clean the MongoDB database.
     */
    protected function cleanDatabase(): void
    {
        $connection = config('database.default');

        if ($connection === 'mongodb') {
            // Drop all collections in test database
            $collections = DB::connection('mongodb')->getMongoDB()->listCollections();

            foreach ($collections as $collection) {
                $collectionName = $collection->getName();

                // Skip system collections
                if (!str_starts_with($collectionName, 'system.')) {
                    DB::connection('mongodb')
                        ->getCollection($collectionName)
                        ->drop();
                }
            }
        }
    }
}
