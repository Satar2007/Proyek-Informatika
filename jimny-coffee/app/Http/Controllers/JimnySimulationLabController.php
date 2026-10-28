<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\JimnySimulationLabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class JimnySimulationLabController extends Controller
{
    public function show(Request $request, JimnySimulationLabService $service): JsonResponse
    {
        abort_unless(in_array((string)($request->user()?->role), ['owner','admin'], true), 403);
        try {
            return response()->json($service->publicView(), 200, ['Cache-Control'=>'no-store']);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error'=>'Simulasi belum tersedia. Jalankan php artisan jimny:simulation-build.'],503);
        }
    }
}
