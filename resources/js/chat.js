const messagesContainer = document.getElementById('messages');
const messageInput = document.getElementById('message-input');
const sendMessageButton = document.getElementById('send-message-btn');
const newChatButton = document.getElementById('new-chat-btn');
const chatToggleButton = document.getElementById('chat-toggle-btn');
const chatCloseButton = document.getElementById('chat-close-btn');
const chatPopup = document.getElementById('chat-popup');
const chatError = document.getElementById('chat-error');

let activeConversationId = null;
let isSending = false;
let selectedMode = 'inquiry';

async function apiRequest(method, url, data = null) {
    const response = await window.axios({
        method,
        url,
        data,
        headers: {
            Accept: 'application/json',
        },
    });

    return response.data;
}

function setError(message = '') {
    if (!chatError) {
        return;
    }

    if (!message) {
        chatError.classList.add('hidden');
        chatError.textContent = '';
        return;
    }

    chatError.textContent = message;
    chatError.classList.remove('hidden');
}

function setSendingState(sending) {
    isSending = sending;

    if (!sendMessageButton || !messageInput) {
        return;
    }

    sendMessageButton.disabled = sending;
    messageInput.disabled = sending;
    sendMessageButton.classList.toggle('loading', sending);
    sendMessageButton.classList.toggle('loading-spinner', sending);
}

function setChatOpen(isOpen) {
    if (!chatPopup) {
        return;
    }

    chatPopup.classList.toggle('hidden', !isOpen);

    if (isOpen && messageInput) {
        messageInput.focus();
    }
}

function setMode(mode) {
    selectedMode = mode === 'crud' ? 'crud' : 'inquiry';

    const inquiryBtn = document.getElementById('chat-mode-inquiry');
    const crudBtn = document.getElementById('chat-mode-crud');

    if (inquiryBtn && crudBtn) {
        inquiryBtn.classList.toggle('tab-active', selectedMode === 'inquiry');
        crudBtn.classList.toggle('tab-active', selectedMode === 'crud');
    }

    if (messageInput) {
        messageInput.placeholder = selectedMode === 'crud' ? 'Create, update, or delete tasks...' : 'Ask about your tasks...';
        messageInput.focus();
    }
}

function formatAssistantContent(content) {
    let formatted = String(content ?? '').replace(/\r\n/g, '\n').trim();

    // Convert compressed bullet responses like "Task list: - item - item" to multiline.
    if (!formatted.includes('\n') && formatted.includes(' - ')) {
        formatted = formatted.replace(/:\s*-\s+/g, ':\n- ');
        formatted = formatted.replace(/\s-\s(?=#\d+)/g, '\n- ');
    }

    return formatted;
}

function appendMessage(role, content) {
    if (!messagesContainer) {
        return;
    }

    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role}`;
    messageDiv.textContent = role === 'assistant' ? formatAssistantContent(content) : content;

    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function renderMessages(messages) {
    if (!messagesContainer) {
        return;
    }

    messagesContainer.innerHTML = '';

    if (!messages || messages.length === 0) {
        appendMessage('assistant', 'Hi! How can I help you with your tasks today?');
        return;
    }

    for (const message of messages) {
        const role = message.role === 'assistant' ? 'assistant' : 'user';
        appendMessage(role, message.content);
    }
}

async function ensureConversation() {
    if (activeConversationId !== null) {
        return activeConversationId;
    }

    const listPayload = await apiRequest('get', '/chat/conversations');
    const conversations = Array.isArray(listPayload.data) ? listPayload.data : [];

    if (conversations.length > 0) {
        activeConversationId = conversations[0].id;
        return activeConversationId;
    }

    const createPayload = await apiRequest('post', '/chat/conversations', {
        title: 'Task Assistant Chat',
    });

    activeConversationId = createPayload?.data?.id ?? null;
    return activeConversationId;
}

async function loadConversation(conversationId) {
    const payload = await apiRequest('get', `/chat/conversations/${conversationId}`);
    const messages = payload?.data?.messages ?? [];
    renderMessages(messages);
}

async function startNewChat() {
    if (!messagesContainer) {
        return;
    }

    setError('');

    try {
        const payload = await apiRequest('post', '/chat/conversations', {
            title: 'Task Assistant Chat',
        });

        activeConversationId = payload?.data?.id ?? null;
        renderMessages([]);
    } catch {
        setError('Unable to start a new chat right now. Please try again.');
    }
}

async function sendMessage() {
    if (!messageInput || isSending) {
        return;
    }

    const message = messageInput.value.trim();

    if (!message) {
        return;
    }

    setError('');
    appendMessage('user', message);
    messageInput.value = '';
    setSendingState(true);

    try {
        const conversationId = await ensureConversation();

        if (!conversationId) {
            throw new Error('Conversation is not available.');
        }

        const payload = await apiRequest('post', '/chat/messages', {
            conversation_id: conversationId,
            content: message,
            mode: selectedMode,
        });

        const assistantMessage = payload?.data?.assistant_message?.content;
        const assistantMeta = payload?.data?.assistant_message?.metadata ?? {};

        if (assistantMessage) {
            appendMessage('assistant', assistantMessage);

            // If assistant metadata indicates a task change, emit an event and show a small system notice.
            if (assistantMeta && typeof assistantMeta === 'object') {
                const intent = assistantMeta.intent ?? null;
                const taskId = assistantMeta.task_id ?? assistantMeta.taskId ?? null;

                if (intent === 'create' || intent === 'update' || intent === 'delete') {
                    const detail = { intent, taskId };
                    // Dispatch a DOM event so other parts of the app can react.
                    window.dispatchEvent(new CustomEvent('task:changed', { detail }));

                    // Show a lightweight system message in the chat for immediate feedback.
                    const verb = intent === 'create' ? 'Created' : intent === 'update' ? 'Updated' : 'Deleted';
                    const idText = taskId ? ` #${taskId}` : '';
                    appendMessage('system', `${verb} task${idText}.`);
                }
            }
        } else {
            appendMessage('assistant', 'I was unable to generate a response. Please try again.');
        }
    } catch {
        setError('Unable to send your message right now. Please try again.');
        appendMessage('assistant', 'I could not process that message due to a temporary error.');
    } finally {
        setSendingState(false);
        messageInput.focus();
    }
}

async function bootstrapChat() {
    setError('');

    try {
        const conversationId = await ensureConversation();

        if (!conversationId) {
            renderMessages([]);
            return;
        }

        await loadConversation(conversationId);
    } catch {
        renderMessages([]);
        setError('Unable to load chat history right now.');
    }
}

function initializeChat() {
    if (!messagesContainer || !messageInput) {
        return;
    }

    sendMessageButton?.addEventListener('click', sendMessage);
    newChatButton?.addEventListener('click', () => {
        void startNewChat();
    });

    // Mode tab buttons
    const inquiryBtn = document.getElementById('chat-mode-inquiry');
    const crudBtn = document.getElementById('chat-mode-crud');

    inquiryBtn?.addEventListener('click', () => setMode('inquiry'));
    crudBtn?.addEventListener('click', () => setMode('crud'));

    // initialize mode UI
    setMode(selectedMode);

    messageInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void sendMessage();
        }
    });

    chatToggleButton?.addEventListener('click', () => setChatOpen(true));
    chatCloseButton?.addEventListener('click', () => setChatOpen(false));

    void bootstrapChat();
}

document.addEventListener('DOMContentLoaded', initializeChat);