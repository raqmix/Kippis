<?php

namespace App\Providers;

use App\Support\Heartbeat;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
        // Register policies
        \Illuminate\Support\Facades\Gate::policy(\App\Core\Models\Admin::class, \App\Core\Policies\AdminPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Core\Models\Page::class, \App\Core\Policies\PagePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Core\Models\Channel::class, \App\Core\Policies\ChannelPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Core\Models\PaymentMethod::class, \App\Core\Policies\PaymentMethodPolicy::class);

        // Register observers
        \App\Core\Models\SupportTicket::observe(\App\Observers\SupportTicketObserver::class);
        \App\Core\Models\Order::observe(\App\Observers\OrderObserver::class);

        // Queue worker liveness ping — Looping fires every poll, even when idle,
        // so the system-health widget can tell a running-but-empty worker from
        // a dead one.
        Event::listen(Looping::class, fn () => Heartbeat::mark('queue'));

        // Share HTML direction for RTL support
        view()->composer('*', function ($view) {
            $locale = app()->getLocale();
            $direction = $locale === 'ar' ? 'rtl' : 'ltr';
            $view->with('htmlDir', $direction);
        });
    }
}
