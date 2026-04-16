import React, { useEffect, useState, useCallback } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { getCartDirect, removeCartItem, updateCartItem } from '../../utils/api.js';
import Icon from '../shared/Icon.jsx';

export default function CartView() {
  const { state, dispatch, navigate, goBack } = useWorkspace();
  const [cart, setCart] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(null); // key of item being updated

  const fetchCart = useCallback(() => {
    setLoading(true);
    getCartDirect()
      .then(data => {
        setCart(data);
        dispatch({ type: 'SET_CART', data, count: data.item_count || 0 });
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [dispatch]);

  useEffect(() => { fetchCart(); }, []);

  const handleRemove = useCallback((key) => {
    setBusy(key);
    removeCartItem(key)
      .then(() => fetchCart())
      .finally(() => setBusy(null));
  }, [fetchCart]);

  const handleQtyChange = useCallback((key, qty) => {
    setBusy(key);
    updateCartItem(key, qty)
      .then(() => fetchCart())
      .finally(() => setBusy(null));
  }, [fetchCart]);

  // Loading.
  if (loading && !cart) {
    return (
      <div className="sym-cart-view">
        <div className="sym-pv-loading">
          <div className="sym-ai-thinking-dots"><span/><span/><span/></div>
          <p>Loading cart...</p>
        </div>
      </div>
    );
  }

  // Empty cart.
  if (!cart || !cart.items || cart.items.length === 0) {
    return (
      <div className="sym-cart-view">
        <div className="sym-cart-empty">
          <Icon name="cart" size={48} />
          <h2>Your cart is empty</h2>
          <p>Browse our products and configure your order.</p>
          <button className="sym-btn sym-btn--primary" onClick={() => navigate('home')}>Browse Products</button>
        </div>
      </div>
    );
  }

  const cur = cart.currency_symbol || '$';

  return (
    <div className="sym-cart-view">
      <button className="sym-pv-back" onClick={goBack}><Icon name="back" size={16} /> Continue Shopping</button>

      <h1 className="sym-cart-title">Your Cart ({cart.item_count} {cart.item_count === 1 ? 'item' : 'items'})</h1>

      <div className="sym-cart-items">
        {cart.items.map(item => (
          <div key={item.key} className={`sym-cart-item ${busy === item.key ? 'sym-cart-item--busy' : ''}`}>
            <div className="sym-cart-item-img">
              <img src={item.image_url} alt={item.name} />
            </div>
            <div className="sym-cart-item-info">
              <h3 className="sym-cart-item-name">{item.name}</h3>
              {item.options && item.options.length > 0 && (
                <div className="sym-cart-item-options">
                  {item.options.map((opt, i) => (
                    <span key={i} className="sym-cart-item-opt">
                      <strong>{opt.name}:</strong> {opt.value}
                    </span>
                  ))}
                </div>
              )}
              <div className="sym-cart-item-pricing">
                <span className="sym-cart-item-unit">{cur}{item.price} each</span>
              </div>
            </div>
            <div className="sym-cart-item-qty">
              <button className="sym-qty-btn" onClick={() => handleQtyChange(item.key, Math.max(1, item.quantity - 1))} disabled={item.quantity <= 1}>-</button>
              <span className="sym-qty-val">{item.quantity}</span>
              <button className="sym-qty-btn" onClick={() => handleQtyChange(item.key, item.quantity + 1)}>+</button>
            </div>
            <div className="sym-cart-item-total">
              <span className="sym-cart-item-line-total">{cur}{item.line_total}</span>
              <button className="sym-cart-item-remove" onClick={() => handleRemove(item.key)} title="Remove">
                <Icon name="close" size={14} />
              </button>
            </div>
          </div>
        ))}
      </div>

      <div className="sym-cart-footer">
        <div className="sym-cart-totals">
          <div className="sym-cart-totals-row">
            <span>Subtotal</span>
            <span>{cur}{cart.subtotal}</span>
          </div>
          <div className="sym-cart-totals-row sym-cart-totals-row--total">
            <span>Total</span>
            <span>{cur}{cart.total}</span>
          </div>
        </div>
        <div className="sym-cart-actions">
          <button className="sym-btn sym-btn--primary sym-btn--lg sym-cart-checkout-btn" onClick={() => navigate('checkout')}>
            Proceed to Checkout
          </button>
          <button className="sym-btn sym-btn--ghost" onClick={() => navigate('home')}>
            Continue Shopping
          </button>
        </div>
      </div>
    </div>
  );
}
