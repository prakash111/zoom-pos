<?php

use App\Providers\NativeAppServiceProvider;

return [
    /**
     * The version of your app.
     * It is used to determine if the app needs to be updated.
     * Increment this value every time you release a new version of your app.
     */
    'version' => env('APP_VERSION', env('NATIVEPHP_APP_VERSION', '1.0.3')),

    /**
     * The ID of your application. This should be a unique identifier
     * usually in the form of a reverse domain name.
     * For example: com.nativephp.app
     */
    'app_id' => env('NATIVEPHP_APP_ID', 'com.zoomnearby.zoompos'),

    'app_name' => 'Zoom POS',

    /**
     * If your application allows deep linking, you can specify the scheme
     * to use here. This is the scheme that will be used to open your
     * application from within other applications.
     * For example: "nativephp"
     *
     * This would allow you to open your application using a URL like:
     * nativephp://some/path
     */
    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME', 'zoompos'),

    /**
     * The author of your application.
     */
    'author' => env('NATIVEPHP_APP_AUTHOR', 'Zoomnearby Digital Store'),

    /**
     * The copyright notice for your application.
     */
    'copyright' => env('NATIVEPHP_APP_COPYRIGHT'),

    /**
     * The description of your application.
     */
    'description' => env('NATIVEPHP_APP_DESCRIPTION', 'Complete multi-tenant point of sale and inventory management desktop suite.'),

    /**
     * The Website of your application.
     */
    'website' => env('NATIVEPHP_APP_WEBSITE', 'https://saas.zoomnearby.com'),

    'icon' => base_path('launcher.png'),

    'build' => [
        'win' => ['target' => 'nsis', 'icon' => base_path('launcher.png'), 'artifactName' => 'Zoom-POS-Setup-${version}.${ext}'],
        'linux' => ['target' => ['deb', 'AppImage'], 'icon' => base_path('launcher.png'), 'category' => 'Office', 'artifactName' => 'Zoom-POS-${version}.${ext}'],
    ],

    /**
     * The default service provider for your application. This provider
     * takes care of bootstrapping your application and configuring
     * any global hotkeys, menus, windows, etc.
     */
    'provider' => NativeAppServiceProvider::class,

    /**
     * A list of environment keys that should be removed from the
     * .env file when the application is bundled for production.
     * You may use wildcards to match multiple keys.
     */
    'cleanup_env_keys' => [
        'APP_DEBUG',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'AWS_*',
        'AZURE_*',
        'GITHUB_*',
        'DO_SPACES_*',
        '*_SECRET',
        'BIFROST_*',
        'NATIVEPHP_UPDATER_PATH',
        'NATIVEPHP_APPLE_ID',
        'NATIVEPHP_APPLE_ID_PASS',
        'NATIVEPHP_APPLE_TEAM_ID',
        'NATIVEPHP_AZURE_PUBLISHER_NAME',
        'NATIVEPHP_AZURE_ENDPOINT',
        'NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME',
        'NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME',
    ],

    /**
     * A list of files and folders that should be removed from the
     * final app before it is bundled for production.
     * You may use glob / wildcard patterns here.
     */
    'cleanup_exclude_files' => [
        'build',
        'temp',
        'content',
        'node_modules',
        '*/tests',
    ],

    /**
     * The NativePHP updater configuration.
     */
    'updater' => [
        /**
         * Whether or not the updater is enabled. Please note that the
         * updater will only work when your application is bundled
         * for production.
         */
        'enabled' => env('NATIVEPHP_UPDATER_ENABLED', true),

        /**
         * The updater provider to use.
         * Supported: "github", "s3", "spaces"
         * Note: The "s3" provider is compatible with S3-compatible services like Cloudflare R2.
         */
        'default' => env('NATIVEPHP_UPDATER_PROVIDER', 'spaces'),

        'providers' => [
            'github' => [
                'driver' => 'github',
                'repo' => env('GITHUB_REPO'),
                'owner' => env('GITHUB_OWNER'),
                'token' => env('GITHUB_TOKEN'),
                'vPrefixedTagName' => env('GITHUB_V_PREFIXED_TAG_NAME', true),
                'private' => env('GITHUB_PRIVATE', false),
                'autoupdate_token' => env('GITHUB_AUTOUPDATE_TOKEN'), // Read-only token used by the updater for private repos
                'channel' => env('GITHUB_CHANNEL', 'latest'),
                'releaseType' => env('GITHUB_RELEASE_TYPE', 'draft'),
            ],

            's3' => [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
                'path' => env('NATIVEPHP_UPDATER_PATH', null),
                /**
                 * Optional public URL for serving updates (e.g., CDN or custom domain).
                 * When set, updates will be downloaded from this URL instead of the S3 endpoint.
                 * Useful for S3 with CloudFront or Cloudflare R2 with public access
                 * Example: 'https://updates.yourdomain.com'
                 */
                'public_url' => env('AWS_PUBLIC_URL'),
            ],

            'spaces' => [
                'driver' => 'spaces',
                'key' => env('DO_SPACES_KEY_ID'),
                'secret' => env('DO_SPACES_SECRET_ACCESS_KEY'),
                'name' => env('DO_SPACES_NAME'),
                'region' => env('DO_SPACES_REGION'),
                'path' => env('NATIVEPHP_UPDATER_PATH', null),
            ],
        ],
    ],

    /**
     * The queue workers that get auto-started on your application start.
     */
    'queue_workers' => [
        'default' => [
            'queues' => ['default'],
            'memory_limit' => 128,
            'timeout' => 60,
            'sleep' => 3,
        ],
    ],

    /**
     * Define your own scripts to run before and after the build process.
     *
     * Deliberately never `config:cache` (which `php artisan optimize` would
     * include) here: it freezes whatever `.env` happens to be on THIS build
     * machine — including this server's own production DB_CONNECTION=mysql,
     * DB_HOST, DB_DATABASE — into bootstrap/cache/config.php, and that file
     * ships inside the installer for every install. Worse, it also bakes
     * `nativephp-internal.running` to false (env('NATIVEPHP_RUNNING') isn't
     * true while merely running a build script), which is read via config()
     * everywhere including NativePHP's own per-device database rewrite
     * (NativeServiceProvider::bootingPackage()) — so a cached config
     * silently skips that rewrite entirely and every local DB write instead
     * tries to reach this build server's MySQL at 127.0.0.1:3306 from the
     * end user's machine. `optimize:clear` guarantees no stale cache from a
     * previous build survives into this one.
     */
    'prebuild' => [
        'npm run build',
        'php artisan optimize:clear',
        // Safe to cache, unlike config:cache above: route/view caches don't
        // freeze any per-device or per-build-machine values into the
        // installer, they just precompute route matching and compile Blade
        // views once at build time instead of on every cold start.
        'php artisan route:cache',
        'php artisan view:cache',
    ],

    'postbuild' => [
        // 'rm -rf public/build',
    ],

    /**
     * The NSIS installer configuration for Windows builds.
     *
     * @see https://www.electron.build/generated/nsisoptions
     */
    'nsis' => [
        'delete_app_data_on_uninstall' => env('NATIVEPHP_NSIS_DELETE_APP_DATA', false),
        'oneClick' => false,
        'allowToChangeInstallationDirectory' => true,
        'perMachine' => true,
        'createDesktopShortcut' => true,
        'createStartMenuShortcut' => true,
        'shortcutName' => 'Zoom POS',
        'runAfterFinish' => true,
        'installerIcon' => base_path('launcher.png'),
        'uninstallerIcon' => base_path('launcher.png'),
        'installerHeaderIcon' => base_path('launcher.png'),
    ],

    'deb' => [
        'packageCategory' => 'misc',
        'priority' => 'optional',
        'synopsis' => 'Zoom POS Retail & Supermarket Management',
        'description' => 'Complete multi-tenant point of sale and inventory management desktop suite.',
    ],

    /**
     * Custom PHP binary path.
     */
    'binary_path' => env('NATIVEPHP_PHP_BINARY_PATH', null),
];
