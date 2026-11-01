<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route map: docs/05-routes-and-pages.md. Auth middleware is added in Phase 1.
Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
