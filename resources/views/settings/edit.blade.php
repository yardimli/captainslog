<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Across every device</p><h1 class="text-xl font-bold">API & display settings</h1></div></x-slot>
    <div class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-8">
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
            @csrf @method('PATCH')
            <section class="panel">
                <h2 class="text-lg font-bold">Display preferences</h2>
                <p class="mt-1 text-sm text-slate-500">These settings are saved to your account and follow you between devices.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="time-format">Time format</label><select id="time-format" class="input" name="time_format"><option value="24" @selected(auth()->user()->time_format !== '12')>24-hour (18:30)</option><option value="12" @selected(auth()->user()->time_format === '12')>AM / PM (6:30 PM)</option></select></div>
                    <div><label class="label" for="week-start">Start of the week</label><select id="week-start" class="input" name="week_starts_on">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dayNumber => $dayName)<option value="{{ $dayNumber }}" @selected((auth()->user()->week_starts_on ?? 1) === $dayNumber)>{{ $dayName }}</option>@endforeach</select></div>
                </div>
            </section>

            <section class="panel">
                <h2 class="text-lg font-bold">OpenRouter</h2>
                <p class="mt-1 text-sm text-slate-500">Your key is encrypted in the database and only used by the server for your requests.</p>
                <div class="mt-5 space-y-4">
                    <div><label class="label" for="api-key">API key</label><input id="api-key" type="password" class="input" name="openrouter_api_key" autocomplete="off" placeholder="{{ $hasApiKey ? 'Key saved — enter a new value to replace it' : 'sk-or-v1-…' }}"></div>
                    @if($hasApiKey)<label class="flex gap-2 text-sm text-rose-600"><input type="checkbox" name="remove_api_key" value="1">Remove the saved API key</label>@endif
                    <div><label class="label" for="default-chat-model">Default chat model</label><select id="default-chat-model" class="input" name="default_chat_model" data-model-select="chat-default" data-models-url="{{ route('openrouter.models') }}" data-selected="{{ auth()->user()->default_chat_model }}"><option value="">{{ $hasApiKey ? 'Loading compatible models…' : 'Add an API key to load models' }}</option>@if(auth()->user()->default_chat_model)<option value="{{ auth()->user()->default_chat_model }}" selected>{{ auth()->user()->default_chat_model }}</option>@endif</select><p class="mt-1 text-xs text-slate-500">Only models that support the structured responses used by smart chat are shown.</p></div>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3"><button class="btn">Save settings</button><a class="text-sm font-semibold text-indigo-600" href="{{ route('profile.edit') }}">Account profile & password →</a></div>
        </form>
    </div>
</x-app-layout>
