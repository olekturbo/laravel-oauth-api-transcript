<?php

namespace App\Http\Controllers\Voyager;

use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\Translate\TranslateClient;
use Illuminate\Support\Facades\File;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Illuminate\Support\Facades\Storage;
use Spatie\ArrayToXml\ArrayToXml;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;

class LyricsController extends VoyagerBaseController
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

        // Variables
        $projectId = config('app.google_speech_to_text_project_id');
        $key = config('app.google_speech_to_text_api_key');
        $language = explode(';',$data->language);
        $speech = new SpeechClient();
        $wholePath = json_decode($data->path)[0]->download_link;
        $inputExtension = File::extension($wholePath);
        $outputExtension = 'flac';
        $lyricsExtension = 'xml';
        $fileDirectory = File::dirname($wholePath);
        $fileName = File::name($wholePath);
        $disk = 'public';


        // Options
        $speechToTextOptions = [
            'projectId' => $projectId,
            'languageCode' => $language[0],
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

        $translationTarget = $language[1];

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
        // Save FLAC File Path to Database
        $data->flacPath = $fileDirectory . '/' . $fileName . '.' . $outputExtension;

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
        $data->lyricsPath = $fileDirectory . '/' . $fileName . '.' . $lyricsExtension;

        // Saving Data
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

}
