@extends('voyager::master')

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                <tr>
                                    <td>Name</td>
                                    <td>Lyrics</td>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($lyrics as $lyric)
                                <tr>
                                        <td>{{ $lyric->name }}</td>
                                        <td><a href="storage/{{ asset($lyric->lyrics_path) }}">Download</a></td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
