# Application LLM Bridge (`Llm::`)

Spinx includes a clean, lightweight abstraction layer for integrating Large Language Models (LLMs) into any application.

---

## ⚡ Supported Providers
- **Anthropic Claude** (`claude-3-7-sonnet`, `claude-3-5-haiku`, etc.)
- **OpenAI** (`gpt-4o`, `gpt-4o-mini`, `o1`, `o3-mini`, etc.)

---

## 🚀 Quick Usage

### 1. Simple One-Liner Chat
```php
use Spinx\Llm\Llm;

$reply = Llm::chat('Write a one-sentence summary of Domain-Driven Design in PHP.');
```

### 2. Multi-Turn Conversation & Structured DTO
```php
use Spinx\Llm\Llm;
use Spinx\Llm\LlmRequest;
use Spinx\Llm\ChatMessage;

$request = (new LlmRequest())
    ->setModel('claude-3-7-sonnet-20250219')
    ->setSystemPrompt('You are a helpful software engineering assistant.')
    ->setTemperature(0.7)
    ->addMessage(ChatMessage::user('What is the difference between an Entity and a Value Object?'));

$response = Llm::provider('anthropic')->generate($request);

echo $response->getText();
echo "Input Tokens: " . $response->getInputTokens() . "\n";
echo "Output Tokens: " . $response->getOutputTokens() . "\n";
```

### 3. Structured JSON Parsing
```php
$request = (new LlmRequest())
    ->setSystemPrompt('You must output strictly valid JSON matching the schema: {"title": string, "tags": string[]}')
    ->addUserMessage('Generate a blog post outline for PHP persistent workers.');

$response = Llm::generate($request);

// Automatically decodes response text into an associative PHP array
$data = $response->json();

print_r($data);
// ['title' => 'Building Fast APIs with Persistent Workers', 'tags' => ['php', 'roadrunner', 'swoole']]
```

---

## ⚙️ Configuration (`config/llm.php`)

```php
return [
    'default' => env('LLM_DEFAULT_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key'     => env('ANTHROPIC_API_KEY'),
            'model'       => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-7-sonnet-20250219'),
            'max_tokens'  => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
            'temperature' => 0.7,
        ],
        'openai' => [
            'api_key'     => env('OPENAI_API_KEY'),
            'model'       => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'max_tokens'  => (int) env('OPENAI_MAX_TOKENS', 4096),
            'temperature' => 0.7,
        ],
    ],
];
```
