@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Account setup') }}
        </h2>
@endsection

@section('content')
    <div id="profile-page-container" class="py-6 sm:py-8">
        <div id="profile-section-list" class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @include('partials.account-tabs')
            <div id="profile-information-panel" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div id="profile-information-content" class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div id="password-update-panel" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div id="password-update-content" class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div id="account-deletion-panel" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div id="account-deletion-content" class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection
