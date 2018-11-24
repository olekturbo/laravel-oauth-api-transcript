<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Speech\SpeechClient;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Illuminate\Support\Facades\Storage;

class WelcomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $projectId = config('app.google_speech_to_text_project_id');
        $speech = new SpeechClient([
            'projectId' => $projectId,
            'languageCode' => 'en_US',
        ]);
        $inputExtension = 'mp3';
        $outputExtension = 'flac';
        $fileDirectory = 'tests';
        $fileName = 'bedoes';
        $disk = 'public';

        $options = [
            'encoding' => $outputExtension,
            'sampleRateHertz' => 44100,
            'enableWordTimeOffsets' => true,
            'key' => config('app.google_speech_to_text_api_key')
        ];

        $format = new \FFMpeg\Format\Audio\Flac();
        $format->setAudioChannels(1);

        FFMpeg::fromDisk($disk)
            ->open($fileDirectory . '/' . $fileName . '.' . $inputExtension)
            ->export()
            ->toDisk($disk)
            ->inFormat($format)
            ->save($fileDirectory . '/' . $fileName . '.' . $outputExtension);

        $filePath = Storage::disk($disk)->url($fileDirectory . '/' . $fileName . '.' . $outputExtension);

        $results = $speech->recognize(fopen($filePath, 'r'), $options);
        dd($results);

        foreach ($results as $result) {
            $alternative = $result->alternatives()[0];
            dump('Transcript: %s' . PHP_EOL, $alternative['transcript']);
            dump('Confidence: %s' . PHP_EOL, $alternative['confidence']);
            foreach ($alternative['words'] as $wordInfo) {
                dump('  Word: %s (start: %s, end: %s)' . PHP_EOL,
                    $wordInfo['word'],
                    $wordInfo['startTime'],
                    $wordInfo['endTime']);
            }

        }
        return view('welcome');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
