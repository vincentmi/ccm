<?php
/**
 * use for Laravel
 */
namespace CCM\Laravel;

use CCM\Context;

/**
 * Class CCMService
 * @codeCoverageIgnore
 * @package CCM\Laravel
 */
class CCMService extends Context
{
    private $app ;
    public function __construct($app)
    {
        $this->app = $app;
    }
}
