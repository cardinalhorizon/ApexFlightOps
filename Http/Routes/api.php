<?php

/**
 * This is required to have a valid API key
 */
Route::group(['middleware' => ['api.auth']], function() {
    Route::get('/flights/search', 'ApiFlightsController@index');
    Route::get('/flights/search/airports', 'ApiController@flight_airports');
    Route::get('/flights/{id}', 'ApiFlightsController@show');
    Route::get('/bids', 'ApiFlightsController@getBids');
    Route::get('/pireps', 'ApiController@pireps');
    Route::get('/pireps/geo', 'ApiController@pireps_geospatial');
    Route::get('/pireps/{id}', 'ApiController@showPirep');
    Route::get('/simbrief/{id}', 'SimBriefController@getOFP');
    Route::match(['post','put','delete','options'], '/user/bids', 'ApiController@bids');
});
Route::get('/', function() {
    // pull the version from the module.json file
    $moduleJson = \Illuminate\Support\Facades\File::get(base_path('modules/ApexFlightOps/module.json'));
    $moduleData = json_decode($moduleJson, true);
    $version = $moduleData['version'] ?? 'unknown';
    return response()->json(['version' => $version]);
});
Route::get('/acars', 'AcarsController@live_flights');
Route::get('/acars/geojson', 'ApiController@pirepsGeoJSON');
Route::get('/metar', function() {
    $response = Http::get('https://aviationweather.gov/api/data/metar?format=geojson');
    return $response->json();
});
