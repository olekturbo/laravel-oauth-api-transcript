<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Role;

class Task extends Model
{
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function viewWell() {

        $verification_role = Role::where('name', 'verification')->first();
        $transcription_role = Role::where('name', 'transcription')->first();
        $admin_role = Role::where('name', 'admin')->first();

        $pendingStatus = "pending";
        $verificationStatus = "verification";
        $newStatus = "new";

        if($this->status == $newStatus) {
            return true;
        }


        if(Auth::user()->role_id == $admin_role->id) {
            return true;
        }

        if(Auth::user()->role_id == $transcription_role->id) {
            if($this->status == $pendingStatus && $this->user_id == Auth::user()->id) {
                return true;
            }
        }

        if(Auth::user()->role_id == $verification_role->id) {
            if($this->status == $verificationStatus) {
                return true;
            }
        }

        return false;
    }
}
