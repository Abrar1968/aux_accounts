<?php

use App\Http\Controllers\Integrations\GoogleSheetController;
use Illuminate\Support\Facades\Route;

/*
| Machine-to-machine endpoints (prefix /api, no session / CSRF). Each one authenticates its caller
| itself — the Google Sheets webhook checks an HMAC signature made with the shared secret.
*/
Route::post('/integrations/google-sheets/leads', GoogleSheetController::class)
    ->middleware('throttle:180,1')
    ->name('integrations.sheets.leads');
