# CMSC 129 Lab 3 Implementation Plan

## Working Agreement

We will implement this project in strict human-in-the-loop mode.

For every step:
1. Copilot implements only that step.
2. Copilot provides what changed and a manual test checklist.
3. Copilot provides one commit message for that exact step.
4. Copilot stops and waits for your confirmation before proceeding.

You can ask questions at any step before approving the next one.

---

## Step-by-Step Plan

### Step 1: Provider Setup and Secure Scaffolding (Backend Only)
Goal: Prepare Gemini integration safely without exposing secrets.

Tasks:
1. Add Gemini configuration mapping in `config/services.php`.
2. Add placeholder variables in `.env.example`.
3. Keep API calls backend-only (no frontend key usage).

Human intervention required:
1. Create a Gemini API key in Google AI Studio.
2. Put it in local `.env` only.
3. Never commit real API keys.

Commit message:
`chore(ai): add Gemini env and service configuration scaffolding`

---

### Step 2: Conversation Persistence and Chat API Contract
Goal: Create persistent chat data model and authenticated message endpoint contract.

Tasks:
1. Add migrations and models for chat conversations and chat messages.
2. Scope conversations/messages to authenticated user.
3. Add validated message request contract.
4. Add authenticated chat routes.

Commit message:
`feat(chat): add persistent conversation models and authenticated message API contract`

---

### Step 3: Minimum Inquiry Engine (Backend)
Goal: Deliver minimum chatbot behavior for inquiry-only use cases.

Tasks:
1. Implement inquiry-only assistant response logic.
2. Support at least 5 inquiry types about tasks.
3. Add graceful handling for unclear queries.
4. Enforce strict user scoping for all data access.
5. Maintain context with recent message history (minimum baseline).

Commit message:
`feat(chatbot): implement inquiry assistant with task-scoped query handlers`

---

### Step 4: Frontend Phase A (Minimum Chatbot Integration)
Goal: Integrate inquiry chatbot end-to-end before CRUD assistant features.

Tasks:
1. Connect chat widget to backend endpoint.
2. Add loading, error, and message history rendering states.
3. Keep behavior inquiry-only in this phase.
4. Mount widget on all authenticated pages through shared layout.

Commit message:
`feat(ui-chat): integrate inquiry chatbot widget across authenticated pages`

Approval Gate A:
Manual testing and confirmation before expanded assistant work begins.

---

### Step 5: Minimum Requirement Tests and Documentation Slice
Goal: Lock in minimum requirement quality before expanded scope.

Tasks:
1. Add feature tests for inquiry flows and baseline context behavior.
2. Update README minimum section with setup and sample inquiry prompts.

Commit message:
`test(docs): cover minimum chatbot inquiries and document usage`

---

### Step 6: Correction Phase - Gemini Inquiry Integration
Goal: Align minimum chatbot with lab requirement by using Gemini API during inquiry phase.

Tasks:
1. Add dedicated AI integration service for Gemini API calls (backend proxy only).
2. Add dedicated prompt service for inquiry prompt engineering and response shaping.
3. Refactor inquiry flow to call Gemini for inquiry responses instead of purely local rule-based output.
4. Keep inquiry safeguards: no CRUD execution in this correction step.
5. Keep user scoping and context behavior intact while moving response generation to Gemini.

Commit message:
`refactor(chatbot): route inquiry responses through Gemini AI service and prompt layer`

Step 6 refinements applied after validation:
1. Broadened inquiry language pattern coverage (Option A, low API usage) so more natural phrasing can be understood without forcing strict command words.
2. Added AI fallback intent classification for unmatched inquiry phrasing:
	1. Rules still run first.
	2. If rules miss, Gemini classifies the intent to a safe inquiry schema.
	3. Backend maps classification to canonical internal query phrases and executes only user-scoped reads.
3. Kept inquiry-only safeguards intact (no CRUD execution in this phase).

Refinement commit messages:
`refactor(chatbot): broaden inquiry language patterns for rule-first intent detection`
`feat(chatbot): add Gemini fallback intent classification for unmatched inquiries`

---

### Step 7: Expanded Backend Assistant (Natural Language CRUD)
Goal: Add AI assistant capability for create, update, and delete with safe controls.

Tasks:
1. Add tool-based backend CRUD operations from natural language.
2. Enforce ownership checks in every operation.
3. Add mandatory two-step confirmation for update and delete.
4. Persist pending destructive actions and execute only after explicit confirmation.
5. Expand context handling to support stronger follow-up understanding.

Commit message:
`feat(assistant): add tool-driven CRUD actions with two-step confirmations`

---

### Step 8: Frontend Phase B (Expanded CRUD UX)
Goal: Integrate confirmation flow and visible board updates for CRUD actions.

Tasks:
1. Show confirmation prompts in chat for update and delete.
2. Show operation results and summaries.
3. Ensure task board reflects CRUD outcomes reliably.

Commit message:
`feat(ui-assistant): add CRUD confirmation UX and task board refresh integration`

Approval Gate B:
Manual testing and confirmation before final hardening.

---

### Step 9: Final Hardening, Full Docs, Demo Readiness
Goal: Complete rubric-aligned verification and finalize deliverables.

Tasks:
1. Add security hardening (including route protection and throttling).
2. Add full feature tests for happy and failure paths.
3. Finalize README for setup, env vars, sample prompts, and screenshots.
4. Final pass against rubric checklist.

Commit message:
`chore(release): finalize AI assistant tests, security hardening, and lab documentation`

Approval Gate C:
Final sign-off for submission readiness.

---

## Human-Only Interventions

1. Create and manage Gemini API key.
2. Place secret only in local `.env`.
3. Run manual acceptance checks at Gates A, B, and C.
4. Ask process questions before approving each next step.

---

## Verification

1. Confirm widget appears on all authenticated pages and not on guest pages.
2. Confirm at least 5 inquiry types return correct task data.
3. Confirm context works across at least 10 messages with follow-up references.
4. Confirm create, update, and delete via natural language work and stay user-scoped.
5. Confirm update and delete do not execute without explicit confirmation.
6. Confirm task board reflects assistant CRUD outcomes.
7. Confirm no API key appears in frontend code or build output.
8. Run feature tests for happy paths and failure paths.
9. Confirm README matches rubric requirements.

---

## Scope Boundaries

Included:
1. Minimum + expanded features
2. Gemini provider
3. DB-persisted history
4. Global authenticated widget
5. English-only prompts
6. Two-step chat confirmation for update and delete

Excluded for first pass:
1. Multi-provider fallback
2. Multilingual support
3. Voice input
4. Advanced analytics

---

## Notes for Execution

1. We will commit at the end of each approved step.
2. We will not proceed to the next step without your explicit approval.
3. If a step is blocked by credentials or external setup, Copilot will provide exact human actions and wait.
