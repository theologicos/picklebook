<?php

namespace App\Providers;

use App\Support\Business;
use App\Support\Format;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One business per install; settings are read lazily and once per request.
        $this->app->singleton(Business::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every view can use $business (name, hours, GCash number, Messenger link...).
        View::share('business', $this->app->make(Business::class));

        // 5 login attempts per minute for each mobile number + device, to slow down password guessing.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('mobile_number')).'|'.$request->ip()));

        // @peso($booking->total_amount) -> ₱1,250
        Blade::directive('peso', fn (string $expression) => "<?php echo \\".Format::class."::peso({$expression}); ?>");
    }
}
