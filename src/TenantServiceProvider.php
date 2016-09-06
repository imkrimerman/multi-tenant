<?php namespace im\MultiTenant;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Class TenantableServiceProvider
 *
 * @package im\MultiTenant
 */
class TenantableServiceProvider extends ServiceProvider
{

    /**
     *
     */
    public function register()
    {
        $this->app->singleton(Resolver::class, function ($app) {
            return new Resolver($app);
        });
    }

    /**
     * @param Resolver $resolver
     */
    public function boot(Resolver $resolver)
    {

        //resolve tenant, catch PDOExceptions to prevent errors during migration
        try {
            $resolver->resolveTenant();
        } catch (\PDOException $e) {
        }

    }

}
