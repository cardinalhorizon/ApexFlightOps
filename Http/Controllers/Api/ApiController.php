<?php

namespace Modules\ApexFlightOps\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Models\Airport;
use App\Models\Enums\PirepState;
use App\Models\Flight;
use App\Models\GeoJson;
use App\Models\Pirep;
use App\Repositories\AcarsRepository;
use App\Repositories\Criteria\WhereCriteria;
use App\Repositories\PirepRepository;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * class ApiController
 * @package Modules\ApexFlightOps\Http\Controllers\Api
 */
class ApiController extends Controller
{
    public function __construct(
        public GeoService $geoSvc,
        public AcarsRepository $acarsRepo,
        public PirepRepository $pirepRepo,
    ) {
    }
    /**
     * Just send out a message
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function flight_airports(Request $request)
    {
        // parse the request for if we want departure, arrival or alternate airports
        $type = $request->get('type', 'departure');
        $hubs_only = $request->get('hubs_only', false);
        $opposing_airport_id = $request->get('opposing_airport_id', null);
        // Get all the flights in the system by their departure airport
        $select = $type === 'departure' ? 'dpt_airport_id' : 'arr_airport_id';

        $airport_ids = Flight::select($select)
            ->groupBy($select)
            ->get();

        // Setup a query for the airports
        if ($hubs_only) {
            $airport_ids = $airport_ids->pluck($select);
            $airports = Airport::whereIn('id', $airport_ids)
                ->where('hub', true)
                ->get();
        } else {
            $airport_ids = $airport_ids->pluck($select);
            $airports = Airport::whereIn('id', $airport_ids)
                ->get();
        }

        return response()->json($this->generateGeoJsonFromAirports($airports));
    }

    public function pirepsGeoJSON(Request $request): JsonResponse
    {
        $pireps = $this->acarsRepo->getPositions(setting('acars.live_time'));

        $flight = new GeoJson();

        /**
         * @var Pirep $pirep
         */
        foreach ($pireps as $pirep) {
            /**
             * @var $point \App\Models\Acars
             */
            $point = $pirep->position;
            if (!$point) {
                continue;
            }

            $flight->addPoint($point->lat, $point->lon, [
                'pirep_id' => $pirep->id,
                'callsign' => $pirep->airline->icao . $pirep->flight_number,
                'alt'      => $point->altitude,
                'heading'  => $point->heading ?: 0,
            ]);
        }

        return response()->json([
            'data' => $flight->getPoints(),
        ]);
    }

    public function showPirep($pirep_id)
    {
        $pirep = Pirep::find($pirep_id);
        $pirep->load('acars');
        return new \App\Http\Resources\Pirep($pirep);
    }

    public function pireps(Request $request)
    {
        $user = Auth::user();

        $where = [['user_id', $user->id]];
        $where[] = ['state', '<>', PirepState::CANCELLED];

        // Support retrieval of deleted relationships
        $with = [
            'aircraft' => function ($query) {
                return $query->withTrashed();
            },
            'airline' => function ($query) {
                return $query->withTrashed();
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'comments',
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
            'fares',
        ];

        $this->pirepRepo->with($with)->pushCriteria(new WhereCriteria($request, $where));
        $pireps = $this->pirepRepo->sortable(['submitted_at' => 'desc'])->paginate();

        // return the PirepResource collection
        $resource = \App\Http\Resources\Pirep::collection($pireps);
        return $resource;
    }

    public function pireps_geospatial(Request $request)
    {
        // Get all the PIREPs for that user with a special select so we're not grabbing everything
        $pireps = Pirep::where('user_id', Auth::user()->id)->with('dpt_airport', 'arr_airport')
            ->select([
                'dpt_airport_id',
                'arr_airport_id',
            ])
            ->get();

        // foreach pirep, check for duplicate flights with the same departure and arrival
        // if there are duplicates, remove them

        $pireps = $pireps->unique(function ($pirep) {
            return $pirep->dpt_airport_id . $pirep->arr_airport_id;
        });
        // generate a unique list of airports
        $airports = collect($pireps->map(function ($pirep) {
            return [
                'id'   => $pirep->dpt_airport_id,
                'name' => $pirep->dpt_airport->name,
                'icao' => $pirep->dpt_airport->icao,
                'iata' => $pirep->dpt_airport->iata,
                'hub'  => $pirep->dpt_airport->hub,
                'lat'  => $pirep->dpt_airport['lat'],
                'lon'  => $pirep->dpt_airport['lon'],
            ];
        })->merge($pireps->map(function ($pirep) {
            return [
                'id'   => $pirep->arr_airport_id,
                'name' => $pirep->arr_airport->name,
                'icao' => $pirep->arr_airport->icao,
                'iata' => $pirep->arr_airport->iata,
                'hub'  => $pirep->arr_airport->hub,
                'lat'  => $pirep->arr_airport['lat'],
                'lon'  => $pirep->arr_airport['lon'],
            ];
        })));
        // make pireps a interable array
        $pireps = $pireps->values()->all();
        return response()->json([
            'airports' => $this->generateGeoJsonFromAirports($airports),
            'flights'  => $pireps,
        ]);
    }

    /**
     * Generate GeoJSON Feature Collection from a collection of airports
     *
     * @param array $airports
     * @return array
     */
    private function generateGeoJsonFromAirports($airports)
    {
        $features = [];
        foreach ($airports as $airport) {
            $features[] = [
                'type'     => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$airport['lon'], $airport['lat']],
                ],
                'properties' => [
                    'id'   => $airport['id'],
                    'name' => $airport['name'],
                    'icao' => $airport['icao'],
                    'iata' => $airport['iata'],
                    'hub'  => $airport['hub'],
                ],
            ];
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
