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
        $success = null;
        $message = null;
        $status_response = null;

        $user = $request->user();

        if (!$user) {
            $success = false;
            $message = 'Unauthenticated.';
            $status_response = 401;
        }

        if ($user->role === 'owner') {
            return $next($request);
        }

        if (in_array($user->role, ['worker', 'treasurer'], true)) {
            $hasField = DB::table('field_workers')
                ->where('fk_user_id', $user->id)
                ->exists();

            if ($hasField) {
                return $next($request);
            }

            $success = false;
            $message = 'Akses ditolak. Anda belum ditugaskan ke lapangan manapun.';
            $status_response = 403;
        }

        return response()->json(['success' => $success, 'message' => $message], $status_response);
    }
}
