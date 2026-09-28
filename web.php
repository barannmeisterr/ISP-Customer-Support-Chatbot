<?php

/** @noinspection AnonymousFunctionStaticInspection */
/**  
author:Arda Baran

*/

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\{Auth\Middleware\Authorize, Support\Facades\Auth, Support\Facades\Broadcast, Support\Facades\Route};
use App\Http\Controllers\ChatbotController;

    Route::post('/internal-chatbot', [ChatbotController::class, 'handle'])
        ->name('internal_chatbot');
    Route::get('/internal-chatbot/overview', [ChatbotController::class, 'show'])
        ->name('internal_chatbot_overview');
