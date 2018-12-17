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
       $task->expected_transcription_time = $request['expectedTranscriptionTime'];
       $task->status = $request['status'];
       $task->message = $request['message'];
       $task->save();

       return response()->json([
           'message' => 'Success'
       ], 200);

   }

   public function show($id) {
       $task = Task::find($id);

       $json = [
         'mediaLength' => $task->length,
         'name' => $task->name,
         'endDate' => $task->end_date,
         'expectedTranscriptionTime' => $task->expected_transcription_time,
         'status' => $task->status,
         'message' => $task->message
       ];

       return response()->json($json);
   }

   public function index() {
       $tasks = Task::all();

       return response()->json($tasks);
   }
}
