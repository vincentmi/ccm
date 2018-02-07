<?php
/**
 * use for Laravel
 */
namespace CCM\Laravel;

use Illuminate\Support\Facades\Facade AS LaravelFacade;

/**
 * @see CCM\Context
 * @codeCoverageIgnore
 */
class CCM extends LaravelFacade
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
