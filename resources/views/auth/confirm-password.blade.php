@extends('layouts.guest')
@section('eyebrow', 'Security checkpoint')
@section('title', "Confirm it's you")
@section('subtitle', 'This area contains sensitive information. Re-enter your password to continue.')
@section('content')
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <div id="password-confirmation-field">
            <label for="password" class="label">{{ __('Password') }}</label>
            <input id="password" class="input mt-2 block w-full" type="password" name="password" required autofocus autocomplete="current-password">
            @if($errors->get('password'))<ul class="mt-2 space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400">@foreach($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        </div>
        <button type="submit" class="btn w-full justify-center">{{ __('Confirm') }}</button>
    </form>
@endsection
