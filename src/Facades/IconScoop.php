<?php

namespace Luminarix\IconScoop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Luminarix\IconScoop\IconScoop
 */
class IconScoop extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Luminarix\IconScoop\IconScoop::class;
    }
}
