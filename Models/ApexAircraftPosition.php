<?php

namespace Modules\ApexFlightOps\Models;

use App\Contracts\Model;
use App\Models\Aircraft;

/**
 * Class ApexAircraftPosition
 * @package Modules\ApexFlightOps\Models
 */
class ApexAircraftPosition extends Model
{
    public $table = 'apex_acf_positions';
    protected $fillable = [
        'aircraft_id',
        'latitude',
        'longitude',
        'heading',
    ];

    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }
}
