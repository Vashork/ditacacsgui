<?php

declare(strict_types=1);

ini_set('max_execution_time', 300);
set_time_limit(300);
ini_set('memory_limit', '1024M');

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require __DIR__ . '/../constants.php';
require __DIR__ . '/../app/Validation/initValidation.php';
initRespectValidationFactory();

use Slim\App;
use Slim\Factory\AppFactory;
use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

// Create DI Container
$container = new Container();

/**
 * Compute the current HA role from the on-disk yaml (file-based, not container-based,
 * so it can be called before any controller is resolved).
 * Returns: 'master' | 'slave' | 'standalone'
 */
$haRole = static function (): string {
    $mainFile = '/opt/tgui_data/ha/ha-settings.yaml';
    if (!file_exists($mainFile)) {
        return 'standalone';
    }
    $cfg = \Symfony\Component\Yaml\Yaml::parseFile($mainFile);
    $role = $cfg['role'] ?? 0;
    return $role === 1 ? 'master' : ($role === 2 ? 'slave' : 'standalone');
};

/**
 * Slave DB password is the slave PSK by design (read from ha-settings.yaml).
 */
$slavePsk = static function (): string {
    $mainFile = '/opt/tgui_data/ha/ha-settings.yaml';
    if (!file_exists($mainFile)) {
        return '';
    }
    $cfg = \Symfony\Component\Yaml\Yaml::parseFile($mainFile);
    return ($cfg['role'] ?? 0) === 2 ? (string) ($cfg['psk_s'] ?? '') : '';
};

// Register database connection (a SINGLE shared Capsule instance)
$container->set('db', function () use ($haRole, $slavePsk) {
    $capsule = new Capsule();

    $driver = $_ENV['DB_DRIVER'] ?? 'mysql';

    if ($driver === 'sqlite') {
        // DEV-ONLY: use SQLite files (no MySQL needed for local smoke-testing).
        $base   = __DIR__ . '/../storage/sqlite';
        if (!is_dir($base)) { @mkdir($base, 0777, true); }
        $defFile = $base . '/tgui.sqlite';
        $logFile = $base . '/tgui_log.sqlite';

        // Create empty files so SQLiteConnector doesn't throw "does not exist".
        if (!file_exists($defFile)) { touch($defFile); }
        if (!file_exists($logFile)) { touch($logFile); }

        $capsule->addConnection(['driver' => 'sqlite', 'database' => $defFile, 'prefix' => '', 'foreign_key_constraints' => false], 'default');
        $capsule->addConnection(['driver' => 'sqlite', 'database' => $logFile, 'prefix' => '', 'foreign_key_constraints' => false], 'logging');

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Build schema + seed on first run (when api_users table is missing).
        $tables = $capsule->connection('default')->getSchemaBuilder()->getAllTables();
        if (!in_array('api_users', array_column($tables, 'name'), true) && !in_array('api_users', $tables, true)) {
            \tgui\SchemaBuilderSqlite::build($defFile);
        }

        return $capsule;
    }

    $role = $haRole();
    $isSlave = ($role === 'slave');

    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST'] ?? 'localhost',
        'database'  => $_ENV['DB_DATABASE'] ?? 'tgui',
        'username'  => $isSlave ? 'tgui_ro' : ($_ENV['DB_USERNAME'] ?? 'tgui_user'),
        'password'  => $isSlave ? $slavePsk() : ($_ENV['DB_PASSWORD'] ?? ''),
        'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATE'] ?? 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ], 'default');

    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST'] ?? 'localhost',
        'database'  => $_ENV['DB_DATABASE_LOG'] ?? 'tgui_log',
        'username'  => $isSlave ? 'tgui_ro' : ($_ENV['DB_USERNAME'] ?? 'tgui_user'),
        'password'  => $isSlave ? $slavePsk() : ($_ENV['DB_PASSWORD'] ?? ''),
        'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATE'] ?? 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ], 'logging');

    // Make Capsule the global + static resolver so BOTH
    //   $capsule->table(...)  (instance)
    //   Capsule::table(...)   (static pass-through, used 242x in controllers)
    // resolve to the same connections.
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // FIRST-RUN SCHEMA BOOTSTRAP (master/standalone only).
    //
    // On a brand-new MySQL database the tables do not exist yet, and the app's
    // only schema-init endpoint (GET /apicheck/database/?update=1) is auth-gated,
    // so the very first request could never create them -> POST /auth/signin/
    // 500'd with "Table 'tgui.ldap_bind' doesn't exist". We bootstrap the full
    // schema + default admin here, at boot, BEFORE any auth middleware runs.
    //
    // Idempotent: buildIfMissing() is a no-op once `api_users` exists, so this
    // only does real work on the very first request to a fresh DB.
    //
    // SLAVE EXEMPT: a replica must NOT create tables locally (it is read_only and
    // its schema is replicated from the master). Bootstrapping here would fail
    // on read_only and would also diverge from the master's GTID stream.
    if (!$isSlave) {
        \tgui\SchemaBuilderMysql::buildIfMissing();
    }

    return $capsule;
});

