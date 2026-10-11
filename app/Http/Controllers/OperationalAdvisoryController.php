<?php

namespace App\Http\Controllers;

use App\Services\OperationalAdvisoryService;
use Illuminate\Http\JsonResponse;

class OperationalAdvisoryController extends Controller
{
    public function __invoke(OperationalAdvisoryService $advisory): JsonResponse
    {
        return response()->json($advisory->snapshot())
            ->header('Cache-Control', 'private, no-store');
    }
}
