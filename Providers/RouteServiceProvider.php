<?php

namespace Modules\ApexFlightOps\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\ApexFlightOps\Http\Middleware\ResponseHeaders;

/**
 * Register the routes required for your module here
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'Modules\ApexFlightOps\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @param  Router $router
     * @return void
     */
    public function before(Router $router)
    {
        //
    }

    /**
     * Define the routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     *
     * @return void
     */
    public function map(Router $router)
    {
        $this->registerWebRoutes();
        $this->registerAdminRoutes();
        $this->registerApiRoutes();
    }

    /**
     *
     */
    protected function registerWebRoutes(): void
    {
        $config = [
            'as'         => 'apexflightops.',
            'prefix'     => 'apex',
            'namespace'  => $this->namespace.'\Frontend',
            'middleware' => ['web'],
        ];

        Route::group($config, function() {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/web.php');
        });
    }

    protected function registerAdminRoutes(): void
    {
        $config = [
            'as'         => 'admin.apexflightops.',
            'prefix'     => 'admin/apexflightops',
            'namespace'  => $this->namespace.'\Admin',
            'middleware' => ['web', 'role:admin'],
        ];

        Route::group($config, function() {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/admin.php');
        });
    }

    /**
     * Register any API routes your module has. Remove this if you aren't using any
     */
    protected function registerApiRoutes(): void
    {
        $config = [
            'as'         => 'api.apexflightops.',
            'prefix'     => 'api/apexflightops',
            'namespace'  => $this->namespace.'\Api',
            'middleware' => ['api', ResponseHeaders::class],
        ];

        Route::group($config, function() {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/api.php');
        });
    }
}
