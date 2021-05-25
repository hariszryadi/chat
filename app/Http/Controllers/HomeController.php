<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Message;
use Auth;
use Pusher\Pusher;
use DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // select all users except logged in user
        // $users = User::where('id', '!=', Auth::id())->get();

        //count how many message are unread from the selected user
        $users = DB::select("SELECT users.id, users.name, users.avatar, users.email, count(is_read) as unread
                            FROM users LEFT JOIN messages ON users.id = messages.from AND is_read = 0 
                            AND messages.to = " . Auth::id() ." WHERE users.id != " . Auth::id() . "
                            GROUP BY users.id, users.name, users.avatar, users.email");
                            
        return view('home', compact('users'));
    }

    public function getMessage($user_id)
    {
        $my_id = Auth::id();
        // when click to see message selected user's message will be read, update
        Message::where(['from' => $user_id, 'to' => $my_id])->update(['is_read' => 1]);

        // getting all message for selected user
        // getting those message which is from = Auth::id() and to = user_id OR from = user_id and to = Auth::id()
        $messages = Message::where(function($query) use ($user_id, $my_id) {
            $query->where('from', $my_id)->where('to', $user_id);
        })->orWhere(function($query) use ($user_id, $my_id) {
            $query->where('from', $user_id)->where('to', $my_id);
        })->get();

        return view('messages.index', compact('messages')); 
    }

    public function sendMessage(Request $request)
    {
        $from = Auth::id();
        $to = $request->receiver_id;
        $message = $request->message;

        $data = new Message();
        $data->from = $from;
        $data->to = $to;
        $data->message = $message;
        $data->is_read = 0; // message will be unread when send message 
        $data->save();

        // pusher
        $options = [
            'cluster' => 'ap1',
            'useTLS' => true
        ];

        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );

        $data = ['from' => $from, 'to' => $to]; // sending from and to user id when pressed enter
        $pusher->trigger('my-channel', 'my-event', $data);
    }
}
