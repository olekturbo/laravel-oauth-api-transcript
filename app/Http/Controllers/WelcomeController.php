<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Speech\SpeechClient;

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
        $fileName = asset('storage/tests/bedoes.flac');

        $options = [
            'encoding' => 'FLAC',
            'sampleRateHertz' => 44100,
            'key' => config('app.google_speech_to_text_api_key')
        ];

        $results = $speech->recognize(fopen($fileName, 'r'), $options);

        foreach ($results as $result) {
            dump( 'Transcription: ' . $result->alternatives()[0]['transcript'] . PHP_EOL);
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
