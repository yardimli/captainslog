@extends('layouts.guest')
@section('eyebrow', 'Account recovery')
@section('title', 'Choose a new password')
@section('subtitle', 'Set a fresh password to securely return to your total record.')
@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div id="reset-email-field">
            <label for="email" class="label">{{ __('Email') }}</label><input id="email" class="input mt-2 block w-full" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">@if($errors->get('email'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <div id="reset-password-field">
            <label for="password" class="label">{{ __('Password') }}</label><input id="password" class="input mt-2 block w-full" type="password" name="password" required autocomplete="new-password">@if($errors->get('password'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <div id="reset-password-confirmation-field">
            <label for="password_confirmation" class="label">{{ __('Confirm Password') }}</label><input id="password_confirmation" class="input mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password">@if($errors->get('password_confirmation'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password_confirmation') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <button type="submit" class="btn w-full justify-center">{{ __('Reset Password') }}</button>
    </form>
@endsection
