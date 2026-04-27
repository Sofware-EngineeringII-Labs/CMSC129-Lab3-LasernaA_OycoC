# Chatbot Implementation Overview

## 1. Project Summary

This implementation adds an AI-enabled chatbot to the Laravel task manager with a staged approach:
1. Minimum phase: inquiry chatbot with conversation history, context, and on-page widget.
2. Correction phase: inquiry responses routed through Gemini API (backend proxy), with rule-first plus AI fallback classification.
3. Expanded phase (next): CRUD-capable assistant with confirmations.

The current state already includes a working inquiry chatbot with Gemini integration and AI fallback intent classification for unmatched phrasing.

---

## 2. How the AI API Is Integrated

The Gemini API integration is backend-only and isolated in dedicated services:
1. `app/Services/AIService.php`
   1. Sends requests to Gemini endpoint using Laravel HTTP client.
   2. Reads API key/model/base URL from server config.
   3. Returns generated text safely, with empty-string fallback on failure.
2. `app/Services/PromptService.php`
   1. Builds inquiry response prompts.
   2. Builds fallback intent-classification prompts for unmatched user phrasing.
3. `app/Services/Chat/InquiryChatService.php`
   1. Orchestrates intent resolution and safe backend query execution.
   2. Uses `PromptService` + `AIService` for final response wording and fallback classification.

Configuration points:
1. `config/services.php` (Gemini config mapping)
2. `.env` (real key, model, base URL)
3. `.env.example` (placeholders only)

---

## 3. Inquiry Chatbot vs CRUD Assistant

### Inquiry Chatbot (current)
1. Purpose: answer questions about tasks.
2. Allowed actions: read/query/count/filter only.
3. Behavior:
   1. Rule-first intent matching for low-cost handling.
   2. AI fallback classifier when rules do not match.
   3. Gemini-generated conversational response wording.
4. Safety: CRUD-style commands are blocked in inquiry mode.

### CRUD Assistant (next step)
1. Purpose: perform create/update/delete via natural language.
2. Needs:
   1. Explicit backend tool/action execution.
   2. Mandatory confirmations for destructive changes.
   3. Result reflection in both chat and main UI.
3. Safety: ownership checks and confirmation gates before writes/deletes.

---

## 4. Function Calling / Tool Use Pattern

The project uses a backend-controlled tool pattern (not direct DB access by AI):
1. AI infers intent and optional filters.
2. Backend validates and maps intent to internal query/action paths.
3. Backend executes Eloquent queries/actions under authenticated user scope.
4. AI is used for language understanding/phrasing, not as direct DB executor.

Current inquiry tool equivalents are implemented inside `InquiryChatService` as safe handlers:
1. list tasks
2. due today
3. completed count
4. oldest pending
5. status count

The same pattern will be expanded in CRUD phase for create/update/delete tools with confirmation.

---

## 5. Prompt Engineering Strategies Used

Prompting is split by responsibility:
1. Inquiry response prompt (`PromptService::buildInquiryPrompts`)
   1. System rules enforce inquiry-only mode.
   2. Uses structured backend analysis payload (tasks/count/filters/context).
   3. Asks for concise user-facing response.
2. Fallback classification prompt (`PromptService::buildInquiryFallbackClassificationPrompts`)
   1. Restricts output to strict JSON schema.
   2. Restricts allowed intents and enum values.
   3. Explicitly disallows CRUD intents in this phase.

This reduces hallucination risk and keeps backend in control of data operations.

---

## 6. Conversation Context Handling

Context is persisted in database and re-used in follow-up questions:
1. `chat_conversations` table stores per-user conversation records.
2. `chat_messages` table stores user/assistant turns.
3. Assistant metadata stores structured context (such as `last_task_ids`, filters, intent).
4. Follow-up phrases (for example, “what about high priority ones?”) can re-filter previous result sets.

This supports the required context continuity while keeping scope user-specific.

---

## 7. Security Measures for API Keys

Security controls implemented:
1. API key stored only in `.env`, never in frontend code.
2. Placeholder-only values in `.env.example`.
3. Gemini calls executed server-side in `AIService`.
4. Frontend calls only backend routes (`/chat/...`).
5. Auth middleware protects chat routes.
6. Conversation lookups are user-scoped (`user()->chatConversations()->findOrFail(...)`).

This aligns with the lab rule that AI must not directly access the database.

---

## 8. Error Handling and Edge Cases

Current handling includes:
1. API failure fallback:
   1. If Gemini fails or returns empty, service returns deterministic fallback content.
