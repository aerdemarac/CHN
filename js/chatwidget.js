/**
 * Molehi-AI Chatbot Widget (Chatwidget.js)
 * Gemini API + Vanilla JS + LocalStorage History
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'chn_ai_chat_history_v1';
    const MAX_STORED_MESSAGES = 40;

    // Default welcome message
    const DEFAULT_WELCOME = {
        id: 'msg_welcome',
        role: 'bot',
        text: 'Merhaba! Ben **Kıbrıs Sağlık Ağı (CHN)** Yapay Zeka Asistanıyım. 🩺\n\nSize randevu süreçleri, doktor branşları, aciliyet (triage) renkleri veya genel sağlık konularında nasıl yardımcı olabilirim?',
        time: formatTime(new Date())
    };

    // Calculate endpoint relative to this script location
    function getChatEndpoint() {
        const script = document.querySelector('script[src*="chatwidget.js"]');
        if (script && script.src) {
            const scriptUrl = new URL(script.src, window.location.href);
            return new URL('../backend/chat.php', scriptUrl).href;
        }
        return (window.location.pathname.includes('/test-endpoints/') || window.location.pathname.includes('/pages/'))
            ? '../backend/chat.php'
            : 'backend/chat.php';
    }

    function formatTime(date) {
        return new Intl.DateTimeFormat('tr-TR', {
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    // Markdown-lite to HTML safe formatter
    function renderMarkdown(text) {
        if (!text) return '';

        // 1. Escape HTML
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        // 2. Bold: **text**
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // 3. Italic: *text*
        html = html.replace(/(^|[^*])\*(?!\*)([^*]+)\*(?!\*)/g, '$1<em>$2</em>');

        // 4. Code: `code`
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        // 5. Blockquotes: > quote
        html = html.replace(/^&gt;\s?(.*)$/gm, '<blockquote>$1</blockquote>');

        // 6. Bullet lists
        const lines = html.split('\n');
        let inList = false;
        let processedLines = [];

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const bulletMatch = line.match(/^[-*•]\s+(.*)$/);

            if (bulletMatch) {
                if (!inList) {
                    processedLines.push('<ul>');
                    inList = true;
                }
                processedLines.push(`<li>${bulletMatch[1]}</li>`);
            } else {
                if (inList) {
                    processedLines.push('</ul>');
                    inList = false;
                }
                processedLines.push(line);
            }
        }
        if (inList) {
            processedLines.push('</ul>');
        }

        html = processedLines.join('\n');

        // 7. Paragraphs & Linebreaks
        html = html.replace(/\n\n+/g, '</p><p>');
        html = html.replace(/\n/g, '<br>');

        return `<p>${html}</p>`;
    }

    class ChnChatWidget {
        constructor() {
            this.isOpen = false;
            this.isLoading = false;
            this.history = this.loadHistory();
            this.initDOM();
            this.bindEvents();
            this.renderMessages();
        }

        loadHistory() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        return parsed;
                    }
                }
            } catch (err) {
                console.warn('[CHN Chatbot] LocalStorage okuma hatası:', err);
            }
            return [DEFAULT_WELCOME];
        }

        saveHistory() {
            try {
                const trimmed = this.history.slice(-MAX_STORED_MESSAGES);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
            } catch (err) {
                console.warn('[CHN Chatbot] LocalStorage yazma hatası:', err);
            }
        }

        clearHistory() {
            if (window.confirm('Sohbet geçmişinizi temizlemek istediğinizden emin misiniz?')) {
                this.history = [
                    {
                        ...DEFAULT_WELCOME,
                        id: 'msg_welcome_' + Date.now(),
                        time: formatTime(new Date())
                    }
                ];
                this.saveHistory();
                this.renderMessages();
            }
        }

        initDOM() {
            // Check if widget already exists
            if (document.getElementById('chn-chat-widget-root')) {
                return;
            }

            const root = document.createElement('div');
            root.id = 'chn-chat-widget-root';
            root.className = 'chn-chat-widget-root';

            root.innerHTML = `
                <!-- Floating Bean Button -->
                <button type="button" class="chn-chat-bean" id="chn-chat-bean" aria-label="Molehi-AI Asistanı Sohbetini Aç" aria-expanded="false">
                    <span class="chn-chat-bean__badge" aria-hidden="true"></span>
                    
                    <!-- Chat Icon -->
                    <svg class="chn-chat-bean__icon chn-chat-bean__icon--chat" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.38 5.08L2.1 21.9a1 1 0 0 0 1.25 1.25l4.82-1.28A9.96 9.96 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.57 0-3.04-.42-4.32-1.15a1 1 0 0 0-.74-.11l-3.23.86.86-3.23a1 1 0 0 0-.11-.74A7.95 7.95 0 0 1 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8zm-3-9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm3 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm3 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                    </svg>

                    <!-- Close Icon -->
                    <svg class="chn-chat-bean__icon chn-chat-bean__icon--close" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>

                    <span class="chn-chat-bean__tooltip">Molehi-AI Assistant</span>
                </button>

                <!-- Chat Window -->
                <section class="chn-chat-window" id="chn-chat-window" aria-labelledby="chn-chat-title" role="dialog" aria-modal="true" aria-hidden="true">
                    <!-- Header -->
                    <header class="chn-chat-header">
                        <div class="chn-chat-header__info">
                            <div class="chn-chat-header__avatar">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A1.5 1.5 0 0 0 6 14.5 1.5 1.5 0 0 0 7.5 16 1.5 1.5 0 0 0 9 14.5 1.5 1.5 0 0 0 7.5 13m9 0a1.5 1.5 0 0 0-1.5 1.5 1.5 1.5 0 0 0 1.5 1.5 1.5 1.5 0 0 0 1.5-1.5 1.5 1.5 0 0 0-1.5-1.5"/>
                                </svg>
                            </div>
                            <div class="chn-chat-header__text">
                                <h3 class="chn-chat-header__title" id="chn-chat-title">Molehi-AI Assistant</h3>
                                <span class="chn-chat-header__status">
                                    <span class="chn-chat-header__status-dot"></span>
                                    Çevrimiçi
                                </span>
                            </div>
                        </div>
                        <div class="chn-chat-header__actions">
                            <button type="button" class="chn-chat-header__btn" id="chn-chat-btn-clear" title="Sohbeti Temizle" aria-label="Sohbet geçmişini temizle">
                                <svg viewBox="0 0 24 24">
                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                            </button>
                            <button type="button" class="chn-chat-header__btn" id="chn-chat-btn-close" title="Kapat" aria-label="Sohbet penceresini kapat">
                                <svg viewBox="0 0 24 24">
                                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                </svg>
                            </button>
                        </div>
                    </header>

                    <!-- Message Feed -->
                    <div class="chn-chat-body" id="chn-chat-body">
                        <!-- Quick suggestions -->
                        <div class="chn-chat-quick-actions" id="chn-chat-quick-actions">
                            <button type="button" class="chn-chat-chip" data-prompt="Randevu nasıl alabilirim?">📅 Randevu Alma</button>
                            <button type="button" class="chn-chat-chip" data-prompt="Triage aciliyet renkleri nelerdir?">🚦 Triage Renkleri</button>
                            <button type="button" class="chn-chat-chip" data-prompt="Acil durumda ne yapmalıyım?">🚨 Acil Durum (112)</button>
                            <button type="button" class="chn-chat-chip" data-prompt="Hangi tıbbi branşlar var?">🩺 Doktor Branşları</button>
                        </div>

                        <!-- Message items injected here -->
                        <div class="chn-chat-messages" id="chn-chat-messages"></div>

                        <!-- Typing Indicator -->
                        <div class="chn-chat-msg chn-chat-msg--bot" id="chn-chat-typing" style="display: none;">
                            <div class="chn-chat-msg__avatar">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A1.5 1.5 0 0 0 6 14.5 1.5 1.5 0 0 0 7.5 16 1.5 1.5 0 0 0 9 14.5 1.5 1.5 0 0 0 7.5 13m9 0a1.5 1.5 0 0 0-1.5 1.5 1.5 1.5 0 0 0 1.5 1.5 1.5 1.5 0 0 0 1.5-1.5 1.5 1.5 0 0 0-1.5-1.5"/>
                                </svg>
                            </div>
                            <div class="chn-chat-msg__bubble">
                                <div class="chn-chat-typing">
                                    <span class="chn-chat-typing__dot"></span>
                                    <span class="chn-chat-typing__dot"></span>
                                    <span class="chn-chat-typing__dot"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Input Form -->
                    <footer class="chn-chat-footer">
                        <form class="chn-chat-form" id="chn-chat-form">
                            <textarea 
                                class="chn-chat-input" 
                                id="chn-chat-input" 
                                placeholder="Bir soru sorun... (Enter gönderir)" 
                                rows="1" 
                                maxlength="2000"
                                required
                            ></textarea>
                            <button type="submit" class="chn-chat-send-btn" id="chn-chat-send-btn" aria-label="Gönder" disabled>
                                <svg viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </form>
                        <div class="chn-chat-disclaimer">
                            Molehi-AI tıbbi tanı koymaz. Acil durumlar için lütfen 112'yi arayınız.
                        </div>
                    </footer>
                </section>
            `;

            document.body.appendChild(root);

            // Cache DOM references
            this.beanBtn = document.getElementById('chn-chat-bean');
            this.chatWindow = document.getElementById('chn-chat-window');
            this.messagesContainer = document.getElementById('chn-chat-messages');
            this.chatBody = document.getElementById('chn-chat-body');
            this.typingIndicator = document.getElementById('chn-chat-typing');
            this.chatForm = document.getElementById('chn-chat-form');
            this.chatInput = document.getElementById('chn-chat-input');
            this.sendBtn = document.getElementById('chn-chat-send-btn');
            this.btnClear = document.getElementById('chn-chat-btn-clear');
            this.btnClose = document.getElementById('chn-chat-btn-close');
        }

        bindEvents() {
            // Toggle open / close
            this.beanBtn.addEventListener('click', () => this.toggleWindow());
            this.btnClose.addEventListener('click', () => this.closeWindow());

            // Clear conversation history
            this.btnClear.addEventListener('click', () => this.clearHistory());

            // Input changes
            this.chatInput.addEventListener('input', () => {
                this.autoResizeInput();
                this.sendBtn.disabled = this.chatInput.value.trim().length === 0 || this.isLoading;
            });

            // Enter to send (Shift+Enter for newline)
            this.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!this.sendBtn.disabled) {
                        this.sendMessage();
                    }
                }
            });

            // Form submit
            this.chatForm.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!this.sendBtn.disabled) {
                    this.sendMessage();
                }
            });

            // Quick suggestion chips
            document.querySelectorAll('.chn-chat-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    const prompt = chip.getAttribute('data-prompt');
                    if (prompt) {
                        this.chatInput.value = prompt;
                        this.autoResizeInput();
                        this.sendMessage();
                    }
                });
            });

            // Escape key closes window
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeWindow();
                }
            });
        }

        autoResizeInput() {
            this.chatInput.style.height = 'auto';
            this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 90) + 'px';
        }

        toggleWindow() {
            if (this.isOpen) {
                this.closeWindow();
            } else {
                this.openWindow();
            }
        }

        openWindow() {
            this.isOpen = true;
            this.chatWindow.classList.add('is-open');
            this.chatWindow.setAttribute('aria-hidden', 'false');
            this.beanBtn.classList.add('is-active');
            this.beanBtn.setAttribute('aria-expanded', 'true');
            this.scrollToBottom();
            setTimeout(() => this.chatInput.focus(), 150);
        }

        closeWindow() {
            this.isOpen = false;
            this.chatWindow.classList.remove('is-open');
            this.chatWindow.setAttribute('aria-hidden', 'true');
            this.beanBtn.classList.remove('is-active');
            this.beanBtn.setAttribute('aria-expanded', 'false');
        }

        scrollToBottom() {
            setTimeout(() => {
                this.chatBody.scrollTop = this.chatBody.scrollHeight;
            }, 30);
        }

        renderMessages() {
            this.messagesContainer.innerHTML = '';
            this.history.forEach((msg) => {
                this.appendMessageToDOM(msg);
            });
            this.scrollToBottom();
        }

        appendMessageToDOM(msg) {
            const el = document.createElement('div');
            el.className = `chn-chat-msg chn-chat-msg--${msg.role}`;

            const avatarHTML = msg.role === 'bot'
                ? `<div class="chn-chat-msg__avatar">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A1.5 1.5 0 0 0 6 14.5 1.5 1.5 0 0 0 7.5 16 1.5 1.5 0 0 0 9 14.5 1.5 1.5 0 0 0 7.5 13m9 0a1.5 1.5 0 0 0-1.5 1.5 1.5 1.5 0 0 0 1.5 1.5 1.5 1.5 0 0 0 1.5-1.5 1.5 1.5 0 0 0-1.5-1.5"/>
                    </svg>
                   </div>`
                : '';

            const bubbleContent = renderMarkdown(msg.text);

            el.innerHTML = `
                ${avatarHTML}
                <div class="chn-chat-msg__content">
                    <div class="chn-chat-msg__bubble">${bubbleContent}</div>
                    <span class="chn-chat-msg__time">${msg.time}</span>
                </div>
            `;

            this.messagesContainer.appendChild(el);
        }

        async sendMessage() {
            const text = this.chatInput.value.trim();
            if (!text || this.isLoading) return;

            // 1. Add user message
            const userMsg = {
                id: 'msg_' + Date.now(),
                role: 'user',
                text: text,
                time: formatTime(new Date())
            };

            this.history.push(userMsg);
            this.saveHistory();
            this.appendMessageToDOM(userMsg);

            // Clear input
            this.chatInput.value = '';
            this.autoResizeInput();
            this.sendBtn.disabled = true;
            this.scrollToBottom();

            // 2. Show typing indicator
            this.isLoading = true;
            this.typingIndicator.style.display = 'flex';
            this.scrollToBottom();

            // 3. Prepare payload with recent history
            const historyPayload = this.history
                .slice(-10)
                .map((m) => ({
                    role: m.role === 'bot' ? 'model' : 'user',
                    text: m.text
                }));

            try {
                const endpoint = getChatEndpoint();
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message: text,
                        history: historyPayload
                    })
                });

                const data = await response.json();

                if (data && data.success && data.reply) {
                    const botMsg = {
                        id: 'msg_' + Date.now(),
                        role: 'bot',
                        text: data.reply,
                        time: formatTime(new Date())
                    };
                    this.history.push(botMsg);
                    this.saveHistory();
                    this.appendMessageToDOM(botMsg);
                } else {
                    const errorText = (data && data.error)
                        ? data.error
                        : 'Üzgünüm, şu anda yanıt oluşturulamadı. Lütfen tekrar deneyiniz.';
                    const errorMsg = {
                        id: 'msg_err_' + Date.now(),
                        role: 'bot',
                        text: `⚠️ **Hata:** ${errorText}`,
                        time: formatTime(new Date())
                    };
                    this.history.push(errorMsg);
                    this.saveHistory();
                    this.appendMessageToDOM(errorMsg);
                }
            } catch (err) {
                console.error('[CHN Chatbot] İstek hatası:', err);
                const networkMsg = {
                    id: 'msg_net_' + Date.now(),
                    role: 'bot',
                    text: '⚠️ Sunucu veya ağ bağlantısı kurulamadı. Lütfen bağlantınızı kontrol ediniz.',
                    time: formatTime(new Date())
                };
                this.history.push(networkMsg);
                this.saveHistory();
                this.appendMessageToDOM(networkMsg);
            } finally {
                this.isLoading = false;
                this.typingIndicator.style.display = 'none';
                this.sendBtn.disabled = this.chatInput.value.trim().length === 0;
                this.scrollToBottom();
            }
        }
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new ChnChatWidget());
    } else {
        new ChnChatWidget();
    }
})();
