@extends('layouts.guest')
@section('eyebrow', 'Begin your journey')
@section('title', "Start your captain's log")
@section('subtitle', 'Create your private workspace for daily notes, events, media, and conversations.')
@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div id="registration-name-field">
            <label for="name" class="label">{{ __('Name') }}</label><input id="name" class="input mt-2 block w-full" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">@if($errors->get('name'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('name') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <div id="registration-email-field">
            <label for="email" class="label">{{ __('Email') }}</label><input id="email" class="input mt-2 block w-full" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">@if($errors->get('email'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <div id="registration-password-field">
            <label for="password" class="label">{{ __('Password') }}</label><input id="password" class="input mt-2 block w-full" type="password" name="password" required autocomplete="new-password">
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use at least 8 characters.</p>
            @if($errors->get('password'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <div id="registration-password-confirmation-field">
            <label for="password_confirmation" class="label">{{ __('Confirm Password') }}</label><input id="password_confirmation" class="input mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password">@if($errors->get('password_confirmation'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password_confirmation') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <button type="submit" class="btn w-full justify-center">{{ __('Register') }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">Already have a log? <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Sign in</a></p>
@endsection
