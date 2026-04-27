<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        if (app()->environment('testing')) {
            return '';
        }

        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash-lite');
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');

        if ($apiKey === '') {
            return '';
        }

        $endpoint = sprintf('%s/v1beta/models/%s:generateContent', $baseUrl, $model);

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $apiKey])
            ->post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $userPrompt],
                        ],
                    ],
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                ],
            ]);

        if (!$response->successful()) {
            return '';
        }

        $parts = $response->json('candidates.0.content.parts', []);

        if (!is_array($parts) || $parts === []) {
            return '';
        }

        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }
}
