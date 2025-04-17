<?php

namespace Luminarix\IconScoop;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IconScoopServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('iconscoop')
            ->hasConfigFile()
            ->hasAssets();
    }
}
