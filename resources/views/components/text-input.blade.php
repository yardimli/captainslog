@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'rounded-xl border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100']) !!}>
