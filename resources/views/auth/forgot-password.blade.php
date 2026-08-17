@extends('layouts.guest')
@section('eyebrow', 'Account recovery')
@section('title', 'Recover access')
@section('subtitle', "Enter your email and we'll send you a secure link to choose a new password.")
@section('content')
    @if(session('status'))<div id="password-reset-status-message" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div id="password-reset-email-field">
            <label for="email" class="label">{{ __('Email') }}</label>
            <input id="email" class="input mt-2 block w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @if($errors->get('email'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <button type="submit" class="btn w-full justify-center">{{ __('Email Password Reset Link') }}</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Back to sign in</a></p>
@endsection
