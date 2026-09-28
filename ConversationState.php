<?php

namespace App\Services\bot;
/**
 * Author: Arda Baran
 *
 * Description:
 *
 * This class manages the conversational state of the ISS Chatbot.
 *
 * The chatbot uses the State Design Pattern to keep track of the
 * current state of an ongoing conversation. Instead of treating
 * every incoming message as an independent request, the chatbot
 * stores information about the current conversation and uses that
 * information to determine how the next message should be handled.
 *
 * The conversation state is stored in the Laravel session using
 * a dedicated session key.
 *
 * The stored state may contain:
 *
 * 1) category_id
 *    The category currently being processed.
 *
 * 2) node_id
 *    The current decision-tree node within the category.
 *
 * 3) temp_value
 *    Temporary data required during the current conversation flow.
 *
 * 4) customer_name
 *    The customer's name used for personalized chatbot responses.
 *
 * The main purpose of this class is to provide a centralized interface
 * for creating, reading, updating, and clearing the chatbot state.
 *
 * In this architecture, the conversation flow can be represented as:
 *
 * User Message
 *      ↓
 * ConversationState
 *      ↓
 * Current Category / Node
 *      ↓
 * Decision Tree
 *      ↓
 * Next Node
 *
 * This allows the chatbot to remember where the user is in a
 * multi-step conversation and continue from the correct state
 * when a new message is received.
 */
class ConversationState
{
    protected const SESSION_KEY = 'chatbot_state';

    public function get(): ?array
    {
        return session(self::SESSION_KEY);
    }


    public function start(string $categoryId, string $nodeId): void
    {
        session([
            self::SESSION_KEY => [
                'category_id' => $categoryId,
                'node_id' => $nodeId
            ]
        ]);
    }

    public function moveTo(string $nodeId): void
    {
        $state = $this->get();
        if (!$state) return;

        $state['node_id'] = $nodeId;

        session([self::SESSION_KEY => $state]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function setTemp($value)
    {
        $state = $this->get() ?? [];
        $state['temp_value'] = $value;
        session([self::SESSION_KEY => $state]);
    }

    public function getTemp()
    {
        $state = $this->get();
        return $state['temp_value'] ?? null;
    }

    public function clearTemp()
    {
        $state = $this->get();
        if ($state && isset($state['temp_value'])) {
            unset($state['temp_value']);
            session([self::SESSION_KEY => $state]);
        }
    }


    public function setCustomerName(string $name): void
    {
        $state = $this->get() ?? [];
        $state['customer_name'] = $name;
        session([self::SESSION_KEY => $state]);
    }

    public function getCustomerName(): ?string
    {
        $state = $this->get();
        return $state['customer_name'] ?? null;
    }

    public function clearCustomerName(): void
    {
        $state = $this->get();
        if ($state && isset($state['customer_name'])) {
            unset($state['customer_name']);
            session([self::SESSION_KEY => $state]);
        }
    }

}
