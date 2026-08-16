<x-guest-layout>
    <x-slot name="eyebrow">Account recovery</x-slot>
    <x-slot name="title">Recover access</x-slot>
    <x-slot name="subtitle">Enter your email and we'll send you a secure link to choose a new password.</x-slot>
    <x-auth-session-status class="mb-5" :status="session('status')" />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <x-primary-button class="w-full justify-center">{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Back to sign in</a></p>
</x-guest-layout>
