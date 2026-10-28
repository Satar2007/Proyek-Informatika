<?php

namespace App\Http\Controllers;

use App\Services\JimnyIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class JimnyIntelligenceController extends Controller
{
    public function dashboard(Request $request, JimnyIntelligenceService $intelligence): JsonResponse
    {
        // Middleware and controller both check role (defense in depth).
        abort_unless(in_array($request->user()?->role, ['admin', 'owner'], true), 403);
        try {
            return response()->json($intelligence->snapshot());
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Analitik belum dapat dibaca. Coba lagi.'], 503);
        }
    }
}
