<?php

namespace App\Providers;

use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // {{ }} escapes; @pkr(123456) prints "PKR 123,456" everywhere.
        Blade::directive('pkr', fn (string $expression) => "<?php echo \\".Money::class."::format({$expression}); ?>");

        View::share('shopUrl', config('atompay.shop_url'));
    }
}
