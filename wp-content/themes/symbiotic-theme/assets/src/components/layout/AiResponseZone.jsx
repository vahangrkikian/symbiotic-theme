import React from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { renderInlineMarkdown } from '../../utils/markdown.js';
import Icon, { AiAvatar } from '../shared/Icon.jsx';
import CalculatorBlock from '../views/CalculatorBlock.jsx';

export default function AiResponseZone() {
  const { state, sendMessage, navigate, clearChat } = useWorkspace();

  if (!state.messages.length) return null;

  return (
    <div className="sym-ai-zone">
      <div className="sym-ai-zone-divider">
        <AiAvatar size={20} />
        <span>AI Assistant</span>
        <button className="sym-ai-zone-clear" onClick={clearChat}>
          Clear <Icon name="close" size={14} />
        </button>
      </div>

      {state.messages.map(msg => (
        <div key={msg.id} className={`sym-ai-block sym-ai-block--${msg.role}`}>
          {msg.role === 'user' && (
            <div className="sym-ai-query">
              <Icon name="search" size={16} />
              <span>{msg.content}</span>
            </div>
          )}

          {msg.role === 'bot' && (
            <div className="sym-ai-response">
              {/* Text content */}
              {msg.content && (
                <div className="sym-ai-prose" dangerouslySetInnerHTML={{ __html: renderInlineMarkdown(msg.content) }} />
              )}
              {msg.isStreaming && <span className="sym-ai-cursor" />}

              {/* Product grid block */}
              {msg.extra?.products && (
                <ProductGridBlock data={msg.extra.products} onAction={sendMessage} onNavigate={navigate} />
              )}

              {/* Calculator block */}
              {msg.extra?.calculator && (
                <CalculatorBlock data={msg.extra.calculator} onAction={sendMessage} />
              )}

              {/* Comparison block */}
              {msg.extra?.comparison && (
                <ComparisonBlock data={msg.extra.comparison} />
              )}

              {/* Cart block */}
              {msg.extra?.cart && (
                <CartBlock data={msg.extra.cart} onAction={sendMessage} />
              )}

              {/* Cart action */}
              {msg.extra?.cartAction && (
                <div className="sym-ai-notice sym-ai-notice--success">
                  <Icon name="check" size={16} />
                  {msg.extra.cartAction.action === 'add' ? 'Added to cart' :
                   msg.extra.cartAction.action === 'remove' ? 'Removed from cart' : 'Cart updated'}
                </div>
              )}

              {/* Order block */}
              {msg.extra?.order && <OrderBlock data={msg.extra.order} />}
              {msg.extra?.orders && <OrdersBlock data={msg.extra.orders} />}

              {/* Policy block */}
              {msg.extra?.policies && <PolicyBlock data={msg.extra.policies} />}

              {/* Shipping block */}
              {msg.extra?.shipping && <ShippingBlock data={msg.extra.shipping} onAction={sendMessage} />}

              {/* Checkout */}
              {msg.extra?.checkout && (
                <div className="sym-ai-cta-banner">
                  <h3>Ready to complete your order?</h3>
                  <a href={msg.extra.checkout.checkout_url} className="sym-btn sym-btn--primary">
                    Proceed to Checkout →
                  </a>
                </div>
              )}

              {/* Quick replies */}
              {msg.extra?.quickReplies?.length > 0 && !msg.isStreaming && (
                <div className="sym-ai-suggestions">
                  {msg.extra.quickReplies.map((r, i) => (
                    <button key={i} className="sym-ai-suggestion" onClick={() => sendMessage(r.text)}>
                      {r.text}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      ))}

      {state.isThinking && (
        <div className="sym-ai-block sym-ai-block--bot">
          <div className="sym-ai-response">
            <div className="sym-ai-thinking">
              <AiAvatar size={20} />
              <div className="sym-ai-thinking-dots"><span/><span/><span/></div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Rich Blocks ──

function ProductGridBlock({ data, onAction, onNavigate }) {
  const products = data.products || [];
  if (!products.length) return null;
  return (
    <div className="sym-block-products">
      <div className="sym-block-header">
        <h3>{data.total || products.length} products found</h3>
      </div>
      <div className="sym-product-grid">
        {products.map(p => (
          <div key={p.id} className="sym-pcard"
            onClick={() => onNavigate('product', { productId: p.id })}
            style={{ cursor: 'pointer' }}>
            <div className="sym-pcard-img-wrap">
              <img src={p.image_url} alt={p.name} className="sym-pcard-img" loading="lazy" />
              {p.on_sale && <span className="sym-pcard-badge">Sale</span>}
              {p.has_calculator && <span className="sym-pcard-badge sym-pcard-badge--config">Configurable</span>}
            </div>
            <div className="sym-pcard-body">
              {p.brand && <span className="sym-pcard-brand">{p.brand}</span>}
              <h4 className="sym-pcard-name">{p.name}</h4>
              {p.short_description && <p className="sym-pcard-desc">{p.short_description}</p>}
              <div className="sym-pcard-price">
                {p.has_calculator ? (
                  <span className="sym-pcard-amount">From ${p.price || '—'}</span>
                ) : (
                  <>
                    <span className="sym-pcard-amount">${p.price}</span>
                    {p.on_sale && p.regular_price !== p.price && (
                      <span className="sym-pcard-was">${p.regular_price}</span>
                    )}
                  </>
                )}
              </div>
              <button className={`sym-pcard-btn ${p.has_calculator ? 'sym-pcard-btn--config' : ''}`}
                onClick={(e) => { e.stopPropagation(); onNavigate('product', { productId: p.id }); }}>
                {p.has_calculator ? 'Configure & Price →' : 'View Product →'}
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ComparisonBlock({ data }) {
  const products = data?.comparison || [];
  if (products.length < 2) return null;
  return (
    <div className="sym-block-comparison">
      <h3>Product Comparison</h3>
      <div className="sym-comparison-table">
        {products.map(p => (
          <div key={p.id} className="sym-comparison-item">
            <img src={p.image_url} alt={p.name} loading="lazy" />
            <strong>{p.name}</strong>
            <span className="sym-pcard-amount">${p.price}</span>
            <span>{p.in_stock ? '✓ In stock' : '✗ Out of stock'}</span>
            <span>{'★'.repeat(Math.round(p.rating || 0))} {p.rating || '—'}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function CartBlock({ data, onAction }) {
  return (
    <div className="sym-block-cart">
      <div className="sym-block-cart-row">
        <span><Icon name="cart" size={18} /> {data.item_count || 0} items in cart</span>
        <strong>${data.total || '0.00'}</strong>
      </div>
      <div className="sym-block-cart-actions">
        <button className="sym-btn sym-btn--primary" onClick={() => onAction('Proceed to checkout')}>Checkout</button>
        <button className="sym-btn sym-btn--ghost" onClick={() => onAction('Continue shopping')}>Continue Shopping</button>
      </div>
    </div>
  );
}

function OrderBlock({ data }) {
  return (
    <div className="sym-block-order">
      <div className="sym-block-order-header">
        <span>Order #{data.order_id}</span>
        <span className={`sym-status sym-status--${data.status_key}`}>{data.status}</span>
      </div>
      <div className="sym-block-order-details">
        <span>Date: {data.date_created}</span>
        <span>Total: ${data.total}</span>
        {data.payment_method && <span>Payment: {data.payment_method}</span>}
      </div>
    </div>
  );
}

function OrdersBlock({ data }) {
  const orders = data?.orders || [];
  return (
    <div className="sym-block-orders">
      <h3>Your Recent Orders</h3>
      {orders.map(o => (
        <div key={o.order_id} className="sym-block-order">
          <div className="sym-block-order-header">
            <span>#{o.order_id}</span>
            <span className="sym-status">{o.status}</span>
            <span>{o.date_created}</span>
            <strong>${o.total}</strong>
          </div>
          <span className="sym-block-order-items">{o.items_summary}</span>
        </div>
      ))}
    </div>
  );
}

function PolicyBlock({ data }) {
  const policies = data?.policies || {};
  return (
    <div className="sym-block-policy">
      {Object.entries(policies).map(([key, content]) => (
        <div key={key} className="sym-block-policy-section">
          <h4>{key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</h4>
          <p>{content}</p>
        </div>
      ))}
    </div>
  );
}

function ShippingBlock({ data, onAction }) {
  const methods = data?.methods || [];
  return (
    <div className="sym-block-shipping">
      <h3>Shipping to {data?.destination?.country} {data?.destination?.state || ''}</h3>
      {methods.length > 0 ? (
        <div className="sym-block-shipping-methods">
          {methods.map((m, i) => (
            <div key={i} className="sym-block-shipping-row">
              <span>{m.method}</span>
              <strong>${m.cost}</strong>
            </div>
          ))}
        </div>
      ) : (
        <p>No shipping methods available for this destination.</p>
      )}
      <button className="sym-btn sym-btn--primary" style={{ marginTop: 16 }} onClick={() => onAction('Proceed to checkout')}>
        Continue to Checkout
      </button>
    </div>
  );
}
