<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Proposal;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class ProposalsController extends Controller
{
   public function store(Request $request) {

       $validator = Validator::make($request->all(),
           [
               'email' => 'required',
               'title' => 'required',
               'description' => 'required',
               'type' => 'required',
           ]
       );

       $user = User::where('email', $request['email'])->first();

       if($validator->fails() || !$user)
           return response()->json([
               'message' => 'Wrong Request! Expected data: title, description, type, email'
           ], 401);

       $proposal = new Proposal();
       $proposal->title = $request['title'];
       $proposal->description = $request['description'];
       $proposal->type = $request['type'];
       $proposal->user_id = $user->id;
       $proposal->status = "new";
       $proposal->save();

       return response()->json([
           'message' => 'Success'
       ], 200);

   }
}
