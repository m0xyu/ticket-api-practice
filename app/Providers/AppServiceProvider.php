<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\ServiceProvider;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Scribe;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = $this->app;
        Model::shouldBeStrict(!$app->isProduction());

        if (class_exists(Scribe::class)) {
            Scribe::beforeResponseCall(function (Request $request, ExtractedEndpointData $endpointData) {

                $routeNamesToSkip = ['login', 'register', 'sanctum.csrf-cookie'];

                if (in_array($endpointData->route->getName(), $routeNamesToSkip)) {
                    return;
                }

                $user = User::first() ?? User::factory()->create([
                    'email' => 'scribe-test@example.com',
                    'password' => bcrypt('password'),
                ]);

                Sanctum::actingAs($user, ['*']);
            });
        }
    }
}
