@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
@endsection

@section('content')
    <div id="profile-page-container" class="py-12">
        <div id="profile-section-list" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
