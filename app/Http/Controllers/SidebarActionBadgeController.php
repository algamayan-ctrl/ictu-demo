<?php

namespace App\Http\Controllers;

use App\Services\SidebarActionCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarActionBadgeController extends Controller
{
    public function __invoke(Request $request, SidebarActionCountService $service): JsonResponse
    {
        return response()->json([
            'counts' => $service->counts($request->user()),
            'tones' => $service->tones($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }
}
