<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives a throttled heartbeat only when the authenticated user actually
 * interacts with the browser page.
 */
class SessionActivityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->put(
            'last_user_activity_at',
            now()->timestamp
        );

        return response()->noContent();
    }
}