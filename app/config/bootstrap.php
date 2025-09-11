<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.8
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

/*
 * This file is loaded by your src/Application.php bootstrap method.
 * Feel free to extend/extract parts of the bootstrap process into your own files
 * to suit your needs/preferences.
 */

/*
 * Configure paths required to find CakePHP + general filepath constants
 */
require __DIR__ . DIRECTORY_SEPARATOR . 'paths.php';

/*
 * Bootstrap CakePHP
 * Currently all this does is initialize the router (without loading your routes)
 */
require CORE_PATH . 'config' . DS . 'bootstrap.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Datasource\ConnectionManager;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\Routing\Router;
use Cake\Utility\Security;
use function Cake\Core\env;

/*
 * Load global functions for collections, translations, debugging etc.
 */
require CAKE . 'functions.php';

/*
 * See https://github.com/josegonzalez/php-dotenv for API details.
 *
 * Uncomment block of code below if you want to use `.env` file during development.
 * You should copy `config/.env.example` to `config/.env` and set/modify the
 * variables as required.
 *
 * The purpose of the .env file is to emulate the presence of the environment
 * variables like they would be present in production.
 *
 * If you use .env files, be careful to not commit them to source control to avoid
 * security risks. See https://github.com/josegonzalez/php-dotenv#general-security-information
 * for more information for recommended practices.
*/
// if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
//     $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
//     $dotenv->parse()
//         ->putenv()
//         ->toEnv()
//         ->toServer();
// }

/*
 * Initializes default Config store and loads the main configuration file (app.php)
 *
 * CakePHP contains 2 configuration files after project creation:
 * - `config/app.php` for the default application configuration.
 * - `config/app_local.php` for environment specific configuration.
 */
try {
    Configure::config('default', new PhpConfig());
    Configure::load('app', 'default', false);
    // Read site specific configurations from the COmanage Registry local directory
    Configure::config('default', new PhpConfig(LOCAL . 'config' . DS));
    Configure::load('database', 'default');
} catch (\Exception $e) {
    exit($e->getMessage() . "\n");
}

/*
 * Load an environment local configuration file to provide overrides to your configuration.
 * Notice: For security reasons app_local.php **should not** be included in your git repo.
 *
if (file_exists(CONFIG . 'app_local.php')) {
    Configure::load('app_local', 'default');
}*/

/*
 * When debug = true the metadata cache should only last for a short time.
 */
if (Configure::read('debug')) {
    Cache::disable();
    //Configure::write('Cache._cake_model_.duration', '+2 minutes');
    //Configure::write('Cache._cake_translations_.duration', '+2 minutes');
    // disable router cache during development
    //Configure::write('Cache._cake_routes_.duration', '+2 seconds');
    Configure::write('DebugKit.forceEnable', true);
}

/*
 * Set the default server timezone. Using UTC makes time calculations / conversions easier.
 * Check https://php.net/manual/en/timezones.php for list of valid timezone strings.
 */
date_default_timezone_set(Configure::read('App.defaultTimezone'));

/*
 * Configure the mbstring extension to use the correct encoding.
 */
mb_internal_encoding(Configure::read('App.encoding'));

/*
 * Set the default locale. This controls how dates, number and currency is
 * formatted and sets the default language to use for translations.
 */
ini_set('intl.default_locale', Configure::read('App.defaultLocale'));

/*
 * Register application error and exception handlers.
 */
(new ErrorTrap(Configure::read('Error')))->register();
(new ExceptionTrap(Configure::read('Error')))->register();
/*
 * CLI/Command specific configuration.
 */
if (PHP_SAPI === 'cli') {
    // Set the fullBaseUrl to allow URLs to be generated in commands.
    // This is useful when sending email from commands.
    // Configure::write('App.fullBaseUrl', php_uname('n'));

    // Set logs to different files so they don't have permission conflicts.
    if (Configure::check('Log.debug')) {
        Configure::write('Log.debug.file', 'cli-debug');
    }
    if (Configure::check('Log.error')) {
        Configure::write('Log.error.file', 'cli-error');
    }
}

/*
 * Set the full base URL.
 * This URL is used as the base of all absolute links.
 * Can be very useful for CLI/Commandline applications.
 */
