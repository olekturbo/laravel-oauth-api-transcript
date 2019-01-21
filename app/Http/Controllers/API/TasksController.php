<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Notification;
use App\Task;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\Translate\TranslateClient;
use Illuminate\Support\Facades\File;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Spatie\ArrayToXml\ArrayToXml;
use TCG\Voyager\Models\Role;

class TasksController extends Controller
{
   public function store(Request $request) {

       $validator = Validator::make($request->all(),
           [
               'name' => 'required',
               'endDate' => 'required',
               'expectedTranscriptionTime' => 'required',
               'status' => 'required',
               'message' => 'required',
               'video' => 'required|file'
           ]
       );

       if($validator->fails())
           return response()->json([
               'message' => 'Wrong Request! Expected data: name, endDate, expectedTranscriptionTime, status, message, video'
           ], 401);

       $video      = $request->file('video');
       $fileName   = time() . '.' . $video->getClientOriginalExtension();
       $originalName = $video->getClientOriginalName();
       $video_array = [
           'download_link' => 'tasks/' . $fileName,
           'original_name' => $originalName
       ];

       Storage::disk('public')->put('tasks/' . $fileName, file_get_contents($video));

       /************** GOOGLE API *************/

       // Variables
       $projectId = config('app.google_speech_to_text_project_id');
       $key = config('app.google_speech_to_text_api_key');
       $speech = new SpeechClient();
       $wholePath = 'tasks/' . $fileName;
       $inputExtension = File::extension($wholePath);
       $outputExtension = 'flac';
       $lyricsExtension = 'xml';
       $fileDirectory = File::dirname($wholePath);
       $fileName = File::name($wholePath);
       $disk = 'public';

       // Options
       $speechToTextOptions = [
           'projectId' => $projectId,
           'languageCode' => 'en_US',
           'enableAutomaticPunctuation' => true,
           'encoding' => $outputExtension,
           'sampleRateHertz' => 44100,
           'enableWordTimeOffsets' => true,
           'key' => $key
       ];

       $translationOptions = [
           'projectId' => $projectId,
           'key' => $key
       ];

       $translationTarget = 'pl';

       // Translation
       $translate = new TranslateClient($translationOptions);

       // Format
       $format = new \FFMpeg\Format\Audio\Flac();
       $format->setAudioChannels(1);

       // Convert from $inputExtension to FLAC
       FFMpeg::fromDisk($disk)
           ->open($fileDirectory . '/' . $fileName . '.' . $inputExtension)
           ->export()
           ->toDisk($disk)
           ->inFormat($format)
           ->save($fileDirectory . '/' . $fileName . '.' . $outputExtension);

       // Get FLAC File Path
       $filePath = Storage::disk($disk)->url($fileDirectory . '/' . $fileName . '.' . $outputExtension);
       // Data Length
       $length = FFMpeg::fromDisk($disk)
           ->open($fileDirectory . '/' . $fileName . '.' . $inputExtension)
           ->getDurationInMiliseconds();

       // Get Translation Results
       $results = $speech->recognize(fopen($filePath, 'r'), $speechToTextOptions);

       foreach ($results as $result) {
           $alternative = $result->alternatives()[0];
           $alternative['transcript'] = $translate->translate($alternative['transcript'], ['target' => $translationTarget])['text'];
           $exploded = explode(' ', $alternative['transcript']);
           $filtered = array_filter($exploded, 'strlen');
           foreach ($alternative['words'] as $i => $wordInfo) {
               if(array_key_exists($i, $filtered)) {
                   $text['word'][$i] = [
                       'translation' => $filtered[$i],
                       'startTime' => floatval(substr($wordInfo['startTime'], 0, -1))*1000,
                       'endTime' => floatval(substr($wordInfo['endTime'],0,-1))*1000
                   ];
               }
           }
       }

       // Save Lyrics File Path To Database
       $lyricsFile = fopen(storage_path() . '/app/public/' .$fileDirectory . '/' . $fileName . '.' . $lyricsExtension, "wb");
       $xml = ArrayToXml::convert($text, 'transcript',false,'UTF-8');
       fwrite($lyricsFile, $xml);
       fclose($lyricsFile);

       $task = new Task();
       $task->length = $length;
       $task->lyrics_path = $fileDirectory . '/' . $fileName . '.' . $lyricsExtension;
       $task->name = $request['name'];
       $task->end_date = $request['endDate'];
       $task->expected_transcription_time = $request['expectedTranscriptionTime'];
       $task->status = $request['status'];
       $task->message = $request['message'];
       $task->path = '['.json_encode($video_array).']';
       $task->save();

       $transcribentRole = Role::where('name', 'transcription')->first();
       $transcribents = User::where('role_id', $transcribentRole->id)->get();

       foreach($transcribents as $transcribent) {
           $notification = new Notification();
           $notification->title = "Nowe zadanie do transkrypcji!";
           $notification->message = "Nowe zadanie zostało przesłane do transkrypcji przez użytkownika o adresie e-mail: " . Auth::user()->email . '. Nazwa zadania: ' . $task->name . '.';
           $notification->sender = Auth::user()->email;
           $notification->user_id = $transcribent->id;
           $notification->save();
       }

       $header = array (
           'Content-Type' => 'application/json; charset=UTF-8',
           'charset' => 'utf-8'
       );

       return response()->json([
           'message' => 'Sukces!'
       ], 200, $header, JSON_UNESCAPED_UNICODE);

   }

