# Reverb deployment checklist for peersunity.com

The reported `https://peersunity.com/app/{APP_KEY}` 404 means the HTTPS virtual host is reachable, but Nginx is serving the request as a normal Laravel HTTP route instead of proxying `/app` to the Reverb process. Reverb should listen locally on `127.0.0.1:8080`; browsers and Flutter connect through `wss://peersunity.com:443/app/{APP_KEY}`.

## Required production `.env` values

```dotenv
APP_URL=https://peersunity.com
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis

REVERB_APP_ID=peersunity
REVERB_APP_KEY=<public app key used by Flutter>
REVERB_APP_SECRET=<private signing secret>
REVERB_HOST=peersunity.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_SERVER_PATH=
```

## Verification commands

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan reverb:start --host=127.0.0.1 --port=8080
sudo ss -lntp | grep ':8080'
sudo nginx -t
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart peersunity-reverb
curl -i https://peersunity.com/app/${REVERB_APP_KEY}?protocol=7\&client=flutter\&version=1.0\&flash=false
php artisan tinker --execute="event(new App\\Events\\ReverbConnectionTested('hello from production'));"
```

Expected result: the `curl` request should no longer be a Laravel 404. Without websocket upgrade headers it may not complete a websocket session, but it must be handled by the Reverb proxy, not by Laravel routing.


## Flutter configuration

Use the same public app key as `REVERB_APP_KEY` and connect to `wss://peersunity.com:443/app/<REVERB_APP_KEY>`. With `pusher_channels_flutter`, set `host: peersunity.com`, `wsPort: 443`, `wssPort: 443`, `useTLS: true`, `enabledTransports: ['wss']`, and authenticate private/presence channels through `https://peersunity.com/api/broadcasting/auth`.
