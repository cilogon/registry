<?php
declare(strict_types=1);

namespace SqlConnector;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Event\EventInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\RouteBuilder;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\StringUtilities;

/**
 * Plugin for SqlConnector
 */
class SqlConnectorPlugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // PAR-SqlProvisioner-5 When data in a reference table is updated,
        // Reference Data is resynced.

        // The SQL Provisioner Event Listener updates Reference Data for
        // reference models that are not otherwise provisionable.
        // We register here rather than in events() or eventListeners()
        // so we can just attach to the event managers of the tables we
        // care about, rather than every table in the application.
        
        foreach(['ExternalIdentitySources', 'TermsAndConditions'] as $t) {
            FactoryLocator::get('Table')->get($t)->getEventManager()->on(
                'Model.afterSave',
                function(EventInterface $event, $entity) {
                    // Note we'll get called twice for each save, once for the active
                    // record and once for the (newly created) Changelog archive record.
                    // We only care about the active record.

                    $clkey = $entity->changelogAttributeName();

                    if($entity->$clkey == null) {
                        // This is the active record, find the CO and see if there
                        // are any SqlProvisioners that need to have their reference
                        // data resynced.

                        $Table = $event->getSubject();
                        $coId = $Table->calculateCoForRecord($entity);

                        $ProvisioningTargetsTable = TableRegistry::getTableLocator()->get(
                            'ProvisioningTargets'
                        );

                        $cfgs = $ProvisioningTargetsTable->find()
                                                         ->where([
                                                            'plugin' => 'SqlConnector.SqlProvisioners',
                                                            'co_id' => $coId,
                                                            'status' => SuspendableStatusEnum::Active
                                                         ])
                                                         ->contain(['SqlProvisioners'])
                                                         ->all();

                        // We now have the (possible empty) set of SQL Provisioners
                        // in the same CO as the $entity of interest.

                        foreach($cfgs as $cfg) {
                            Log::info(
                                "PAR-SqlProvisioner-5 Resyncing SqlProvisioner " . $cfg->sql_provisioner->id . " reference data after save of " . StringUtilities::entityToClassName($entity) . " " . $entity->id,
                                ['scope' => ['rule']]
                            );

                            $ProvisioningTargetsTable->SqlProvisioners
                                                     ->syncReferenceData($cfg->sql_provisioner->id);    
                        }
                    }
                }
            );
        }
    }

    /**
     * Add routes for the plugin.
     *
     * If your plugin has many routes and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'SqlConnector',
            ['path' => '/sql-connector'],
            function (RouteBuilder $builder) {
                // Add custom routes here

                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add your middlewares here

        return $middlewareQueue;
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add your commands here

        $commands = parent::console($commands);

        return $commands;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        // Add your services here
    }
}