$fullBaseUrl = Configure::read('App.fullBaseUrl');
if (!$fullBaseUrl) {
    /*
     * When using proxies or load balancers, SSL/TLS connections might
     * get terminated before reaching the server. If you trust the proxy,
     * you can enable `$trustProxy` to rely on the `X-Forwarded-Proto`
     * header to determine whether to generate URLs using `https`.
     *
     * See also https://book.cakephp.org/5/en/controllers/request-response.html#trusting-proxy-headers
     */
    $trustProxy = false;

    $s = null;
    if (env('HTTPS') || ($trustProxy && env('HTTP_X_FORWARDED_PROTO') === 'https')) {
        $s = 's';
    }

    $httpHost = env('HTTP_HOST');
    if ($httpHost) {
        $fullBaseUrl = 'http' . $s . '://' . $httpHost;
    }
    unset($httpHost, $s);
}
if ($fullBaseUrl) {
    Router::fullBaseUrl($fullBaseUrl);
}
unset($fullBaseUrl);

/*
 * Apply the loaded configuration settings to their respective systems.
 * This will also remove the loaded config data from memory.
 */
Cache::setConfig(Configure::consume('Cache'));
ConnectionManager::setConfig(Configure::consume('Datasources'));
TransportFactory::setConfig(Configure::consume('EmailTransport'));
Mailer::setConfig(Configure::consume('Email'));
Log::setConfig(Configure::consume('Log'));
//Security::setSalt(Configure::consume('Security.salt'));

// Set the salt from the environment if available, else from the filesystem,
// and if the salt cannot be determined we're probably in SetupCommand,
// which will create it.
$salt = env('SECURITY_SALT', null);

if(is_null($salt)) {
  $securitySaltFile = LOCAL . "config" . DS . "security.salt";
  if(file_exists($securitySaltFile)) {
    $salt = file_get_contents($securitySaltFile);
  }
}

if($salt) {
  Security::setSalt($salt);
}

/*
 * Setup detectors for mobile and tablet.
 * If you don't use these checks you can safely remove this code
 * and the mobiledetect package from composer.json.
 */
ServerRequest::addDetector('mobile', function ($request) {
    $detector = new \Detection\MobileDetect();

    return $detector->isMobile();
});
ServerRequest::addDetector('tablet', function ($request) {
    $detector = new \Detection\MobileDetect();

    return $detector->isTablet();
});

/*
 * You can enable default locale format parsing by adding calls
 * to `useLocaleParser()`. This enables the automatic conversion of
 * locale specific date formats when processing request data. For details see
 * @link https://book.cakephp.org/5/en/core-libraries/internationalization-and-localization.html#parsing-localized-datetime-data
 */
// \Cake\Database\TypeFactory::build('time')->useLocaleParser();
// \Cake\Database\TypeFactory::build('date')->useLocaleParser();
// \Cake\Database\TypeFactory::build('datetime')->useLocaleParser();
// \Cake\Database\TypeFactory::build('timestamp')->useLocaleParser();
// \Cake\Database\TypeFactory::build('datetimefractional')->useLocaleParser();
// \Cake\Database\TypeFactory::build('timestampfractional')->useLocaleParser();
// \Cake\Database\TypeFactory::build('datetimetimezone')->useLocaleParser();
// \Cake\Database\TypeFactory::build('timestamptimezone')->useLocaleParser();

/*
 * Custom Inflector rules, can be set to correctly pluralize or singularize
 * table, model, controller names or whatever other string is passed to the
 * inflection functions.
 */
// \Cake\Utility\Inflector::rules('plural', ['/^(inflect)or$/i' => '\1ables']);
// \Cake\Utility\Inflector::rules('irregular', ['red' => 'redlings']);
// \Cake\Utility\Inflector::rules('uninflected', ['dontinflectme']);

\Cake\Utility\Inflector::rules('irregular', ['co_terms_and_condition' => 'co_terms_and_conditions']);
\Cake\Utility\Inflector::rules('irregular', ['cou' => 'cous']);
\Cake\Utility\Inflector::rules('irregular', ['meta' => 'meta']);

/*
 * Define some constants
 */
define("DEF_COMANAGE_CO_NAME", "COmanage");
// Default invitation validity, in minutes (used in various places, should probably be moved elsewhere)
define("DEF_INV_VALIDITY", 1440);

// Default window for reprovisioning on group validity change
define("DEF_GROUP_SYNC_WINDOW", 1440);

// Default window for Garbage Collection
define("DEF_GARBAGE_COLLECT_INTERVAL", 1440);

// Default global search limit
define("DEF_GLOBAL_SEARCH_LIMIT", 500);

// Default Search block limit
define("DEF_SEARCH_LIMIT", 25);