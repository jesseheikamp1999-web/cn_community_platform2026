<?php

use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\DiscordSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/discord-sync', DiscordSyncController::class);

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/mijncn/profile', [PlatformController::class, 'profile'])->middleware('abilities:mijncn:read');
    Route::get('/awards', [PlatformController::class, 'awards'])->middleware('abilities:awards:read');
    Route::get('/tasks', [PlatformController::class, 'tasks'])->middleware('abilities:tasks:read');
    Route::get('/partners', [PlatformController::class, 'partners'])->middleware('abilities:partners:read');
    Route::get('/news', [PlatformController::class, 'news'])->middleware('abilities:content:read');
    Route::get('/events', [PlatformController::class, 'events'])->middleware('abilities:content:read');
    Route::get('/discord/members/{discordId}', [PlatformController::class, 'discordMember'])->middleware('abilities:discord:read');
});
