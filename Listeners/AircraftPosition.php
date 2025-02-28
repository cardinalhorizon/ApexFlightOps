<?php

namespace Modules\ApexFlightOps\Listeners;

use App\Contracts\Listener;
use App\Events\PirepFiled;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Class AircraftPosition
 * @package Modules\ApexFlightOps\Listeners
 */
class AircraftPosition extends Listener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PirepFiled $event)
    {
        // get the last ACARS message from the pirep that shows the aircraft position, then write it to the db
        $pirep = $event->pirep;
        $acars = $pirep->acars()->orderBy('created_at', 'desc')->first();

        if($acars) {
            $position = $acars->position;
            $pirep->update([
                'lat' => $position['lat'],
                'lon' => $position['lon'],
            ]);
        }
    }
}
