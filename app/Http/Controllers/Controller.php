<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    use AuthorizesRequests;

    /** Redirect back with a toast (rendered by ToastStack via the shared `flash.toast` prop). */
    protected function toast(string $message, string $type = 'success', ?string $title = null): RedirectResponse
    {
        return back()->with('toast', ['type' => $type, 'title' => $title ?? ($type === 'success' ? 'Saved' : 'Notice'), 'message' => $message]);
    }
}
