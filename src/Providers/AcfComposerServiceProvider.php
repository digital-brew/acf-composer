<?php

namespace Rafflex\AcfComposer\Providers;

use Illuminate\Support\ServiceProvider;

class AcfComposerServiceProvider extends ServiceProvider
{
    /**
     * Register and compose fields.
     *
     * @return void
     */
    public function register()
    {
        if (is_null(config('acf')) && config('app.preflight')) {
            app('files')->copy(realpath(__DIR__ . '/../config/acf.php'), app()->configPath('acf.php'));
        }

        collect(config('acf.blocks'))
            ->each(function ($block) {
                if (is_string($block)) {
                    $block = new $block($this);
                }

                $block->compose();
            });

        collect(config('acf.fields'))
            ->each(function ($field) {
                if (is_string($field)) {
                    $field = new $field($this);
                }

                $field->compose();
            });

        collect(config('acf.forms'))
            ->each(function ($field) {
                if (is_string($field)) {
                    $field = new $field($this);
                }

                $field->compose();
            });

        collect(config('acf.forms'))
            ->each(function ($field) {
                if (is_string($field)) {
                    $field = new $field($this);
                }

                $field->register();
            });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        //
    }
}
