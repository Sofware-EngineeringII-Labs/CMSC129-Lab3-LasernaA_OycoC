<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\Task;
use App\Models\User;
use App\Services\AIService;
use App\Services\PromptService;
use Illuminate\Support\Collection;

class InquiryChatService
{
    private const MAX_LIST_RESULTS = 10;

    public function __construct(
        private readonly AIService $aiService,
        private readonly PromptService $promptService,
    ) {
    }

    /**
     * Generate an inquiry-only assistant response for a user's message.
     *
     * @return array{content:string, metadata:array<string,mixed>}
     */
    public function respond(User $user, ChatConversation $conversation, string $message): array
    {
        $normalized = mb_strtolower(trim($message));
        $previousContext = $this->getPreviousAssistantContext($conversation);

        if ($this->looksLikeCrudCommand($normalized)) {
            return [
                'content' => "I am currently in inquiry-only mode. I can answer questions about your tasks, but I cannot create, update, or delete tasks yet.",
                'metadata' => [
                    'intent' => 'blocked_action',
                ],
            ];
        }

        $analysis = $this->handleSpecificInquiries($user, $normalized, $previousContext);

        if ($analysis !== null) {
            $prompts = $this->promptService->buildInquiryPrompts($message, $analysis, $previousContext);
            $generatedContent = $this->aiService->generateText($prompts['system'], $prompts['user']);

            return [
                'content' => $generatedContent !== '' ? $generatedContent : $analysis['fallback_content'],
                'metadata' => array_merge($analysis['metadata'], [
                    'response_source' => $generatedContent !== '' ? 'gemini' : 'fallback',
                ]),
            ];
        }

        return [
            'content' => "I am not fully sure what you mean yet. Try one of these:\n- Show my tasks\n- What tasks are due today?\n- Show high-priority tasks\n- How many completed tasks do I have?\n- What is my oldest pending task?",
            'metadata' => [
                'intent' => 'unclear',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $previousContext
     * @return array{content:string, metadata:array<string,mixed>}|null
     */
    private function handleSpecificInquiries(User $user, string $normalized, array $previousContext): ?array
    {
        if ($this->isDueTodayIntent($normalized)) {
            $tasks = $user->tasks()
                ->whereDate('due_date', now()->toDateString())
                ->orderBy('status')
                ->orderBy('priority')
                ->orderBy('created_at')
                ->limit(self::MAX_LIST_RESULTS)
                ->get();

            return $this->buildTaskListReply('due_today', 'Tasks due today', $tasks);
        }

        if ($this->isCompletedCountIntent($normalized)) {
            $count = $user->tasks()->where('status', 'done')->count();

            return [
                'fallback_content' => "You currently have {$count} completed task(s).",
                'analysis' => [
                    'count' => $count,
                ],
                'metadata' => [
                    'intent' => 'completed_count',
                    'count' => $count,
                ],
            ];
        }

        if ($this->isOldestPendingIntent($normalized)) {
            $task = $user->tasks()
                ->whereIn('status', ['backlog', 'todo', 'in_progress'])
                ->orderBy('created_at')
                ->first();

            if ($task === null) {
                return [
                    'fallback_content' => 'You do not have any pending tasks right now.',
                    'analysis' => [
                        'count' => 0,
                    ],
                    'metadata' => [
                        'intent' => 'oldest_pending',
                        'count' => 0,
                    ],
                ];
            }

            $dueDate = $this->formatDueDate($task->due_date);
            $content = "Your oldest pending task is #{$task->id} \"{$task->title}\" ({$task->status}, {$task->priority}, due {$dueDate}).";

            return [
                'fallback_content' => $content,
                'analysis' => [
                    'task' => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'due_date' => $dueDate,
                    ],
                ],
                'metadata' => [
                    'intent' => 'oldest_pending',
                    'task_id' => $task->id,
                    'last_task_ids' => [$task->id],
                ],
            ];
        }

        if ($this->isStatusCountIntent($normalized, $status)) {
            $count = $user->tasks()->where('status', $status)->count();
            $label = str_replace('_', ' ', $status);

            return [
                'fallback_content' => "You currently have {$count} task(s) with status {$label}.",
                'analysis' => [
                    'status' => $status,
                    'count' => $count,
                ],
                'metadata' => [
                    'intent' => 'status_count',
                    'status' => $status,
                    'count' => $count,
                ],
            ];
        }

        if ($this->isListIntent($normalized)) {
            $query = $user->tasks();

            $usingFollowupContext = $this->isFollowUpReference($normalized)
                && isset($previousContext['last_task_ids'])
                && is_array($previousContext['last_task_ids'])
                && $previousContext['last_task_ids'] !== [];

            if ($usingFollowupContext) {
                $query->whereIn('id', $previousContext['last_task_ids']);
            }

            $appliedFilters = [];

            $status = $this->extractStatus($normalized);
            if ($status !== null) {
                $query->where('status', $status);
                $appliedFilters['status'] = $status;
            }

            $priority = $this->extractPriority($normalized);
            if ($priority !== null) {
                $query->where('priority', $priority);
                $appliedFilters['priority'] = $priority;
            }

            $tasks = $query
                ->orderBy('status')
                ->orderBy('priority')
                ->orderBy('created_at')
                ->limit(self::MAX_LIST_RESULTS)
                ->get();

            $title = $usingFollowupContext ? 'Filtered tasks from previous context' : 'Task list';

            return $this->buildTaskListReply('task_list', $title, $tasks, $appliedFilters);
        }

        return null;
    }

    private function looksLikeCrudCommand(string $normalized): bool
    {
        $crudKeywords = [
            'create', 'add ', 'new task', 'insert',
            'update', 'edit ', 'change ', 'set ',
            'delete', 'remove', 'archive', 'mark task', 'complete task',
        ];

        foreach ($crudKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isDueTodayIntent(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'due today',
            'today due',
            'tasks today',
            'today tasks',
            'for today',
        ]) || (str_contains($normalized, 'today') && str_contains($normalized, 'due'));
    }

    private function isCompletedCountIntent(string $normalized): bool
    {
        return $this->containsAny($normalized, ['how many', 'count', 'number of'])
            && $this->containsAny($normalized, ['completed', 'done', 'finished']);
    }

    private function isOldestPendingIntent(string $normalized): bool
    {
        return $this->containsAny($normalized, ['oldest', 'earliest'])
            && $this->containsAny($normalized, ['pending', 'task', 'open']);
    }

    private function isStatusCountIntent(string $normalized, ?string &$status): bool
    {
        $status = $this->extractStatus($normalized);

        if ($status === null) {
            return false;
        }

        return $this->containsAny($normalized, ['how many', 'count', 'number of']);
    }

    private function isListIntent(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'show',
            'list',
            'what tasks',
            'what are my tasks',
            'what are the tasks',
            'what do i have',
            'what tasks do i have',
            'can you show my tasks',
            'can you list my tasks',
            'my tasks',
            'tasks i have',
            'do i have tasks',
            'which tasks',
            'give me my tasks',
        ]) || $this->isFollowUpReference($normalized);
    }

