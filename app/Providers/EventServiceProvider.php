<?php

namespace App\Providers;

use App\Core\Events\AdminLoggedIn;
use App\Core\Events\AdminLoggedOut;
use App\Core\Events\FailedLoginAttempt;
use App\Core\Events\RoleAssigned;
use App\Core\Events\TicketStatusChanged;
use App\Core\Listeners\LogAdminActivity;
use App\Core\Listeners\LogSecurityEvent;
use App\Core\Listeners\SendNotification;
use App\Core\Listeners\HandleAdminLogout;
use App\Core\Listeners\HandleAdminLogin;
use App\Core\Listeners\SendFilamentNotification;
use App\Core\Listeners\SendDatabaseNotification;
use App\Events\OrderCreated;
use App\Listeners\SendNewOrderNotification;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SocialiteWasCalled::class => [
            AppleExtendSocialite::class . '@handle',
        ],
        AdminLoggedIn::class => [
            LogAdminActivity::class . '@handleAdminLoggedIn',
            SendFilamentNotification::class . '@handleAdminLoggedIn',
            SendDatabaseNotification::class . '@handleAdminLoggedIn',
        ],
        AdminLoggedOut::class => [
            LogAdminActivity::class . '@handleAdminLoggedOut',
        ],
        FailedLoginAttempt::class => [
            LogSecurityEvent::class,
            SendFilamentNotification::class . '@handleFailedLogin',
            SendDatabaseNotification::class . '@handleFailedLogin',
        ],
        TicketStatusChanged::class => [
            SendNotification::class,
        ],
        OrderCreated::class => [
            SendNewOrderNotification::class,
        ],
        Logout::class => [
            HandleAdminLogout::class . '@handle',
        ],
        Login::class => [
            HandleAdminLogin::class . '@handle',
        ],
    ];

    public function boot(): void
    {
        //
    }
}

