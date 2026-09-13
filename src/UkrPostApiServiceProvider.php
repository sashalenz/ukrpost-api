<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class UkrPostApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('ukrpost-api')->hasConfigFile();
    }
}
