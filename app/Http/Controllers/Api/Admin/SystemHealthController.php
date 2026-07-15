<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function show(): JsonResponse
    {
        $businesses = Business::query()
            ->with('whatsappAccount:id,business_id,phone_e164,mode,connection_status,last_checked_at')
            ->withMax('conversations as last_activity_at', 'last_activity_at')
            ->withCount(['conversations as pending_escalations' => fn ($q) => $q->where('status', 'escalada')])
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'status' => $business->status,
                'whatsapp_phone' => $business->whatsappAccount?->phone_e164,
                'whatsapp_mode' => $business->whatsappAccount?->mode,
                'whatsapp_connection' => $business->whatsappAccount?->connection_status,
                'whatsapp_last_checked_at' => $business->whatsappAccount?->last_checked_at?->toIso8601String(),
                'last_activity_at' => $business->last_activity_at,
                'pending_escalations' => $business->pending_escalations,
            ]);

        $failedJobs = DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit(20)
            ->get(['id', 'queue', 'exception', 'failed_at']);

        return response()->json([
            'data' => [
                'businesses' => $businesses,
                'failed_jobs' => $failedJobs,
                'queue' => [
                    'pending_jobs' => DB::table('jobs')->count(),
                    'failed_jobs_total' => DB::table('failed_jobs')->count(),
                ],
            ],
        ]);
    }
}
