<?php

namespace macropage\laravel_check24\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class DapartoF
 * @package macropage\laravel_daparto
 */
class Check24 extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string {
        return 'check24';
    }
}
