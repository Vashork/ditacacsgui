<?php

declare(strict_types=1);

namespace tgui\Tests\Support;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tuupola\Middleware\JwtAuthentication;

/**
 * Test-only factory that assembles the REAL production Slim app surface
 * (production DI bindings, production middleware, production
 * registerRoutes) with both Illuminate connections pointed at the two
 * OS-temp SQLite files - WITHOUT requiring bootstrap/app.php, whose
 * sqlite branch hardcodes the repo storage path.
 *
 * Lifecycle (truthful, per process): app() builds the app ONCE
 * (idempotent); reset() clears per-case request state (session/cookies)
 * and reuses the SAME cached instance. A second construction in-process
 * would re-require single-require files (constants.php / initValidation.php)
 * and fatal, so the factory never rebuilds - a fresh phpunit run is the
 * fresh-process boundary.
 */
final class TestAppFactory
{
    private static ?App $app = null;
    private static bool $bootstrapped = false;

    /**
     * Build (once per process) and return the production-surface Slim app
     * wired to the two temp SQLite files.
     *
     * @throws RuntimeException fail-closed when the app cannot be built
     */
    public static function app(): App
    {
        if (self::$app !== null) {
            return self::$app;
        }
        self::build();
        return self::$app;
    }

    /**
     * Per-case reset: clear request state (session/cookies) so the next
     * dispatch starts fresh. The cached app is reused - see class docs for
     * why it is never rebuilt in-process.
     */
    public static function reset(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    /** @return ?App the cached instance (null before the first build) */
    public static function cachedApp(): ?App
    {
        return self::$app;
    }

    private static function build(): void
    {
        if (self::$bootstrapped) {
            throw new RuntimeException('TestAppFactory::build() called twice in one process; impossible by design (see app()/reset()).');
        }
        self::$bootstrapped = true;

        $apiRoot = dirname(__DIR__, 2);
        $def = (string) ($_ENV['TGUI_TEST_SQLITE_DEFAULT'] ?? '');
        $log = (string) ($_ENV['TGUI_TEST_SQLITE_LOG'] ?? '');
        if ($def === '' || $log === '') {
            throw new RuntimeException('TGUI_TEST_SQLITE_DEFAULT/TGUI_TEST_SQLITE_LOG env not set by tests/bootstrap.php.');
        }

        // --- Production single-require files (same order as bootstrap) ---
        // dotenv: same safeLoad as production (never overwrites existing).
        Dotenv::createImmutable($apiRoot)->safeLoad();
        // constants.php: defines APIVER/TACVER/HA*/TAC* ONCE (guarded).
        if (!defined('APIVER')) {
            require $apiRoot . '/constants.php';
        }
        // Validation factory: same require + init as production bootstrap.
        if (!function_exists('initRespectValidationFactory')) {
            require $apiRoot . '/app/Validation/initValidation.php';
            initRespectValidationFactory();
        }

        // --- DI container: the production bindings, verbatim surface ---
        $container = new \DI\Container();

        // 'db': the single shared Capsule, wired to the TWO TEMP files.
        // (Production bootstrap's sqlite branch, with the hardcoded repo
        // $base replaced by the harness temp paths.)
        $container->set('db', static function () use ($def, $log): Capsule {
            $capsule = new Capsule();
            $capsule->addConnection(['driver' => 'sqlite', 'database' => $def, 'prefix' => '', 'foreign_key_constraints' => false], 'default');
            $capsule->addConnection(['driver' => 'sqlite', 'database' => $log, 'prefix' => '', 'foreign_key_constraints' => false], 'logging');
            // setAsGlobal + bootEloquent: required because controllers use
            // the static Capsule::table(...) (242 call sites) and Eloquent
            // models as a static resolver.
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            // Production schema entry point (the same production class the
            // bootstrap's sqlite branch calls), run ONCE per process.
            if (!defined('TGUI_TEST_SCHEMA_BUILT')) {
                define('TGUI_TEST_SCHEMA_BUILT', true);
                \tgui\SchemaBuilderSqlite::build($def);
            }
            return $capsule;
        });

        // 'validator' + 'auth': production singletons.
        $container->set('validator', static function (): \tgui\Validation\Validator {
            return new \tgui\Validation\Validator();
        });
        $container->set('auth', static function (): \tgui\Auth\Auth {
            return new \tgui\Auth\Auth();
        });

        // Controller short names: the SAME map as the production bootstrap
        // (Controller::__get resolves cross-controller calls by string key;
        // PHP-DI autowires each FQCN's \DI\Container constructor).
        foreach (self::controllerMap() as $shortName => $fqcn) {
            $container->set($shortName, static function (\DI\Container $c) use ($fqcn) {
                return $c->get($fqcn);
            });
        }

        // --- Slim app + production middleware (same order as bootstrap) ---
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new JwtAuthentication([
            'secret' => $_ENV['JWT_SECRET'] ?? '',
            'algorithm' => [$_ENV['JWT_ALGORITHM'] ?? 'HS256'],
            'secure' => false,
            'path' => '/auth',
            'ignore' => [
                '/auth/signin/',
                '/auth/singin/',
                '/tacacs/user/change_passwd/change/',
                '/backup/download/',
                '/backup/upload/',
                '/ha/',
                '/export/',
                '/import/',
            ],
            'error' => function (\Psr\Http\Message\ResponseInterface $response, array $arguments): \Psr\Http\Message\ResponseInterface {
                $data = ['status' => 'error', 'message' => $arguments['message'] ?? 'Unauthorized'];
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json')->withJson($data);
            },
        ]));
        $app->add(new \tgui\Middleware\ChangeHeaderMiddleware($container));

        // --- Production routes (the real registerRoutes) ---
        require $apiRoot . '/app/routes.php';
        \registerRoutes($app, $container);

        self::$app = $app;
    }

