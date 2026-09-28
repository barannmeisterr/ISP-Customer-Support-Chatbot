<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This controller handles HTTP requests related to the ISS Chatbot.
 *
 * It acts as the entry point between the client application and
 * the chatbot service layer.
 *
 * The controller is intentionally kept lightweight and delegates
 * the actual chatbot processing to dedicated service classes.
 *
 * Main responsibilities:
 *
 * 1) Receive chatbot messages from HTTP requests.
 *
 * 2) Pass user messages to ChatbotService for processing.
 *
 * 3) Return chatbot responses as JSON.
 *
 * 4) Retrieve authenticated customer overview information through
 *    CustomerOverviewService.
 *
 * The general architecture is:
 *
 * Client
 *   ↓
 * ChatbotController
 *   ↓
 * Service Layer
 *   ↓
 * Chatbot / Customer Services
 *   ↓
 * JSON Response
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\bot\ChatbotService;
use App\Services\bot\CustomerOverviewService;
class ChatbotController extends Controller
{

    public function handle(Request $request, ChatbotService $chatbot)
    {
        $message = $request->input('message', '');

        $reply = $chatbot->reply($message);

        return response()->json($reply);
    }

    public function show(CustomerOverviewService $overviewService)
    {

        return response()->json([
            'success' => true,
            'data' => $overviewService->get()

        ]);
    }
}

