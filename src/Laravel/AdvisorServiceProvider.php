<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Psr\Log\LoggerInterface;
use Waynestate\Advisor\Config;
use Waynestate\Advisor\Server\Booted;
use Waynestate\Advisor\Server\Runtime;

/**
 * Registers ADVISOR with laravel/mcp (`php artisan mcp:start advisor`).
 *
 * Settings come from config/advisor.php, which reads env() there, because
 * Laravel does not always put .env values in getenv(). The same refusal
 * rules apply as for the stdio command: off unless enabled, never in
 * production.
 */
final class AdvisorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . '/config/advisor.php', 'advisor');

        $this->app->singleton(Config::class, function (Application $app): Config {
            $env = [];
            foreach ((array) $app->make('config')->get('advisor.env', []) as $name => $value) {
                // env() turns "true" into a boolean; Config reads strings.
                $env[(string) $name] = is_bool($value) ? ($value ? '1' : '0') : (is_scalar($value) ? (string) $value : null);
            }
            $env['APP_ENV'] = (string) $app->environment();

            return Config::fromEnv($env, $app->basePath());
        });

        $this->app->singleton(Booted::class, fn(Application $app): Booted => Runtime::boot(
            $app->make(Config::class),
            $app->make(LoggerInterface::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 2) . '/config/advisor.php' => $this->app->configPath('advisor.php')], 'advisor-config');

        if (!class_exists(Mcp::class) || $this->app->make(Config::class)->refusal() !== null) {
            return;
        }

        Mcp::local('advisor', AdvisorServer::class);
    }
}
