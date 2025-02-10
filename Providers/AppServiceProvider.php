<?php

namespace Modules\ApexFlightOps\Providers;

use App\Contracts\Modules\ServiceProvider;

/**
 * @package $NAMESPACE$
 */
class AppServiceProvider extends ServiceProvider
{
    private $moduleSvc;

    protected $defer = false;

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->moduleSvc = app('App\Services\ModuleService');

        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();

        $this->registerLinks();

        // Uncomment this if you have migrations
        // $this->loadMigrationsFrom(__DIR__ . '/../$MIGRATIONS_PATH$');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        //
    }

    /**
     * Add module links here
     */
    public function registerLinks(): void
    {
        // Show this link if logged in
        // $this->moduleSvc->addFrontendLink('ApexFlightOps', '/apexflightops', '', $logged_in=true);

        // Admin links:
        $this->moduleSvc->addAdminLink('ApexFlightOps', '/admin/apexflightops');
    }

    /**
     * Register config.
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('apexflightops.php'),
        ], 'apexflightops');

        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'apexflightops');
    }

    /**
     * Register views.
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/apexflightops');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath,], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return str_replace('default', setting('general.theme'), $path) . '/modules/apexflightops';
        }, \Config::get('view.paths')), [$sourcePath]), 'apexflightops');
    }

    /**
     * Register translations.
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/apexflightops');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'apexflightops');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../Resources/lang', 'apexflightops');
        }
    }
}
