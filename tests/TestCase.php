<?php

// tests/TestCase.php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // You can add common helper methods for your tests here.
    // For example, methods to create mock objects, set up database states (for integration tests), etc.

    protected function setUp(): void
    {
        parent::setUp();
        // Common setup for all tests, if any.
    }

    protected function tearDown(): void
    {
        // Common teardown for all tests, if any.
        parent::tearDown();
    }

    // Example helper method
    // protected function createApplication()
    // {
    //     // Logic to bootstrap your application for testing
    //     // This is highly dependent on your application structure
    //     // return require __DIR__.'/../app/bootstrap.php';
    // }
}