    private function isFollowUpReference(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'those',
            'them',
            'that list',
            'these',
            'ones',
            'from that',
            'from those',
        ]) || str_starts_with($normalized, 'what about');
    }

    private function extractStatus(string $normalized): ?string
    {
        if (str_contains($normalized, 'in progress')) {
            return 'in_progress';
        }

        $map = [
            'backlog' => 'backlog',
            'todo' => 'todo',
            'to do' => 'todo',
            'done' => 'done',
            'completed' => 'done',
        ];

        foreach ($map as $needle => $status) {
            if (str_contains($normalized, $needle)) {
                return $status;
            }
        }

        return null;
    }

    private function extractPriority(string $normalized): ?string
    {
        $map = [
            'high priority' => 'high',
            'medium priority' => 'medium',
            'low priority' => 'low',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
        ];

        foreach ($map as $needle => $priority) {
            if (str_contains($normalized, $needle)) {
                return $priority;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $appliedFilters
     * @return array{content:string, metadata:array<string,mixed>}
     */
    private function buildTaskListReply(string $intent, string $title, Collection $tasks, array $appliedFilters = []): array
    {
        if ($tasks->isEmpty()) {
            return [
                'fallback_content' => "{$title}: no matching tasks found.",
                'analysis' => [
                    'title' => $title,
                    'tasks' => [],
                    'filters' => $appliedFilters,
                ],
                'metadata' => [
                    'intent' => $intent,
                    'count' => 0,
                    'filters' => $appliedFilters,
                    'last_task_ids' => [],
                ],
            ];
        }

        $lines = $tasks->map(function (Task $task): string {
            $dueDate = $this->formatDueDate($task->due_date);

            return "- #{$task->id} {$task->title} ({$task->status}, {$task->priority}, due {$dueDate})";
        });

        $content = $title . ":\n" . $lines->implode("\n");
        $taskData = $tasks->map(function (Task $task): array {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $this->formatDueDate($task->due_date),
            ];
        })->values()->all();

        return [
            'fallback_content' => $content,
            'analysis' => [
                'title' => $title,
                'tasks' => $taskData,
                'filters' => $appliedFilters,
            ],
            'metadata' => [
                'intent' => $intent,
                'count' => $tasks->count(),
                'filters' => $appliedFilters,
                'last_task_ids' => $tasks->pluck('id')->all(),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
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

    private function formatDueDate(mixed $dueDate): string
    {
        if ($dueDate instanceof \DateTimeInterface) {
            return $dueDate->format('Y-m-d');
        }

        return 'no due date';
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
