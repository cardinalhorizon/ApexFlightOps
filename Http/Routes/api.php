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

});
Route::get('/', function() {
    return response()->json(['version' => '1.0.0']);
});
Route::get('/acars/geojson', 'ApiController@pirepsGeoJSON');
Route::get('/metar', function() {
    $response = Http::get('https://aviationweather.gov/api/data/metar?format=geojson');
    return $response->json();
});
