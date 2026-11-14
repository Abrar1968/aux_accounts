<?php

namespace App\Http\Middleware;

use App\Services\Mail\MailboxService;
use App\Services\System\BrandingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared Inertia props: authenticated user + permissions, flash toasts, unread
 * notification count and the public Pusher config used by Laravel Echo.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'timezone' => config('app.timezone'),
                'currency' => 'BDT',
            ],
            // letterhead for print views / PDFs (logo, seal, company details)
            'brand' => fn () => $user ? app(BrandingService::class)->brand() : null,
            'guestBrand' => fn () => $user ? null : app(BrandingService::class)->guestBrand(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_path,
                    'role' => $user->role?->name,
                    'permissions' => $user->role?->name === 'owner' ? ['*'] : ($user->role?->permissions ?? []),
                ] : null,
            ],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
            'notifications' => [
                'unread' => fn () => $user?->unreadNotifications()->count() ?? 0,
            ],
            'inbox' => fn () => $user?->hasPermission('email.inbox') ? ['unread' => app(MailboxService::class)->unreadCount()] : null,
            'pusher' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
        ];
    }
}
