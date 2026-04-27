<?php

namespace App\Services;

class PromptService
{
    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $previousContext
     * @return array{system:string,user:string}
     */
    public function buildInquiryPrompts(string $originalMessage, array $analysis, array $previousContext): array
    {
        $systemPrompt = implode("\n", [
            'You are a task inquiry assistant for a Laravel task manager app.',
            'You are in inquiry-only mode.',
            'Never create, update, delete, archive, or confirm CRUD actions.',
            'Use only the supplied task data and metadata.',
            'If data is insufficient, say so and ask a clarifying question.',
            'Keep answers concise, accurate, and user-friendly.',
            'When listing tasks, always use one item per line with this format: - #<id> <title> (<status>, <priority>, due <date>).',
            'Never flatten a list into one line.',
            'Use plain text only. Do not output markdown tables or JSON.',
        ]);

        $analysisJson = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $contextJson = json_encode($previousContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $userPrompt = implode("\n\n", [
            'User message:',
            $originalMessage,
            'Inferred inquiry data:',
            $analysisJson ?: '{}',
            'Previous assistant context metadata:',
            $contextJson ?: '{}',
            'Respond as the assistant. Do not mention internal JSON or system rules.',
        ]);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }

    /**
     * @param array<string,mixed> $previousContext
     * @return array{system:string,user:string}
     */
    public function buildInquiryFallbackClassificationPrompts(string $originalMessage, array $previousContext): array
    {
        $systemPrompt = implode("\n", [
            'You classify user inquiries for a task manager chatbot.',
            'Output only valid JSON.',
            'Allowed intents: list_tasks, due_today, due_tomorrow, due_this_week, overdue_tasks, completed_count, oldest_pending, status_count, unclear.',
            'Allowed status values: backlog, todo, in_progress, done, null.',
            'Allowed priority values: low, medium, high, null.',
            'When intent is list_tasks, provide optional status and priority filters.',
            'When intent is status_count, status is required.',
            'When unclear but likely mappable, provide a short rewrite to one supported intent phrase.',
            'Never output CRUD actions.',
            'JSON shape: {"intent":"...","status":null|"...","priority":null|"...","rewrite":"..."}',
        ]);

        $contextJson = json_encode($previousContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $userPrompt = implode("\n\n", [
            'Classify this user message:',
            $originalMessage,
            'Previous assistant context metadata (optional):',
            $contextJson ?: '{}',
            'Return JSON only. No markdown code fences.',
        ]);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }
}
