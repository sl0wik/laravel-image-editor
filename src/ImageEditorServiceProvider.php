<?php

namespace Sl0wik\LaravelImageEditor;

use Illuminate\Support\ServiceProvider;

class ImageEditorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->setupConfig();
    }

    /**
     * Setup configuration.
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__.'/../config/images.php');

        $this->publishes([$source => config_path('images.php')]);

        $this->mergeConfigFrom($source, 'images');
    }
}
