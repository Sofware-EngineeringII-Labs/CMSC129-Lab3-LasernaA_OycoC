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
}
