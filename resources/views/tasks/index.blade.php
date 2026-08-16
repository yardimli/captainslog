<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-bold">Repeating tasks & event buttons</h1></x-slot>
    <div class="mx-auto grid max-w-5xl gap-5 p-4 sm:p-6 lg:grid-cols-[22rem_1fr] lg:p-8">
        <section class="panel h-fit">
            <h2 class="mb-4 font-bold">Create a task button</h2>
            <form method="POST" action="{{ route('tasks.store') }}" class="space-y-4">@csrf
                <div><label class="label">Friendly name</label><input class="input" name="name" required placeholder="Stress level"></div>
                <div><label class="label" for="new-task-color">Button color</label><div class="flex items-center gap-3"><span id="new-task-preview" class="h-7 w-7 shrink-0 rounded-md border border-slate-300 shadow-sm dark:border-slate-600" style="background-color:#4f46e5" title="#4f46e5"></span><input id="new-task-color" data-color-input="new-task-preview" type="color" name="color" value="#4f46e5" class="h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950"><span class="text-xs text-slate-500">Choose any color</span></div></div>
                <label class="flex gap-2 text-sm"><input type="checkbox" name="is_sticky" value="1">Always show at the top of each day</label>
                <div><label class="label">Optional values</label><textarea class="input" name="options_text" rows="3" placeholder="1, 2, 3, 4, 5"></textarea><p class="mt-1 text-xs text-slate-500">Comma or line separated. When present, a value is required before the event is tracked.</p></div>
                <button class="btn w-full">Create task</button>
            </form>
        </section>
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Your task buttons</h2>
            @forelse($tasks as $task)
                <details class="panel" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3"><span class="h-5 w-5 shrink-0 rounded-md border border-slate-300 shadow-sm dark:border-slate-600" style="background-color:{{ $task->color_hex }}" title="{{ $task->color_hex }}"></span><strong>{{ $task->name }}</strong><span class="ml-auto text-xs text-slate-500">{{ $task->is_sticky ? 'Sticky' : 'Dropdown' }} · {{ $task->is_active ? 'Active' : 'Archived' }}</span></summary>
                    <form method="POST" action="{{ route('tasks.update', $task) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('PATCH')
                        <input class="input" name="name" value="{{ $task->name }}" required>
                        <div class="flex items-center gap-2"><span id="task-preview-{{ $task->id }}" class="h-7 w-7 shrink-0 rounded-md border border-slate-300 shadow-sm dark:border-slate-600" style="background-color:{{ $task->color_hex }}" title="{{ $task->color_hex }}"></span><input aria-label="{{ $task->name }} color" data-color-input="task-preview-{{ $task->id }}" type="color" name="color" value="{{ $task->color_hex }}" class="h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950"></div>
                        <textarea class="input sm:col-span-2" name="options_text" rows="2">{{ implode(', ', $task->options ?? []) }}</textarea>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_sticky" value="1" @checked($task->is_sticky)>Sticky</label><button class="btn">Save changes</button>
                    </form>
                    @if($task->is_active)<form method="POST" action="{{ route('tasks.destroy', $task) }}" class="mt-3 text-right">@csrf @method('DELETE')<button class="text-sm text-rose-600">Archive task</button></form>@endif
                </details>
            @empty<div class="panel text-sm text-slate-500">No task buttons yet.</div>@endforelse
        </section>
    </div>
</x-app-layout>
