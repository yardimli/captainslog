@extends('layouts.app')

@section('header')
    <div id="log-search-heading"><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Search</p><h1 class="text-xl font-bold">Search all logs</h1></div>
@endsection

@section('content')
    <div id="log-search-page" class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6 lg:p-8">
        <form id="log-search-form" method="GET" action="{{ route('search.index') }}" class="panel flex gap-2" role="search">
            <label class="sr-only" for="log-search-input">Search all logs</label>
            <input id="log-search-input" class="input" type="search" name="q" value="{{ $keyword }}" maxlength="200" placeholder="Search notes, events, projects, commits, sites…" autofocus>
            <button class="btn" type="submit">Search</button>
        </form>
        <section id="log-search-results" class="space-y-4" aria-live="polite" aria-busy="false">@include('search.partials.results')</section>
    </div>

    <div id="search-result-overlay" class="fixed inset-0 hidden" style="z-index:80" data-overlay="search-result" data-overlay-side="right" data-search-result-readonly role="dialog" aria-modal="true" aria-labelledby="search-result-title">
        <button type="button" class="absolute inset-0 bg-slate-950/55 opacity-0 transition-opacity" data-overlay-backdrop data-overlay-close="search-result" aria-label="Close log entry details"></button>
        <aside class="absolute inset-y-0 right-0 w-full max-w-md translate-x-full overflow-y-auto bg-slate-100 p-4 shadow-2xl transition-transform duration-300 dark:bg-slate-950 sm:p-6" data-overlay-panel>
            <div id="search-result-heading" class="flex items-start gap-3"><div id="search-result-heading-copy" class="min-w-0 flex-1"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Read-only log entry</p><h2 id="search-result-title" class="mt-1 break-words text-2xl font-black" data-search-result-title>Log entry</h2><p class="mt-1 text-sm text-slate-500"><span data-search-result-date></span> · <span data-search-result-time></span></p></div><button type="button" class="btn-secondary" data-overlay-close="search-result">Close</button></div>
            <div id="search-result-content" class="mt-6" data-search-result-content aria-readonly="true"></div>
            <a class="btn mt-5 w-full justify-center" data-search-result-date-link>Open date</a>
        </aside>
    </div>
@endsection
