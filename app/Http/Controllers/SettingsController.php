<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings.edit', [
            'hasApiKey' => (bool) $user->openRouterApiKey(),
            'apiKeyNeedsReplacement' => $user->hasInvalidOpenRouterApiKey(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'openrouter_api_key' => 'nullable|string|max:500',
            'time_format' => 'required|in:12,24',
            'week_starts_on' => 'required|integer|between:0,6',
            'default_chat_model' => 'nullable|string|max:191',
        ]);
        $user = $request->user();
        if ($user->hasInvalidOpenRouterApiKey()) {
            $user->newQuery()->whereKey($user->getKey())->update(['openrouter_api_key' => null]);
            $user->refresh();
        }

        $user->update([
            'time_format' => $data['time_format'],
            'week_starts_on' => $data['week_starts_on'],
            'default_chat_model' => $data['default_chat_model'] ?: null,
        ]);
        if (($data['openrouter_api_key'] ?? '') !== '') {
            $user->update(['openrouter_api_key' => $data['openrouter_api_key']]);
        }
        if ($request->boolean('remove_api_key')) {
            $user->update(['openrouter_api_key' => null]);
        }

        return back()->with('status', 'Account and OpenRouter settings saved.');
    }
}
