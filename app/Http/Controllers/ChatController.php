<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatMessageRequest;
use App\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function listConversations(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->chatConversations()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'last_message_at', 'created_at', 'updated_at']);

        return response()->json([
            'data' => $conversations,
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $request->user()->chatConversations()->create([
            'title' => $validated['title'] ?? null,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'message' => 'Conversation created.',
            'data' => $conversation,
        ], 201);
    }

    public function showConversation(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->findUserConversation($request, $conversationId);

        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
            ],
        ]);
    }

    public function storeMessage(ChatMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $conversation = $this->findUserConversation($request, (int) $validated['conversation_id']);

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['content'],
            'metadata' => null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message stored.',
            'data' => [
                'conversation' => $conversation->fresh(),
                'chat_message' => $message,
            ],
        ], 201);
    }

    private function findUserConversation(Request $request, int $conversationId): ChatConversation
    {
        return $request->user()
            ->chatConversations()
            ->findOrFail($conversationId);
    }
}