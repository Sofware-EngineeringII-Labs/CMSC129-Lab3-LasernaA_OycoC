const messagesContainer = document.getElementById('messages');
const messageInput = document.getElementById('message-input');
const sendMessageButton = document.getElementById('send-message-btn');
const newChatButton = document.getElementById('new-chat-btn');
const chatToggleButton = document.getElementById('chat-toggle-btn');
const chatCloseButton = document.getElementById('chat-close-btn');
const chatPopup = document.getElementById('chat-popup');

function setChatOpen(isOpen) {
    if (!chatPopup) {
        return;
    }

    chatPopup.classList.toggle('hidden', !isOpen);

    if (isOpen && messageInput) {
        messageInput.focus();
    }
}

function appendMessage(role, content) {
    if (!messagesContainer) {
        return;
    }

    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role}`;
    messageDiv.textContent = content;

    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function startNewChat() {
    if (!messagesContainer) {
        return;
    }

    messagesContainer.innerHTML = '';
    appendMessage('assistant', 'Hi! How can I help you with your tasks today?');
}

function sendMessage() {
    if (!messageInput) {
        return;
    }

    const message = messageInput.value.trim();

    if (!message) {
        return;
    }

    appendMessage('user', message);
    messageInput.value = '';

    setTimeout(() => {
        appendMessage('assistant', `You said: ${message}`);
    }, 500);
}

function initializeChat() {
    if (!messagesContainer || !messageInput) {
        return;
    }

    sendMessageButton?.addEventListener('click', sendMessage);
    newChatButton?.addEventListener('click', startNewChat);

    messageInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    chatToggleButton?.addEventListener('click', () => setChatOpen(true));
    chatCloseButton?.addEventListener('click', () => setChatOpen(false));

    startNewChat();
}

document.addEventListener('DOMContentLoaded', initializeChat);