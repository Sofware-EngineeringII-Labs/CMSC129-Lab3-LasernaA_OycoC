<button id="chat-toggle-btn" type="button" class="btn btn-primary fixed bottom-6 right-6 z-40 shadow-lg">
    Chat
</button>

<section id="chat-popup" class="hidden fixed bottom-24 right-6 z-40 w-[22rem] max-w-[calc(100vw-2rem)] rounded-xl border border-base-300 bg-base-100 shadow-2xl">
    <div class="border-b border-base-300 px-4 py-2">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Task Assistant</h3>
            <div class="flex items-center gap-2">
                <button id="new-chat-btn" type="button" class="btn btn-xs btn-outline">New Chat</button>
                <button id="chat-close-btn" type="button" class="btn btn-xs btn-ghost">Close</button>
            </div>
        </div>

        <div class="mt-2 flex gap-1" role="tablist" aria-label="Chat mode">
            <button id="chat-mode-inquiry" class="btn btn-ghost btn-xs tab-active">Inquiry</button>
            <button id="chat-mode-crud" class="btn btn-ghost btn-xs">CRUD</button>
        </div>
    </div>

    <div id="chat-error" class="hidden border-b border-base-300 px-4 py-2 text-xs text-red-300 bg-red-950/40"></div>

    <div id="messages" class="h-72 overflow-y-auto p-4 space-y-2"></div>

    <div class="border-t border-base-300 p-3">
        <div class="flex gap-2">
            <input type="text" id="message-input" class="input input-bordered input-sm w-full" placeholder="Ask about your tasks..." />
            <button id="send-message-btn" type="button" class="btn btn-sm btn-primary">Send</button>
        </div>
    </div>
</section>
