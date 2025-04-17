<?php

namespace Luminarix\IconScoop\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Luminarix\IconScoop\IconScoopServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Luminarix\\IconScoop\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            IconScoopServiceProvider::class,
        ];
    }
}