   public function show($id) {
       $task = Task::find($id);
       
       $task->status = "transcription";
       $task->save();

       $json = [
         'mediaLength' => $task->length,
         'name' => $task->name,
         'endDate' => $task->end_date,
         'expectedTranscriptionTime' => $task->expected_transcription_time,
         'status' => $task->status,
         'message' => $task->message,
         'files' => [
             asset('storage/'.json_decode($task->path)[0]->download_link),
             asset('storage/'.$task->lyrics_path),
             asset('storage/'.$task->info),
             asset('storage/'.$task->text)
         ]
       ];

       return response()->json($json);
   }

   public function index() {
       $verification_role = Role::where('name', 'verification')->first();
       $transcription_role = Role::where('name', 'transcription')->first();
       $admin_role = Role::where('name', 'admin')->first();
       if(Auth::user()->role_id == $verification_role->id) {
           $tasks = Task::where('status', 'verification')->get();
       } else if(Auth::user()->role_id == $transcription_role->id) {
           $first_tasks = Task::where('status', 'transcription')->where('user_id', Auth::id())->get();
           $second_tasks = Task::where('status', 'new')->get();
           $tasks = $first_tasks->merge($second_tasks);
       } else if(Auth::user()->role_id == $admin_role->id) {
           $tasks = Task::all();
       }

       $json = [];

       foreach($tasks as $task) {
           $json[] = [
               'id' => $task->id,
               'mediaLength' => $task->length,
               'name' => $task->name,
               'endDate' => $task->end_date,
               'expectedTranscriptionTime' => $task->expected_transcription_time,
               'status' => $task->status,
               'message' => $task->message,
               'files' => [
                   asset('storage/'.json_decode($task->path)[0]->download_link),
                   asset('storage/'.$task->lyrics_path),
                   asset('storage/'.$task->info),
                   asset('storage/'.$task->text)
               ]
           ];
       }

       return response()->json($json);
   }

   public function update($id, Request $request) {
       $textFile = $request->file('text');
       $xmlFile = $request->file('lyricsPath');

       $textFileName   = time() . '_' . $textFile->getClientOriginalName();
       $xmlFileName   = time() . '_' . $xmlFile->getClientOriginalName();

       $task = Task::find($id);

       Storage::disk('public')->put('tasks/' . $textFileName, file_get_contents($textFile));
       Storage::disk('public')->put('tasks/' . $xmlFileName, file_get_contents($xmlFile));

       $task->text = 'tasks/' . $textFileName;
       $task->lyrics_path = 'tasks/' . $xmlFileName;
       $task->status = 'verification';

       $verificationRole = Role::where('name', 'verification')->first();
       $verificators = User::where('role_id', $verificationRole->id)->get();

       foreach($verificators as $verificator) {
           $notification = new Notification();
           $notification->title = "Nowe zadanie do weryfikacji!";
           $notification->message = "Nowe zadanie zostało przesłane do weryfikacji przez użytkownika o adresie e-mail: " . Auth::user()->email . '. Nazwa zadania: ' . $task->name . '.';
           $notification->sender = Auth::user()->email;
           $notification->user_id = $verificator->id;
           $notification->save();
       }

       if($request->comment) {
           $task->message = 'Komentarz od transkrybenta: ' . $request->comment;
       }

       $task->save();

       $header = array (
           'Content-Type' => 'application/json; charset=UTF-8',
           'charset' => 'utf-8'
       );

       return response()->json('Pomyślnie wysłano zadanie.', 200, $header, JSON_UNESCAPED_UNICODE);
   }

    public function verify($id, Request $request) {

        $header = array (
            'Content-Type' => 'application/json; charset=UTF-8',
            'charset' => 'utf-8'
        );

       if(!isset($request->verified)) {
           return response()->json('Brak zmiennej "verified".', 400, $header, JSON_UNESCAPED_UNICODE);
       }
        $verified = $request->verified;
        $task = Task::find($id);
        $response = null;
        $title = null;
        $message = null;

        if($verified) {
            $xmlFile = $request->file('lyricsPath');
            $xmlFileName = time() . '_' . $xmlFile->getClientOriginalName();
            Storage::disk('public')->put('tasks/' . $xmlFileName, file_get_contents($xmlFile));
            $task->lyrics_path = 'tasks/' . $xmlFileName;
            $task->status = 'closed';
            $response = 'Pomyślnie zakończono zadanie.';

            $title = 'Zadanie zaakceptowane!';
            $message = "Zadanie zostało zaakceptowane przez użytkownika o adresie e-mail: " . Auth::user()->email . '. Nazwa zadania: ' . $task->name . '.';
        }
        else {
            $task->status = 'new';
            $response = 'Pomyślnie odrzucono zadanie.';
            $title = 'Zadanie odrzucone!';
            $message = "Zadanie zostało odrzucone przez użytkownika o adresie e-mail: " . Auth::user()->email . '. Nazwa zadania: ' . $task->name . '.';
        }

        if($request->comment) {
            $task->message = 'Komentarz od weryfikanta: ' . $request->comment;
        }

        $users = User::all();

        foreach($users as $user) {
            $notification = new Notification();
            $notification->title = $title;
            $notification->message = $message;
            $notification->sender = Auth::user()->email;
            $notification->user_id = $user->id;
            $notification->save();
        }

        $task->save();

        return response()->json($response, 200, $header, JSON_UNESCAPED_UNICODE);
    }

    public function lyrics() {
       $lyrics = Task::all()->pluck('lyrics_path');

       return response()->json($lyrics, 200);
    }

    public function changeStatus($id) {
       $task = Task::find($id);
       $task->status = "transcription";
       $task->user_id = Auth::id();
       $task->save();

       return response()->json('Status zmieniony', 200);
    }
}
