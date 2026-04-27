<?php

use App\Models\Task;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

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

it('supports deleting a task via chat with confirmation', function () {
    $user = User::factory()->create();

    // create a task to delete
    Task::create([
        'user_id' => $user->id,
        'title' => 'Removable task',
        'description' => 'Should be deletable via chat.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Delete Flow Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'Delete task #1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'delete_confirm');
    $response->assertJsonPath('data.assistant_message.metadata.pending_action.type', 'delete');

    $assistantContent = (string) $response->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('Are you sure')
        ->toContain('Removable task');

    // confirm deletion
    $confirmResponse = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'yes',
    ]);

    $confirmResponse->assertCreated();
    $confirmResponse->assertJsonPath('data.assistant_message.metadata.intent', 'delete');

    // the task should be soft-deleted
    $deleted = Task::withTrashed()->find(1);
    expect($deleted)->not->toBeNull();
    expect($deleted->trashed())->toBeTrue();
});

it('updates a task by name without requiring an id', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'buy milk',
        'description' => 'Groceries task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Update By Name Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'update buy milk to buy groceries',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'update');
    $response->assertJsonPath('data.assistant_message.metadata.update.title', 'buy groceries');

    expect(Task::query()->where('user_id', $user->id)->where('title', 'buy groceries')->exists())->toBeTrue();
});

it('deletes a task by name without requiring an id', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'buy milk',
        'description' => 'Groceries task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Delete By Name Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'delete buy milk',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'delete_confirm');
    $response->assertJsonPath('data.assistant_message.metadata.pending_action.type', 'delete');

    $confirmResponse = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'yes',
    ]);

    $confirmResponse->assertCreated();
    $confirmResponse->assertJsonPath('data.assistant_message.metadata.intent', 'delete');

    expect(Task::withTrashed()->where('user_id', $user->id)->where('title', 'buy milk')->first()?->trashed())->toBeTrue();
});

it('updates status and priority by name without requiring an id', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'buy milk',
        'description' => 'Groceries task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Update Status Priority Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'update buy milk as done and high priority',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'update');
    $response->assertJsonPath('data.assistant_message.metadata.update.status', 'done');
    $response->assertJsonPath('data.assistant_message.metadata.update.priority', 'high');

    $task = Task::query()->where('user_id', $user->id)->where('title', 'buy milk')->first();

    expect($task)->not->toBeNull();
    expect($task?->status)->toBe('done');
    expect($task?->priority)->toBe('high');
});

it('prefers exact task title matches even when duplicates exist', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Design task search endpoint',
        'description' => 'First duplicate.',
        'due_date' => null,
        'status' => 'in_progress',
        'priority' => 'medium',
        'position' => 0,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'Design task search endpoint',
        'description' => 'Second duplicate.',
        'due_date' => null,
        'status' => 'in_progress',
        'priority' => 'medium',
        'position' => 1,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Duplicate Title Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'set Design task search endpoint to backlog',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'update');
    $response->assertJsonPath('data.assistant_message.metadata.update.status', 'todo');

    expect(Task::query()->where('user_id', $user->id)->where('title', 'Design task search endpoint')->count())->toBe(2);
    expect(Task::query()->where('user_id', $user->id)->where('title', 'Design task search endpoint')->where('status', 'todo')->count())->toBe(1);
    expect(Task::query()->where('user_id', $user->id)->where('title', 'Design task search endpoint')->where('status', 'in_progress')->count())->toBe(1);
});

it('treats set to in progess as a status update instead of delete', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Review archived task UX',
        'description' => 'UI task.',
        'due_date' => null,
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Typo Status Update Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'mode' => 'crud',
        'content' => 'set Review archived task UX to in progess',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'update');
    $response->assertJsonPath('data.assistant_message.metadata.update.status', 'in_progress');

    $task = Task::query()->where('user_id', $user->id)->where('title', 'Review archived task UX')->first();

    expect($task)->not->toBeNull();
    expect($task?->status)->toBe('in_progress');
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

it('returns overdue tasks inquiry results', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Late unresolved task',
        'description' => 'Past due and not completed.',
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'todo',
        'priority' => 'high',
        'position' => 0,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'Late but already done',
        'description' => 'Past due but completed.',
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'done',
        'priority' => 'low',
        'position' => 1,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'Future task',
        'description' => 'Not overdue.',
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 2,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Overdue Inquiry Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'Show overdue tasks',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'overdue_tasks');

    $assistantContent = (string) $response->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('Late unresolved task')
        ->not->toContain('Late but already done')
        ->not->toContain('Future task');
});

it('returns tasks due this week inquiry results', function () {
    $user = User::factory()->create();

    Task::create([
        'user_id' => $user->id,
        'title' => 'Due this week task',
        'description' => 'Within this week.',
        'due_date' => now()->addDay()->toDateString(),
        'status' => 'todo',
        'priority' => 'high',
        'position' => 0,
    ]);

    Task::create([
        'user_id' => $user->id,
        'title' => 'Due next week task',
        'description' => 'Outside this week.',
        'due_date' => now()->addDays(8)->toDateString(),
        'status' => 'todo',
        'priority' => 'low',
        'position' => 1,
    ]);

    $conversationResponse = $this->actingAs($user)->postJson('/chat/conversations', [
        'title' => 'Due This Week Inquiry Test',
    ]);

    $conversationResponse->assertCreated();

    $conversationId = $conversationResponse->json('data.id');

    $response = $this->actingAs($user)->postJson('/chat/messages', [
        'conversation_id' => $conversationId,
        'content' => 'What tasks are due this week?',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.assistant_message.metadata.intent', 'due_this_week');

    $assistantContent = (string) $response->json('data.assistant_message.content');

    expect($assistantContent)
        ->toContain('Due this week task')
        ->not->toContain('Due next week task');
});
