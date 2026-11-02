<?php

namespace App\Providers;

use App\Support\Business;
use App\Support\Format;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        // @peso($booking->total_amount) -> ₱1,250
        Blade::directive('peso', fn (string $expression) => "<?php echo \\".Format::class."::peso({$expression}); ?>");
    }
}
