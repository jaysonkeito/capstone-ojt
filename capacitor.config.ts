import type { CapacitorConfig } from '@capacitor/cli';

/**
 * The Android app is a native shell around the Laravel app: everything the
 * intern sees is served by the web server, so the whole product — Blade
 * views, session auth, the database — stays in this one codebase.
 *
 * The bundled web/ folder is an empty stub; `server.url` is what the WebView
 * actually loads. Point a dev build at the machine running `php artisan serve`
 * by exporting CAP_SERVER_URL before `npx cap sync`:
 *
 *   CAP_SERVER_URL=http://192.168.1.20:8000 npx cap sync
 *
 * http:// targets also flip on cleartext traffic, which Android blocks by
 * default; production always runs https so the setting stays off there.
 */
const devServerUrl = process.env.CAP_SERVER_URL;

const config: CapacitorConfig = {
  appId: 'com.norsubscojt.tracker',
  appName: 'OJT Tracker',
  webDir: 'cap-www',
  server: {
    url: devServerUrl ?? 'https://norsubscojt.online',
    cleartext: devServerUrl?.startsWith('http://') ?? false,
    // Marks every request from the app so the web side can tell the native
    // app apart from a mobile browser (the "install the app" banner must
    // never show inside the app itself).
    appendUserAgent: 'OJTTrackerApp/1.0',
  },
  android: {
    allowMixedContent: false,
    captureInput: true,
    webContentsDebuggingEnabled: false,
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 1200,
      launchAutoHide: true,
      backgroundColor: '#4f46e5',
      androidSplashResourceName: 'splash',
      androidScaleType: 'CENTER_CROP',
      showSpinner: false,
    },
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#4f46e5',
    },
  },
};

export default config;
