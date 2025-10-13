document.addEventListener( 'DOMContentLoaded', () => {

    const chatBtn = document.createElement( 'button' );
    chatBtn.id = 'gg-ai-button';
    chatBtn.textContent = '💬 Chat';
    document.body.appendChild( chatBtn );

    const chatBox = document.createElement( 'div' );
    chatBox.id = 'gg-ai-box';
    chatBox.innerHTML = `
        <div class="gg-chat-header">AI Chat <span id="gg-close">×</span></div>
        <div class="gg-chat-messages"></div>
        <div class="gg-chat-input">
            <input type="text" id="gg-input" placeholder="Ask me anything..." />
            <button id="gg-send">Send</button>
        </div>
    `;
    document.body.appendChild( chatBox );

    const messages = chatBox.querySelector( '.gg-chat-messages' );
    const input = chatBox.querySelector( '#gg-input' );
    const sendBtn = chatBox.querySelector( '#gg-send' );
    const closeBtn = chatBox.querySelector( '#gg-close' );

    chatBox.style.display = 'none';

    chatBtn.onclick = () => chatBox.style.display = 'flex';
    closeBtn.onclick = () => chatBox.style.display = 'none';

    async function sendMessage() {
        const msg = input.value.trim();
        if ( ! msg ) return;

        messages.innerHTML += `<div class="msg user">${msg}</div>`;
        input.value = '';
        messages.innerHTML += `<div class="msg bot typing">...</div>`;
        messages.scrollTop = messages.scrollHeight;

        const response = await fetch( ggAiChat.restUrl, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': ggAiChat.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify( { message: msg } )
        });

        const data = await response.json();
        messages.querySelector( '.typing' ).remove();

        if ( data.success ) {
            messages.innerHTML += `<div class="msg bot">${data.data.reply}</div>`;
        } else {
            messages.innerHTML += `<div class="msg bot error">Error: ${data.data.error}</div>`;
        }

        messages.scrollTop = messages.scrollHeight;
    }

    sendBtn.onclick = sendMessage;

    input.addEventListener( 'keypress', e => {
        if ( e.key === 'Enter' ) sendMessage();
    });

});
