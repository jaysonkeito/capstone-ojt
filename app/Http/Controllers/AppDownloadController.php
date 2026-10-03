<?php

namespace App\Http\Controllers;

/**
 * The public "Get the app" page linked from the mobile-browser install
 * banner. The APK itself lives outside git: the server either streams it
 * from public/downloads (drop app-release.apk there after a build) or
 * redirects to APP_APK_URL from .env — which later becomes the Play Store
 * listing without touching the banner.
 */
class AppDownloadController extends Controller
{
    public function show()
    {
        $apkPath = public_path('downloads/ojt-tracker.apk');
        $apkUrl = config('services.app.apk_url');

        $ready = is_file($apkPath) || filled($apkUrl);

        return view('download', [
            'ready' => $ready,
            'direct' => is_file($apkPath)
                ? asset('downloads/ojt-tracker.apk')
                : $apkUrl,
        ]);
    }
}
