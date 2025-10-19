document.addEventListener('DOMContentLoaded', () => {
    const STORAGE_KEY = 'ggAiChatHistory';

    // UI
    const chatBtn  = document.createElement('button');
    chatBtn.id = 'gg-ai-button';
    chatBtn.textContent = '💬 Chat';
    document.body.appendChild(chatBtn);

    const chatBox = document.createElement('div');
    chatBox.id = 'gg-ai-box';
    chatBox.innerHTML = `
        <div class="gg-chat-header">Virtual Assistant
            <div id="gg-header-buttons">
                <span id="gg-clear" title="Clear conversation" style="margin-right:8px; cursor:pointer;">⟲</span>
                <span id="gg-close" style="cursor:pointer;">×</span>
            </div>
        </div>
        <div class="gg-chat-messages"></div>
        <div class="gg-chat-input">
            <input type="text" id="gg-input" placeholder="Ask me anything..." />
            <button id="gg-send">Send</button>
        </div>`;
    document.body.appendChild(chatBox);

    const messagesEl = chatBox.querySelector('.gg-chat-messages');
    const input      = chatBox.querySelector('#gg-input');
    const sendBtn    = chatBox.querySelector('#gg-send');
    const closeBtn   = chatBox.querySelector('#gg-close');
    const clearBtn   = chatBox.querySelector('#gg-clear');

    chatBox.style.display = 'none';

    // Load existing history
    let history = [];
    try {
        history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch (_) { history = []; }

    function renderHistory() {
        messagesEl.innerHTML = '';
        history.forEach(m => {
            const who = m.role === 'user' ? 'user' : 'bot';
            messagesEl.innerHTML += `<div class="msg ${who}">${m.content}</div>`;
        });
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    renderHistory();

    function saveHistory() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
    }

    chatBtn.onclick  = () => { chatBox.style.display = 'flex'; input.focus(); };
    closeBtn.onclick = () => { chatBox.style.display = 'none'; };
    clearBtn.onclick = () => {
        history = [];
        saveHistory();
        renderHistory();
    };

    async function sendMessage() {
        const msg = input.value.trim();
        if (!msg) return;

        // push user message
        history.push({ role: 'user', content: msg });
        saveHistory();
        messagesEl.innerHTML += `<div class="msg user">${msg}</div>`;
        input.value = '';
        messagesEl.innerHTML += `<div class="msg bot typing">...</div>`;
        messagesEl.scrollTop = messagesEl.scrollHeight;

        // send last N turns to keep payload small (e.g., last 10 messages)
        const MAX_MESSAGES = 20;
        const recentHistory = history.slice(-MAX_MESSAGES);

        const response = await fetch(ggAiChat.restUrl, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': ggAiChat.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: msg, history: recentHistory })
        });

        const data = await response.json();
        messagesEl.querySelector('.typing')?.remove();

        if (data.success) {
            const reply = data.data.reply || '…';
            history.push({ role: 'assistant', content: reply });
            saveHistory();
            messagesEl.innerHTML += `<div class="msg bot">${reply}</div>`;
        } else {
            messagesEl.innerHTML += `<div class="msg bot error">Error: ${data.data.error}</div>`;
        }
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    sendBtn.onclick = sendMessage;
    input.addEventListener('keypress', e => {
        if (e.key === 'Enter') sendMessage();
    });
});
