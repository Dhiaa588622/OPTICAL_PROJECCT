<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfflineDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'uuid'],
            'module' => ['required', 'in:pos,inventory,forms'],
            'form_key' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $existing = DB::table('offline_drafts')
            ->where('user_id', $request->user()->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();
        if ($existing) {
            return response()->json(['id' => $existing->id, 'status' => $existing->status, 'message' => __('offline.synced_pending')], 200);
        }
        $now = now();
        $id = DB::table('offline_drafts')->insertGetId([
            ...$validated,
            'payload' => json_encode($validated['payload']),
            'user_id' => $request->user()->id,
            'status' => 'pending_review',
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'id' => $id,
            'status' => 'pending_review',
            'message' => __('offline.synced_pending'),
        ], 202);
    }
}
