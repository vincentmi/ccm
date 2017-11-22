<?php
/**
 * use for Laravel
 */
namespace CCM\Laravel;

use CCM\Context;

class CCMService extends Context
{
    private $app ;
    public function __construct($app)
    {
        $this->app = $app;
    }
}
