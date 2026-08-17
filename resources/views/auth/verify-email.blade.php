@extends('layouts.guest')
@section('eyebrow', 'One final step')
@section('title', 'Verify your email')
@section('subtitle', "We sent a verification link to your email. Open it to activate your captain's log.")
@section('content')
    @if (session('status') == 'verification-link-sent')
        <div id="verification-status-message" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ __('A new verification link has been sent to the email address you provided during registration.') }}</div>
    @endif
    <div id="verification-actions" class="space-y-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn w-full justify-center">{{ __('Resend Verification Email') }}</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">{{ __('Log Out') }}</button>
        </form>
    </div>
@endsection
