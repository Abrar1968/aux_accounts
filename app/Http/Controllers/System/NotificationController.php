<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpdatePreferencesRequest;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => $this->notifications->list($request->user(), $request->query('filter', 'all')),
            'filter' => $request->query('filter', 'all'),
        ]);
    }

    /** JSON for the bell dropdown. */
    public function latest(Request $request)
    {
        return response()->json(['items' => $request->user()->notifications()->limit(10)->get(), 'unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id)
    {
        $this->notifications->markRead($request->user(), $id);

        return back();
    }

    public function readAll(Request $request)
    {
        $this->notifications->markRead($request->user());

        return $this->toast('All notifications marked as read.');
    }

    public function preferences(Request $request): Response
    {
        return Inertia::render('Profile/Notifications', ['preferences' => $this->notifications->preferencesFor($request->user())]);
    }

    public function savePreferences(UpdatePreferencesRequest $request)
    {
        $this->notifications->savePreferences($request->user(), $request->validated('preferences'));

        return $this->toast('Notification preferences saved.');
    }
}
