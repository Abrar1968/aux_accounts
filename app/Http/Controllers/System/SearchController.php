<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search)
    {
        return response()->json(['results' => $search->search($request->user(), (string) $request->query('q', ''))]);
    }
}
