<!-- Chat Interface -->

<!DOCTYPE html>
<html>
    <head>
        <title>Customer Service Chat</title>
        @vite(['resources/css/app.css', 'resources/js/chat.js'])
    </head>
    <body>
        <div class="header">
            <h1>Welcome to Our Customer Service</h1>
            <p>How can we help you today?</p>
        </div>
        <div id="chat-container">
            <div id="messages"></div>
            <div class="input-container">
                <div class="input-wrapper">
                    <input type="text" id="message-input" placeholder="Type your message...">
                </div>
                <button onclick="sendMessage()">Send</button>
                <button id="new-chat-btn" onclick="startNewChat()">New Chat</button>
            </div>
        </div>
    </body>
</html>
