import React, { useRef, useEffect, useState, useCallback } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { renderInlineMarkdown } from '../../utils/markdown.js';
import Icon, { AiAvatar } from '../shared/Icon.jsx';
import { t } from '../../utils/i18n.js';

export default function ChatView() {
  const { state, sendMessage } = useWorkspace();
  const messagesEndRef = useRef(null);
  const [inputValue, setInputValue] = useState('');

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [state.messages]);

  const handleSend = useCallback((e) => {
    e.preventDefault();
    if (!inputValue.trim() || state.isStreaming) return;
    sendMessage(inputValue.trim());
    setInputValue('');
  }, [inputValue, sendMessage, state.isStreaming]);

  const handleKeyDown = useCallback((e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend(e);
    }
  }, [handleSend]);

  return (
    <div className="sym-view sym-view--chat">
      {/* Message flow */}
      <div className="sym-messages" aria-live="polite" role="log">
        {state.messages.length === 0 && (
          <div className="sym-empty-state">
            <AiAvatar size={44} />
            <p className="sym-empty-title">How can I help you today?</p>
            <p>{t('chat.empty')}</p>
          </div>
        )}

        {state.messages.map(msg => (
          <div key={msg.id} className={`sym-msg sym-msg--${msg.role}`}>
            {msg.role === 'bot' && (
              <div className="sym-msg-avatar">
                <AiAvatar size={28} />
              </div>
            )}
            <div className={`sym-msg-bubble sym-msg-bubble--${msg.role}`}>
              {msg.content && (
                <div
                  className="sym-msg-text"
                  dangerouslySetInnerHTML={{ __html: renderInlineMarkdown(msg.content) }}
                />
              )}
              {msg.isStreaming && <span className="sym-cursor" />}

              {/* Inline attachment cards */}
              {msg.extra?.products && <InlineProducts data={msg.extra.products} onSend={sendMessage} />}
              {msg.extra?.cartAction && <InlineCartAction data={msg.extra.cartAction} />}
              {msg.extra?.cart && <InlineCart data={msg.extra.cart} onSend={sendMessage} />}
              {msg.extra?.comparison && <InlineComparison data={msg.extra.comparison} />}
              {msg.extra?.order && <InlineOrder data={msg.extra.order} />}
              {msg.extra?.orders && <InlineOrders data={msg.extra.orders} />}
              {msg.extra?.policies && <InlinePolicies data={msg.extra.policies} />}
              {msg.extra?.shipping && <InlineShipping data={msg.extra.shipping} />}
              {msg.extra?.checkout && <InlineCheckout data={msg.extra.checkout} />}

              {/* Quick replies */}
              {msg.extra?.quickReplies?.length > 0 && !msg.isStreaming && (
                <div className="sym-quick-replies">
                  {msg.extra.quickReplies.map((r, i) => (
                    <button key={i} className="sym-quick-reply" onClick={() => sendMessage(r.text)}>
                      {r.text}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}

        {state.isThinking && (
          <div className="sym-msg sym-msg--bot">
            <div className="sym-msg-avatar">
              <AiAvatar size={28} />
            </div>
            <div className="sym-msg-bubble sym-msg-bubble--bot" role="status" aria-label="AI is thinking">
              <div className="sym-typing"><span/><span/><span/></div>
            </div>
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      {/* Chat input bar (bottom) */}
      <form className="sym-chat-input" onSubmit={handleSend}>
        <label className="sym-sr-only" htmlFor="sym-chat-textarea">{t('chat.placeholder')}</label>
        <textarea
          id="sym-chat-textarea"
          className="sym-chat-textarea"
          value={inputValue}
          onChange={e => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={t('chat.placeholder')}
          rows={1}
          disabled={state.isStreaming}
        />
        <button type="submit" className="sym-chat-send" disabled={!inputValue.trim() || state.isStreaming} aria-label="Send message">
          <Icon name="send" size={18} />
        </button>
      </form>
    </div>
  );
}

// ── Inline Card Components ──

function InlineProducts({ data, onSend }) {
  const products = data.products || [];
  if (!products.length) return null;
  return (
    <div className="sym-inline-cards">
      {products.slice(0, 4).map(p => (
        <div key={p.id} className="sym-product-card">
          <img src={p.image_url} alt={p.name} className="sym-product-card-img" />
          <div className="sym-product-card-info">
            <span className="sym-product-card-name">{p.name}</span>
            {p.brand && <span className="sym-product-card-brand">{p.brand}</span>}
            <div className="sym-product-card-price">
              <span className="sym-product-card-amount">${p.price}</span>
              {p.on_sale && p.regular_price !== p.price && (
                <span className="sym-product-card-sale">${p.regular_price}</span>
              )}
            </div>
            {p.rating > 0 && <span className="sym-product-card-rating">{'★'.repeat(Math.round(p.rating))} {p.rating}</span>}
          </div>
          <button className="sym-product-card-btn" onClick={() => onSend(`Add product ${p.id} to my cart`)}>
            Add to Cart
          </button>
        </div>
      ))}
    </div>
  );
}

function InlineCartAction({ data }) {
  if (!data) return null;
  const msg = data.action === 'add' ? 'Added to cart' : data.action === 'remove' ? 'Removed from cart' : 'Cart updated';
  return <div className="sym-inline-notice sym-inline-notice--success">{msg}</div>;
}

function InlineCart({ data, onSend }) {
  if (!data) return null;
  return (
    <div className="sym-inline-cart">
      <div className="sym-inline-cart-row">
        <span>Items: {data.item_count || 0}</span>
        <span>Total: ${data.total || '0.00'}</span>
      </div>
      <button className="sym-inline-cart-btn" onClick={() => onSend('Proceed to checkout')}>Checkout</button>
    </div>
  );
}

function InlineComparison({ data }) {
  const products = data?.comparison || [];
  if (products.length < 2) return null;
  return (
    <div className="sym-inline-comparison">
      <div className="sym-comparison-grid" style={{ gridTemplateColumns: `repeat(${products.length}, 1fr)` }}>
        {products.map(p => (
          <div key={p.id} className="sym-comparison-col">
            <img src={p.image_url} alt={p.name} className="sym-comparison-img" />
            <strong>{p.name}</strong>
            <span className="sym-comparison-price">${p.price}</span>
            <span>{p.in_stock ? 'In stock' : 'Out of stock'}</span>
            <span>{'★'.repeat(Math.round(p.rating || 0))} {p.rating || '—'}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function InlineOrder({ data }) {
  if (!data) return null;
  return (
    <div className="sym-inline-order">
      <div className="sym-inline-order-header">
        <span>Order #{data.order_id}</span>
        <span className={`sym-status-badge sym-status--${data.status_key}`}>{data.status}</span>
      </div>
      <div className="sym-inline-order-details">
        <span>Date: {data.date_created}</span>
        <span>Total: ${data.total}</span>
        <span>Payment: {data.payment_method}</span>
      </div>
    </div>
  );
}

function InlineOrders({ data }) {
  const orders = data?.orders || [];
  if (!orders.length) return null;
  return (
    <div className="sym-inline-orders">
      {orders.map(o => (
        <div key={o.order_id} className="sym-inline-order-row">
          <span>#{o.order_id}</span>
          <span className="sym-status-badge">{o.status}</span>
          <span>{o.date_created}</span>
          <span>${o.total}</span>
        </div>
      ))}
    </div>
  );
}

function InlinePolicies({ data }) {
  const policies = data?.policies || {};
  return (
    <div className="sym-inline-policies">
      {Object.entries(policies).map(([key, content]) => (
        <div key={key} className="sym-inline-policy">
          <h4>{key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</h4>
          <p>{content}</p>
        </div>
      ))}
    </div>
  );
}

function InlineShipping({ data }) {
  if (!data) return null;
  const methods = data.methods || [];
  return (
    <div className="sym-inline-shipping">
      <div className="sym-inline-shipping-dest">
        Shipping to: {data.destination?.country} {data.destination?.state || ''}
      </div>
      {methods.length > 0 ? (
        <div className="sym-inline-shipping-methods">
          {methods.map((m, i) => (
            <div key={i} className="sym-inline-shipping-row">
              <span>{m.method}</span>
              <span>${m.cost}</span>
            </div>
          ))}
        </div>
      ) : (
        <p className="sym-text-muted">No shipping methods available for this destination.</p>
      )}
    </div>
  );
}

function InlineCheckout({ data }) {
  if (!data?.checkout_url) return null;
  return (
    <div className="sym-inline-checkout">
      <a href={data.checkout_url} className="sym-inline-checkout-btn" target="_blank" rel="noopener noreferrer">
        Complete Your Order →
      </a>
    </div>
  );
}
