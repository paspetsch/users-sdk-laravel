<?php

namespace Compredict\User\Providers;

use App\Authentication\CompredictUserProvider;
use App\Auth\Models\User;
use App\Auth\Providers\UserProvider;
use Auth;
use Compredict\API\Users\Client as Client;
use Illuminate\Support\ServiceProvider;
use \Illuminate\Filesystem\Filesystem;

class CompredictAuthServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new Service
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct($app, Filesystem $files)
    {
        parent::__construct($app);

        $this->files = $files;
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('compredict_users', function ($app) {
            $config = $app->make('config')->get('compredict');
            $usersConfig = $config['users'];
            $cp_user_client = Client::getInstance($usersConfig['admin_key']);
            $cp_user_client->failOnError($usersConfig['fail_on_error']);
            return $cp_user_client;
        });

        $this->app->alias('compredict_users', 'Compredict\API\Users\Client');

        Auth::provider('compredict', function ($app, array $config) {
            return new CompredictUserProvider();
        });

        // register User class and User Provider
        $this->app->bind('Compredict\User\Auth\Models\User', function ($app) {
            return new User();
        });

        // add custom guard provider
        Auth::provider('compredict', function ($app, array $config) {
            return new UserProvider($app->make('Compredict\User\Auth\Models\User'));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();
        $this->publishRoutes();
        $this->publishViews();
        $this->publishUser();
    }

    protected function publishConfig()
    {
        $source = dirname(__DIR__) . '/config/compredict.php';
        $this->publishes([$source => config_path('compredict.php')]);
        $this->mergeConfigFrom($source, 'compredict');
    }

    protected function publishRoutes()
    {
        $source = dirname(__DIR__) . '/src/Auth/Routes/web.php';
        $this->loadRoutesFrom($source);
    }

    protected function publishViews()
    {
        $source = dirname(__DIR__) . '/src/Auth/Views/auth';
        $this->loadViewsFrom($source, 'auth');
    }

    protected function publishUser()
    {
        $filepath = dirname(__DIR__) . '/src/Auth/Stubs/User.stub';
        $stub = $this->files->get($filepath);
        $location = app_path()+"\User.php";
        $this->files->put($location, $stub);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['compredict_users', 'Compredict\API\Users\Client'];
    }
}
