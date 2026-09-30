<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastSeen
{
    /**
     * Keep "online" tracking lightweight by writing at most once per minute
     * for each authenticated browser session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active) {
            $lastTouch = (int) $request->session()->get(
                'maps_last_seen_touch',
                0
            );

            if ($lastTouch === 0 || (time() - $lastTouch) >= 60) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'last_seen_at' => now(),
                    ]);

                $request->session()->put(
                    'maps_last_seen_touch',
                    time()
                );
            }
        }

        return $next($request);
    }
}
