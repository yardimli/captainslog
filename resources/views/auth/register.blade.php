<x-guest-layout>
    <x-slot name="eyebrow">Begin your journey</x-slot>
    <x-slot name="title">Start your captain's log</x-slot>
    <x-slot name="subtitle">Create your private workspace for daily notes, events, media, and conversations.</x-slot>
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="mt-2 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use at least 8 characters.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
        <x-primary-button class="w-full justify-center">{{ __('Register') }}</x-primary-button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">Already have a log? <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Sign in</a></p>
</x-guest-layout>
