<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionKeepAliveController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->put('_keep_alive_at', now()->timestamp);

        return response()->noContent();
    }
}