2. Unmatched intent fallback:
   1. Rule miss -> AI classification attempt.
   2. If classifier fails or invalid JSON -> user-friendly clarification fallback.
3. Validation safeguards:
   1. Message payload validation via `ChatMessageRequest`.
4. Ownership and access safeguards:
   1. Non-owner conversation IDs fail with not found.
5. Inquiry-only safety:
   1. CRUD-like prompts return blocked-action guidance in current phase.

---

## 9. How AI Service Communicates with Database

The communication path is controlled and indirect:
1. AI never queries DB directly.
2. Backend service reads DB using Eloquent under authenticated user scope.
3. Backend sends only relevant structured results to AI for response generation.
4. Backend stores final assistant response and metadata back to `chat_messages`.

So the flow is:
1. user message -> backend route/controller
2. backend intent handling/query -> structured analysis
3. structured analysis -> Gemini for natural response
4. assistant response -> persisted message -> returned to frontend

---

## 10. End-to-End Request Flow

1. UI capture and submit
   1. User types a message in the widget UI.
   2. Frontend code sends a POST request to `/chat/messages` with:
      1. `conversation_id`
      2. `content`
   3. Request is sent with authenticated session cookies (same web app session).

2. Route and controller entry
   1. Request enters authenticated chat route in `routes/web.php`.
   2. `ChatController@storeMessage` receives the request.
   3. `ChatMessageRequest` validates payload shape and limits.

3. Conversation ownership and persistence
   1. Controller resolves conversation through user-scoped lookup.
   2. If conversation does not belong to current user, request fails safely.
   3. Valid user message is saved in `chat_messages` as role `user`.

4. Inquiry orchestration in backend service
   1. Controller calls `InquiryChatService::respond`.
   2. Service first enforces inquiry-only safety:
      1. CRUD-style requests are blocked in this phase.
   3. Service tries rule-first intent detection (low-cost path).
   4. If no rule matches, service triggers AI fallback classification:
      1. Gemini classifies to safe inquiry schema.
      2. Backend validates/normalizes the classification.
      3. Backend maps classification to canonical internal inquiry path.
   5. Backend performs user-scoped Eloquent reads (never direct AI-to-DB).
   6. Service builds structured analysis payload from query results.

5. Prompt build and Gemini response generation
   1. `PromptService` builds system + user prompts using:
      1. original user message,
      2. structured analysis,
      3. previous assistant context metadata.
   2. `AIService` sends request to Gemini with server-side API key.
   3. If Gemini returns valid content, that becomes assistant response.
   4. If Gemini fails/returns empty, deterministic fallback text is used.

6. Assistant persistence and response to UI
   1. Controller stores assistant message in `chat_messages` as role `assistant`.
   2. Assistant metadata stores intent, filters, context IDs, and source flags.
   3. Controller updates conversation `last_message_at`.
   4. JSON response returns both user and assistant messages.

7. Frontend render and state update
   1. Frontend appends assistant response to chat window.
   2. Loading state is removed.
   3. Any server-side error is shown as a user-friendly UI message.

8. Context continuity for next turns
   1. On the next message, service reads latest assistant metadata.
   2. Follow-up phrases can reference prior result sets.
   3. This enables context-aware inquiry flow without exposing raw DB access to AI.

---

## 11. Responsibility Map (by File)

Core chatbot files:
1. `routes/web.php` - chat endpoints under auth middleware.
2. `app/Http/Controllers/ChatController.php` - chat API orchestration.
3. `app/Http/Requests/ChatMessageRequest.php` - message payload validation.
4. `app/Services/Chat/InquiryChatService.php` - inquiry intent logic + fallback orchestration.
5. `app/Services/PromptService.php` - prompt engineering and classification schema prompts.
6. `app/Services/AIService.php` - Gemini HTTP integration.
7. `app/Models/ChatConversation.php` and `app/Models/ChatMessage.php` - persistence models.
8. `resources/js/chat.js` - widget request/response handling.
9. `resources/views/components/chat-widget.blade.php` - widget UI markup.
10. `resources/views/components/layout.blade.php` - global auth-only mounting.

---

## 12. Notes for Demo and Defense

When presenting:
1. Emphasize secure backend-only API integration.
2. Explain rule-first plus AI fallback as a cost-aware, robust strategy.
3. Show context follow-up behavior in a short conversation sequence.
4. Highlight that DB access always remains backend controlled.
5. Clarify inquiry-only vs upcoming CRUD assistant separation.
