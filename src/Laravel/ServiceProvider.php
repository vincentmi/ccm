<?php

namespace CCM\Laravel;

use Illuminate\Support\ServiceProvider AS LaravelServiceProvider;

/**
 * Class ServiceProvider
 * @codeCoverageIgnore
 * @package CCM\Laravel
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * 在容器中注册绑定。
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ccm', function ($app) {
            return new CCMService($app);
        });
    }
}