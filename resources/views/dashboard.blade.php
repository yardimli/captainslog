@extends('layouts.app')

@section('header')
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
@endsection

@section('content')
    <div id="dashboard-page-container" class="py-12">
        <div id="dashboard-content-width" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div id="dashboard-status-panel" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div id="dashboard-status-message" class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>
@endsection
