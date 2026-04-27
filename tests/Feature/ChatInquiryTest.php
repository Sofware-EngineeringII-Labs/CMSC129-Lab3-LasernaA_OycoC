<?php

use App\Models\Task;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns due today inquiry results for authenticated users', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Submit due today report',
        'description' => 'A task due today.',
        'due_date' => now()->toDateString(),
        'status' => 'todo',
        'priority' => 'high',
        'position' => 0,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'Future planning task',
        'description' => 'A task due tomorrow.',
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'todo',
        'priority' => 'low',
        'position' => 1,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Inquiry Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'What tasks are due today?',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.role', 'assistant');
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'due_today');

    $assistantContent = (string) $response->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('Submit due today report')
        ->not->toContain('Future planning task');
});

it('blocks CRUD-like commands while in inquiry only mode', function () {
    $user = User::factory()->create();

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Blocked Action Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'Delete task #1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'blocked_action');

    $assistantContent = (string) $response->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('inquiry-only mode')
        ->toContain('cannot create, update, or delete');
});

it('supports follow-up filtering using previous task list context', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Low priority cleanup',
        'description' => 'Low priority task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'low',
        'position' => 0,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'High priority launch',
        'description' => 'High priority task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'high',
        'position' => 1,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Context Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $firstResponse = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'Show my tasks',
    ]);

    $firstResponse->assertCreated();
    $firstResponse->assertJsonPath('data.assistant_message.metadata.intent', 'task_list');

    $followUpResponse = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'What about high priority ones?',
    ]);

    $followUpResponse->assertCreated();
    $followUpResponse->assertJsonPath('data.assistant_message.metadata.intent', 'task_list');
    $followUpResponse->assertJsonPath('data.assistant_message.metadata.filters.priority', 'high');

    $assistantContent = (string) $followUpResponse->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('High priority launch')
        ->not->toContain('Low priority cleanup');
});

it('uses AI fallback classification when rule intent matching fails', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Prepare sprint report',
        'description' => 'Weekly report task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'high',
        'position' => 0,
    ]);

    $fakeAiService = new class extends AIService {
        private int $callCount = 0;

        public function generateText(string $systemPrompt, string $userPrompt): string
        {
            $this->callCount++;

            if ($this->callCount === 1) {
                return '{"intent":"list_tasks","status":null,"priority":null,"rewrite":"show my tasks"}';
            }

            return 'Here are your tasks based on your request.';
        }
    };

    app()->instance(AIService::class, $fakeAiService);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'AI Fallback Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'Could you pull up everything on my plate?',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'task_list');
    $response->assertJsonPath('data.assistant_message.metadata.intent_source', 'ai_fallback');
    $response->assertJsonPath('data.assistant_message.metadata.response_source', 'gemini');
    $response->assertJsonPath('data.assistant_message.content', 'Here are your tasks based on your request.');
});
