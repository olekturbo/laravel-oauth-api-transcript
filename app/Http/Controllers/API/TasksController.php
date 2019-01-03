<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\Translate\TranslateClient;
use Illuminate\Support\Facades\File;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Spatie\ArrayToXml\ArrayToXml;

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
         'message' => $task->message,
         'files' => [
             'media' => asset(json_decode($task->path)[0]->download_link),
             'transcription' => asset($task->lyrics_path),
             'taskInfo' => asset($task->info),
             'transcriptionText' => asset($task->text)
         ]
       ];

       return response()->json($json);
   }

   public function index() {
       $tasks = Task::all();

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
                   'media' => asset(json_decode($task->path)[0]->download_link),
                   'transcription' => asset($task->lyrics_path),
                   'taskInfo' => asset($task->info),
                   'transcriptionText' => asset($task->text)
               ]
           ];
       }

       return response()->json($json);
   }
}
