<?php

namespace Raditor\DesignPattern;

use Illuminate\Support\ServiceProvider;
use Raditor\DesignPattern\Commands\RepositoryCommand;
use Raditor\DesignPattern\Commands\ServiceCommand;

class DesignPatternProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->commands([
            ServiceCommand::class,
            RepositoryCommand::class
        ]);
    }
}
