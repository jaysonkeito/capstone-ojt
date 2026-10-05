<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Policies\InternPolicy;
use Illuminate\Notifications\ChannelManager;
use App\Models\Office;
use App\Models\AuditLog;
use App\Models\OjtEnrollment;
use App\Models\OjtLog;
use App\Models\OjtSetting;
use App\Models\DocumentTemplate;
use App\Models\LogRequest;
use App\Models\TimesheetCertification;
use App\Models\PlacementRequest;
use App\Models\CompletionRecommendation;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
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
        // Activity trail — every create/update/delete on the tracked records
        // lands in audit_logs with the acting user, for the System Admin's
        // activity log page.
        foreach ([
            User::class,
            OjtLog::class,
            OjtEnrollment::class,
            Office::class,
            DocumentTemplate::class,
            LogRequest::class,
            TimesheetCertification::class,
            PlacementRequest::class,
            CompletionRecommendation::class,
            OjtSetting::class,
        ] as $audited) {
            $audited::observe(AuditObserver::class);
        }

        // Sign-ins and sign-outs belong on the same trail. The subject is a
        // stand-in settings row so the trail line reads consistently.
        Event::listen(Login::class, fn ($event) => AuditLog::record('logged-in', new OjtSetting(), [], $event->user));
        Event::listen(Logout::class, fn ($event) => AuditLog::record('logged-out', new OjtSetting(), [], $event->user));
        // Policy naming convention maps models to same-named policies; the
        // monitor-role rules live on InternPolicy, keyed to the User model.
        Gate::policy(User::class, InternPolicy::class);

        // "fcm" delivers notifications to the Android app's registered
        // devices. The channel itself is a no-op until Firebase credentials
        // are configured (services.fcm.credentials), so test runs and fresh
        // deployments never break on it.
        Notification::resolved(function (ChannelManager $manager) {
            $manager->extend('fcm', fn () => app(FcmChannel::class));
        });

        // The layout's Notifications link needs the recipient's recent
        // notifications and unread count on every page that renders it.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('unreadNotificationCount', $user ? $user->unreadNotifications()->count() : 0);
        });
    }
}
