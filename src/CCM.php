<?php
/**
 * use for laravel
 */
namespace CCM;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Database\DatabaseManager
 * @see \Illuminate\Database\Connection
 */
class CCM extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ccm';
    }

}
