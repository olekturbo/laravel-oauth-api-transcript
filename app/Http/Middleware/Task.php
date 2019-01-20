<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Role;

class Task
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $verification_role = Role::where('name', 'verification')->first();
        $transcription_role = Role::where('name', 'transcription')->first();
        $admin_role = Role::where('name', 'admin')->first();

        $pendingStatus = "pending";
        $verificationStatus = "verification";
        $newStatus = "new";

        $isOkay = false;

        if($request->status == $newStatus) {
            $isOkay = true;
        }


        if(Auth::user()->role_id == $admin_role->id) {
            $isOkay = true;
        }

        if(Auth::user()->role_id == $transcription_role->id) {
            if($request->status == $pendingStatus && $request->user_id == Auth::user()->id) {
                $isOkay = true;
            }
        }

        if(Auth::user()->role_id == $verification_role->id) {
            if($request->status == $verificationStatus) {
                $isOkay = true;
            }
        }

        if(!$isOkay)
            return back();

        return $next($request);
    }
}
