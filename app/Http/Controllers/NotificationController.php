<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own notifications (generic over notification type). Another user's notification is a 404.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $page = $user->notifications()->paginate(20)->withQueryString();

        return Inertia::render('notifications/Index', [
            'notifications' => NotificationPresenter::many($user, $page->getCollection()),
            'unreadCount' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'prev_url' => $page->previousPageUrl(),
                'next_url' => $page->nextPageUrl(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        // Scoped to the user's own notifications, so anyone else's id resolves to 404.
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
