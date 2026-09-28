<?php
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class manages the authentication information required by
 * the ISS Chatbot services.
 *
 * The authentication data is stored in the Laravel session and
 * can be accessed by other chatbot services whenever authentication
 * credentials are required.
 *
 * The class provides three basic operations:
 *
 * 1) get()
 *    Retrieves the currently stored authentication information.
 *
 * 2) start()
 *    Initializes the authentication information and stores it
 *    in the current session.
 *
 * 3) clear()
 *    Removes the authentication information from the session.
 *
 * This class acts as a centralized authentication-state manager.
 * Instead of storing authentication information independently in
 * multiple services, chatbot services can use BotAuthInfo as a
 * single source for the current authentication state.
 *
 * The general flow is:
 *
 * BotAuthInfo
 *      │
 *      ├── get()   → Read authentication state
 *      │
 *      ├── start() → Initialize authentication state
 *      │
 *      └── clear() → Remove authentication state
 *
 * Other services such as DecisionActions and
 * GetCustomerInfoFromOIM can use this class to access
 * the current authentication information.
 */
namespace App\Services\bot;

class BotAuthInfo
{
    protected const AUTH_SESSION_KEY = 'chatbot_auth';

    public function get(): ?array
    {
        return session(self::AUTH_SESSION_KEY);
    }

    public function start(): void
    {
        session([
            self::AUTH_SESSION_KEY => [
                'username' => 'aaa',
                'password' => 'bbb',
                'musteri_api_key' => 'secret',
            ]
        ]);
    }

    public function clear(): void
    {
        session()->forget(self::AUTH_SESSION_KEY);
    }
}

