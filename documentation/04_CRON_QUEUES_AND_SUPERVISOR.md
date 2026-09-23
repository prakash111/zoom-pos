# Scheduler, Queues, Notifications and Background Operations

## Scheduled commands found in this release

| Command | Schedule | Purpose |
| --- | --- | --- |
| `notifications:dispatch-scheduled` | Every minute | Dispatch scheduled notifications |
| `notifications:dispatch-vertical` | Every minute | Dispatch industry reminders |
| `license:check-status` | Daily, background | Recheck licensed core/modules |
| `app:dispatch-automated-reminders --scheduled` | Every fifteen minutes | Evaluate customer reminders in tenant timezones |
| `queue:work --queue=notifications --stop-when-empty --tries=3 --timeout=120` | Every minute | Drain the notifications queue on this installation |

Schedules are defined in `routes/console.php`. They use overlap locks; selected tasks also use `onOneServer()`. Configure a shared lock-capable cache for multiple scheduler nodes. The code also contains repair-reminder, ledger-backfill, landing-content and color-setup maintenance commands; they are not all scheduled automatically.

## Install the scheduler

Under the application service account, set the actual deployment path and PHP binary:

```cron
* * * * * cd /var/www/saas && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Inspect the registered schedule with `php artisan schedule:list`. Run only one intended scheduler topology and verify clock/timezone configuration. A minute tick does not mean every reminder is due every minute.

## Queue processing

Jobs include product image generation, automated reminder delivery, tenant sample-data seeding and desktop sync cycles. Configure the asynchronous connection before starting workers; the `sync` connection executes inline.

Example Supervisor program for the default queue:

```ini
[program:metroretail-default]
directory=/var/www/saas
command=/usr/bin/php /var/www/saas/artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=90
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/saas/storage/logs/default-worker.log
stopwaitsecs=3600
```

Replace the path/account and include each queue actually used. This release already schedules a short-lived notifications worker; if adopting a resident notifications worker, review that schedule as part of the deployment configuration. Keep the connection retry interval longer than the worker timeout, with room for long image/PDF/provider operations. Restart workers after deployment using `php artisan queue:restart`.

## Delivery workflow

Configure and test SMTP/SMS/WhatsApp/webhook or custom notification channels; save tenant preferences and reminder timing; schedule a test reminder; inspect its dispatch record and recipient delivery. A queued event, accepted provider request and final message delivery are distinct stages.

Inspect failed jobs and application logs before retrying. Preserve idempotency identifiers when diagnosing duplicate requests. Never delete pending client sales to clear a sync warning.
