<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Policies\InternPolicy;
use Illuminate\Notifications\ChannelManager;
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
