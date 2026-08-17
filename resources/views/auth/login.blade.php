@extends('layouts.guest')

@section('eyebrow', 'Secure access')
@section('title', 'Welcome back')
@section('subtitle', 'Sign in to resume your timeline, recordings, and daily reflections.')

@section('content')
    @if(session('status'))<div id="login-status-message" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div id="login-email-field"><label for="email" class="label">{{ __('Email') }}</label><input id="email" class="input mt-2 block w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">@if($errors->get('email'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif</div>
        <div id="login-password-field"><div id="login-password-label-row" class="flex items-center justify-between gap-4"><label for="password" class="label">{{ __('Password') }}</label>@if(Route::has('password.request'))<a class="rounded-md text-sm font-semibold text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:focus:ring-offset-slate-900" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>@endif</div><input id="password" class="input mt-2 block w-full" type="password" name="password" required autocomplete="current-password">@if($errors->get('password'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif</div>
        <label for="remember_me" class="flex cursor-pointer items-center gap-3 text-sm text-slate-600 dark:text-slate-300"><input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-white text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" name="remember"><span>{{ __('Remember me') }}</span></label>
        <button type="submit" class="btn w-full justify-center">{{ __('Log in') }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">New aboard? <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Create an account</a></p>
@endsection
