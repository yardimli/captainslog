<x-guest-layout>
    <x-slot name="eyebrow">Security checkpoint</x-slot>
    <x-slot name="title">Confirm it's you</x-slot>
    <x-slot name="subtitle">This area contains sensitive information. Re-enter your password to continue.</x-slot>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <x-primary-button class="w-full justify-center">{{ __('Confirm') }}</x-primary-button>
    </form>
</x-guest-layout>