// Register validator
$container->set('validator', static function () {
    return new \tgui\Validation\Validator();
});

// Register auth
$container->set('auth', static function () {
    return new \tgui\Auth\Auth();
});

// Register controllers under BOTH a string key (used by Controller::__get for
// cross-controller calls like $this->APILoggingCtrl) AND the FQCN (used by
// routes.php: $container->get(SomeCtrl::class)). PHP-DI auto-wires the
// constructor (\DI\Container) for us.
$controllerMap = [
    'HomeController'       => \tgui\Controllers\HomeController::class,
    'AuthController'       => \tgui\Controllers\Auth\AuthController::class,
    'APIUsersCtrl'         => \tgui\Controllers\API\APIUsers\APIUsersCtrl::class,
    'APIUserGrpsCtrl'      => \tgui\Controllers\API\APIUserGrps\APIUserGrpsCtrl::class,
    'APISettingsCtrl'      => \tgui\Controllers\APISettings\APISettingsCtrl::class,
    'APIHACtrl'            => \tgui\Controllers\APIHA\APIHACtrl::class,
    'APINotificationCtrl'  => \tgui\Controllers\APINotification\APINotificationCtrl::class,
    'APIDevCtrl'           => \tgui\Controllers\APIDev\APIDevCtrl::class,
    'APIBackupCtrl'        => \tgui\Controllers\APIBackup\APIBackupCtrl::class,
    'APIUpdateCtrl'        => \tgui\Controllers\APIUpdate\APIUpdateCtrl::class,
    'APIDownloadCtrl'      => \tgui\Controllers\APIDownload\APIDownloadCtrl::class,
    'APILoggingCtrl'       => \tgui\Controllers\APILogging\APILoggingCtrl::class,
    'APICheckerCtrl'       => \tgui\Controllers\APIChecker\APICheckerCtrl::class,
    'TACDevicesCtrl'       => \tgui\Controllers\TAC\TACDevices\TACDevicesCtrl::class,
    'TACDeviceGrpsCtrl'    => \tgui\Controllers\TAC\TACDeviceGrps\TACDeviceGrpsCtrl::class,
    'TACUsersCtrl'         => \tgui\Controllers\TAC\TACUsers\TACUsersCtrl::class,
    'TACUserGrpsCtrl'      => \tgui\Controllers\TAC\TACUserGrps\TACUserGrpsCtrl::class,
    'TACACLCtrl'           => \tgui\Controllers\TAC\TACACL\TACACLCtrl::class,
    'TACServicesCtrl'      => \tgui\Controllers\TAC\TACServices\TACServicesCtrl::class,
    'TACCMDCtrl'           => \tgui\Controllers\TAC\TACCMD\TACCMDCtrl::class,
    'TACConfigCtrl'        => \tgui\Controllers\TACConfig\TACConfigCtrl::class,
    'TACExportCtrl'        => \tgui\Controllers\TAC\TACExport\TACExportCtrl::class,
    'TACImportCtrl'        => \tgui\Controllers\TAC\TACImport\TACImportCtrl::class,
    'TACReportsCtrl'       => \tgui\Controllers\TACReports\TACReportsCtrl::class,
    'ObjAddress'           => \tgui\Controllers\Obj\ObjAddress\ObjAddress::class,
    'MAVISLDAP'            => \tgui\Controllers\MAVIS\MAVISLDAP\MAVISLDAPCtrl::class,
    'MAVISLocal'           => \tgui\Controllers\MAVIS\MAVISLocal\MAVISLocalCtrl::class,
    'MAVISOTP'             => \tgui\Controllers\MAVIS\MAVISOTP\MAVISOTPCtrl::class,
    'MAVISSMS'             => \tgui\Controllers\MAVIS\MAVISSMS\MAVISSMSCtrl::class,
    'ConfManager'          => \tgui\Controllers\ConfManager\ConfManager::class,
    'ConfModels'           => \tgui\Controllers\ConfManager\ConfModels::class,
    'ConfDevices'          => \tgui\Controllers\ConfManager\ConfDevices::class,
    'ConfGroups'           => \tgui\Controllers\ConfManager\ConfGroups::class,
    'ConfigCredentials'    => \tgui\Controllers\ConfManager\ConfigCredentials::class,
    'ConfQueries'          => \tgui\Controllers\ConfManager\ConfQueries::class,
    'HAGeneral'            => \tgui\Controllers\APIHA\HAGeneral::class,
    'HAMaster'             => \tgui\Controllers\APIHA\HAMaster::class,
    'HASlave'              => \tgui\Controllers\APIHA\HASlave::class,
];

