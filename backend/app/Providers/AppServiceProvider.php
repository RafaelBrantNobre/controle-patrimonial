<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->limitarTentativasDeLogin();
    }

    /*
    | Até 5 tentativas de login por minuto para o mesmo e-mail no mesmo
    | computador. Dificulta adivinhar senhas (tarefa 2.4).
    | Usado em routes/api.php com ->middleware('throttle:login').
    */
    private function limitarTentativasDeLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $chave = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($chave)->response(fn () => response()->json([
                'message' => 'Muitas tentativas de entrada. Aguarde um minuto e tente de novo.',
            ], 429));
        });
    }
}
