<?php

namespace App\Http\Controllers\API;

use App\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function index() {
        $notifications = Auth::user()->notifications;

        $json = [];

        foreach($notifications as $notification){
            $json[] = [
                'id' => $notification->id,
                'subject' => $notification->title,
                'message' => $notification->message,
                'sender' => $notification->sender,
                'deliveryDate' => $notification->created_at->format('d-m-Y h:i:s')
            ];
        }

        return response()->json($json);
    }

    public function show($id) {
        $notification = Notification::find($id);

        $json = [
            'id' => $notification->id,
            'subject' => $notification->title,
            'message' => $notification->message,
            'sender' => $notification->sender,
            'deliveryDate' => $notification->created_at->format('d-m-Y h:i:s')
        ];

        return response()->json($json);
    }

    public function destroy($id) {
        $notification = Notification::find($id);

        $notification->delete();

        $header = array (
            'Content-Type' => 'application/json; charset=UTF-8',
            'charset' => 'utf-8'
        );

        return response()->json('Notyfikacja usunięta pomyślnie.', 200, $header, JSON_UNESCAPED_UNICODE);
    }
}
