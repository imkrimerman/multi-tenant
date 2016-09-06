<?php namespace im\Tenantable;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use im\Tenantable\Events\SetActiveTenantEvent;
use im\Tenantable\Events\TenantNotResolvedEvent;
use im\Tenantable\Events\TenantResolvedEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Resolver
 *
 * @package im\Tenantable
 */
class Resolver
{
    /**
     * @var Application|null
     */
    private $app = null;

    /**
     * @var null
     */
    private $request = null;

    /**
     * @var null
     */
    private $activeTenant = null;

    /**
     * @var bool
     */
    private $consoleDispatcher = false;

    /**
     * Resolver constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     *
     */
    public function resolveTenant()
    {

        //save default db connection value
        if (config('tenantable.database.default', null) == null) {
            config()->set('tenantable.database.default', config('database.default'));
        }

        //register artisan events
        $this->registerTenantConsoleArgument();

        $this->registerConsoleStartEvent();

        $this->registerConsoleTerminateEvent();

        //resolve by request type
        if ($this->app->runningInConsole()) {
            $this->resolveByConsole();
        } else {
            $this->resolveByRequest();
        }
    }

    /**
     *
     */
    private function registerTenantConsoleArgument()
    {
        //register --tenant option for console
        $this->app['events']->listen(ArtisanStarting::class, function ($event) {
            $definition = $event->artisan->getDefinition();
            $definition->addOption(new InputOption('--tenant', null, InputOption::VALUE_OPTIONAL,
                'The tenant the command should be run for (id,uuid,domain).'));
            $event->artisan->setDefinition($definition);
            $event->artisan->setDispatcher($this->getConsolerDispatcher());
        });
    }

    /**
     * @return bool
     */
    private function getConsolerDispatcher()
    {
        if (!$this->consoleDispatcher) {
            $this->consoleDispatcher = app(EventDispatcher::class);
        }

        return $this->consoleDispatcher;
    }

