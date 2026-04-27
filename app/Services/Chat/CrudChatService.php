<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\Task;
use App\Models\User;
use App\Services\AIService;
use App\Services\PromptService;

class CrudChatService
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly PromptService $promptService,
    ) {
    }

    public function respond(User $user, ChatConversation $conversation, string $message): array
    {
        $normalized = mb_strtolower(trim($message));

        $previousContext = $this->getPreviousAssistantContext($conversation);

        // If there's a pending action from the assistant, handle confirmations
        if (isset($previousContext['pending_action']) && is_array($previousContext['pending_action'])) {
            $pending = $previousContext['pending_action'];

            if ($this->isAffirmative($normalized)) {
                return $this->performPendingAction($user, $pending);
            }

            return [
                'content' => 'Okay — I will not perform that action.',
                'metadata' => [
                    'intent' => 'crud_cancelled',
                ],
            ];
        }

        // Try rule-based detection first (fast path)
        if ($this->looksLikeCreate($normalized)) {
            return $this->handleCreate($user, $message);
        }

        if ($this->looksLikeDelete($normalized)) {
            return $this->handleDeleteRequest($user, $normalized, $message);
        }

        if ($this->looksLikeUpdate($normalized)) {
            return $this->handleUpdate($user, $normalized, $message);
        }

        // AI-powered fallback for unclear messages
        return $this->handleCrudFallback($user, $message);
    }

    private function handleCreate(User $user, string $message): array
    {
        $title = $this->extractTaskTitle($message);

        if ($title === '' || mb_strlen($title) < 2) {
            return [
                'content' => 'What should the new task be called? Please provide a title.',
                'metadata' => [
                    'intent' => 'create_followup',
                ],
            ];
        }

        $priority = $this->extractPriority($message);

        $task = $user->tasks()->create([
            'title' => $title,
            'description' => '', // Empty by default, can be edited later
            'status' => 'backlog',
            'priority' => $priority ?? 'medium',
        ]);

        $suffix = $priority ? " with $priority priority" : '';
        return [
            'content' => "Created task #{$task->id} \"{$task->title}\"$suffix.",
            'metadata' => [
                'intent' => 'create',
                'task_id' => $task->id,
            ],
        ];
    }

    private function handleDeleteRequest(User $user, string $normalized, string $originalMessage): array
    {
        $task = $this->resolveTaskByReference($user, $originalMessage, $normalized);

        if ($task === null) {
            return [
                'content' => 'Which task would you like to delete? Say "Delete buy milk" or mention the task name.',
                'metadata' => [
                    'intent' => 'delete_followup',
                ],
            ];
        }

        if (is_array($task)) {
            return [
                'content' => $task['content'],
                'metadata' => [
                    'intent' => $task['intent'],
                ],
            ];
        }

        return $this->buildDeleteConfirmation($task);
    }

    private function handleUpdate(User $user, string $normalized, string $originalMessage): array
    {
        [$targetPhrase, $updatePhrase] = $this->splitUpdateCommand($originalMessage);

        $task = $targetPhrase !== null
            ? $this->resolveTaskByReference($user, $targetPhrase, mb_strtolower($targetPhrase))
            : $this->resolveTaskByReference($user, $originalMessage, $normalized);

        if ($task === null) {
            $followUp = $targetPhrase !== null
                ? "I could not find a task named \"{$targetPhrase}\"."
                : 'Which task would you like to update? Say "Mark buy milk as done" or mention the task name.';

            return [
                'content' => $followUp,
                'metadata' => [
                    'intent' => 'update_followup',
                ],
            ];
        }

        if (is_array($task)) {
            return [
                'content' => $task['content'],
                'metadata' => [
                    'intent' => $task['intent'],
                ],
            ];
        }

        $updates = [];
        $messages = [];
        $candidateText = $updatePhrase ?? $originalMessage;

        $newStatus = $this->extractStatus($candidateText, $normalized);
        if ($newStatus !== null && $newStatus !== $task->status) {
            $updates['status'] = $newStatus;
            $messages[] = $newStatus === 'done'
                ? "Marked task #{$task->id} as done."
                : "Updated task #{$task->id} status to {$newStatus}.";
        }

        $newPriority = $this->extractPriority($candidateText);
        if ($newPriority !== null && $newPriority !== $task->priority) {
            $updates['priority'] = $newPriority;
            $messages[] = "Updated task #{$task->id} priority to {$newPriority}.";
        }

        $newTitle = $updatePhrase !== null
            ? $this->normalizeTaskReferenceCandidate($updatePhrase)
            : $this->extractNewTitle($originalMessage);

        if ($newTitle !== null && mb_strlen(trim($newTitle)) > 1 && !$this->looksLikeStatusOrPriority($newTitle)) {
            $updates['title'] = trim($newTitle);
            $messages[] = "Updated task #{$task->id} title to \"{$newTitle}\".";
        }

        if ($updates === []) {
            return [
                'content' => 'I can update the title, status, or priority. Try:\n- "Mark buy milk as done"\n- "Set buy milk to high priority"\n- "Rename buy milk to \"New name\""',
                'metadata' => [
                    'intent' => 'update_help',
                ],
            ];
        }

        $task->update($updates);

        return [
            'content' => implode(' ', $messages),
            'metadata' => [
                'intent' => 'update',
                'task_id' => $task->id,
                'update' => $updates,
            ],
        ];
    }

    private function performPendingAction(User $user, array $pending): array
    {
        if (!isset($pending['type'])) {
            return [
                'content' => 'No pending action to perform.',
                'metadata' => ['intent' => 'crud_none'],
            ];
        }

        if ($pending['type'] === 'delete' && isset($pending['task_id'])) {
            $task = $user->tasks()->find($pending['task_id']);
            if ($task === null) {
                return [
                    'content' => "Task #{$pending['task_id']} no longer exists.",
                    'metadata' => ['intent' => 'delete_not_found'],
                ];
            }

            $task->delete();

            return [
                'content' => "Deleted task #{$pending['task_id']}.",
                'metadata' => ['intent' => 'delete'],
            ];
        }

        return [
            'content' => 'I do not know how to perform that action.',
            'metadata' => ['intent' => 'crud_unknown'],
        ];
    }

    private function buildDeleteConfirmation(Task $task): array
    {
        return [
            'content' => "Are you sure you want to delete \"{$task->title}\"? Reply 'yes' to confirm.",
            'metadata' => [
                'intent' => 'delete_confirm',
                'pending_action' => [
                    'type' => 'delete',
                    'task_id' => $task->id,
                ],
            ],
        ];
    }

    private function extractId(string $normalized): ?int
    {
        if (preg_match('/#(\d+)/', $normalized, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/task\s+(\d+)/', $normalized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function resolveTaskByReference(User $user, string $message, string $normalized): Task|array|null
    {
        $id = $this->extractId($normalized);

        if ($id !== null) {
            $task = $user->tasks()->find($id);

            if ($task !== null) {
                return $task;
            }

            return [
                'content' => "I could not find a task for #{$id}.",
                'intent' => 'delete_not_found',
            ];
        }

        $candidate = $this->extractTaskReference($message);

        if ($candidate === null) {
            return null;
        }

        $exactMatches = $user->tasks()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($exactMatches->isNotEmpty()) {
            return $exactMatches->first();
        }

        $matches = $user->tasks()
            ->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($candidate) . '%'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        if ($matches->isEmpty()) {
            return [
                'content' => "I could not find a task named \"{$candidate}\".",
                'intent' => 'delete_not_found',
            ];
        }

        if ($matches->count() > 1) {
            // If all matching tasks have exactly the same title, prefer the most recently updated one.
            $titles = $matches->map(fn (Task $t) => mb_strtolower(trim($t->title)))->unique();
            if ($titles->count() === 1) {
                return $matches->first();
            }

            // Otherwise provide a clearer disambiguation message including ids and timestamps.
            $items = $matches->map(fn (Task $t) => "#{$t->id} ({$t->title})")->take(5)->implode(', ');

            return [
                'content' => "I found multiple tasks matching \"{$candidate}\": {$items}. Please use a more specific name or include the task id.",
                'intent' => 'crud_ambiguous',
            ];
        }

        return $matches->first();
    }

    private function extractTaskReference(string $message): ?string
    {
        if (preg_match('/"([^"]+)"|\'([^\']+)\'/s', $message, $m)) {
            return $this->normalizeTaskReferenceCandidate(trim($m[1] ?? $m[2] ?? ''));
        }

        $normalized = mb_strtolower(trim($message));
        $normalized = preg_replace('/^(create|add|new|make|delete|remove|update|edit|change|mark|finish|rename|set|move)\s+(?:a\s+|an\s+|the\s+)?(?:task|todo)?\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^(to\s+|as\s+|called\s+|named\s+|titled\s+)/i', '', $normalized) ?? $normalized;
        return $this->normalizeTaskReferenceCandidate($normalized);
    }

    private function splitUpdateCommand(string $message): array
    {
        $normalized = trim($message);
        $normalized = preg_replace('/^(update|edit|change|rename|set|move)\s+/i', '', $normalized) ?? $normalized;

        if (preg_match('/^("[^"]+"|\'[^\']+\'|[^\n]+?)\s+to\s+(.+)$/i', $normalized, $matches)) {
            return [
                $this->normalizeTaskReferenceCandidate(trim($matches[1], " \t\n\r\0\x0B\"'")),
                trim($matches[2], " \t\n\r\0\x0B"),
            ];
        }

        if (preg_match('/^("[^"]+"|\'[^\']+\'|[^\n]+?)\s+as\s+(.+)$/i', $normalized, $matches)) {
            return [
                $this->normalizeTaskReferenceCandidate(trim($matches[1], " \t\n\r\0\x0B\"'")),
                trim($matches[2], " \t\n\r\0\x0B"),
            ];
        }

        return [
            $this->extractTaskReference($message),
            null,
        ];
    }

    private function normalizeTaskReferenceCandidate(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        // Avoid stripping words that may legitimately appear in task titles (e.g. "status").
        $candidate = preg_replace('/\b(as|priority|done|complete|completed|high|medium|low|urgent|critical|important|asap|optional|later|whenever)\b.*$/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(to|for)\b.*$/i', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B-:,.!?\"'");

        return $candidate !== '' ? $candidate : null;
    }

    private function extractStatus(string $message, string $normalized): ?string
    {
        $subject = mb_strtolower($message . ' ' . $normalized);

        if ($this->containsAny($subject, ['in progess', 'inproggess', 'inprogress', 'in progress'])) {
            return 'in_progress';
        }

        if ($this->containsAny($subject, ['done', 'completed', 'complete', 'finished', 'finish', 'mark as done', 'mark done', 'make it done'])) {
            return 'done';
        }

        if ($this->containsAny($subject, ['todo', 'to do', 'backlog', 'pending', 'not started'])) {
            return 'todo';
        }

        if ($this->containsAny($subject, ['in progress', 'in_progress', 'working on'])) {
            return 'in_progress';
        }

        return null;
    }

    private function looksLikeStatusOrPriority(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        return $this->extractStatus($normalized, $normalized) !== null || $this->extractPriority($normalized) !== null;
    }

    private function handleCrudFallback(User $user, string $message): array
    {
        $prompts = $this->buildCrudFallbackClassificationPrompts($message);
        $rawClassification = $this->aiService->generateText($prompts['system'], $prompts['user']);

        if ($rawClassification === '') {
            return $this->helpMessage();
        }

        $classification = $this->extractCrudClassification($rawClassification);

        if ($classification === null) {
            return $this->helpMessage();
        }

        $intent = $classification['intent'];

        if ($intent === 'create') {
            return $this->handleCreate($user, $classification['rewrite'] ?? $message);
        }

        if ($intent === 'delete') {
            return $this->handleDeleteRequest($user, mb_strtolower($classification['rewrite'] ?? $message), $classification['rewrite'] ?? $message);
        }

        if ($intent === 'update') {
            return $this->handleUpdate($user, mb_strtolower($classification['rewrite'] ?? $message), $classification['rewrite'] ?? $message);
        }

        return $this->helpMessage();
    }

    private function buildCrudFallbackClassificationPrompts(string $message): array
    {
        return [
            'system' => 'You classify user messages for a task manager CRUD chatbot. Respond with JSON only, no markdown. {"intent":"create|update|delete","rewrite":"normalized phrase"}',
            'user' => "Classify this message for a task manager:\n\"$message\"\n\nRespond with only the JSON.",
        ];
    }

    private function extractCrudClassification(string $raw): ?array
    {
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            if (preg_match('/\{[^}]*"intent"[^}]*\}/s', $raw, $matches) !== 1) {
                return null;
            }
            $decoded = json_decode($matches[0], true);
            if (!is_array($decoded)) {
                return null;
            }
        }

        $intent = isset($decoded['intent']) && is_string($decoded['intent'])
            ? mb_strtolower(trim($decoded['intent']))
            : '';

        if (!in_array($intent, ['create', 'update', 'delete'], true)) {
            return null;
        }

        $rewrite = isset($decoded['rewrite']) && is_string($decoded['rewrite']) ? trim($decoded['rewrite']) : '';

        return [
            'intent' => $intent,
            'rewrite' => $rewrite ?: null,
        ];
    }

    private function extractTaskTitle(string $message): string
    {
        // Try to extract from quotes first
        if (preg_match('/\"([^\"]+)\"|\'([^\']+)\'/s', $message, $m)) {
            return $m[1] ?? $m[2] ?? '';
        }

        // Try regex pattern for natural language like "create a task to buy milk"
        if (preg_match('/(create|add|new|make)\s+(?:(?:a\s+)?(?:new\s+)?)?(?:task|todo)?\s+(?:to\s+|for\s+)?(?:a\s+)?([^\.!\?\n]+)/i', $message, $m)) {
            return trim($m[2] ?? '');
        }

        // Fallback: take the rest of the message after CRUD keywords
        $title = preg_replace('/^(create|add|new|make)\s+(?:task\s+)?(a\s+)?(new\s+)?/i', '', $message);
        return trim($title);
    }

    private function extractNewTitle(string $message): ?string
    {
        // Pattern 1: "to \"new title\""
        if (preg_match('/to\s+"([^"]+)"/i', $message, $m)) {
            return $m[1];
        }

        // Pattern 2: "to 'new title'"
        if (preg_match("/to\s+'([^']+)'/i", $message, $m)) {
            return $m[1];
        }

        // Pattern 3: "rename to new title" or "rename: new title"
        if (preg_match('/rename\s+(?:to\s+)?(?:as\s+)?(?:a\s+)?(?:\"([^\"]+)\"|\'([^\']+)\'|([^\.!\n]+))/i', $message, $m)) {
            return $m[1] ?? $m[2] ?? trim($m[3] ?? '');
        }

        // Pattern 4: "change to ..." or "set to ..."
        if (preg_match('/(change|set)\s+(?:to\s+)?(?:\"([^\"]+)\"|\'([^\']+)\'|([^\.!\n]+))/i', $message, $m)) {
            return $m[2] ?? $m[3] ?? trim($m[4] ?? '');
        }

        return null;
    }

    private function findTaskIdByTitle(User $user, string $message): ?int
    {
        $task = $this->resolveTaskByReference($user, $message, mb_strtolower($message));

        return $task instanceof Task ? $task->id : null;
    }

    private function extractPriority(string $message): ?string
    {
        $map = [
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            'urgent' => 'high',
            'critical' => 'high',
            'important' => 'high',
            'asap' => 'high',
            'priority' => null, // ignore bare "priority"
            'optional' => 'low',
            'later' => 'low',
            'whenever' => 'low',
        ];

        $normalized = mb_strtolower($message);
        foreach ($map as $needle => $priority) {
            if ($priority !== null && str_contains($normalized, $needle)) {
                return $priority;
            }
        }

        return null;
    }

    private function looksLikeCreate(string $normalized): bool
    {
        return $this->containsAny($normalized, ['create', 'add', 'new task', 'insert', 'make a task', 'make task']);
    }

    private function looksLikeDelete(string $normalized): bool
    {
        return (bool) preg_match('/\b(delete|remove|archive|discard)\b/i', $normalized);
    }

    private function looksLikeUpdate(string $normalized): bool
    {
        return $this->containsAny($normalized, ['update', 'edit', 'change', 'mark', 'complete', 'finish', 'rename', 'set', 'move']);
    }

    private function isAffirmative(string $normalized): bool
    {
        return $this->containsAny($normalized, ['yes', 'yep', 'confirm', 'do it', 'sure', 'okay', 'ok', 'yup', 'go ahead']);
    }

    private function helpMessage(): array
    {
        return [
            'content' => "I can help with tasks. Try:\n- 'Create task \"Buy milk\" (high priority)'\n- 'Mark task #5 as done' or 'Finish #5'\n- 'Rename #5 to \"New name\"'\n- 'Delete task #5'\n- 'Set #5 to low priority'",
            'metadata' => [
                'intent' => 'crud_help',
            ],
        ];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function getPreviousAssistantContext(ChatConversation $conversation): array
    {
        $message = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        if ($message === null || !is_array($message->metadata)) {
            return [];
        }

        return $message->metadata;
    }
}
