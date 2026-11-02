<?php

namespace App\Providers;

use App\Events\PaymentPosted;
use App\Listeners\SendPaymentRecordedNotification;
use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Services\Accounting\Engine\DepreciationCalculator;
use App\Services\Accounting\Engine\RecognitionCalculator;
use App\Services\Shared\AuditService;
use App\Services\Shared\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped singletons: settings cache, audit mute flag, pure calculators.
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(DepreciationCalculator::class);
        $this->app->singleton(RecognitionCalculator::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction() && ! $this->app->runningInConsole() && ! $this->app->runningUnitTests());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Owners ("*") pass every gate; everything else goes through permissions/policies.
        Gate::before(fn (User $user) => $user->hasPermission('*') ? true : null);
        Gate::policy(Lead::class, LeadPolicy::class);

        Event::listen(PaymentPosted::class, SendPaymentRecordedNotification::class);

        // Shared hosting may terminate TLS before PHP; keep generated links (emails, assets) on https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
