<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Contoh fitur yang dikunci per-user lewat middleware page.access:reports.
 * Ganti isinya dengan logic report yang sebenarnya nanti.
 */
class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Fitur Reports aktif untuk kamu.']);
    }
}
