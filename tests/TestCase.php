<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.default') === 'mysql' && ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
            throw new \RuntimeException('Tests require a dedicated MySQL database whose name ends in _test.');
        }

        return $app;
    }
}
