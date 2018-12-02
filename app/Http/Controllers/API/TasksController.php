<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class TasksController extends Controller
{
   public function store(Request $request) {

       $validator = Validator::make($request->all(),
           [
               'mediaLength' => 'required',
               'name' => 'required',
               'endDate' => 'required',
               'expectedTranscriptionTime' => 'required',
               'status' => 'required',
               'message' => 'required',
           ]
       );

       if($validator->fails())
           return response()->json([
               'message' => 'Wrong Request! Expected data: mediaLength, name, endDate, expectedTranscriptionTime, status, message'
           ], 401);

       $task = new Task();
       $task->length = $request['mediaLength'];
       $task->name = $request['name'];
       $task->end_date = $request['endDate'];
       $task->expected_transcription_time = $request['expected_transcription_time'];
       $task->status = $request['status'];
       $task->message = $request['message'];
       $task->save();

       return response()->json([
           'message' => 'Success'
       ], 200);

   }
}
