<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'email' => 'required|string|email',
                'password' => 'required|string'
            ]
        );

        $credentials = request(['email', 'password']);

        if(!Auth::attempt($credentials) || $validator->fails())
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);

        $user = $request->user();

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;

        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);

        $token->save();

        return response()->json([
            'token' => $tokenResult->accessToken,
            'expirationDate' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString(),
            'role' => $user->role_id,
            'login' => $user->email
        ]);
    }

    public function upload(Request $request) {
        $request->validate([
            'video' => 'file'
        ]);

        $video      = $request->file('video');
        $fileName   = time() . '.' . $video->getClientOriginalExtension();
        $path = $video->getRealPath();

        Storage::disk('local')->put('public/videos'.'/'.$fileName, $path, 'public');

        return response()->json('File has been uploaded');
    }
}