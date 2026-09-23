# Web Server, Domains and HTTPS

## Nginx deployment

Point the domain to the server, install a certificate for that hostname and expose the application's `public/` directory. The project root contains private configuration and must not be the web root. Adapt this example to your path and PHP-FPM socket; certificate provisioning is separate.

```nginx
server {
    listen 443 ssl;
    server_name saas.example.com;
    root /var/www/saas/public;
    index index.php;
    ssl_certificate /etc/letsencrypt/live/saas.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saas.example.com/privkey.pem;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ ^/index\.php(/|$) {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
```

Use a separate HTTP virtual host for certificate challenges and HTTPS redirection according to your certificate client's instructions. Validate the effective configuration with `nginx -t` before reloading. The [Laravel deployment guide](https://laravel.com/framework/docs/deployment) is the upstream reference for production routing.

## Tenant subdomains and custom domains

1. Configure tenant slug/domain in the tenant's settings.
2. Point its DNS record to the intended application ingress.
3. Configure a virtual host and TLS certificate covering that hostname; a platform wildcard does not cover unrelated customer domains.
4. Open the URL and confirm the expected tenant storefront, login, asset URLs and account callbacks.
5. Verify generated invoice links, social login redirects and gateway return URLs use the intended HTTPS origin.

DNS and TLS are infrastructure tasks. Saving a domain name in the application does not issue a certificate or create DNS records automatically.

## Apache and reverse proxies

For Apache, use `public/` as DocumentRoot, permit the application's rewrite rules and configure its PHP handler and certificate. Behind a reverse proxy, configure trusted proxy behavior and forwarded scheme/host handling for your topology. Test redirects and secure cookies through the real public address.

## Public assets and documentation

Run `php artisan storage:link` for configured public uploads and build the Vite manifest before opening the app. Keep `.env`, logs, backup exports and signing files private. The documentation portal is served from `/documentation/`; `/documwnation/` remains the existing compatibility copy. Both use the same existing chapters and SVG assets.