foreach ($controllerMap as $shortName => $fqcn) {
    // String key for Controller::__get() cross-controller calls.
    // Use a closure that resolves the FQCN via PHP-DI auto-wiring, so the
    // returned value is a CONTROLLER OBJECT (not the FQCN string).
    $container->set($shortName, static function (\DI\Container $c) use ($fqcn) {
        return $c->get($fqcn);
    });
    // NOTE: We do NOT register the FQCN explicitly. PHP-DI's AutoWiring source
    // resolves $container->get(X::class) by auto-constructing X (injecting \DI\Container).
    // Registering the FQCN with a closure that calls $c->get($fqcn) causes a circular dependency.
}

// Create Slim App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
// ErrorMiddleware is auto-added by AppFactory::create() with correct deps.

// Add JWT Authentication middleware (tuupola/slim-jwt-auth v3)
$jwtMiddleware = new \Tuupola\Middleware\JwtAuthentication([
    'secret'   => $_ENV['JWT_SECRET'] ?? '',
    'algorithm' => [$_ENV['JWT_ALGORITHM'] ?? 'HS256'],
    'secure'   => false,
    'path'     => '/auth',
    'ignore'   => [
        '/auth/signin/',
        '/auth/singin/',
        '/tacacs/user/change_passwd/change/',
        '/backup/download/',
        '/backup/upload/',
        '/ha/',
        '/export/',
        '/import/',
    ],
    'error'    => function (\Psr\Http\Message\ResponseInterface $response, array $arguments): \Psr\Http\Message\ResponseInterface {
        $data = [
            "status"  => "error",
            "message" => $arguments["message"] ?? "Unauthorized",
        ];
        return $response
            ->withStatus(401)
            ->withHeader("Content-Type", "application/json")
            ->withJson($data);
    },
]);
$app->add($jwtMiddleware);

// Add custom middleware
$app->add(new \tgui\Middleware\ChangeHeaderMiddleware($container));

// Load routes
require __DIR__ . '/../app/routes.php';
registerRoutes($app, $container);

return $app;
