# Server and Client Requirements

## Verified release requirements

| Component | Requirement / evidence |
| --- | --- |
| PHP | `^8.3` in `composer.json`; match CLI and PHP-FPM |
| Laravel | `^13.17`; install the lockfile rather than choosing a different framework version |
| Livewire | `^4.4` |
| Composer | Use Composer with the project's lockfile and run platform checks |
| Database | Shared database; configured drivers are in `config/database.php`; validate the chosen MySQL/MariaDB version against migrations |
| Frontend | Node/npm compatible with `package-lock.json`; Vite assets built by `npm ci` then `npm run build` |
| Queue/cache/session | Select configured drivers explicitly; Redis is optional and needs a compatible PHP client |
| Web server | Nginx/PHP-FPM or a correctly configured equivalent; document root is `public/` |
| Background work | Scheduler plus workers for the queues used by the application |
| Flutter client | `mobile/pubspec.yaml`: package `zoom_pos_mobile`, version `1.0.5+7`, Dart `>=3.4.0 <4.0.0` |
| Android | `minSdk=23`, Java/Kotlin target 17, compile/target SDK inherited from Flutter configuration |
| Windows Flutter build | Windows with Visual Studio Desktop development with C++ and Windows SDK |
| NativePHP desktop | Separate Composer desktop package `^2.2`; do not confuse its build/runtime with Flutter Windows |

The Dart lower bound alone does not guarantee compatibility with the installed Flutter SDK and locked plugins. Resolve the supplied lockfile, run `flutter doctor -v` and build the intended target. Do not copy an arbitrary Flutter 3.24/Gradle version into this release.

## Server preparation

Choose a supported Linux distribution, such as your managed Ubuntu server release, and install PHP 8.3 or a version allowed by the lockfile. Distribution package availability varies. Laravel 13 requires at least PHP 8.3: [official Laravel releases](https://laravel.com/framework/docs/releases).

Enable extensions needed by Composer and the installer: BCMath, Ctype, cURL, DOM/XML, Fileinfo, GD, Intl, JSON, Mbstring, OpenSSL, PCRE, PDO with the database driver, Tokenizer and ZIP. Some are built into PHP rather than installed as separate packages. Verify the production host with:

```bash
php --version
php -m
composer check-platform-reqs --no-dev
```

Size CPU, memory, disk and worker concurrency from measured tenant count, product volume, peak checkout traffic, PDF/image jobs and retention needs. The code does not establish a universal tenants-per-server guarantee. Keep disk space for uploads, exports, logs and release rollback; test peak behavior before choosing a commercial capacity limit.

## Network and provider dependencies

Provide HTTPS access for web/app clients, DNS/TLS for configured tenant domains, outbound access to the license authority and selected payment/message/AI providers, and the SMTP or webhook access required by the configured integrations. Network printers need connectivity from the client device to the printer. Firebase push and hosted checkout require their own provider setup.

[Windows prerequisites](https://docs.flutter.dev/platform-integration/windows/setup) and [Laravel production deployment](https://laravel.com/framework/docs/deployment) provide the upstream environment instructions. No OS package installation or service reconfiguration was performed while preparing this documentation.
