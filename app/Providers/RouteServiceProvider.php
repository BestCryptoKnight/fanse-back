<?php

namespace App\Providers;

use App\Models\Subscription;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        Route::bind('subscription', function ($id) {
            return Subscription::withTrashed()->find($id);
        });

        $this->routes(function () {
            $this->mapApiRoutes();
            $this->mapAdminRoutes();

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }

    protected function mapAdminRoutes()
    {
        Route::prefix('admin')
            ->middleware('api')
            ->namespace($this->namespace . '\Admin')
            ->group(base_path('routes/admin.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        $latest = config('app.version');
        $latest = substr($latest, 0, strpos($latest, '.'));

        // v1
        Route::prefix('v1')
            ->middleware('api')
            ->namespace($this->namespace . '\Api\v1')
            ->group(base_path('routes/api/v1.php'));

        // and latest version
        Route::prefix('latest')
            ->middleware('api')
            ->namespace($this->namespace . '\Api\v' . $latest)
            ->group(base_path('routes/api/v' . $latest . '.php'));
    }
}
