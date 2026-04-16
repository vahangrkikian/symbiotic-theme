/**
 * WC AI Chatbot — Frontend widget (900+ lines, vanilla JS, no dependencies)
 */
(function () {
  'use strict';

  if (!window.wcaicData) return;

  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------
  const data = window.wcaicData;
  const state = {
    open: false,
    sending: false,
    messages: [],
    sessionKey: 'wcaic_chat_' + location.hostname,
    welcomeLoaded: false,
    streamController: null,
  };

  // ---------------------------------------------------------------------------
  // Utilities
  // ---------------------------------------------------------------------------
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escAttr(str) {
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function renderInlineMarkdown(text) {
    let safe = escHtml(text);
    // Bold
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic
    safe = safe.replace(/\*(.+?)\*/g, '<em>$1</em>');
    // Inline code
    safe = safe.replace(/`(.+?)`/g, '<code>$1</code>');
    // Links
    safe = safe.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, (_, label, url) => {
      return '<a href="' + escAttr(url) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
    });
    // Newlines
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }

  function buildStarRating(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
      if (rating >= i) html += '★';
      else if (rating >= i - 0.5) html += '½';
      else html += '☆';
    }
    return html;
  }

  function buildExtraFromAttachments(attachments) {
    if (!Array.isArray(attachments)) return {};
    const extra = {};
    for (const att of attachments) {
      switch (att.type) {
        case 'products':     extra.products     = att; break;
        case 'product_detail': extra.productDetail = att.product; break;
        case 'cart':         extra.cart         = att.data; break;
        case 'cart_action':  extra.cartAction   = att; break;
        case 'checkout':     extra.checkout     = att.data; break;
      }
    }
    extra.quickReplies = buildQuickReplies(attachments);
    return extra;
  }

  function buildQuickReplies(attachments) {
    if (!Array.isArray(attachments) || !attachments.length) return [];
    const last = attachments[attachments.length - 1];
    const replies = [];
    switch (last.type) {
      case 'products':
        replies.push({ type: 'message', text: 'Show details of the first one' });
        replies.push({ type: 'message', text: 'Add first to cart' });
        replies.push({ type: 'message', text: 'Filter by price' });
        break;
      case 'cart_action':
        if (last.action === 'add') {
          replies.push({ type: 'message', text: 'View my cart' });
          replies.push({ type: 'message', text: 'Keep shopping' });
          replies.push({ type: 'link', text: 'Checkout now', url: data.checkoutUrl || '/checkout' });
        } else {
          replies.push({ type: 'message', text: 'View updated cart' });
          replies.push({ type: 'message', text: 'Keep shopping' });
        }
        break;
      case 'cart':
        replies.push({ type: 'link', text: 'Checkout now', url: data.checkoutUrl || '/checkout' });
        replies.push({ type: 'message', text: 'Continue shopping' });
        replies.push({ type: 'message', text: 'Apply coupon code' });
        break;
      case 'checkout':
        if (last.data && last.data.checkout_url) {
          replies.push({ type: 'link', text: 'Complete your order', url: last.data.checkout_url });
        }
        break;
    }
    return replies.slice(0, 4);
  }

  // ---------------------------------------------------------------------------
  // DOM references
  // ---------------------------------------------------------------------------
  let panel, toggleBtn, messagesDiv, textarea, sendBtn, typingDiv, clearBtn;

  // ---------------------------------------------------------------------------
  // Build widget HTML
  // ---------------------------------------------------------------------------
  function buildWidget() {
    const pos     = data.settings.position || 'bottom-right';
    const color   = data.settings.primaryColor || '#2563eb';
    const posClass = 'wcaic-pos-' + pos;

    document.documentElement.style.setProperty('--wcaic-primary', color);

    // Toggle button
    toggleBtn = document.createElement('button');
    toggleBtn.className = 'wcaic-toggle ' + posClass;
    toggleBtn.setAttribute('aria-label', 'Open AI chat');
    toggleBtn.innerHTML = `
      <svg viewBox="0 0 24 24"><path d="M20 2H4C2.9 2 2 2.9 2 4v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
    `;

    // Panel
    panel = document.createElement('div');
    panel.className = 'wcaic-panel ' + posClass;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'AI Shopping Assistant');

    const storeName = escHtml(data.storeName || 'AI Shopping');

    panel.innerHTML = `
      <div class="wcaic-header">
        <div class="wcaic-header-info">
          <span class="wcaic-status-dot"></span>
          <div>
            <div class="wcaic-header-name">${storeName}</div>
            <div class="wcaic-header-sub">AI Shopping Assistant &bull; Online</div>
          </div>
        </div>
        <div class="wcaic-header-actions">
          <button class="wcaic-header-btn wcaic-clear-btn" title="Clear conversation">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6l-1 14H6L5 6M9 6V4h6v2"/></svg>
          </button>
          <button class="wcaic-header-btn wcaic-close-btn" title="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <div class="wcaic-messages"></div>
      <div class="wcaic-typing" role="status" aria-label="AI is typing">
        <span></span><span></span><span></span>
      </div>
      <div class="wcaic-input-area">
        <div class="wcaic-hint">Ask about products, prices, availability&hellip;</div>
        <div class="wcaic-input-row">
          <textarea class="wcaic-textarea" placeholder="Type a message&hellip;" rows="1" maxlength="2000"></textarea>
          <button class="wcaic-send-btn" aria-label="Send">
            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </button>
        </div>
        <div class="wcaic-char-count">0 / 2000</div>
      </div>
    `;

    document.body.appendChild(toggleBtn);
    document.body.appendChild(panel);

    messagesDiv = panel.querySelector('.wcaic-messages');
    textarea    = panel.querySelector('.wcaic-textarea');
    sendBtn     = panel.querySelector('.wcaic-send-btn');
    typingDiv   = panel.querySelector('.wcaic-typing');
    clearBtn    = panel.querySelector('.wcaic-clear-btn');

    const charCount = panel.querySelector('.wcaic-char-count');
    textarea.addEventListener('input', () => {
      autoResize(textarea);
      charCount.textContent = textarea.value.length + ' / 2000';
    });
  }

  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
  }

  // ---------------------------------------------------------------------------
  // Event listeners
  // ---------------------------------------------------------------------------
  function attachEvents() {
    toggleBtn.addEventListener('click', toggleChat);
    panel.querySelector('.wcaic-close-btn').addEventListener('click', closeChat);
    clearBtn.addEventListener('click', clearSession);

    textarea.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    sendBtn.addEventListener('click', sendMessage);

    // Event delegation: product add-to-cart buttons
    messagesDiv.addEventListener('click', (e) => {
      const btn = e.target.closest('.wcaic-add-to-cart');
      if (btn) {
        const id = btn.dataset.productId;
        if (id) sendMessage('Add product ' + id + ' to my cart');
      }
    });

    // Event delegation: "Ask AI" buttons on product pages
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('wcaic-ask-ai') || e.target.closest('.wcaic-ask-ai')) {
        openChat();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Chat open / close
  // ---------------------------------------------------------------------------
  function toggleChat() {
    state.open ? closeChat() : openChat();
  }

  function openChat() {
    state.open = true;
    panel.classList.add('wcaic-open');
    toggleBtn.setAttribute('aria-expanded', 'true');
    textarea.focus();

    if (!state.welcomeLoaded && state.messages.length === 0) {
      fetchWelcomeData();
      state.welcomeLoaded = true;
    }
  }

  function closeChat() {
    state.open = false;
    panel.classList.remove('wcaic-open');
    toggleBtn.setAttribute('aria-expanded', 'false');
  }

  // ---------------------------------------------------------------------------
  // Session persistence
  // ---------------------------------------------------------------------------
  function loadSession() {
    try {
      const saved = sessionStorage.getItem(state.sessionKey);
      if (saved) {
        const msgs = JSON.parse(saved);
        if (Array.isArray(msgs) && msgs.length) {
          state.messages = msgs;
          restoreMessages();
          state.welcomeLoaded = true;
        }
      }
    } catch (e) { /* ignore */ }
  }

  function saveSession() {
    try {
      sessionStorage.setItem(state.sessionKey, JSON.stringify(state.messages.slice(-30)));
    } catch (e) { /* ignore */ }
  }

  function clearSession() {
    state.messages = [];
    messagesDiv.innerHTML = '';
    sessionStorage.removeItem(state.sessionKey);
    state.welcomeLoaded = false;

    fetch(data.restUrl + 'wcaic/v1/clear', {
      method: 'POST',
      headers: { 'X-WP-Nonce': data.nonce, 'Content-Type': 'application/json' },
    });

    fetchWelcomeData();
    state.welcomeLoaded = true;
  }

  function restoreMessages() {
    messagesDiv.innerHTML = '';
    state.messages.forEach(msg => renderMessage(msg, false));
    scrollToBottom();
  }

  // ---------------------------------------------------------------------------
  // Welcome flow
  // ---------------------------------------------------------------------------
  function fetchWelcomeData() {
    fetch(data.restUrl + 'wcaic/v1/welcome', {
      headers: { 'X-WP-Nonce': data.nonce },
    })
      .then(r => r.json())
      .then(resp => {
        if (resp.success) {
          renderWelcomeButtons(resp.categories || [], resp.brands || []);
        }
      })
      .catch(() => {
        // Show basic greeting if welcome fetch fails
        addMessage('bot', data.settings.greeting || 'Hi! How can I help you today?', {});
      });
  }

  function renderWelcomeButtons(categories, brands) {
    const greeting = data.settings.greeting || 'Hi! How can I help you today?';
    addMessage('bot', greeting, { welcomeData: { categories, brands } });
  }

  // ---------------------------------------------------------------------------
  // Message sending
  // ---------------------------------------------------------------------------
  function sendMessage(text) {
    const msgText = typeof text === 'string' ? text.trim() : textarea.value.trim();
    if (!msgText || state.sending) return;

    // Remove all quick-reply rows (they disappear on next user message)
    messagesDiv.querySelectorAll('.wcaic-quick-replies').forEach(el => el.remove());

    if (typeof text !== 'string') {
      textarea.value = '';
      textarea.style.height = 'auto';
      panel.querySelector('.wcaic-char-count').textContent = '0 / 2000';
    }

    addMessage('user', msgText, {});
    state.sending = true;
    sendBtn.disabled = true;
    showTyping();

    if (data.isStreaming) {
      sendViaStream(msgText);
    } else {
      sendViaFetch(msgText);
    }
  }

  // ---------------------------------------------------------------------------
  // Non-streaming fetch
  // ---------------------------------------------------------------------------
  function sendViaFetch(text) {
    fetch(data.restUrl + 'wcaic/v1/chat', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   data.nonce,
      },
      body: JSON.stringify({ message: text }),
    })
      .then(r => r.json())
      .then(resp => {
        hideTyping();
        finishSend();
        if (resp.success) {
          const extra = buildExtraFromAttachments(resp.attachments || []);
          addMessage('bot', resp.reply || '', extra);
        } else {
          addMessage('bot', 'Sorry, something went wrong. Please try again.', {});
        }
      })
      .catch(() => {
        hideTyping();
        finishSend();
        addMessage('bot', 'Network error. Please check your connection.', {});
      });
  }

  // ---------------------------------------------------------------------------
  // SSE streaming
  // ---------------------------------------------------------------------------
  function sendViaStream(text) {
    const controller   = new AbortController();
    state.streamController = controller;

    let streamBubble = null;
    let fullText     = '';
    let stashedAttachments = [];

    fetch(data.restUrl + 'wcaic/v1/stream', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   data.nonce,
      },
      body: JSON.stringify({ message: text }),
      signal: controller.signal,
    })
      .then(r => {
        if (!r.ok || !r.body) throw new Error('Stream unavailable');
        const reader  = r.body.getReader();
        const decoder = new TextDecoder();
        let buffer    = '';

        function read() {
          return reader.read().then(({ done, value }) => {
            if (done) {
              finishStream(fullText, stashedAttachments);
              return;
            }
            buffer += decoder.decode(value, { stream: true });
            const events = parseSSE(buffer);
            buffer = events.remainder;
            events.parsed.forEach(handleStreamEvent);
            return read();
          });
        }
        return read();
      })
      .catch(err => {
        if (err.name !== 'AbortError') {
          hideTyping();
          finishSend();
          addMessage('bot', 'Streaming error. Please try again.', {});
        }
      });

    function handleStreamEvent({ event, data: d }) {
      switch (event) {
        case 'status':
          // Typing indicator already shown
          break;
        case 'token':
          if (!streamBubble) {
            hideTyping();
            streamBubble = createStreamBubble();
          }
          fullText += d.text || '';
          appendToStreamBubble(streamBubble, d.text || '');
          break;
        case 'attachments':
          stashedAttachments = d.attachments || [];
          break;
        case 'done':
          finishStream(fullText, stashedAttachments);
          break;
        case 'error':
          hideTyping();
          finishSend();
          addMessage('bot', d.message || 'An error occurred.', {});
          break;
      }
    }
  }

  function parseSSE(buffer) {
    const parts   = buffer.split('\n\n');
    const remainder = parts.pop(); // incomplete chunk
    const parsed  = [];
    for (const part of parts) {
      const lines  = part.split('\n');
      let event    = 'message';
      let dataStr  = '';
      for (const line of lines) {
        if (line.startsWith('event: ')) event   = line.slice(7).trim();
        if (line.startsWith('data: '))  dataStr = line.slice(6).trim();
      }
      if (dataStr) {
        try {
          parsed.push({ event, data: JSON.parse(dataStr) });
        } catch (e) { /* skip malformed */ }
      }
    }
    return { parsed, remainder };
  }

  function createStreamBubble() {
    const wrapper = document.createElement('div');
    wrapper.style.display = 'contents';

    const bubble = document.createElement('div');
    bubble.className = 'wcaic-bubble wcaic-bubble--bot wcaic-streaming';

    wrapper.appendChild(bubble);
    messagesDiv.appendChild(wrapper);
    scrollToBottom();
    return { wrapper, bubble };
  }

  function appendToStreamBubble(streamBubble, text) {
    const bubble = streamBubble.bubble;
    bubble.innerHTML = renderInlineMarkdown(bubble.dataset.raw = (bubble.dataset.raw || '') + text);
    scrollToBottom();
  }

  function finishStream(fullText, attachments) {
    // Remove streaming bubble
    const streamingBubbles = messagesDiv.querySelectorAll('.wcaic-streaming');
    streamingBubbles.forEach(el => {
      const parent = el.parentElement;
      if (parent && parent.style.display === 'contents') {
        parent.remove();
      } else {
        el.remove();
      }
    });

    hideTyping();
    finishSend();

    if (fullText || attachments.length) {
      const extra = buildExtraFromAttachments(attachments);
      addMessage('bot', fullText, extra);
    }
  }

  // ---------------------------------------------------------------------------
  // Typing indicator
  // ---------------------------------------------------------------------------
  function showTyping() { typingDiv.classList.add('wcaic-visible'); scrollToBottom(); }
  function hideTyping() { typingDiv.classList.remove('wcaic-visible'); }

  function finishSend() {
    state.sending = false;
    sendBtn.disabled = false;
    textarea.focus();
  }

  // ---------------------------------------------------------------------------
  // Message rendering
  // ---------------------------------------------------------------------------
  function addMessage(role, content, extra) {
    const msg = { role, content, extra: extra || {}, time: Date.now() };
    state.messages.push(msg);
    renderMessage(msg, true);
    saveSession();
  }

  function renderMessage(msg, scroll) {
    if (msg.role === 'user') {
      const bubble = document.createElement('div');
      bubble.className = 'wcaic-bubble wcaic-bubble--user';
      bubble.innerHTML = renderInlineMarkdown(msg.content);
      messagesDiv.appendChild(bubble);
    } else {
      // Bot message
      if (msg.extra && msg.extra.products) {
        // Product carousel/grid instead of text bubble
        renderProducts(msg.extra.products.products || [], msg.extra.products.query || {});
      } else if (msg.content) {
        const bubble = document.createElement('div');
        bubble.className = 'wcaic-bubble wcaic-bubble--bot';
        bubble.innerHTML = renderInlineMarkdown(msg.content);
        messagesDiv.appendChild(bubble);
      }

      // Welcome data chips
      if (msg.extra && msg.extra.welcomeData) {
        renderWelcomeChips(msg.extra.welcomeData);
      }

      // Quick replies
      if (msg.extra && msg.extra.quickReplies && msg.extra.quickReplies.length) {
        renderQuickReplies(msg.extra.quickReplies);
      }
    }

    if (scroll !== false) scrollToBottom();
  }

  // ---------------------------------------------------------------------------
  // Products
  // ---------------------------------------------------------------------------
  function renderProducts(products, query) {
    const queryStr = typeof query === 'string' ? query : (query.query || '');
    const header = document.createElement('div');
    header.className = 'wcaic-bubble wcaic-bubble--bot';
    header.textContent = 'Found ' + products.length + ' results' + (queryStr ? ' for "' + queryStr + '"' : '');
    messagesDiv.appendChild(header);

    if (!products.length) return;

    let container;
    const isCarousel = products.length >= 3;

    if (isCarousel) {
      const wrapper = document.createElement('div');
      container = document.createElement('div');
      container.className = 'wcaic-carousel';
      wrapper.appendChild(container);

      const nav = document.createElement('div');
      nav.className = 'wcaic-carousel-nav';
      nav.innerHTML = '<button class="wcaic-nav-btn wcaic-nav-prev">&larr;</button><button class="wcaic-nav-btn wcaic-nav-next">&rarr;</button>';
      wrapper.appendChild(nav);

      nav.querySelector('.wcaic-nav-prev').addEventListener('click', () => {
        container.scrollLeft -= 180;
      });
      nav.querySelector('.wcaic-nav-next').addEventListener('click', () => {
        container.scrollLeft += 180;
      });

      messagesDiv.appendChild(wrapper);
    } else {
      container = document.createElement('div');
      container.className = 'wcaic-product-grid';
      messagesDiv.appendChild(container);
    }

    products.forEach(product => {
      container.appendChild(buildProductCard(product));
    });
  }

  function buildProductCard(product) {
    const card = document.createElement('div');
    card.className = 'wcaic-product-card';

    const discount = product.on_sale && parseFloat(product.regular_price) > 0
      ? Math.round((1 - parseFloat(product.sale_price) / parseFloat(product.regular_price)) * 100)
      : 0;

    const imageUrl  = escAttr(product.image_url || '');
    const name      = escHtml(product.name || '');
    const brand     = product.brand ? '<span class="wcaic-brand">' + escHtml(product.brand) + '</span>' : '';
    const saleBadge = discount > 0 ? '<span class="wcaic-sale-badge">-' + discount + '%</span>' : '';
    const priceHtml = product.on_sale
      ? '<del>' + escHtml(product.regular_price) + '</del> <span class="wcaic-sale-price">' + escHtml(product.sale_price) + '</span>'
      : escHtml(product.price || '');
    const ratingHtml = product.rating > 0
      ? '<div class="wcaic-stars">' + buildStarRating(product.rating) + ' <span>(' + (product.review_count || 0) + ')</span></div>'
      : '';
    const stockHtml = !product.in_stock ? '<span class="wcaic-stock-badge">Out of Stock</span>' : '';
    const disabled  = !product.in_stock ? ' disabled' : '';

    card.innerHTML = `
      <div class="wcaic-card-image">
        <img src="${imageUrl}" loading="lazy" alt="${name}">
        ${saleBadge}
      </div>
      <div class="wcaic-card-body">
        ${brand}
        <h4 class="wcaic-card-name">${name}</h4>
        <div class="wcaic-price">${priceHtml}</div>
        ${ratingHtml}
        ${stockHtml}
        <div class="wcaic-card-actions">
          <button class="wcaic-add-to-cart" data-product-id="${escAttr(String(product.id))}"${disabled}>Add to Cart</button>
          <a href="${escAttr(product.permalink || '#')}" class="wcaic-view-btn" target="_blank" rel="noopener">View</a>
        </div>
      </div>
    `;

    return card;
  }

  // ---------------------------------------------------------------------------
  // Welcome chips
  // ---------------------------------------------------------------------------
  function renderWelcomeChips({ categories, brands }) {
    const wrapper = document.createElement('div');

    if (categories && categories.length) {
      const sec = document.createElement('div');
      sec.className = 'wcaic-welcome-section';
      sec.innerHTML = '<div class="wcaic-welcome-label">Browse by category</div>';
      const chips = document.createElement('div');
      chips.className = 'wcaic-welcome-chips';
      (categories.slice(0, 8)).forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'wcaic-welcome-btn';
        btn.textContent = cat.name;
        btn.addEventListener('click', () => sendMessage('Show me ' + cat.name + ' products'));
        chips.appendChild(btn);
      });
      sec.appendChild(chips);
      wrapper.appendChild(sec);
    }

    if (brands && brands.length) {
      const sec = document.createElement('div');
      sec.className = 'wcaic-welcome-section';
      sec.innerHTML = '<div class="wcaic-welcome-label">Shop by brand</div>';
      const chips = document.createElement('div');
      chips.className = 'wcaic-welcome-chips';
      (brands.slice(0, 8)).forEach(brand => {
        const btn = document.createElement('button');
        btn.className = 'wcaic-welcome-btn';
        btn.textContent = brand.name;
        btn.addEventListener('click', () => sendMessage('Show me ' + brand.name + ' products'));
        chips.appendChild(btn);
      });
      sec.appendChild(chips);
      wrapper.appendChild(sec);
    }

    if (wrapper.children.length) {
      messagesDiv.appendChild(wrapper);
    }
  }

  // ---------------------------------------------------------------------------
  // Quick replies
  // ---------------------------------------------------------------------------
  function renderQuickReplies(replies) {
    if (!replies || !replies.length) return;
    const row = document.createElement('div');
    row.className = 'wcaic-quick-replies';

    replies.forEach(r => {
      const btn = document.createElement('button');
      btn.className = 'wcaic-quick-reply';
      btn.textContent = r.text;
      btn.addEventListener('click', () => {
        if (r.type === 'link' && r.url) {
          window.open(r.url, '_blank', 'noopener,noreferrer');
        } else {
          sendMessage(r.text);
        }
      });
      row.appendChild(btn);
    });

    messagesDiv.appendChild(row);
  }

  // ---------------------------------------------------------------------------
  // Scroll
  // ---------------------------------------------------------------------------
  function scrollToBottom() {
    requestAnimationFrame(() => {
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    });
  }

  // ---------------------------------------------------------------------------
  // Init
  // ---------------------------------------------------------------------------
  function init() {
    buildWidget();
    attachEvents();
    loadSession();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
