<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.edit', ['hasApiKey' => (bool) $request->user()->openrouter_api_key]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['openrouter_api_key' => 'nullable|string|max:500']);
        if (($data['openrouter_api_key'] ?? '') !== '') {
            $request->user()->update(['openrouter_api_key' => $data['openrouter_api_key']]);
        }
        if ($request->boolean('remove_api_key')) {
            $request->user()->update(['openrouter_api_key' => null]);
        }

        return back()->with('status', 'OpenRouter settings saved.');
    }
}
