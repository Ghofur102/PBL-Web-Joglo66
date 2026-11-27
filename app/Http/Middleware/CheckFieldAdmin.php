<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckFieldAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (in_array($user->role, ['admin', 'manager', 'owner'], true)) {
            return $next($request);
        }

        if (in_array($user->role, ['worker', 'treasurer'], true)) {
            $hasField = DB::table('field_workers')
                ->where('fk_user_id', $user->id)
                ->exists();

            if ($hasField) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak.',
        ], 403);
    }
}
