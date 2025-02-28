<?php

namespace Modules\ApexFlightOps\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\BidFlight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FlightService;
use App\Services\FareService;
use App\Repositories\FlightRepository;
use App\Repositories\Criteria\WhereCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use App\Http\Resources\Flight as FlightResource;
use App\Models\Bid;
use App\Services\UserService;
use App\Services\BidService;

/**
 * class ApiController
 * @package Modules\ApexFlightOps\Http\Controllers\Api
 */
class ApiFlightsController extends Controller
{
    /**
     * ApiController constructor.
     */
    public function __construct(public FlightService $flightSvc,
                                public FareService $fareSvc,
                                public FlightRepository $flightRepo,
                                public UserService $userSvc,
                                public BidService $bidSvc)
    {
    }
    /**
     * Just send out a message
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $where = [
            'active'  => true,
            'visible' => true,
        ];

        // Allow the option to bypass some of these restrictions for the searches
        if (!$request->filled('ignore_restrictions') || $request->get('ignore_restrictions') === '0') {
            if (setting('pilots.restrict_to_company')) {
                $where['airline_id'] = $user->airline_id;
            }

            if (setting('pilots.only_flights_from_current')) {
                $where['dpt_airport_id'] = $user->curr_airport_id;
            }
        }

        try {
            $this->flightRepo->resetCriteria();
            $this->flightRepo->searchCriteria($request);
            $this->flightRepo->pushCriteria(new WhereCriteria($request, $where, [
                'airline' => ['active' => true],
            ]));

            $this->flightRepo->pushCriteria(new RequestCriteria($request));

            $with = [
                'airline',
                'fares',
                'field_values',
                'simbrief' => function ($query) use ($user) {
                    return $query->with('aircraft')->where('user_id', $user->id);
                },
            ];

            $relations = [
                'subfleets',
            ];

            if ($request->has('with')) {
                $relations = explode(',', $request->input('with', ''));
            }

            foreach ($relations as $relation) {
                $with = array_merge($with, match ($relation) {
                    'subfleets' => [
                        'subfleets',
                        'subfleets.aircraft',
                        'subfleets.aircraft.bid',
                        'subfleets.fares',
                    ],
                    default => [],
                });
            }

            $flights = $this->flightRepo->with($with)->paginate();
        } catch (RepositoryException $e) {
            return response($e, 503);
        }
        $bids = Bid::where('user_id', $user->id)->pluck('flight_id')->toArray();
        // TODO: Remove any flights here that a user doesn't have permissions to
        foreach ($flights as $flight) {
            if (in_array('subfleets', $relations)) {
                $this->flightSvc->filterSubfleets($user, $flight);
            }

            $this->fareSvc->getReconciledFaresForFlight($flight);

            // Now, get the GIS data for the map
            $flight->gis = $this->generateGISData($flight);

            // Check if the user has bid on this flight
            $flight->is_bid = in_array($flight->id, $bids);

        }

        return FlightResource::collection($flights);
    }

    public function show(string $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var \App\Models\Flight $flight */
        $flight = $this->flightRepo->with([
            'airline',
            'fares',
            'subfleets' => ['aircraft.bid', 'fares'],
            'field_values',
            'dpt_airport',
            'arr_airport',
            'simbrief' => function ($query) use ($user) {
                return $query->with('aircraft')->where('user_id', $user->id);
            },
        ])->find($id);

        $flight = $this->flightSvc->filterSubfleets($user, $flight);
        $flight = $this->fareSvc->getReconciledFaresForFlight($flight);

        $flight->gis = $this->generateGISData($flight);
        $flight->is_bid = Bid::where(['user_id' => $user->id, 'flight_id' => $flight->id])->exists();
        return new FlightResource($flight);
    }

    public function getBids()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $relations = [
            'subfleets',
            'simbrief_aircraft',
        ];
        $bids = $this->bidSvc->findBidsForUser($user, $relations);
        $bids = Bid::where('user_id', $user->id)->with(
            [
                'flight' => function ($query) {
                    $query->with([
                        'airline',
                        'fares',
                        'subfleets' => ['aircraft.bid', 'fares'],
                        'field_values',
                        'dpt_airport',
                        'arr_airport',
                        'simbrief' => function ($query) {
                            $query->with('aircraft');
                        },
                    ]);
                },
                'aircraft',
            ]
        )->paginate();
        // load the aircraft ident attribute
        foreach ($bids as $bid) {
            $bid->aircraft->append('ident');
        }

        $resoruce = \App\Http\Resources\Bid::collection($bids);
        return $resoruce;
    }

    private function generateGISData($flight)
    {
        // This is a placeholder for the GIS data
        $departure = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$flight->dpt_airport->lon, $flight->dpt_airport->lat],
            ],
            'properties' => [
                'name' => $flight->dpt_airport->name,
                'icao' => $flight->dpt_airport->icao,
            ],
        ];

        $arrival = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$flight->arr_airport->lon, $flight->arr_airport->lat],
            ],
            'properties' => [
                'name' => $flight->arr_airport->name,
                'icao' => $flight->arr_airport->icao,
            ],
        ];

        $line = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $this->calculateGreatCircleRoute(
                    [$flight->dpt_airport->lon, $flight->dpt_airport->lat],
                    [$flight->arr_airport->lon, $flight->arr_airport->lat]
                ),
            ],
            'properties' => [],
        ];

        return [
            'type' => 'FeatureCollection',
            'features' => [$departure, $arrival, $line],
        ];
    }
    private function calculateGreatCircleRoute($start, $end)
    {
        $lat1 = deg2rad($start[1]);
        $lon1 = deg2rad($start[0]);
        $lat2 = deg2rad($end[1]);
        $lon2 = deg2rad($end[0]);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) * sin($dLat / 2) + cos($lat1) * cos($lat2) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $points = [];
        $points[] = $start;
        for ($i = 0; $i <= 10; $i++) {
            $f = $i / 10;
            $A = sin((1 - $f) * $c) / sin($c);
            $B = sin($f * $c) / sin($c);
            $x = $A * cos($lat1) * cos($lon1) + $B * cos($lat2) * cos($lon2);
            $y = $A * cos($lat1) * sin($lon1) + $B * cos($lat2) * sin($lon2);
            $z = $A * sin($lat1) + $B * sin($lat2);
            $lat = atan2($z, sqrt($x * $x + $y * $y));
            $lon = atan2($y, $x);
            $points[] = [rad2deg($lon), rad2deg($lat)];
        }
        $points[] = $end;

        return $points;
    }
}
