# 🤖 ISS Customer Support Chatbot

A **stateful, rule-based, and similarity-based customer support chatbot** developed with **PHP and Laravel** for Internet Service Provider (ISP) customer support operations.

The chatbot analyzes user messages using text similarity algorithms, identifies the most relevant intent and conversation category, manages multi-step conversations through session-based state management, and communicates with external **ISS/OIM APIs** to retrieve customer information and perform operational actions.

The system is designed to go beyond static chatbot responses by supporting **dynamic conversation flows, customer personalization, external API integrations, payment operations, connection diagnostics, field-failure tickets, and dynamic internet plan selection**.

---

---

# 👨‍💻 Author

**Arda Baran**

Computer Engineer

---

## 📑 Table of Contents

- [About the Project](#-about-the-project)
- [Key Features](#-key-features)
- [Technology Stack](#-technology-stack)
- [Architecture](#-architecture)
- [Request Processing Flow](#-request-processing-flow)
- [Project Structure](#-project-structure)
- [Core Services](#-core-services)
- [ChatbotService](#-chatbotservice)
- [DecisionTree](#-decisiontree)
- [TextSimilarityService](#-textsimilarityservice)
- [Conversation State Management](#-conversation-state-management)
- [State Design Pattern](#-state-design-pattern)
- [BotAuthInfo](#-botauthinfo)
- [OIM Integration](#-oim-integration)
- [CustomerOverviewService](#-customeroverviewservice)
- [ISS API Integration](#-iss-api-integration)
- [DecisionActions](#-decisionactions)
- [Dynamic Plan Selection](#-dynamic-plan-selection)
- [Knowledge Base](#-knowledge-base)
- [Conversation Nodes](#-conversation-nodes)
- [Action Status Mapping](#-action-status-mapping)
- [Action Text Parser](#-action-text-parser)
- [API Endpoints](#-api-endpoints)
- [Example Usage](#-example-usage)
- [Example Conversation](#-example-conversation)
- [Response Structure](#-response-structure)
- [Installation](#-installation)
- [Environment Configuration](#-environment-configuration)
- [Security](#-security)
- [Performance](#-performance)
- [Testing](#-testing)
- [Design Decisions](#-design-decisions)
- [Adding a New Intent](#-adding-a-new-intent)
- [Adding a New Action](#-adding-a-new-action)
- [Adding a New Conversation State](#-adding-a-new-conversation-state)
- [Error Handling](#-error-handling)
- [Future Improvements](#-future-improvements)
- [Development Architecture](#-development-architecture)
- [Author](#-author)
- [License](#-license)

---

# 📌 About the Project

This project is a customer support chatbot designed to automate common support operations for an Internet Service Provider.

Instead of relying only on static responses, the system analyzes each user message, identifies the most relevant conversation category, determines the appropriate conversation node, maintains the current state of the conversation, and performs backend operations when necessary.

For example, when a user sends:

```text
I don't have internet access

```

the request can follow a flow similar to:

```text
User Message
      │
      ▼
ChatbotController
      │
      ▼
ChatbotService
      │
      ▼
DecisionTree
      │
      ▼
TextSimilarityService
      │
      ▼
Internet Problem Category
      │
      ▼
ConversationState
      │
      ▼
DecisionActions
      │
      ▼
ISS / OIM API
      │
      ▼
Dynamic Response

```

This architecture allows the chatbot to:

- Understand different variations of the same user intent
- Handle spelling mistakes and typing errors
- Normalize Turkish characters and punctuation
- Maintain multi-step conversations
- Store temporary conversation data
- Retrieve customer information from OIM
- Check internet connection status
- Check payment and debt information
- Retrieve payment links
- Create field-failure tickets
- Retrieve current internet plan information
- Retrieve available plans
- Submit plan change requests
- Generate dynamic conversation branches
- Personalize responses using customer information

---

# ✨ Key Features

## 🧠 Natural Language Matching

The user's message does not have to exactly match a predefined trigger.

For example, the following messages can potentially be mapped to the same category:

```text
Internetim yok

```

```text
İnternete bağlanamıyorum

```

```text
internte baglantim yok

```

The system uses multiple similarity techniques to determine how closely the user's message matches predefined intent triggers.

---

## 🌳 Decision Tree-Based Conversations

Conversation flows are represented as a decision tree.

A simplified example:

```text
Internet Problem
       │
       ├── PPP Session Check
       │
       ├── Connection Check
       │
       ├── Troubleshooting
       │
       └── Field Failure

```

This allows conversation logic to be maintained independently from the application code.

---

## 🔄 Stateful Conversations

The chatbot supports multi-step conversations instead of treating every message as an independent request.

For example:

```text
Bot:
Please enter your subscriber number.

User:
12345678

Bot:
I'm checking your connection...

```

The chatbot knows that `12345678` belongs to the currently active conversation flow because the conversation state is stored in the Laravel session.

---

## 👤 Customer Personalization

Customer information can be retrieved from OIM and used to personalize chatbot responses.

For example:

```text
Dear customer Arda, I am checking your internet connection.

```

The personalization layer is implemented through:

```text
GetCustomerInfoFromOIM

```

---

## 🌐 External API Integration

The chatbot integrates with external ISP and OIM services for real operational data.

Supported operations include:

- Authentication
- Customer information retrieval
- PPP session checks
- Connection history
- Payment information
- Payment links
- Field-failure ticket creation
- Internet plan information
- Available plan retrieval
- Plan change requests

---

# 🛠 Technology Stack

| Technology        | Purpose                                     |
| ----------------- | ------------------------------------------- |
| PHP               | Primary programming language                |
| Laravel           | Backend framework                           |
| Guzzle            | HTTP/API communication                      |
| JSON              | Knowledge base and conversation definitions |
| Laravel Session   | Conversation and authentication state       |
| REST API          | ISS/OIM integration                         |
| Decision Tree     | Conversation flow management                |
| Levenshtein       | Character-level similarity                  |
| `similar_text()`  | General text similarity                     |
| Cosine Similarity | Word-frequency similarity                   |

---

# 🏗 Architecture

The project follows a service-oriented backend architecture.

High-level architecture:

```text
                         ┌─────────────────────┐
                         │       Client        │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │ ChatbotController  │
                         └──────────┬──────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │   ChatbotService   │
                         └──────┬───────┬──────┘
                                │       │
                 ┌──────────────┘       └──────────────┐
                 ▼                                     ▼
        ┌─────────────────┐                   ┌──────────────────┐
        │  DecisionTree   │                   │ ConversationState│
        └────────┬────────┘                   └──────────────────┘
                 │
                 ▼
        ┌─────────────────────┐
        │TextSimilarityService│
        └─────────────────────┘

                         ChatbotService
                               │
                               ▼
                      ┌─────────────────┐
                      │ DecisionActions │
                      └───────┬─────────┘
                              │
                    ┌─────────┴──────────┐
                    ▼                    ▼
               ISS API               OIM API

```

---

# 🔄 Request Processing Flow

The general request lifecycle is:

```text
User Message
     │
     ▼
ChatbotController
     │
     ▼
ChatbotService::reply()
     │
     ├── Is there an active conversation state?
     │       │
     │       ├── Yes → handleNode()
     │       │
     │       └── No
     │
     ▼
Customer Personalization
     │
     ▼
DecisionTree::matchCategory()
     │
     ▼
TextSimilarityService
     │
     ▼
Best Matching Category
     │
     ▼
Root Node
     │
     ▼
ConversationState::start()
     │
     ▼
Node Processing
     │
     ├── Question
     ├── Instruction
     ├── Action
     └── Final
     │
     ▼
JSON Response

```

---

# 📁 Project Structure

The main chatbot services are organized as follows:

```text
app/
├── Http/
│   └── Controllers/
│       └── ChatbotController.php
│
└── Services/
    └── bot/
        ├── BotAuthInfo.php
        ├── ChatbotService.php
        ├── ConversationState.php
        ├── CustomerOverviewService.php
        ├── DecisionActions.php
        ├── DecisionTree.php
        ├── GetCustomerInfoFromOIM.php
        └── TextSimilarityService.php

storage/
└── bot/
    └── knowledge.json

```

---

# 🧩 Core Services

The project separates different responsibilities into dedicated services.

| Service                   | Responsibility                           |
| ------------------------- | ---------------------------------------- |
| `ChatbotController`       | HTTP entry point                         |
| `ChatbotService`          | Conversation orchestration               |
| `DecisionTree`            | Category and node selection              |
| `TextSimilarityService`   | User message matching                    |
| `ConversationState`       | Conversation state management            |
| `BotAuthInfo`             | Authentication state management          |
| `DecisionActions`         | External system operations               |
| `GetCustomerInfoFromOIM`  | Customer information and personalization |
| `CustomerOverviewService` | Customer information aggregation         |

This separation follows the **Single Responsibility Principle** and keeps the controller layer lightweight.

---

# 🎯 ChatbotService

`ChatbotService` is the main orchestration layer of the chatbot.

It coordinates the other services instead of implementing every responsibility itself.

Its main dependencies are:

```text
DecisionTree
ConversationState
DecisionActions
GetCustomerInfoFromOIM

```

The main entry point is:

```php
reply(string $message)

```

The method determines whether the user is already inside an active conversation and routes the request accordingly.

---

# 🌳 DecisionTree

`DecisionTree` is responsible for determining which conversation category best matches the user's message.

It loads the chatbot knowledge base from:

```text
storage/bot/knowledge.json

```

The main structure is:

```text
categories
    │
    ├── intent_triggers
    │
    └── tree
         │
         ├── root
         │
         └── nodes

```

For example:

```json
{
    "id": "internet_problem",
    "intent_triggers": [
        "internetim yok",
        "internete bağlanamıyorum",
        "internet çalışmıyor"
    ]
}

```

When the user writes:

```text
internte baglantim yok

```

each trigger is compared against the message using `TextSimilarityService`.

The category with the highest similarity score is selected if it exceeds the configured threshold.

Current threshold:

```text
0.55

```

---

# 🧠 TextSimilarityService

`TextSimilarityService` provides the text comparison mechanisms used by the chatbot.

The current implementation combines three approaches.

---

## 1. Levenshtein Similarity

Levenshtein similarity is useful for detecting character-level differences.

It is particularly useful for:

- Typographical errors
- Missing characters
- Extra characters
- Small spelling differences

Example:

```text
User input:
internte baglantim yok

Expected:
internet bağlantım yok

```

Character-level similarity can help recognize these as related messages.

---

## 2. General Text Similarity

The current implementation uses PHP's:

```php
similar_text()

```

function to calculate general textual similarity.

> **Implementation note:** The method is currently named `jaroWinkler()`, but the implementation actually uses PHP's `similar_text()` function. This means it is not a true Jaro-Winkler implementation. The method can either be renamed to reflect its current behavior or replaced with an actual Jaro-Winkler algorithm in the future.

---

## 3. Cosine Similarity

Cosine similarity compares the word-frequency representation of two messages.

For example:

```text
internet bağlantım yok

```

and:

```text
internet bağlantı problemi yaşıyorum

```

share important terms.

Cosine similarity captures this word-level relationship.

---

# 🧮 Hybrid Similarity Score

The three similarity methods are combined into a single hybrid score.

Current weights:

```text
Cosine Similarity       55%
Text Similarity         30%
Levenshtein Similarity  15%

```

Formula:

```text
Hybrid Score =
    Cosine × 0.55
  + Text Similarity × 0.30
  + Levenshtein × 0.15

```

The resulting score is normalized to a range approximately between:

```text
0.0 ───────────────── 1.0
Low similarity       High similarity

```

For example:

```text
Hybrid Score = 0.78

```

indicates a relatively strong similarity between the two messages.

---

# 🧹 Text Normalization

Before comparing messages, the text is normalized.

For example:

```text
"İNTERNETİM YOK!!!"

```

can be normalized into:

```text
"internetim yok"

```

The normalization process includes:

- Lowercase conversion
- Turkish character normalization
- Punctuation removal
- Whitespace normalization
- Trimming
- Tokenization

Example:

```text
"İnternet bağlantım yok!!!"
              ↓
"internet baglantim yok"

```

---

# 🔄 Conversation State Management

The chatbot uses Laravel sessions to preserve conversation state between requests.

A simplified state can look like:

```json
{
    "category_id": "internet_problem",
    "node_id": "check_connection",
    "temp_value": "12345678",
    "customer_name": "Arda"
}

```

The main state fields are:

| Field           | Purpose                            |
| --------------- | ---------------------------------- |
| `category_id`   | Currently active category          |
| `node_id`       | Currently active conversation node |
| `temp_value`    | Temporary conversation data        |
| `customer_name` | Customer personalization data      |

---

# 🔄 State Design Pattern

The project uses the **State Design Pattern** through the `ConversationState` class.

The purpose is to centralize conversation state management and allow the chatbot to transition between different conversation states.

A simplified flow:

```text
Node A
  │
  ▼
ConversationState::moveTo()
  │
  ▼
Node B

```

For example:

```text
Internet Problem
       │
       ▼
Subscriber Number
       │
       ▼
Connection Check
       │
       ▼
Result

```

Each request can continue from the state established by the previous request.

---

# 🧠 ConversationState

`ConversationState` is responsible for maintaining the chatbot's current conversational context.

Its main operations include:

```php
get()
start()
moveTo()
clear()

setTemp()
getTemp()
clearTemp()

setCustomerName()
getCustomerName()
clearCustomerName()

```

The class allows the application to treat the conversation as a sequence of states instead of a collection of unrelated HTTP requests.

---

# 🔐 BotAuthInfo

`BotAuthInfo` centrally manages the authentication context used by chatbot services.

Authentication state is stored in the Laravel session under:

```text
chatbot_auth

```

The service provides:

```php
get()
start()
clear()

```

Other services such as:

```text
DecisionActions
GetCustomerInfoFromOIM

```

can therefore access the current authentication context without independently managing the authentication state.

> **Security note:** Real usernames, passwords, API keys, and tokens should never be hard-coded into the source code. Production credentials should be stored in environment variables or a dedicated secrets-management system.

---

# 👤 OIM Integration

`GetCustomerInfoFromOIM` is responsible for retrieving customer information from the OIM system.

The basic flow is:

```text
BotAuthInfo
      │
      ▼
OIM Login
      │
      ▼
Authentication Token
      │
      ▼
Customer Information
      │
      ▼
Customer Name
      │
      ▼
Personalized Response

```

For example:

```text
Dear customer Arda,

```

can be generated dynamically based on the customer's OIM information.

---

# 📊 CustomerOverviewService

`CustomerOverviewService` acts as an aggregation layer.

It combines customer information from OIM with operational information retrieved through `DecisionActions`.

The resulting overview may contain:

- Connection status
- Connection timestamp
- Debt amount
- Payment link
- Subscriber number
- Customer name
- Current tariff

Example:

```json
{
    "connection_durum": "online",
    "debt": "250.00 ₺",
    "payment_linki": "https://example.com/payment",
    "connection_zaman": "2026-09-27 14:30:00",
    "aboneNumarasi": "12345678",
    "isim": "Arda",
    "tarife": "100 Mbps"
}

```

---

# 🌐 ISS API Integration

`DecisionActions` contains the operational integrations between the chatbot and external ISS/OIM services.

The class acts as the integration layer responsible for executing real backend operations.

---

# ⚙️ DecisionActions

The main supported operations include:

---

## PPP Session Check

```php
check_ppp_session()

```

Checks the customer's PPP session status.

Possible states can include:

```text
online
offline
not_found

```

---

## Connection Session

```php
check_connection_session()

```

Retrieves connection-related information such as:

```text
connection_status
connection_start
connection_end

```

---

## Internet Connection Troubleshooting

```php
check_connection_session_for_internet_yok()

```

Provides a dedicated connection lookup for the "I don't have internet" troubleshooting flow.

---

## Payment Check

```php
check_payment_session()

```

Retrieves payment-related information such as:

- Outstanding debt
- Invoice information
- Payment status
- Payment information

---

## Payment Link

```php
check_payment_link_session()

```

Retrieves a payment URL when available.

---

## Field Failure

```php
check_saha_ariza_session()

```

Creates a field-failure ticket through the OIM system.

---

## Internet Plan Speed

```php
check_tarife_hiz_session()

```

Retrieves the current plan's upload and download speeds.

For example:

```text
51200k/102400k

```

can be converted into:

```text
Upload:   50 Mbps
Download: 100 Mbps

```

---

## Available Plans

```php
check_available_plans_session()

```

Retrieves plans that are currently available to the customer.

---

## Plan Change Request

```php
check_plan_change_request_session()

```

Submits a plan change request to the OIM system.

---

# 🔀 Dynamic Plan Selection

One of the more advanced features of the project is dynamic plan selection.

Instead of hard-coding every available internet plan into the conversation tree, the chatbot can retrieve available plans from the external API and dynamically construct conversation branches.

Flow:

```text
User
 │
 │ "Show available plans"
 ▼
ChatbotService
 │
 ▼
DecisionActions
 │
 ▼
OIM API
 │
 ▼
Available Plans
 │
 ▼
ConversationState
 │
 ▼
Dynamic Branches
 │
 ▼
User Selection
 │
 ▼
Plan Change Request

```

For example, an API may return:

```json
[
    {
        "id": 101,
        "name": "100 Mbps",
        "download": 100,
        "upload": 20
    },
    {
        "id": 102,
        "name": "200 Mbps",
        "download": 200,
        "upload": 40
    }
]

```

The chatbot can then generate the corresponding branches at runtime.

This means that changes to available plans do not necessarily require changes to the chatbot's source code.

---

# 🧠 Knowledge Base

Conversation definitions are stored in:

```text
storage/bot/knowledge.json

```

A simplified structure:

```json
{
    "categories": [
        {
            "id": "internet_problem",
            "intent_triggers": [
                "internetim yok",
                "internete bağlanamıyorum",
                "internet çalışmıyor"
            ],
            "tree": {
                "root": "node_start",
                "nodes": {
                    "node_start": {
                        "type": "question",
                        "text": "How can I help you with your internet connection?",
                        "branches": []
                    }
                }
            }
        }
    ]
}

```

This separates conversation definitions from PHP application logic.

---

# 🌳 Conversation Nodes

The conversation tree can contain different node types.

## Question Node

A question node waits for user input.

Example:

```text
Is your modem powered on?

1 - Yes
2 - No

```

---

## Instruction Node

An instruction node does not require user input and can automatically move to another node.

Example:

```text
Your connection is being checked...

```

---

## Action Node

An action node executes backend logic.

For example:

```text
check_connection_session

```

---

## Final Node

A final node indicates that the current conversation has finished.

The conversation state can then be cleared:

```php
ConversationState::clear()

```

---

# 🗺️ Action Status Mapping

Action results can be mapped to different conversation nodes.

For example:

```text
Action
  │
  ├── online
  │      ↓
  │   node_online
  │
  ├── offline
  │      ↓
  │   node_offline
  │
  └── not_found
         ↓
      node_error

```

This allows the action implementation and conversation flow to remain relatively independent.

---

# 🧩 Action Text Parser

`actionTextParser()` converts backend action results into user-facing chatbot messages.

For example, the backend might return:

```json
{
    "status": "online",
    "connection_start": "14:20"
}

```

The chatbot can transform this into a human-readable response such as:

```text
Your connection appears to be active. Your latest connection started at 14:20.

```

The parser can work with information such as:

- Customer name
- PPP username
- PPP password
- Connection status
- Connection start/end
- Debt
- Payment link
- Ticket information
- Upload speed
- Download speed

---

# 🔌 API Endpoints

The controller exposes the chatbot through HTTP endpoints.

## Chatbot

```http
POST /chatbot

```

Request:

```json
{
    "message": "internetim yok"
}

```

Example response:

```json
{
    "text": "Let's check your internet connection.",
    "branches": [
        {
            "label": "Yes",
            "value": "yes"
        },
        {
            "label": "No",
            "value": "no"
        }
    ]
}

```

---

## Customer Overview

```http
GET /chatbot/show

```

Example response:

```json
{
    "success": true,
    "data": {
        "connection_durum": "online",
        "debt": "0 ₺",
        "payment_linki": null,
        "connection_zaman": "2026-09-27 14:30:00",
        "aboneNumarasi": "12345678",
        "isim": "Arda",
        "tarife": "100 Mbps"
    }
}

```

> The exact endpoint paths depend on the Laravel routes configured in the project.

---

# 💻 Example Usage

A frontend client can send a message using:

```javascript
fetch('/chatbot', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        message: 'İnternetim yok'
    })
});

```

The backend then processes the request:

```text
ChatbotController
       ↓
ChatbotService
       ↓
DecisionTree
       ↓
TextSimilarityService
       ↓
Category
       ↓
ConversationState

```

---

# 💬 Example Conversation

### User

```text
merhaba internetim yok

```

### Bot

```text
Dear customer Arda, let's check your internet connection.

Is your modem powered on?

```

### User

```text
evet

```

### Bot

```text
I'm checking your connection...

```

### Backend

```text
check_connection_session()

```

### ISS API

```json
{
    "connection_status": "offline"
}

```

### Bot

```text
Your connection does not appear to be active at the moment.

```

---

# 📦 Response Structure

Chatbot responses generally follow a structure similar to:

```json
{
    "text": "Bot response",
    "branches": []
}

```

The `branches` property represents the options that can be presented to the user.

Example:

```json
{
    "text": "Please select an operation.",
    "branches": [
        {
            "label": "Check my connection",
            "value": "connection_check"
        },
        {
            "label": "Payment information",
            "value": "payment"
        }
    ]
}

```

---

# 🚀 Installation

## Requirements

Recommended environment:

```text
PHP 8.x
Laravel
Composer
MySQL or compatible database
Redis (optional)
ISS API
OIM API

```

---

## 1. Clone the Repository

```bash
git clone <repository-url>
cd <project-directory>

```

---

## 2. Install Composer Dependencies

```bash
composer install

```

---

## 3. Configure Environment

Create the `.env` file:

```bash
cp .env.example .env

```

Generate the Laravel application key:

```bash
php artisan key:generate

```

---

## 4. Configure the Database

Configure your database settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_name
DB_USERNAME=database_user
DB_PASSWORD=database_password

```

Run migrations:

```bash
php artisan migrate

```

---

## 5. Configure the Knowledge Base

Make sure the chatbot knowledge base exists:

```text
storage/bot/knowledge.json

```

---

## 6. Start the Application

```bash
php artisan serve

```

---

# ⚙️ Environment Configuration

External API credentials should be configured through environment variables.

Example:

```env
ISS_API_URL=
OIM_API_URL=

ISS_USERNAME=
ISS_PASSWORD=

OIM_USERNAME=
OIM_PASSWORD=

CUSTOMER_API_KEY=

```

Laravel configuration files can then expose these values to the application:

```php
config('services.iss.url');

```

---

# 🔐 Security

Because this chatbot can work with customer information and authentication credentials, security is an important part of the architecture.

## Secrets

The following information should never be committed to the repository:

```text
Username
Password
API Key
API Token
Session Token
Customer Credentials

```

These values should be stored in:

```text
.env

```

or a dedicated secret-management system.

---

## `.gitignore`

A recommended configuration:

```gitignore
.env
.env.*
!.env.example

/storage/*.key
/storage/logs/*

```

---

# 🔒 Customer Data Protection

The chatbot may process sensitive customer information such as:

- Subscriber numbers
- Customer names
- Payment information
- PPP credentials
- Connection information

Therefore, sensitive customer data should not unnecessarily appear in:

```text
Application logs
Debug output
Exception logs
API request logs
API response logs

```

Production logging should be carefully configured to avoid exposing customer data or credentials.

---

# ⚡ Performance

Text similarity calculations can become increasingly expensive as the number of categories and intent triggers grows.

The current matching process is conceptually similar to:

```text
For each category
    ↓
For each trigger
    ↓
Normalize text
    ↓
Calculate Levenshtein
    ↓
Calculate text similarity
    ↓
Calculate cosine similarity
    ↓
Calculate hybrid score

```

As the knowledge base grows, this can increase CPU usage.

Potential optimizations include:

- Trigger caching
- Normalized trigger caching
- Precomputed vectors
- TF-IDF
- Redis caching
- Embedding-based semantic search
- Dedicated vector databases

---

# 🧪 Testing

Important areas to test include:

## Text Similarity

```text
internetim yok
İnternetim yok
INTERNETIM YOK
internte yok
internet baglantim yok

```

These variations should be evaluated against the intended category.

---

## Category Matching

Each intent should be tested with:

- Exact trigger phrases
- Misspelled messages
- Short messages
- Messages containing additional words
- Messages without Turkish characters
- Different capitalization
- Punctuation variations

---

## State Management

The following sequence should be tested:

```text
start()
    ↓
moveTo()
    ↓
setTemp()
    ↓
getTemp()
    ↓
clearTemp()
    ↓
clear()

```

---

## API Integration

External API failure scenarios should also be tested:

```text
200 OK
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
500 Internal Server Error
Timeout
Invalid JSON
Unexpected response

```

---

# 🧱 Design Decisions

## Service Layer

Business logic is not placed directly inside the controller.

Instead:

```text
Controller
    ↓
Service
    ↓
External API

```

This provides:

- Better separation of concerns
- Improved testability
- Cleaner controllers
- Reusable business logic
- Easier maintenance

---

# 🌳 Data-Driven Decision Tree

Conversation flows are not hard-coded entirely into PHP classes.

Instead, they are represented in the knowledge base.

This means:

```text
PHP Code
    │
    │ remains mostly unchanged
    ▼
knowledge.json
    │
    ▼
Conversation Flow

```

can be modified independently.

---

# 🔄 State Pattern

The State Design Pattern allows the chatbot to preserve context between requests.

For example:

```text
Question
   ↓
User Input
   ↓
Next Node
   ↓
Action
   ↓
Result
   ↓
Next Node

```

The `ConversationState` service centralizes this state transition process.

---

# 🧩 Separation of Concerns

Each major component has a specific responsibility:

```text
ChatbotController
    ↓
HTTP Layer

ChatbotService
    ↓
Conversation Orchestration

DecisionTree
    ↓
Decision Making

TextSimilarityService
    ↓
Text Matching

ConversationState
    ↓
Conversation State

BotAuthInfo
    ↓
Authentication State

DecisionActions
    ↓
External Operations

GetCustomerInfoFromOIM
    ↓
Customer Information

CustomerOverviewService
    ↓
Customer Data Aggregation

```

This separation makes the architecture easier to understand, test, and extend.

---

# ➕ Adding a New Intent

A new conversation topic can be added to the knowledge base.

For example:

```json
{
    "id": "modem_problem",
    "intent_triggers": [
        "modemim bozuldu",
        "modem çalışmıyor",
        "modem arızalı"
    ],
    "tree": {
        "root": "modem_start",
        "nodes": {
            "modem_start": {
                "type": "question",
                "text": "Are the lights on your modem?",
                "branches": []
            }
        }
    }
}

```

Once added, the new category can be evaluated by `DecisionTree`.

---

# ➕ Adding a New Action

A new backend operation can be added to `DecisionActions`.

For example:

```php
public function check_modem_status(string $subscriberId): array
{
    // API request
}

```

The corresponding action can then be referenced from the conversation tree.

---

# 🔀 Adding a New Conversation State

If a conversation needs to store additional temporary information, `ConversationState` can be extended with a dedicated getter/setter pair.

For example:

```php
setTicketNumber()
getTicketNumber()
clearTicketNumber()

```

The value can then become part of the active conversation state.

---

# ❗ Error Handling

Because the chatbot depends on external services, several failure scenarios must be handled:

```text
Authentication failure
API timeout
Invalid API response
Missing customer
Invalid subscriber number
Invalid JSON
Unexpected API status
Missing action
Missing node
Invalid conversation state

```

External API failures should generally be converted into meaningful user-facing chatbot responses rather than exposing raw technical exceptions.

---

# 🚀 Future Improvements

## Semantic Embeddings

The current system uses:

```text
Levenshtein
similar_text()
Cosine Similarity

```

A future version could introduce embedding-based semantic search.

For example:

```text
"I cannot access the internet"

"I can't connect to the web"

"My connection is completely down"

```

may be semantically related even when they have relatively different word structures.

---

## Intent Classification

A dedicated intent classification layer could be placed before the decision tree:

```text
User Message
      ↓
Intent Classifier
      ↓
Category
      ↓
Decision Tree
      ↓
Conversation Node

```

---

## Redis-Based State Management

For high-traffic deployments, conversation state could be stored in Redis instead of the default session storage.

```text
User
 ↓
Laravel
 ↓
Redis
 ↓
Conversation State

```

---

## API Retry Mechanism

External API calls could implement controlled retries:

```text
Request
  ↓
Failure
  ↓
Retry
  ↓
Failure
  ↓
Fallback

```

This could improve resilience against temporary network or service failures.

---

## Queue-Based Processing

Long-running or asynchronous operations could be moved to Laravel queues.

Potential candidates include:

```text
Ticket creation
Notifications
Analytics
Logging
Background synchronization

```

---

## Conversation Analytics

The system could collect metrics such as:

```text
Most common intents
Failed intent matches
Average conversation length
Most frequently used actions
API error rate
Fallback frequency
Successful resolution rate

```

These metrics could help identify weaknesses in the conversation tree and improve the chatbot over time.

---

# 📈 Scalable Architecture

If the application evolves into a larger production system, the architecture could be extended toward:

```text
                    ┌──────────────┐
                    │    Client    │
                    └───────┬──────┘
                            │
                            ▼
                    ┌──────────────┐
                    │ API Gateway  │
                    └───────┬──────┘
                            │
                            ▼
                    ┌────────────────┐
                    │ ChatbotService │
                    └───────┬────────┘
                            │
          ┌─────────────────┼─────────────────┐
          │                 │                 │
          ▼                 ▼                 ▼
    Intent Engine      State Manager    Action Engine
          │                 │                 │
          ▼                 ▼                 ▼
     Similarity          Redis          ISS / OIM
       Engine

```

---

# 🧪 Example End-to-End Architecture

For a request such as:

```http
POST /chatbot

```

with:

```json
{
    "message": "Do I have any outstanding debt?"
}

```

the system can process the request as follows:

```text
ChatbotController
       ↓
ChatbotService
       ↓
ConversationState
       ↓
DecisionTree
       ↓
TextSimilarityService
       ↓
Payment Category
       ↓
Payment Node
       ↓
DecisionActions
       ↓
check_payment_session()
       ↓
ISS API
       ↓
Payment Result
       ↓
actionTextParser()
       ↓
JSON Response

```

---

# 📌 Technical Concepts Demonstrated

This project brings together several backend and software-engineering concepts:

### Service Layer

Business logic is separated from the HTTP controller.

### Dependency Injection

Laravel's dependency injection system is used to provide services to other components.

### State Design Pattern

Conversation state is managed centrally through `ConversationState`.

### Decision Tree

Conversation flow is represented as a deterministic decision structure.

### Data-Driven Architecture

Conversation definitions are stored in a JSON knowledge base.

### Hybrid Text Similarity

Multiple text similarity algorithms are combined into a single matching score.

### REST API Integration

The chatbot communicates with external ISS and OIM services.

### Session Management

Conversation and authentication contexts are persisted between requests.

### Dynamic Branch Generation

Conversation branches can be generated dynamically from external API data.

### Separation of Concerns

Each service has a clearly defined responsibility.

---

# 🧭 Overall System

The complete architecture can be summarized as:

```text
                    USER
                     │
                     ▼
             ChatbotController
                     │
                     ▼
              ChatbotService
                     │
          ┌──────────┴──────────┐
          │                     │
          ▼                     ▼
    DecisionTree          ConversationState
          │                     │
          ▼                     │
 TextSimilarityService           │
          │                     │
          └──────────┬──────────┘
                     ▼
              DecisionActions
                     │
              ┌──────┴──────┐
              │             │
              ▼             ▼
           ISS API        OIM API
              │             │
              └──────┬──────┘
                     ▼
               Final Response

```

The architecture transforms the chatbot from a simple static response system into a backend application capable of:

- Natural-language-style intent matching
- Multi-step conversation management
- Stateful interactions
- Customer personalization
- Real-time external API operations
- Payment and connection workflows
- Dynamic plan selection
- Data-driven conversation flows
- Extensible service-based architecture



# 📄 License

The appropriate license should be selected based on the intended distribution model of the project.

For an open-source release, for example:

```text
MIT License

```

may be considered.

For proprietary or commercial deployments, a custom license may be more appropriate.

---

# ⭐ Summary

This project combines:

```text
Laravel
    +
Service-Oriented Architecture
    +
Dependency Injection
    +
State Design Pattern
    +
Decision Tree
    +
Text Similarity
    +
REST API Integration
    +
Session Management
    +
Dynamic Conversation Flow

```

The result is a modular ISP customer-support chatbot backend capable of combining deterministic conversation logic, similarity-based message matching, stateful conversations, customer personalization, and real-time external system integration.
