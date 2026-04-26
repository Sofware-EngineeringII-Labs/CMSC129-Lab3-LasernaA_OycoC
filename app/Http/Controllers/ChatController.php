<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ChatController extends Controller
{
    public function index()
    {
        $this->ensureUserSession();
        return view('chat.index');
    }

    private function ensureUserSession()
    {
        if (!Session::has('user_id')) {
            Session::put('user_id', (string) \Illuminate\Support\Str::uuid());
        }
    }
}