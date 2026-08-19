<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Services\GoogleCalendarSync;
use Illuminate\Console\Command;

class SyncGoogleCalendars extends Command
{
    protected $signature = 'sensors:sync-google-calendar';

    protected $description = 'Sync the current month for every enabled Google Calendar sensor';

    public function handle(GoogleCalendarSync $sync): int
    {
        $synced = 0;
        Sensor::where('type', Sensor::GOOGLE_CALENDAR)->where('enabled', true)->orderBy('id')->chunkById(50, function ($sensors) use ($sync, &$synced) {
            foreach ($sensors as $sensor) {
                if ($sync->syncSensor($sensor, true)) {
                    $synced++;
                }
            }
        });
        $this->info("Synced {$synced} Google Calendar account(s).");

        return self::SUCCESS;
    }
}
