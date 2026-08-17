@extends('layouts.app')

@section('header')
        <div id="event-setup-page-heading">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Event schedule</p>
            <h1 class="text-xl font-bold">Repeating events & buttons</h1>
        </div>
@endsection

@section('content')
    <div id="event-setup-page-container" class="mx-auto grid max-w-6xl gap-5 p-4 sm:p-6 lg:grid-cols-[24rem_1fr] lg:p-8">
        <section class="panel h-fit">
            <h2 class="font-bold">Create an event button</h2>
            <p class="mt-1 text-sm text-slate-500">Choose when it recurs and add one or more time slots.</p>

            @if($errors->any())
                <div id="event-setup-validation-message" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-200">
                    <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('tasks.store') }}" class="mt-5 space-y-4" data-recurrence-form>
                @csrf
                <div id="new-event-name-field"><label class="label">Friendly name</label><input class="input" name="name" value="{{ old('name') }}" required placeholder="Dog medication"></div>
                <div id="new-event-color-field">
                    <label class="label" for="new-task-color">Button color</label>
                    <div id="new-event-color-control" class="flex items-center gap-3"><span id="new-task-preview" class="h-7 w-7 shrink-0 rounded-md border border-slate-300 shadow-sm dark:border-slate-600" style="background-color:{{ old('color', '#4f46e5') }}"></span><input id="new-task-color" data-color-input="new-task-preview" type="color" name="color" value="{{ old('color', '#4f46e5') }}" class="h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950"></div>
                </div>
                <div id="new-event-recurrence-field">
                    <label class="label">Repeats</label>
                    <select class="input" name="recurrence_type" data-recurrence-select>
                        <option value="daily" @selected(old('recurrence_type') === 'daily')>Every day</option>
                        <option value="weekly" @selected(old('recurrence_type') === 'weekly')>Selected weekdays</option>
                        <option value="monthly" @selected(old('recurrence_type') === 'monthly')>Selected days of the month</option>
                    </select>
                </div>
                <fieldset data-recurrence-weekly class="hidden rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <legend class="px-1 text-sm font-semibold">Weekdays</legend>
                    <div id="new-event-weekday-options" class="grid grid-cols-4 gap-2 text-sm">
                        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $index => $weekday)
                            <label class="flex items-center gap-1.5"><input type="checkbox" name="weekdays[]" value="{{ $index + 1 }}" @checked(in_array($index + 1, old('weekdays', [])))>{{ $weekday }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div id="new-event-month-days-field" data-recurrence-monthly class="hidden"><label class="label">Days of the month</label><input class="input" name="month_days_text" value="{{ old('month_days_text') }}" placeholder="1, 10, 15, 28"><p class="mt-1 text-xs text-slate-500">Use numbers from 1 to 31.</p></div>
                <div id="new-event-time-slots-field"><label class="label">Time slots</label><div id="new-event-time-slots" data-time-slots data-name="scheduled_times[]" data-values='@json(old('scheduled_times', []))'><div id="new-event-time-slot-list" class="space-y-2" data-time-slot-list></div><button type="button" class="btn-secondary mt-2 w-full" data-time-slot-add>+ Add time slot</button></div><p class="mt-1 text-xs text-slate-500">Tap a time to slide through hours and choose minutes in five-minute steps. Each slot can be removed with ×.</p></div>
                <label class="flex gap-2 text-sm"><input type="checkbox" name="is_sticky" value="1" @checked(old('is_sticky'))><span><strong>Sticky event</strong><span class="block text-xs text-slate-500">Show its button in the timeline at every scheduled time.</span></span></label>
                <div id="new-event-options-field"><label class="label">Optional values</label><textarea class="input" name="options_text" rows="3" placeholder="1, 2, 3, 4, 5">{{ old('options_text') }}</textarea><p class="mt-1 text-xs text-slate-500">When present, a value must be selected before tracking.</p></div>
                <button class="btn w-full">Create event</button>
            </form>
        </section>

        <section class="space-y-3">
            <div id="event-button-list-heading"><h2 class="text-lg font-bold">Your event buttons</h2><p class="text-sm text-slate-500">Sticky buttons appear inside their hour; other active events stay in the daily dropdown.</p></div>
            @forelse($tasks as $task)
                <details class="panel" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-start gap-3">
                        <span class="mt-0.5 h-5 w-5 shrink-0 rounded-md border border-slate-300 shadow-sm dark:border-slate-600" style="background-color:{{ $task->color_hex }}"></span>
                        <span><strong class="block">{{ $task->name }}</strong><span class="text-xs text-slate-500">{{ $task->schedule_summary }}</span></span>
                        <span class="ml-auto rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-slate-800">{{ $task->is_sticky ? 'Sticky' : 'Dropdown' }}</span>
                    </summary>

                    <form method="POST" action="{{ route('tasks.update', $task) }}" class="mt-5 grid gap-4 sm:grid-cols-2" data-recurrence-form>
                        @csrf @method('PATCH')
                        <div class="event-definition-name"><label class="label">Friendly name</label><input class="input" name="name" value="{{ $task->name }}" required></div>
                        <div class="event-definition-color"><label class="label">Color</label><div class="event-definition-color-control flex items-center gap-2"><span id="task-preview-{{ $task->id }}" class="h-7 w-7 shrink-0 rounded-md border border-slate-300" style="background-color:{{ $task->color_hex }}"></span><input aria-label="{{ $task->name }} color" data-color-input="task-preview-{{ $task->id }}" type="color" name="color" value="{{ $task->color_hex }}" class="h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950"></div></div>
                        <div class="event-definition-recurrence sm:col-span-2"><label class="label">Repeats</label><select class="input" name="recurrence_type" data-recurrence-select><option value="daily" @selected($task->recurrence_type === 'daily')>Every day</option><option value="weekly" @selected($task->recurrence_type === 'weekly')>Selected weekdays</option><option value="monthly" @selected($task->recurrence_type === 'monthly')>Selected days of the month</option></select></div>
                        <fieldset data-recurrence-weekly class="hidden rounded-xl border border-slate-200 p-3 dark:border-slate-700 sm:col-span-2"><legend class="px-1 text-sm font-semibold">Weekdays</legend><div class="event-definition-weekdays grid grid-cols-4 gap-2 text-sm">@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $index => $weekday)<label class="flex items-center gap-1.5"><input type="checkbox" name="weekdays[]" value="{{ $index + 1 }}" @checked(in_array($index + 1, $task->recurrence_days ?? []))>{{ $weekday }}</label>@endforeach</div></fieldset>
                        <div data-recurrence-monthly class="event-definition-month-days hidden sm:col-span-2"><label class="label">Days of the month</label><input class="input" name="month_days_text" value="{{ implode(', ', $task->recurrence_days ?? []) }}" placeholder="1, 10, 15, 28"></div>
                        <div class="event-definition-time-slots sm:col-span-2"><label class="label">Time slots</label><div class="event-definition-time-slot-control" data-time-slots data-name="scheduled_times[]" data-values='@json($task->scheduled_times ?? [])'><div class="event-definition-time-slot-list space-y-2" data-time-slot-list></div><button type="button" class="btn-secondary mt-2 w-full" data-time-slot-add>+ Add time slot</button></div></div>
                        <div class="event-definition-options sm:col-span-2"><label class="label">Optional values</label><textarea class="input" name="options_text" rows="2">{{ implode(', ', $task->options ?? []) }}</textarea></div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_sticky" value="1" @checked($task->is_sticky)>Sticky in its time slots</label>
                        <button class="btn">Save changes</button>
                    </form>
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="mt-3 text-right" data-confirm-event-delete>@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600">Delete event</button><p class="mt-1 text-xs text-slate-500">Recorded entries will become editable text and keep their media.</p></form>
                </details>
            @empty
                <div id="empty-event-button-message" class="panel text-sm text-slate-500">No event buttons yet.</div>
            @endforelse
        </section>
    </div>
@endsection
