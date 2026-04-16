import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { addCalculatorToCart, getCartDirect, removeCartItem, updateCartItem } from '../../utils/api.js';

/**
 * CalculatorBlock — Axiomprint-style product configurator rendered inline in AI chat.
 *
 * Receives full calculator config from get_product_calculator tool attachment.
 * Handles: option rendering, dependency filters, real-time price calculation, add-to-cart.
 */
export default function CalculatorBlock({ data, onAction }) {
  const { product_id, product_name, image_url, description, formula, min_price, variables } = data;
  const [selections, setSelections] = useState({});
  const [price, setPrice] = useState(0);

  // Initialize defaults.
  useEffect(() => {
    if (!variables?.length) return;
    const defaults = {};
    for (const v of variables) {
      const visibleItems = v.items.filter(it => !it.isHidden);
      const def = visibleItems.find(it => it.isDefault) || visibleItems[0];
      if (def) defaults[v.slug] = def;
    }
    setSelections(defaults);
  }, [variables]);

  // Auto-select filtered items when dependencies change.
  useEffect(() => {
    if (!variables?.length) return;
    const updated = { ...selections };
    let changed = false;

    for (const v of variables) {
      const visible = getVisibleItems(v, updated);
      const current = updated[v.slug];
      if (current && visible.length && !visible.some(it => it.id === current.id)) {
        updated[v.slug] = visible.find(it => it.isDefault) || visible[0];
        changed = true;
      } else if (!current && visible.length) {
        updated[v.slug] = visible[0];
        changed = true;
      }
    }

    if (changed) setSelections(updated);
  }, [selections, variables]);

  // Calculate price whenever selections change.
  useEffect(() => {
    if (!formula || !Object.keys(selections).length) return;
    const p = evaluateFormula(formula, selections, min_price);
    setPrice(p);
  }, [selections, formula, min_price]);

  const handleSelect = useCallback((slug, item) => {
    setSelections(prev => ({ ...prev, [slug]: item }));
  }, []);

  const [addingToCart, setAddingToCart] = useState(false);
  const [cartMsg, setCartMsg] = useState(null);
  const [inlineCart, setInlineCart] = useState(null);
  const [cartBusy, setCartBusy] = useState(null);

  const refreshCart = useCallback(() => {
    getCartDirect().then(c => setInlineCart(c)).catch(() => {});
  }, []);

  const handleAddToCart = useCallback(() => {
    if (addingToCart) return;
    setAddingToCart(true);
    setCartMsg(null);
    setInlineCart(null);

    const selData = {};
    for (const [slug, item] of Object.entries(selections)) {
      selData[slug] = { id: item.id, label: item.label, value: item.value, base: item.base, config: item.config || {} };
    }

    addCalculatorToCart(product_id, selData, price)
      .then(resp => {
        if (resp.success) {
          setCartMsg({ type: 'success', text: `${resp.product_name} added to cart!` });
          refreshCart();
        } else {
          setCartMsg({ type: 'error', text: resp.message || 'Failed to add to cart.' });
        }
      })
      .catch(err => setCartMsg({ type: 'error', text: err.message || 'Network error.' }))
      .finally(() => setAddingToCart(false));
  }, [selections, product_id, price, addingToCart, refreshCart]);

  if (!variables?.length) return null;

  const qty = getQuantity(selections);
  const perUnit = qty > 1 ? (price / qty) : price;

  // Get turnaround ETA.
  const taItem = selections['Turnaround'] || selections['turnaround'];
  let etaDate = null;
  if (taItem?.config?.day_count) {
    const d = new Date();
    d.setDate(d.getDate() + parseInt(taItem.config.day_count, 10));
    etaDate = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
  }

  return (
    <div className="sym-calculator">
      <div className="sym-calc-header">
        {image_url && <img src={image_url} alt={product_name} className="sym-calc-thumb" />}
        <div>
          <h3 className="sym-calc-title">{product_name}</h3>
          {description && <p className="sym-calc-desc">{description}</p>}
        </div>
      </div>

      <div className="sym-calc-options">
        {variables.map(v => {
          if (v.isHidden) return null;
          const visible = getVisibleItems(v, selections);
          if (!visible.length) return null;

          return (
            <div key={v.id} className="sym-calc-group">
              <label className="sym-calc-label">{v.label}</label>
              {renderOptionGroup(v, visible, selections[v.slug], handleSelect)}
            </div>
          );
        })}
      </div>

      <div className="sym-calc-price-panel">
        <div className="sym-calc-price-row">
          <span className="sym-calc-price-label">Total:</span>
          <span className="sym-calc-price-amount">${price.toFixed(2)}</span>
        </div>
        {qty > 1 && (
          <div className="sym-calc-per-unit">${perUnit.toFixed(4)} per unit</div>
        )}
        {etaDate && (
          <div className="sym-calc-eta">Estimated ready: {etaDate}</div>
        )}
        <button className="sym-btn sym-btn--primary sym-calc-add-btn" onClick={handleAddToCart} disabled={addingToCart || price <= 0}>
          {addingToCart ? 'Adding...' : `Add to Cart — $${price.toFixed(2)}`}
        </button>
        {cartMsg && (
          <div className={`sym-pv-cart-msg sym-pv-cart-msg--${cartMsg.type}`}>
            <span>{cartMsg.type === 'success' ? '✓' : '✗'} {cartMsg.text}</span>
          </div>
        )}
        {inlineCart && inlineCart.items && inlineCart.items.length > 0 && (
          <div className="sym-inline-cart">
            <div className="sym-inline-cart-header">
              <span>Cart ({inlineCart.item_count} {inlineCart.item_count === 1 ? 'item' : 'items'})</span>
              <span className="sym-inline-cart-total">{inlineCart.currency_symbol}{inlineCart.total}</span>
            </div>
            <div className="sym-inline-cart-items">
              {inlineCart.items.map(item => (
                <div key={item.key} className={`sym-inline-cart-item ${cartBusy === item.key ? 'sym-inline-cart-item--busy' : ''}`}>
                  <img src={item.image_url} alt={item.name} className="sym-inline-cart-img" />
                  <div className="sym-inline-cart-info">
                    <span className="sym-inline-cart-name">{item.name}</span>
                    {item.options?.length > 0 && (
                      <span className="sym-inline-cart-opts">{item.options.map(o => o.value).join(' · ')}</span>
                    )}
                  </div>
                  <div className="sym-inline-cart-qty">
                    <button onClick={() => { setCartBusy(item.key); updateCartItem(item.key, Math.max(1, item.quantity - 1)).then(refreshCart).finally(() => setCartBusy(null)); }} disabled={item.quantity <= 1}>-</button>
                    <span>{item.quantity}</span>
                    <button onClick={() => { setCartBusy(item.key); updateCartItem(item.key, item.quantity + 1).then(refreshCart).finally(() => setCartBusy(null)); }}>+</button>
                  </div>
                  <span className="sym-inline-cart-price">{inlineCart.currency_symbol}{item.line_total}</span>
                  <button className="sym-inline-cart-rm" onClick={() => { setCartBusy(item.key); removeCartItem(item.key).then(refreshCart).finally(() => setCartBusy(null)); }}>&times;</button>
                </div>
              ))}
            </div>
            <div className="sym-inline-cart-footer">
              <a href={inlineCart.checkout_url} className="sym-btn sym-btn--primary sym-btn--sm sym-inline-cart-checkout" style={{textDecoration:'none',textAlign:'center'}}>
                Checkout — {inlineCart.currency_symbol}{inlineCart.total}
              </a>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Filter Logic ──

function getVisibleItems(variable, selections) {
  return variable.items.filter(item => {
    if (item.isHidden) return false;
    if (!item.filters?.length) return true;

    // Group by variableSlug → OR within group, AND across groups.
    const groups = {};
    for (const f of item.filters) {
      if (!f.variableSlug) continue;
      if (!groups[f.variableSlug]) groups[f.variableSlug] = [];
      groups[f.variableSlug].push(f);
    }

    for (const slug in groups) {
      const selected = selections[slug];
      if (!selected) return false;
      const match = groups[slug].some(f =>
        (f.itemId && selected.id === f.itemId) ||
        (f.itemLabel && selected.label === f.itemLabel)
      );
      if (!match) return false;
    }
    return true;
  });
}

// ── Renderers ──

function renderOptionGroup(v, items, selected, onSelect) {
  switch (v.type) {
    case 'card':
    case 'material_card':
      return (
        <div className="sym-calc-cards">
          {items.map(item => (
            <button key={item.id}
              className={`sym-calc-card ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              {item.config?.image_url && <img src={item.config.image_url} alt="" />}
              <span>{item.label}</span>
              {item.base > 0 && <small>+${item.base.toFixed(2)}</small>}
            </button>
          ))}
        </div>
      );

    case 'radio':
      return (
        <div className="sym-calc-radios">
          {items.map(item => (
            <button key={item.id}
              className={`sym-calc-radio ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              {item.label}
            </button>
          ))}
        </div>
      );

    case 'pill':
      return (
        <div className="sym-calc-pills">
          {items.map(item => (
            <button key={item.id}
              className={`sym-calc-pill ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              {item.label}
            </button>
          ))}
        </div>
      );

    case 'turnaround':
      return (
        <div className="sym-calc-turnaround">
          {items.map(item => (
            <button key={item.id}
              className={`sym-calc-ta-btn ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              <span className="sym-calc-ta-label">{item.label}</span>
              {item.config?.day_count && (
                <span className="sym-calc-ta-days">
                  {item.config.day_count === '0' ? 'Same day' : `${item.config.day_count} days`}
                </span>
              )}
            </button>
          ))}
        </div>
      );

    case 'quantity_tiers':
    case 'size':
    case 'list':
    default:
      return (
        <select className="sym-calc-select"
          value={selected?.id || ''}
          onChange={e => {
            const item = items.find(it => it.id === parseInt(e.target.value, 10));
            if (item) onSelect(v.slug, item);
          }}>
          {items.map(item => (
            <option key={item.id} value={item.id}>{item.label}</option>
          ))}
        </select>
      );
  }
}

// ── Formula Evaluator ──

function getQuantity(selections) {
  const qi = selections['Quantity'] || selections['quantity'];
  return qi ? (parseFloat(qi.label) || qi.value || 1) : 1;
}

function evaluateFormula(formula, selections, minPrice) {
  if (!formula) return 0;
  const subs = {};

  function addSub(key, val) {
    if (key && (subs[key] === undefined || val !== 0)) subs[key] = val;
  }

  for (const [slug, item] of Object.entries(selections)) {
    addSub(slug, item.value);
    addSub(slug + '$base', item.base);

    // Register PascalCase: "print_mode" → "Print_Mode"
    const pc = slug.replace(/(^|_)([a-z])/g, (_, s, c) => s + c.toUpperCase());
    if (pc !== slug) { addSub(pc, item.value); addSub(pc + '$base', item.base); }

    // Register abbreviation variant: "raised_spot_uv" → "Raised_Spot_UV"
    const up = slug.split('_').map(p => p.length <= 3 ? p.toUpperCase() : p.charAt(0).toUpperCase() + p.slice(1)).join('_');
    if (up !== slug && up !== pc) { addSub(up, item.value); addSub(up + '$base', item.base); }

    if (slug.toLowerCase() === 'size' && item.config) {
      const w = parseFloat(item.config.width) || 0;
      const h = parseFloat(item.config.height) || 0;
      const d = parseFloat(item.config.depth) || 0;
      addSub('Size$w', w); addSub('size$w', w);
      addSub('Size$h', h); addSub('size$h', h);
      addSub('Size$d', d); addSub('size$d', d);
    }
  }

  const qi = selections['Quantity'] || selections['quantity'];
  if (qi) {
    const q = parseFloat(qi.label) || qi.value || 1;
    addSub('Quantity', q); addSub('quantity', q);
    addSub('Quantity$base', qi.base); addSub('quantity$base', qi.base);
  }

  let expr = formula.replace(/\$versionsCount/g, '*0 + 1');
  const keys = Object.keys(subs).sort((a, b) => b.length - a.length);
  for (const key of keys) {
    expr = expr.replace(new RegExp(key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), String(subs[key]));
  }

  expr = expr.replace(/floor/gi, 'Math.floor').replace(/round/gi, 'Math.round')
    .replace(/sqrt/gi, 'Math.sqrt').replace(/ceil/gi, 'Math.ceil');

  let result = 0;
  try { result = new Function('return (' + expr + ');')(); if (!isFinite(result)) result = 0; }
  catch { result = 0; }

  if (minPrice > 0 && result < minPrice) result = minPrice;
  return Math.round(result * 100) / 100;
}
