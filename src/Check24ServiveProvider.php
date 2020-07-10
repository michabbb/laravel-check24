<?php

namespace macropage\laravel_check24;

use Illuminate\Support\ServiceProvider;
use macropage\laravel_check24\Console\Check24CommandListOrders;
use macropage\laravel_check24\Console\Check24CommandsetDone;

class Check24ServiveProvider extends ServiceProvider {

    protected string $configPath = __DIR__ . '/../config/check24.php';

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void {
        $this->mergeConfigFrom($this->configPath, 'check24');
        $this->app->singleton('check24', fn() => new check24());
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->configPath => config_path('check24.php')], 'config');
            $this->commands([
                                Check24CommandListOrders::class,
                                Check24CommandsetDone::class
                            ]);
        }
    }
}
