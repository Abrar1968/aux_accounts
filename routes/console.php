<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler (Asia/Dhaka) — docs/03 §5. Hosting cron: * * * * * php artisan schedule:run
| Locally: php artisan schedule:work
*/

$tz = config('app.timezone');

Schedule::command('deploy:auto-migrate')->everyMinute()->withoutOverlapping(); // applies migrations from an uploaded update zip
Schedule::command('mail:fetch')->everyMinute()->withoutOverlapping(10); // Inbox: client replies from the company mailbox
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('crm:safety-check')->everyFiveMinutes()->timezone($tz)->withoutOverlapping();
Schedule::command('accounting:daily')->dailyAt('02:10')->timezone($tz)->withoutOverlapping();
Schedule::command('backup:database')->dailyAt('03:00')->timezone($tz);
Schedule::command('notify:due')->dailyAt('08:30')->timezone($tz);
Schedule::command('crm:digest')->dailyAt('08:35')->timezone($tz);
Schedule::command('notify:period-close')->monthlyOn(25, '10:00')->timezone($tz);