    /** @return array<string,string> the production bootstrap's controller map */
    private static function controllerMap(): array
    {
        return [
            'HomeController' => \tgui\Controllers\HomeController::class,
            'AuthController' => \tgui\Controllers\Auth\AuthController::class,
            'APIUsersCtrl' => \tgui\Controllers\API\APIUsers\APIUsersCtrl::class,
            'APIUserGrpsCtrl' => \tgui\Controllers\API\APIUserGrps\APIUserGrpsCtrl::class,
            'APISettingsCtrl' => \tgui\Controllers\APISettings\APISettingsCtrl::class,
            'APIHACtrl' => \tgui\Controllers\APIHA\APIHACtrl::class,
            'APINotificationCtrl' => \tgui\Controllers\APINotification\APINotificationCtrl::class,
            'APIDevCtrl' => \tgui\Controllers\APIDev\APIDevCtrl::class,
            'APIBackupCtrl' => \tgui\Controllers\APIBackup\APIBackupCtrl::class,
            'APIUpdateCtrl' => \tgui\Controllers\APIUpdate\APIUpdateCtrl::class,
            'APIDownloadCtrl' => \tgui\Controllers\APIDownload\APIDownloadCtrl::class,
            'APILoggingCtrl' => \tgui\Controllers\APILogging\APILoggingCtrl::class,
            'APICheckerCtrl' => \tgui\Controllers\APIChecker\APICheckerCtrl::class,
            'TACDevicesCtrl' => \tgui\Controllers\TAC\TACDevices\TACDevicesCtrl::class,
            'TACDeviceGrpsCtrl' => \tgui\Controllers\TAC\TACDeviceGrps\TACDeviceGrpsCtrl::class,
            'TACUsersCtrl' => \tgui\Controllers\TAC\TACUsers\TACUsersCtrl::class,
            'TACUserGrpsCtrl' => \tgui\Controllers\TAC\TACUserGrps\TACUserGrpsCtrl::class,
            'TACACLCtrl' => \tgui\Controllers\TAC\TACACL\TACACLCtrl::class,
            'TACServicesCtrl' => \tgui\Controllers\TAC\TACServices\TACServicesCtrl::class,
            'TACCMDCtrl' => \tgui\Controllers\TAC\TACCMD\TACCMDCtrl::class,
            'TACConfigCtrl' => \tgui\Controllers\TACConfig\TACConfigCtrl::class,
            'TACExportCtrl' => \tgui\Controllers\TACExport\TACExportCtrl::class,
            'TACImportCtrl' => \tgui\Controllers\TACImport\TACImportCtrl::class,
            'TACReportsCtrl' => \tgui\Controllers\TACReports\TACReportsCtrl::class,
            'ObjAddress' => \tgui\Controllers\Obj\ObjAddress\ObjAddress::class,
            'MAVISLDAP' => \tgui\Controllers\MAVIS\MAVISLDAP\MAVISLDAPCtrl::class,
            'MAVISLocal' => \tgui\Controllers\MAVIS\MAVISLocal\MAVISLocalCtrl::class,
            'MAVISOTP' => \tgui\Controllers\MAVIS\MAVISOTP\MAVISOTPCtrl::class,
            'MAVISSMS' => \tgui\Controllers\MAVIS\MAVISSMS\MAVISSMSCtrl::class,
            'ConfManager' => \tgui\Controllers\ConfManager\ConfManager::class,
            'ConfModels' => \tgui\Controllers\ConfManager\ConfModels::class,
            'ConfDevices' => \tgui\Controllers\ConfManager\ConfDevices::class,
            'ConfGroups' => \tgui\Controllers\ConfManager\ConfGroups::class,
            'ConfigCredentials' => \tgui\Controllers\ConfManager\ConfigCredentials::class,
            'ConfQueries' => \tgui\Controllers\ConfManager\ConfQueries::class,
            'HAGeneral' => \tgui\Controllers\APIHA\HAGeneral::class,
            'HAMaster' => \tgui\Controllers\APIHA\HAMaster::class,
            'HASlave' => \tgui\Controllers\APIHA\HASlave::class,
        ];
    }

    /**
     * Dispatch a synthetic request through the full middleware stack.
     *
     * @return array{0:int, 1:string, 2:\Psr\Http\Message\ResponseInterface}
     */
    public static function dispatch(
        App $app,
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        array $headers = [],
        array $cookies = []
    ): array {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $path . ($query === [] ? '' : '?' . http_build_query($query)));
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        foreach ($cookies as $k => $v) {
            $request = $request->withCookieParams(array_merge($request->getCookieParams(), [$k => $v]));
        }
        if ($json !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody((new StreamFactory())->createStream((string) json_encode($json)));
        }
        $response = $app->handle($request);
        $body = (string) $response->getBody();
        return [$response->getStatusCode(), $body, $response];
    }
}