    /**
     *
     */
    private function registerConsoleStartEvent()
    {
        //possibly disable the command
        $this->getConsolerDispatcher()->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
            $tenant = $event->getInput()->getParameterOption('--tenant', null);
            if (!is_null($tenant)) {
                if ($tenant == '*' || $tenant == 'all') {
                    $event->disableCommand();
                } else {
                    if ($this->isResolved()) {
                        $event->getOutput()->writeln('<info>Running command for ' . $this->getActiveTenant()->domain . '</info>');
                    } else {
                        $event->getOutput()->writeln('<error>Failed to resolve tenant</error>');
                        $event->disableCommand();
                    }
                }
            }
        });
    }

    /**
     * @return bool
     */
    public function isResolved()
    {
        return !is_null($this->getActiveTenant());
    }

    /**
     * @return null
     */
    public function getActiveTenant()
    {
        return $this->activeTenant;
    }

    /**
     * @param Tenant $activeTenant
     */
    public function setActiveTenant(Tenant $activeTenant)
    {
        $this->activeTenant = $activeTenant;
        $this->setDefaultConnection();

        config()->set('app.url', $this->getActiveTenant()->domain);

        event(new SetActiveTenantEvent($this->activeTenant));
    }

    /**
     *
     */
    private function registerConsoleTerminateEvent()
    {
        //run command on the terminate event instead
        $this->getConsolerDispatcher()->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {

            $tenant = $event->getInput()->getParameterOption('--tenant', null);
            if (!is_null($tenant)) {
                if ($tenant == '*' || $tenant == 'all') {
                    //run command for all
                    $command = $event->getCommand();
                    $input = $event->getInput();
                    $output = $event->getOutput();
                    $exitcode = $event->getExitCode();

                    $tenants = Tenant::all();
                    foreach ($tenants as $tenant) {
                        //set tenant
                        $this->setActiveTenant($tenant);
                        $event->getOutput()->writeln('<info>Running command for ' . $this->getActiveTenant()->domain . '</info>');
                        try {
                            $exitCode = $command->run($input, $output);
                        } catch (\Exception $e) {
                            $event = new ConsoleExceptionEvent($command, $input, $output, $e, $e->getCode());
                            $this->getConsolerDispatcher()->dispatch(ConsoleEvents::EXCEPTION, $event);

                            $e = $event->getException();

                            throw $e;
                        }
                    }

                    $event->setExitCode($exitcode);

                }
            }

        });
    }

    /**
     *
     */
    private function resolveByConsole()
    {

        $domain = (new ArgvInput())->getParameterOption('--tenant', null);

        //find tenant by primary domain
        $model = new Tenant();
        $tenant = $model->where('domain', '=', $domain)->first();
        if ($tenant instanceof Tenant) {
            $this->setActiveTenant($tenant);
            event(new TenantResolvedEvent($tenant));

            return;
        }

        //find by uuid
        $tenant = $model->where('uuid', '=', $domain)->first();
        if ($tenant instanceof Tenant) {
            $this->setActiveTenant($tenant);
            event(new TenantResolvedEvent($tenant));

            return;
        }

        //find by id
        $tenant = $model->where('id', '=', $domain)->first();
        if ($tenant instanceof Tenant) {
            $this->setActiveTenant($tenant);
            event(new TenantResolvedEvent($tenant));

            return;
        }

        //if were here the domain could not be found in the primary table
        $model = new Domain();
        $tenant = $model->where('domain', '=', $domain)->first();
        if ($tenant instanceof Domain) {
            $returnModel = $tenant->tenant;
            $this->setActiveTenant($returnModel);
            event(new TenantResolvedEvent($returnModel));

            return;
        }

        //if were here we haven't found anything?
        event(new TenantNotResolvedEvent($this));

        return;
    }

    /**
     *
     */
    private function resolveByRequest()
    {
        $this->request = $this->app->make(Request::class);
        $domain = $this->request->getHost();

        //find tenant by primary domain
        $model = new Tenant();
        $tenant = $model->whereDomain($domain)->first();
        if ($tenant instanceof Tenant) {
            $this->setActiveTenant($tenant);
            event(new TenantResolvedEvent($tenant));

            return;
        }

        //if were here the domain could not be found in the primary table
        $model = new Domain();
        $tenant = $model->whereDomain($domain)->first();
        if ($tenant instanceof Domain) {
            $returnModel = $tenant->tenant;
            $this->setActiveTenant($returnModel);
            event(new TenantResolvedEvent($returnModel));

            return;
        }

        //if were here we haven't found anything?
        event(new TenantNotResolvedEvent($this));

        return;
    }

    /**
     *
     */
    public function setDefaultConnection()
    {

        $tenant = $this->getActiveTenant();
        config()->set('database.connections.tenant.driver', $tenant->driver);
        config()->set('database.connections.tenant.host', $tenant->host);
        config()->set('database.connections.tenant.database', $tenant->database);
        config()->set('database.connections.tenant.username', $tenant->username);
        config()->set('database.connections.tenant.password', $tenant->password);
        if (!empty($tenant->prefix)) {
            $tenant->prefix .= '_';
        }
        config()->set('database.connections.tenant.prefix', $tenant->prefix);
        if ($tenant->driver == 'mysql') {
            config()->set('database.connections.tenant.strict', config('database.connections.mysql.strict'));
        }
        config()->set('database.connections.tenant.charset', 'utf8');
        config()->set('database.connections.tenant.collation', 'utf8_unicode_ci');

        $this->app['db']->purge('tenant');
        $this->app['db']->setDefaultConnection('tenant');
    }

    /**
     *
     */
    public function purgeTenantConnection()
    {
        $this->app['db']->setDefaultConnection(config('tenantable.database.default'));
    }

    /**
     *
     */
    public function reconnectTenantConnection()
    {
        $this->app['db']->setDefaultConnection('tenant');
    }

}
