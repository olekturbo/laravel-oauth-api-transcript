<?php

namespace App\Http\Controllers\Voyager;

use App\Notification;
use App\Task;
use App\User;
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Translate\TranslateClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Illuminate\Support\Facades\Storage;
use Spatie\ArrayToXml\ArrayToXml;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use TCG\Voyager\Models\Role;

class TasksController extends VoyagerBaseController
{
    public function insertUpdateData($request, $slug, $rows, $data)
    {
        $multi_select = [];

        /*
         * Prepare Translations and Transform data
         */
        $translations = is_bread_translatable($data)
            ? $data->prepareTranslations($request)
            : [];

        foreach ($rows as $row) {
            $options = json_decode($row->details);

            // if the field for this row is absent from the request, continue
            // checkboxes will be absent when unchecked, thus they are the exception
            if (!$request->hasFile($row->field) && !$request->has($row->field) && $row->type !== 'checkbox') {
                // if the field is a belongsToMany relationship, don't remove it
                // if no content is provided, that means the relationships need to be removed
                if ((isset($options->type) && $options->type !== 'belongsToMany') || $row->field !== 'user_belongsto_role_relationship') {
                    continue;
                }
            }

            $content = $this->getContentBasedOnType($request, $slug, $row, $options);

            if ($row->type == 'relationship' && $options->type != 'belongsToMany') {
                $row->field = @$options->column;
            }

            /*
             * merge ex_images and upload images
             */
            if ($row->type == 'multiple_images' && !is_null($content)) {
                if (isset($data->{$row->field})) {
                    $ex_files = json_decode($data->{$row->field}, true);
                    if (!is_null($ex_files)) {
                        $content = json_encode(array_merge($ex_files, json_decode($content)));
                    }
                }
            }

            if (is_null($content)) {

                // If the image upload is null and it has a current image keep the current image
                if ($row->type == 'image' && is_null($request->input($row->field)) && isset($data->{$row->field})) {
                    $content = $data->{$row->field};
                }

                // If the multiple_images upload is null and it has a current image keep the current image
                if ($row->type == 'multiple_images' && is_null($request->input($row->field)) && isset($data->{$row->field})) {
                    $content = $data->{$row->field};
                }

                // If the file upload is null and it has a current file keep the current file
                if ($row->type == 'file') {
                    $content = $data->{$row->field};
                }

                if ($row->type == 'password') {
                    $content = $data->{$row->field};
                }
            }

            if ($row->type == 'relationship' && $options->type == 'belongsToMany') {
                // Only if select_multiple is working with a relationship
                $multi_select[] = ['model' => $options->model, 'content' => $content, 'table' => $options->pivot_table];
            } else {
                $data->{$row->field} = $content;
            }
        }

        $data->save();


        /************** GOOGLE API *************/

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . config('app.path_to_google'));
        // Variables
        $projectId = config('app.google_speech_to_text_project_id');
        $key = config('app.google_speech_to_text_api_key');
        $wholePath = json_decode($data->path)[0]->download_link;
        $inputExtension = File::extension($wholePath);
        $outputExtension = 'flac';
        $lyricsExtension = 'xml';
        $fileDirectory = File::dirname($wholePath);
        $fileName = File::name($wholePath);
        $disk = 'public';


        // Options

        $speechToTextOptions = (new RecognitionConfig())
            ->setEncoding(AudioEncoding::FLAC)
            ->setSampleRateHertz(44100)
            ->setLanguageCode('en-US')
            ->setEnableAutomaticPunctuation(true)
            ->setEnableWordTimeOffsets(true);



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
        $data->length = FFMpeg::fromDisk($disk)
            ->open($fileDirectory . '/' . $fileName . '.' . $inputExtension)
            ->getDurationInMiliseconds();

        // Get Translation Results
        $content = file_get_contents($filePath);
        $audio = (new RecognitionAudio())
            ->setContent($content);

        $client = new SpeechClient();
        $response = $client->recognize($speechToTextOptions, $audio);


        foreach ($response->getResults() as $result) {
            $alternatives = $result->getAlternatives();
            $mostLikely = $alternatives[0];
            $translated = $translate->translate($mostLikely->getTranscript(), ['target' => $translationTarget])['text'];
            $words = "";
            $wordsCounter = 0;
            $likelyCounter = 0;
            foreach ($mostLikely->getWords() as $i => $wordInfo) {
                    $startTimeStamp =$mostLikely->getWords()[$likelyCounter]->getStartTime()->serializeToJsonString();
                    $words .= ' ' . $wordInfo->getWord();
                    if(strpos($wordInfo->getWord(), '.') !== false || strpos($wordInfo->getWord(), ',') !== false) {
                        $text['word'][$wordsCounter] = [
                            'translation' => $translate->translate($words, ['target' => $translationTarget])['text'],
                            'startTime' => intval(floatval(str_replace('"', '', str_replace("s", "",$startTimeStamp))) * 1000),
                            'endTime' => intval(floatval(str_replace('"', '', str_replace("s", "", $wordInfo->getEndTime()->serializeToJsonString()))) * 1000)
                        ];
                        $words = "";
                        $wordsCounter++;
                        $likelyCounter = $i + 1;
                    }

            }
        }
        

        $textFileName = $fileName . '.rtf';
        Storage::disk($disk)->put($fileDirectory . '/' . $textFileName, $translated);
        $data->text = $fileDirectory . '/' . $textFileName;

        // Save Lyrics File Path To Database
        $lyricsFile = fopen(storage_path() . '/app/public/' .$fileDirectory . '/' . $fileName . '.' . $lyricsExtension, "wb");
        $xml = ArrayToXml::convert($text, 'transcript',false,'UTF-8');
        fwrite($lyricsFile, $xml);
        fclose($lyricsFile);
        $data->lyrics_path = $fileDirectory . '/' . $fileName . '.' . $lyricsExtension;

        // Saving Data
        $data->save();

        $transcribentRole = Role::where('name', 'transcription')->first();
        $transcribents = User::where('role_id', $transcribentRole->id)->get();

        foreach($transcribents as $transcribent) {
            $notification = new Notification();
            $notification->title = "Nowe zadanie do transkrypcji!";
            $notification->message = "Nowe zadanie zostało przesłane do transkrypcji przez użytkownika o adresie e-mail: " . Auth::user()->email . '. Nazwa zadania: ' . $data->name . '.';
            $notification->sender = Auth::user()->email;
            $notification->user_id = $transcribent->id;
            $notification->save();
        }

        $jsonFileName = $fileName . '.json';
        Storage::disk($disk)->put($fileDirectory . '/' . $jsonFileName, $data->toJson());
        $data->info = $fileDirectory . '/' . $jsonFileName;
        $data->save();

        // Save translations
        if (count($translations) > 0) {
            $data->saveTranslations($translations);
        }

        foreach ($multi_select as $sync_data) {
            $data->belongsToMany($sync_data['model'], $sync_data['table'])->sync($sync_data['content']);
        }

        return $data;
    }

    public function changeStatus($id, $status) {
        $task = Task::find($id);
        $task->status = $status;
        if($status == "pending") {
            $task->user_id = Auth::id();
        } else {
            $task->user_id = null;
        }
        $task->save();
        return redirect()->route('voyager.tasks.index')->with(['message' => 'Status of ' . $task->name . ' has been updated!', 'alert-type' => 'success']);
    }

    private function multiExplode($delimiters,$string) {
        return explode($delimiters[0],strtr($string,array_combine(array_slice($delimiters,1),array_fill(0,count($delimiters)-1,array_shift($delimiters)))));
    }

}
