import React, { useState, useCallback } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { getCartDirect, removeCartItem, updateCartItem } from '../../utils/api.js';
import Icon from '../shared/Icon.jsx';

export default function BeingPanel() {
  const { state, dispatch, navigate, sendMessage } = useWorkspace();
  const data = window.symbioticData || {};
  const isOpen = state.rightOpen;
  const questionsAsked = state.messages.filter(m => m.role === 'user').length;

  const [cartExpanded, setCartExpanded] = useState(false);
  const [cartDetails, setCartDetails] = useState(null);
  const [cartLoading, setCartLoading] = useState(false);
  const [cartBusy, setCartBusy] = useState(null);

  const fetchCart = useCallback(() => {
    setCartLoading(true);
    getCartDirect().then(c => {
      setCartDetails(c);
      dispatch({ type: 'SET_CART', data: c, count: c.item_count || 0 });
    }).catch(() => {}).finally(() => setCartLoading(false));
  }, [dispatch]);

  const toggleCart = useCallback(() => {
    if (!cartExpanded) fetchCart();
    setCartExpanded(prev => !prev);
  }, [cartExpanded, fetchCart]);

  const handleRemove = useCallback((key) => {
    setCartBusy(key);
    removeCartItem(key).then(() => fetchCart()).finally(() => setCartBusy(null));
  }, [fetchCart]);

  const handleQty = useCallback((key, qty) => {
    setCartBusy(key);
    updateCartItem(key, qty).then(() => fetchCart()).finally(() => setCartBusy(null));
  }, [fetchCart]);

  const cartTotal = cartDetails?.total || state.cartData?.total || '0.00';
  const cur = cartDetails?.currency_symbol || data.currencySymbol || '$';

  return (
    <aside className={`sym-panel sym-panel--being ${isOpen ? '' : 'sym-panel--collapsed'}`}>
      <button className="sym-panel-toggle sym-panel-toggle--right" onClick={() => dispatch({ type: 'TOGGLE_RIGHT' })} aria-label={isOpen ? 'Collapse' : 'Expand'}>
        <Icon name={isOpen ? 'chevronRight' : 'chevronLeft'} size={14} />
      </button>

      <div className="sym-panel-body">
        <div className="sym-bp-header">
          <span className="sym-bp-title">Activity</span>
          <span className="sym-bp-sub">Your cart, orders & history</span>
        </div>

        {/* ── Cart Section ── */}
        <div className="sym-bp-section">
          <span className="sym-bp-label">
            <Icon name="cart" size={14} />
            Your Cart
          </span>
          {state.cartCount > 0 ? (
            <div className="sym-bp-cart">
              <div className="sym-bp-cart-row">
                <span>{state.cartCount} items</span>
                <strong>{cur}{cartTotal}</strong>
              </div>
              <button className="sym-bp-btn" onClick={toggleCart}>
                {cartExpanded ? 'Hide Cart' : 'View Cart'}
              </button>
              <button className="sym-bp-btn sym-bp-btn--primary" onClick={() => navigate('checkout')}>
                Checkout
              </button>

              {/* Expanded inline cart */}
              {cartExpanded && (
                <div className="sym-bp-cart-details">
                  {cartLoading && !cartDetails && (
                    <div className="sym-bp-cart-loading">Loading...</div>
                  )}
                  {cartDetails && cartDetails.items && cartDetails.items.map(item => (
                    <div key={item.key} className={`sym-bp-cart-item ${cartBusy === item.key ? 'sym-bp-cart-item--busy' : ''}`}>
                      <img src={item.image_url} alt={item.name} className="sym-bp-cart-img" />
                      <div className="sym-bp-cart-item-info">
                        <span className="sym-bp-cart-item-name">{item.name}</span>
                        {item.options?.length > 0 && (
                          <span className="sym-bp-cart-item-opts">
                            {item.options.map(o => o.value).join(' · ')}
                          </span>
                        )}
                        <div className="sym-bp-cart-item-bottom">
                          <div className="sym-bp-cart-item-qty">
                            <button onClick={() => handleQty(item.key, Math.max(1, item.quantity - 1))} disabled={item.quantity <= 1}>-</button>
                            <span>{item.quantity}</span>
                            <button onClick={() => handleQty(item.key, item.quantity + 1)}>+</button>
                          </div>
                          <span className="sym-bp-cart-item-price">{cur}{item.line_total}</span>
                          <button className="sym-bp-cart-item-rm" onClick={() => handleRemove(item.key)}>&times;</button>
                        </div>
                      </div>
                    </div>
                  ))}
                  {cartDetails && (!cartDetails.items || cartDetails.items.length === 0) && (
                    <div className="sym-bp-cart-loading">Cart is empty</div>
                  )}
                </div>
              )}
            </div>
          ) : (
            <p className="sym-bp-empty">Your cart is empty</p>
          )}
        </div>

        {/* ── Orders ── */}
        {data.isLoggedIn && (
          <div className="sym-bp-section">
            <span className="sym-bp-label">
              <Icon name="package" size={14} />
              Your Orders
            </span>
            <button className="sym-bp-btn" onClick={() => navigate('orders')}>View Orders</button>
          </div>
        )}

        {/* ── Account ── */}
        <div className="sym-bp-section">
          <span className="sym-bp-label">
            <Icon name="user" size={14} />
            Account
          </span>
          {data.isLoggedIn ? (
            <a href="/my-account/" className="sym-bp-btn">My Account</a>
          ) : (
            <a href="/my-account/" className="sym-bp-btn">Login / Register</a>
          )}
        </div>

        {/* ── Conversation ── */}
        {questionsAsked > 0 && (
          <div className="sym-bp-section">
            <span className="sym-bp-label">
              <Icon name="chat" size={14} />
              Conversation
            </span>
            <p className="sym-bp-empty">{questionsAsked} questions asked</p>
            {state.messages.filter(m => m.role === 'user').slice(-5).map(m => (
              <div key={m.id} className="sym-bp-msg-preview">
                <Icon name="search" size={10} />
                <span>{m.content.length > 40 ? m.content.slice(0, 40) + '...' : m.content}</span>
              </div>
            ))}
          </div>
        )}

        {/* ── Quick Actions ── */}
        <div className="sym-bp-section">
          <span className="sym-bp-label">
            <Icon name="star" size={14} />
            Quick Actions
          </span>
          <button className="sym-bp-action" onClick={() => navigate('products')}>Browse Products</button>
          <button className="sym-bp-action" onClick={() => navigate('page', { slug: 'shipping-delivery' })}>Shipping info</button>
        </div>
      </div>
    </aside>
  );
}
