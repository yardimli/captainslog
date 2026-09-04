<?php

namespace App\Http\Controllers;

use App\Services\GuestDemoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestDemoController extends Controller
{
    public function __construct(private GuestDemoService $demo) {}

    public function index(Request $request)
    {
        return view('landing');
    }

    public function enter(Request $request)
    {
        if ($request->user() && ! $request->user()->is_guest) {
            return redirect()->route('calendar');
        }

        Auth::login($this->demo->account());
        $request->session()->regenerate();

        return redirect()->route('calendar')->with('status', 'You are viewing the read-only demo.');
    }

    public function leave(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register');
    }
}
