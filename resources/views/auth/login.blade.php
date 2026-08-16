<x-guest-layout>
    <x-slot name="eyebrow">Secure access</x-slot>
    <x-slot name="title">Welcome back</x-slot>
    <x-slot name="subtitle">Sign in to resume your timeline, recordings, and daily reflections.</x-slot>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <div class="flex items-center justify-between gap-4">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="rounded-md text-sm font-semibold text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:focus:ring-offset-slate-900" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label for="remember_me" class="flex cursor-pointer items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-white text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" name="remember">
            <span>{{ __('Remember me') }}</span>
        </label>
        <x-primary-button class="w-full justify-center">{{ __('Log in') }}</x-primary-button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">New aboard? <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Create an account</a></p>
</x-guest-layout>
