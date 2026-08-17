<?php

use App\Http\Controllers\ApiUsageController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DayLogController;
use App\Http\Controllers\GuestDemoController;
use App\Http\Controllers\LogBlockController;
use App\Http\Controllers\OpenRouterController;
use App\Http\Controllers\PlannerVisibilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionKeepAliveController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [GuestDemoController::class, 'index'])->name('demo.index');
Route::post('/demo/logs/{dailyLog}/blocks', [GuestDemoController::class, 'storeBlock'])->name('demo.blocks.store');
Route::patch('/demo/blocks/{block}', [GuestDemoController::class, 'updateBlock'])->name('demo.blocks.update');
Route::delete('/demo/blocks/{block}', [GuestDemoController::class, 'destroyBlock'])->name('demo.blocks.destroy');
Route::get('/demo/attachments/{attachment}', [GuestDemoController::class, 'showAttachment'])->name('demo.attachments.show');
Route::post('/demo/logs/{dailyLog}/tasks/{task}/events', [GuestDemoController::class, 'storeEvent'])->name('demo.events.store');

Route::redirect('/dashboard', '/calendar')->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/session/keep-alive', SessionKeepAliveController::class)->name('session.keep-alive');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/calendar/{date?}', [CalendarController::class, 'index'])->where('date', '\\d{4}-\\d{2}-\\d{2}')->name('calendar');
    Route::get('/logs/{date}', [DayLogController::class, 'show'])->where('date', '\\d{4}-\\d{2}-\\d{2}')->name('logs.show');
    Route::post('/logs/{dailyLog}/blocks', [LogBlockController::class, 'store'])->name('blocks.store');
    Route::patch('/blocks/{block}', [LogBlockController::class, 'update'])->name('blocks.update');
    Route::delete('/blocks/{block}', [LogBlockController::class, 'destroy'])->name('blocks.destroy');
    Route::patch('/blocks/{block}/visibility', [PlannerVisibilityController::class, 'block'])->name('blocks.visibility');

    Route::post('/logs/{dailyLog}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/logs/{dailyLog}/tasks/{task}/events', [TaskEventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [TaskEventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [TaskEventController::class, 'update'])->name('events.update');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::get('/api-usage', [ApiUsageController::class, 'index'])->name('api-usage.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/openrouter/models', [OpenRouterController::class, 'models'])->name('openrouter.models');
    Route::post('/logs/{dailyLog}/chat', [OpenRouterController::class, 'chat'])->name('openrouter.chat');
    Route::post('/chat-actions/{proposal}/confirm', [OpenRouterController::class, 'confirmChatAction'])->name('openrouter.chat-actions.confirm');
    Route::post('/logs/{dailyLog}/images', [OpenRouterController::class, 'image'])->name('openrouter.images');
    Route::post('/attachments/{attachment}/transcribe', [OpenRouterController::class, 'transcribe'])->name('openrouter.transcribe');
});

require __DIR__.'/auth.php';
