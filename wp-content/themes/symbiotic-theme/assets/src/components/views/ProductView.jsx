import React, { useState, useEffect, useCallback } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { fetchCalculatorConfig, addCalculatorToCart, getCartDirect, removeCartItem, updateCartItem } from '../../utils/api.js';
import Icon from '../shared/Icon.jsx';

/**
 * ProductView — full-page product configurator.
 * Opens when user clicks a product from the grid.
 * Shows product image gallery + calculator options + real-time price.
 */
export default function ProductView() {
  const { state, dispatch, goBack, navigate, sendMessage } = useWorkspace();
  const productId = state.viewParams?.productId;
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selections, setSelections] = useState({});
  const [price, setPrice] = useState(0);
  const [activeImage, setActiveImage] = useState(0);

  // Fetch calculator config.
  useEffect(() => {
    if (!productId) return;
    setLoading(true);
    setError(null);
    fetchCalculatorConfig(productId)
      .then(d => {
        setData(d);
        initDefaults(d.variables || []);
      })
      .catch(e => setError(e.message || 'Failed to load product'))
      .finally(() => setLoading(false));
  }, [productId]);

  function initDefaults(variables) {
    const defaults = {};
    for (const v of variables) {
      const visible = v.items.filter(it => !it.isHidden);
      const def = visible.find(it => it.isDefault) || visible[0];
      if (def) defaults[v.slug] = def;
    }
    setSelections(defaults);
  }

  // Auto-select filtered + recalculate price.
  useEffect(() => {
    if (!data?.variables?.length || !Object.keys(selections).length) return;
    const updated = { ...selections };
    let changed = false;
    for (const v of data.variables) {
      const visible = getVisibleItems(v, updated);
      const cur = updated[v.slug];
      if (cur && visible.length && !visible.some(it => it.id === cur.id)) {
        updated[v.slug] = visible.find(it => it.isDefault) || visible[0];
        changed = true;
      } else if (!cur && visible.length) {
        updated[v.slug] = visible[0];
        changed = true;
      }
    }
    if (changed) setSelections(updated);

    // Calculate price.
    if (data.formula) {
      setPrice(evaluateFormula(data.formula, updated, data.min_price));
    }
  }, [selections, data]);

  const handleSelect = useCallback((slug, item) => {
    setSelections(prev => ({ ...prev, [slug]: item }));
  }, []);

  const [addingToCart, setAddingToCart] = useState(false);
  const [cartMessage, setCartMessage] = useState(null);
  const [inlineCart, setInlineCart] = useState(null);
  const [cartBusy, setCartBusy] = useState(null);

  const handleAddToCart = useCallback(() => {
    if (!data || addingToCart) return;
    setAddingToCart(true);
    setCartMessage(null);
    setInlineCart(null);

    const selData = {};
    for (const [slug, item] of Object.entries(selections)) {
      selData[slug] = { id: item.id, label: item.label, value: item.value, base: item.base, config: item.config || {} };
    }

    addCalculatorToCart(data.product_id, selData, price)
      .then(resp => {
        if (resp.success) {
          setCartMessage({
            type: 'success',
            text: `${resp.product_name} added to cart!`,
            checkoutUrl: resp.checkout_url,
          });
          // Auto-fetch and show inline cart.
          refreshInlineCart();
        } else {
          setCartMessage({ type: 'error', text: resp.message || 'Failed to add to cart.' });
        }
      })
      .catch(err => {
        setCartMessage({ type: 'error', text: err.message || 'Network error.' });
      })
      .finally(() => setAddingToCart(false));
  }, [selections, data, price, addingToCart]);

  const refreshInlineCart = useCallback(() => {
    getCartDirect().then(c => {
      setInlineCart(c);
      dispatch({ type: 'SET_CART', data: c, count: c.item_count || 0 });
    }).catch(() => {});
  }, [dispatch]);

  const handleCartRemove = useCallback((key) => {
    setCartBusy(key);
    removeCartItem(key).then(() => refreshInlineCart()).finally(() => setCartBusy(null));
  }, [refreshInlineCart]);

  const handleCartQty = useCallback((key, qty) => {
    setCartBusy(key);
    updateCartItem(key, qty).then(() => refreshInlineCart()).finally(() => setCartBusy(null));
  }, [refreshInlineCart]);

  if (loading) {
    return (
      <div className="sym-product-view">
        <div className="sym-pv-loading">
          <div className="sym-ai-thinking-dots"><span/><span/><span/></div>
          <p>Loading product...</p>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="sym-product-view">
        <button className="sym-pv-back" onClick={goBack}><Icon name="back" size={16} /> Back</button>
        <p className="sym-pv-error">{error || 'Product not found'}</p>
      </div>
    );
  }

  const qty = getQuantity(selections);
  const perUnit = qty > 1 ? (price / qty) : price;
  const taItem = selections['Turnaround'] || selections['turnaround'];
  let etaDate = null;
  if (taItem?.config?.day_count) {
    const d = new Date();
    d.setDate(d.getDate() + parseInt(taItem.config.day_count, 10));
    etaDate = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
  }

  const allImages = [data.image_url, ...(data.gallery || [])].filter(Boolean);

  return (
    <div className="sym-product-view">
      <button className="sym-pv-back" onClick={goBack}><Icon name="back" size={16} /> Back to products</button>

      <div className="sym-pv-layout">
        {/* Left: Images */}
        <div className="sym-pv-gallery">
          <div className="sym-pv-main-img">
            <img src={allImages[activeImage] || allImages[0]} alt={data.product_name} />
          </div>
          {allImages.length > 1 && (
            <div className="sym-pv-thumbs">
              {allImages.map((img, i) => (
                <button key={i} className={`sym-pv-thumb ${i === activeImage ? 'active' : ''}`} onClick={() => setActiveImage(i)}>
                  <img src={img} alt="" />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Right: Configurator */}
        <div className="sym-pv-config">
          <h1 className="sym-pv-title">{data.product_name}</h1>
          {data.short_description && <p className="sym-pv-desc">{data.short_description}</p>}

          <div className="sym-pv-options">
            {data.variables.map(v => {
              if (v.isHidden) return null;
              const visible = getVisibleItems(v, selections);
              if (!visible.length) return null;

              return (
                <div key={v.id} className="sym-pv-group">
                  <label className="sym-pv-label">{v.label}</label>
                  {renderOptionUI(v, visible, selections[v.slug], handleSelect, data.currency_symbol || '$')}
                </div>
              );
            })}
          </div>

          {/* Price Panel */}
          <div className="sym-pv-price-panel">
            <div className="sym-pv-price-row">
              <span className="sym-pv-price-lbl">Total:</span>
              <span className="sym-pv-price-amt">{data.currency_symbol || '$'}{price.toFixed(2)}</span>
            </div>
            {qty > 1 && <div className="sym-pv-per-unit">{data.currency_symbol || '$'}{perUnit.toFixed(4)} per unit</div>}
            {etaDate && <div className="sym-pv-eta">Estimated ready: {etaDate}</div>}
            <button className="sym-btn sym-btn--primary sym-pv-add-btn" onClick={handleAddToCart} disabled={addingToCart || price <= 0}>
              {addingToCart ? 'Adding...' : `Add to Cart — ${data.currency_symbol || '$'}${price.toFixed(2)}`}
            </button>
            {cartMessage && (
              <div className={`sym-pv-cart-msg sym-pv-cart-msg--${cartMessage.type}`}>
                <span>{cartMessage.type === 'success' ? '✓' : '✗'} {cartMessage.text}</span>
                {cartMessage.type === 'error' && null}
              </div>
            )}

            {/* Inline Cart */}
            {inlineCart && inlineCart.items && inlineCart.items.length > 0 && (
              <InlineCartBlock cart={inlineCart} busy={cartBusy}
                onRemove={handleCartRemove} onQtyChange={handleCartQty}
                onCheckout={() => navigate('checkout')} />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Filter logic ──

function getVisibleItems(variable, selections) {
  return variable.items.filter(item => {
    if (item.isHidden) return false;
    if (!item.filters?.length) return true;
    const groups = {};
    for (const f of item.filters) {
      if (!f.variableSlug) continue;
      if (!groups[f.variableSlug]) groups[f.variableSlug] = [];
      groups[f.variableSlug].push(f);
    }
    for (const slug in groups) {
      const sel = selections[slug];
      if (!sel) return false;
      if (!groups[slug].some(f => (f.itemId && sel.id === f.itemId) || (f.itemLabel && sel.label === f.itemLabel))) return false;
    }
    return true;
  });
}

// ── Option Renderers ──

function renderOptionUI(v, items, selected, onSelect, currency) {
  switch (v.type) {
    case 'card':
    case 'material_card':
      return (
        <div className="sym-pv-cards">
          {items.map(item => (
            <button key={item.id} className={`sym-pv-card ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              {item.config?.image_url && <img src={item.config.image_url} alt="" className="sym-pv-card-img" />}
              <span className="sym-pv-card-name">{item.label}</span>
              {item.base > 0 && <span className="sym-pv-card-price">+{currency}{item.base.toFixed(2)}</span>}
            </button>
          ))}
        </div>
      );

    case 'radio':
      return (
        <div className="sym-pv-radios">
          {items.map(item => (
            <button key={item.id} className={`sym-pv-radio-btn ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              {item.label}
            </button>
          ))}
        </div>
      );

    case 'turnaround':
      return (
        <div className="sym-pv-turnaround">
          {items.map(item => (
            <button key={item.id} className={`sym-pv-ta ${selected?.id === item.id ? 'active' : ''}`}
              onClick={() => onSelect(v.slug, item)}>
              <span className="sym-pv-ta-name">{item.label}</span>
              {item.config?.day_count && (
                <span className="sym-pv-ta-days">{item.config.day_count === '0' ? 'Same day' : item.config.day_count + ' days'}</span>
              )}
            </button>
          ))}
        </div>
      );

    case 'size':
      return <SizeSelector v={v} items={items} selected={selected} onSelect={onSelect} currency={currency} />;

    case 'quantity_tiers':
    case 'list':
    default:
      return (
        <select className="sym-pv-select" value={selected?.id || ''}
          onChange={e => { const it = items.find(i => i.id === parseInt(e.target.value, 10)); if (it) onSelect(v.slug, it); }}>
          {items.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}
        </select>
      );
  }
}

/**
 * Size selector with custom size support.
 */
function SizeSelector({ v, items, selected, onSelect, currency }) {
  const [customW, setCustomW] = useState('');
  const [customH, setCustomH] = useState('');
  const [customD, setCustomD] = useState('');
  const isCustom = selected && (selected.label.toLowerCase().includes('custom') || selected.config?.is_custom === '1');
  const cfg = v.config || {};

  // Detect if product uses depth (3D sizes like boxes).
  const hasDepth = items.some(it => it.config?.depth && parseFloat(it.config.depth) > 0);

  function handleCustomChange(w, h, d) {
    if (!selected) return;
    const width = parseFloat(w) || 0;
    const height = parseFloat(h) || 0;
    const depth = parseFloat(d) || 0;
    const updated = {
      ...selected,
      value: hasDepth ? width * height * depth : width * height,
      config: {
        ...selected.config,
        width: String(width), height: String(height),
        ...(hasDepth ? { depth: String(depth) } : {}),
        is_custom: '1',
      },
    };
    onSelect(v.slug, updated);
  }

  return (
    <div className="sym-pv-size-wrap">
      <select className="sym-pv-select" value={selected?.id || ''}
        onChange={e => {
          const it = items.find(i => i.id === parseInt(e.target.value, 10));
          if (it) {
            setCustomW(''); setCustomH(''); setCustomD('');
            onSelect(v.slug, it);
          }
        }}>
        {items.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}
      </select>
      {isCustom && (
        <div className="sym-pv-custom-size">
          <div className="sym-pv-custom-inputs">
            <div className="sym-pv-custom-field">
              <label>Width</label>
              <input type="number" className="sym-pv-custom-input" placeholder={cfg.min_width || '1'}
                min={cfg.min_width || 1} max={cfg.max_width || 999} step="0.1"
                value={customW} onChange={e => { setCustomW(e.target.value); handleCustomChange(e.target.value, customH, customD); }} />
            </div>
            <span className="sym-pv-custom-x">&times;</span>
            <div className="sym-pv-custom-field">
              <label>Height</label>
              <input type="number" className="sym-pv-custom-input" placeholder={cfg.min_height || '1'}
                min={cfg.min_height || 1} max={cfg.max_height || 999} step="0.1"
                value={customH} onChange={e => { setCustomH(e.target.value); handleCustomChange(customW, e.target.value, customD); }} />
            </div>
            {hasDepth && (
              <>
                <span className="sym-pv-custom-x">&times;</span>
                <div className="sym-pv-custom-field">
                  <label>Depth</label>
                  <input type="number" className="sym-pv-custom-input" placeholder="1"
                    min={1} max={999} step="0.1"
                    value={customD} onChange={e => { setCustomD(e.target.value); handleCustomChange(customW, customH, e.target.value); }} />
                </div>
              </>
            )}
            <span className="sym-pv-custom-unit">{cfg.metric || 'inch'}</span>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Inline Cart Block ──

function InlineCartBlock({ cart, busy, onRemove, onQtyChange, onCheckout }) {
  const cur = cart.currency_symbol || '$';
  return (
    <div className="sym-inline-cart">
      <div className="sym-inline-cart-header">
        <span>Cart ({cart.item_count} {cart.item_count === 1 ? 'item' : 'items'})</span>
        <span className="sym-inline-cart-total">{cur}{cart.total}</span>
      </div>
      <div className="sym-inline-cart-items">
        {cart.items.map(item => (
          <div key={item.key} className={`sym-inline-cart-item ${busy === item.key ? 'sym-inline-cart-item--busy' : ''}`}>
            <img src={item.image_url} alt={item.name} className="sym-inline-cart-img" />
            <div className="sym-inline-cart-info">
              <span className="sym-inline-cart-name">{item.name}</span>
              {item.options && item.options.length > 0 && (
                <span className="sym-inline-cart-opts">
                  {item.options.map(o => o.value).join(' · ')}
                </span>
              )}
            </div>
            <div className="sym-inline-cart-qty">
              <button onClick={() => onQtyChange(item.key, Math.max(1, item.quantity - 1))} disabled={item.quantity <= 1}>-</button>
              <span>{item.quantity}</span>
              <button onClick={() => onQtyChange(item.key, item.quantity + 1)}>+</button>
            </div>
            <span className="sym-inline-cart-price">{cur}{item.line_total}</span>
            <button className="sym-inline-cart-rm" onClick={() => onRemove(item.key)} title="Remove">&times;</button>
          </div>
        ))}
      </div>
      <div className="sym-inline-cart-footer">
        <button className="sym-btn sym-btn--primary sym-btn--sm sym-inline-cart-checkout" onClick={onCheckout}>
          Checkout — {cur}{cart.total}
        </button>
      </div>
    </div>
  );
}

// ── Formula Evaluator ──

function getQuantity(selections) {
  const qi = selections['Quantity'] || selections['quantity'];
  return qi ? (parseFloat(qi.label) || qi.value || 1) : 1;
}

/**
 * Evaluate pricing formula with variable substitution.
 *
 * Handles case mismatches between formula tokens and DB slugs.
 * Formula may use "Size$w", "Material", "Turnaround" (PascalCase from label)
 * while DB slugs are "size", "material", "turnaround" (lowercase).
 *
 * Strategy: register each variable under BOTH its slug and its label-derived form.
 */
function evaluateFormula(formula, selections, minPrice) {
  if (!formula) return 0;

  const subs = {};

  // Helper: add a substitution under a key (avoids overwrites with 0).
  function addSub(key, val) {
    if (key && (subs[key] === undefined || val !== 0)) subs[key] = val;
  }

  for (const [slug, item] of Object.entries(selections)) {
    const val = item.value;
    const base = item.base;

    // Register under the actual slug.
    addSub(slug, val);
    addSub(slug + '$base', base);

    // Register PascalCase: "print_mode" → "Print_Mode"
    const labelSlug = slug.replace(/(^|_)([a-z])/g, (_, sep, c) => sep + c.toUpperCase());
    if (labelSlug !== slug) {
      addSub(labelSlug, val);
      addSub(labelSlug + '$base', base);
    }

    // Register abbreviation variant: "raised_spot_uv" → "Raised_Spot_UV"
    const upperParts = slug.split('_').map(p => p.length <= 3 ? p.toUpperCase() : p.charAt(0).toUpperCase() + p.slice(1));
    const upperSlug = upperParts.join('_');
    if (upperSlug !== slug && upperSlug !== labelSlug) {
      addSub(upperSlug, val);
      addSub(upperSlug + '$base', base);
    }

    // Size-specific: width and height for any variable with size-like slug.
    if (slug.toLowerCase() === 'size' && item.config) {
      const w = parseFloat(item.config.width) || 0;
      const h = parseFloat(item.config.height) || 0;
      const d = parseFloat(item.config.depth) || 0;
      addSub('Size$w', w); addSub('size$w', w);
      addSub('Size$h', h); addSub('size$h', h);
      addSub('Size$d', d); addSub('size$d', d);
    }
  }

  // Quantity: use label (the actual number like "50") not value_numeric.
  const qi = selections['Quantity'] || selections['quantity'];
  if (qi) {
    const qtyNum = parseFloat(qi.label) || qi.value || 1;
    addSub('Quantity', qtyNum);
    addSub('quantity', qtyNum);
    addSub('Quantity$base', qi.base);
    addSub('quantity$base', qi.base);
  }

  // Versions count.
  subs['$versionsCount'] = 1;

  // Substitute into formula.
  let expr = formula;
  expr = expr.replace(/\$versionsCount/g, '*0 + 1');

  // Sort keys longest first to prevent partial matches.
  const keys = Object.keys(subs).sort((a, b) => b.length - a.length);
  for (const key of keys) {
    if (key === '$versionsCount') continue;
    const escaped = key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    expr = expr.replace(new RegExp(escaped, 'g'), String(subs[key]));
  }

  // Replace math functions.
  expr = expr.replace(/floor/gi, 'Math.floor').replace(/round/gi, 'Math.round')
    .replace(/sqrt/gi, 'Math.sqrt').replace(/ceil/gi, 'Math.ceil');

  let result = 0;
  try {
    result = new Function('return (' + expr + ');')();
    if (!isFinite(result)) result = 0;
  } catch {
    result = 0;
  }

  if (minPrice > 0 && result < minPrice) result = minPrice;
  return Math.round(result * 100) / 100;
}
