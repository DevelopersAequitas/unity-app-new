# Reverb deployment checklist for peersunity.com

The live response header shows `Server: Apache`, so the current `https://peersunity.com/app/{APP_KEY}` 404 is not an Nginx problem. Apache is receiving `/app/...` as normal website traffic and passing it to Laravel, where it becomes a 404. Apache must proxy `/app` websocket traffic and `/apps` HTTP traffic to the local Reverb process on `127.0.0.1:8080`.

## Live Laravel path

```bash
cd /home/peersunity/laravel
```

## Required production `.env` values

Keep only one `BROADCAST_CONNECTION` entry. Remove or comment any later `BROADCAST_CONNECTION=log` line because the last duplicate value can override `reverb` when Laravel builds config.

```dotenv
APP_URL=https://peersunity.com
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=287999
REVERB_APP_KEY=z8hlmhiqxac2alirbdtx
REVERB_APP_SECRET=onyiqz7eiyeuyye8ppqp

REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_SERVER_PATH=
```

## Clear and rebuild Laravel config

```bash
cd /home/peersunity/laravel
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan config:cache
```

## Verify the effective Laravel config

```bash
cd /home/peersunity/laravel
php artisan tinker
```

Then check:

```php
config('broadcasting.default');
config('broadcasting.connections.reverb.key');
config('broadcasting.connections.reverb.options.host');
config('broadcasting.connections.reverb.options.port');
config('broadcasting.connections.reverb.options.scheme');
```

Expected values:

```text
reverb
z8hlmhiqxac2alirbdtx
127.0.0.1
8080
http
```

## Check the direct Reverb 500

```bash
cd /home/peersunity/laravel
tail -n 200 storage/logs/laravel.log
tail -n 200 storage/logs/reverb.log
php artisan reverb:restart
```

If Supervisor manages Reverb, restart the Supervisor program instead of relying only on `reverb:restart`.

## Apache websocket proxy for the live `:443` virtual host

Enable the required Apache modules:

```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_wstunnel
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Add this inside the active Apache SSL virtual host for `peersunity.com` only, typically in `/etc/apache2/sites-available/peersunity.com.conf` or the active vhost file used by the server:

```apache
ProxyPreserveHost On
ProxyRequests Off

ProxyPass "/app"  "ws://127.0.0.1:8080/app"
ProxyPassReverse "/app"  "ws://127.0.0.1:8080/app"

ProxyPass "/apps"  "http://127.0.0.1:8080/apps"
ProxyPassReverse "/apps"  "http://127.0.0.1:8080/apps"
```

Do not put this Apache config in Laravel PHP files or Flutter files. Keep the existing Laravel `DocumentRoot`, SSL certificate settings, REST API routes, authentication, and normal website traffic unchanged.

## Validate Apache and Reverb

```bash
sudo apachectl configtest
sudo systemctl reload apache2
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart peersunity-reverb
sudo ss -lntp | grep ':8080'
curl -i "http://127.0.0.1:8080/app/z8hlmhiqxac2alirbdtx?protocol=7&client=js&version=8.4.0&flash=false"
curl -i "https://peersunity.com/app/z8hlmhiqxac2alirbdtx?protocol=7&client=js&version=8.4.0&flash=false"
php artisan tinker --execute="event(new App\Events\ReverbConnectionTested('hello from production'));"
```

Expected result: the public `/app/z8hlmhiqxac2alirbdtx` request must no longer be served by Laravel as a 404. It must be handled by the Apache proxy and reach Reverb. Flutter should connect through `wss://peersunity.com:443/app/z8hlmhiqxac2alirbdtx`.

## Flutter configuration

Do not replace the Flutter app structure or `main.dart`. In the existing realtime service only, use app key `z8hlmhiqxac2alirbdtx`, host `peersunity.com`, `wssPort: 443`, `useTLS: true`, `enabledTransports: ['wss']`, and auth endpoint `https://peersunity.com/api/broadcasting/auth`.
