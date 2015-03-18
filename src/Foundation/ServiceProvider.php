<?php namespace Consolle\Foundation;

use Illuminate\Support\Facades\Facade;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Lista de provider para trocar instancias
     * @var array
     */
    protected $instances = [
        'files'                 => '\NetForce\Framework\IO\Filesystem',
    ];

    /**
     * Create do provider
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        parent::__construct($app);

        // Trocar instancias
        foreach ($this->instances as $provider => $classServiceProvider)
        {
            // Limpar facade
            Facade::clearResolvedInstance($provider);

            // Trocar / Criar
            $this->app->instance($provider, new $classServiceProvider($app));
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Registrar providers
        /*
        foreach ($this->providers as $provider => $classServiceProvider)
        {
            $this->app->singleton($provider, function ($app) use ($classServiceProvider) {
                return new $classServiceProvider($app);
            });
        }/**/
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        $list = [];

        // Carregar lista de providers
        //$list = array_merge($list, array_keys($this->providers));

        return $list;
    }
}