import React, { useState, useEffect, useCallback } from 'react';
import { getCheckout, setCheckoutAddress, setCheckoutShipping, placeOrder } from '../../utils/api.js';

const STEPS = ['review', 'address', 'shipping', 'payment', 'confirm'];
const STEP_LABELS = { review: 'Review', address: 'Address', shipping: 'Shipping', payment: 'Payment', confirm: 'Done' };

export default function CheckoutBlock({ onDone }) {
  const [step, setStep] = useState('review');
  const [maxStep, setMaxStep] = useState(0); // highest step reached (for clickable tabs)
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [orderResult, setOrderResult] = useState(null);

  const [addr, setAddr] = useState({
    first_name: '', last_name: '', email: '', phone: '',
    address_1: '', address_2: '', city: '', state: '', postcode: '', country: 'US',
  });
  const [selectedShipping, setSelectedShipping] = useState('');
  const [selectedPayment, setSelectedPayment] = useState('');

  const currentIdx = STEPS.indexOf(step);

  // Track highest step reached.
  useEffect(() => {
    const idx = STEPS.indexOf(step);
    if (idx > maxStep) setMaxStep(idx);
  }, [step, maxStep]);

  // Load checkout data.
  useEffect(() => {
    setLoading(true);
    getCheckout()
      .then(d => {
        setData(d);
        if (d.address) {
          setAddr(prev => {
            const m = { ...prev };
            for (const k in d.address) { if (d.address[k]) m[k] = d.address[k]; }
            return m;
          });
        }
        if (d.chosen_shipping) setSelectedShipping(d.chosen_shipping);
        if (d.payment_gateways?.length) setSelectedPayment(d.payment_gateways[0].id);
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  // Get visible steps (skip shipping if not needed).
  const visibleSteps = STEPS.filter(s => s !== 'shipping' || data?.needs_shipping);

  // Navigate to step (only allow going back to completed steps).
  const goToStep = useCallback((s) => {
    const targetIdx = STEPS.indexOf(s);
    if (targetIdx <= maxStep && s !== 'confirm') {
      setStep(s);
      setError(null);
    }
  }, [maxStep]);

  const nextStep = useCallback(() => {
    const visIdx = visibleSteps.indexOf(step);
    if (visIdx < visibleSteps.length - 1) {
      setStep(visibleSteps[visIdx + 1]);
      setError(null);
    }
  }, [step, visibleSteps]);

  const prevStep = useCallback(() => {
    const visIdx = visibleSteps.indexOf(step);
    if (visIdx > 0) {
      setStep(visibleSteps[visIdx - 1]);
      setError(null);
    }
  }, [step, visibleSteps]);

  // Handlers.
  const handleAddressSubmit = useCallback(() => {
    const missing = [];
    if (!addr.first_name.trim()) missing.push('First name');
    if (!addr.last_name.trim()) missing.push('Last name');
    if (!addr.email.trim()) missing.push('Email');
    if (!addr.address_1.trim()) missing.push('Address');
    if (!addr.city.trim()) missing.push('City');
    if (!addr.postcode.trim()) missing.push('ZIP code');
    if (missing.length) { setError(missing.join(', ') + ' required.'); return; }

    setBusy(true);
    setError(null);
    setCheckoutAddress(addr)
      .then(resp => {
        if (resp.success) {
          setData(prev => ({ ...prev, shipping_methods: resp.shipping_methods, totals: resp.totals }));
          if (resp.shipping_methods?.length) setSelectedShipping(resp.shipping_methods[0].id);
          nextStep();
        } else {
          setError(resp.message || 'Failed to save address.');
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setBusy(false));
  }, [addr, nextStep]);

  const handleShippingSubmit = useCallback(() => {
    if (!selectedShipping) { setError('Select a shipping method.'); return; }
    setBusy(true);
    setError(null);
    setCheckoutShipping(selectedShipping)
      .then(resp => {
        if (resp.success) {
          setData(prev => ({ ...prev, totals: resp.totals }));
          nextStep();
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setBusy(false));
  }, [selectedShipping, nextStep]);

  const handlePlaceOrder = useCallback(() => {
    setBusy(true);
    setError(null);
    placeOrder(selectedPayment)
      .then(resp => {
        if (resp.success) {
          setOrderResult(resp);
          setStep('confirm');
          setMaxStep(STEPS.indexOf('confirm'));
        } else {
          setError(resp.message || 'Order failed.');
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setBusy(false));
  }, [selectedPayment]);

  const cur = data?.currency_symbol || '$';

  if (loading) return <div className="sym-checkout-loading"><div className="sym-ai-thinking-dots"><span/><span/><span/></div></div>;
  if (!data?.items?.length) return <div className="sym-checkout-empty"><p>Your cart is empty.</p>{onDone && <button className="sym-btn sym-btn--primary" onClick={onDone}>Browse Products</button>}</div>;

  return (
    <div className="sym-checkout">
      {/* Step tabs — clickable for completed steps */}
      <div className="sym-co-steps">
        {visibleSteps.map((s, i) => {
          const sIdx = STEPS.indexOf(s);
          const isCurrent = s === step;
          const isDone = sIdx < currentIdx;
          const isClickable = sIdx <= maxStep && s !== 'confirm';
          return (
            <button key={s} type="button"
              className={`sym-co-step ${isCurrent ? 'active' : ''} ${isDone ? 'done' : ''} ${isClickable ? 'clickable' : ''}`}
              onClick={() => isClickable && goToStep(s)}
              disabled={!isClickable}>
              <span className="sym-co-step-num">{isDone ? '✓' : i + 1}</span>
              <span className="sym-co-step-label">{STEP_LABELS[s]}</span>
            </button>
          );
        })}
      </div>

      {error && <div className="sym-co-error">{error}</div>}

      {/* REVIEW */}
      {step === 'review' && (
        <div className="sym-co-panel">
          <h3>Review Your Order</h3>
          <div className="sym-co-items">
            {data.items.map(it => (
              <div key={it.key} className="sym-co-item">
                <img src={it.image_url} alt={it.name} />
                <div className="sym-co-item-info">
                  <span className="sym-co-item-name">{it.name}</span>
                  {it.options?.length > 0 && <span className="sym-co-item-opts">{it.options.join(' · ')}</span>}
                </div>
                <span className="sym-co-item-qty">×{it.quantity}</span>
                <span className="sym-co-item-price">{cur}{it.line_total}</span>
              </div>
            ))}
          </div>
          <div className="sym-co-subtotal">
            <span>Subtotal ({data.item_count} items)</span>
            <strong>{cur}{data.totals?.subtotal}</strong>
          </div>
          <div className="sym-co-nav">
            <span />
            <button className="sym-btn sym-btn--primary sym-co-next" onClick={nextStep}>Continue to Address →</button>
          </div>
        </div>
      )}

      {/* ADDRESS */}
      {step === 'address' && (
        <div className="sym-co-panel">
          <h3>Shipping Address</h3>
          <div className="sym-co-form">
            <div className="sym-co-row">
              <Field label="First Name *" value={addr.first_name} onChange={v => setAddr(p => ({...p, first_name: v}))} />
              <Field label="Last Name *" value={addr.last_name} onChange={v => setAddr(p => ({...p, last_name: v}))} />
            </div>
            <div className="sym-co-row">
              <Field label="Email *" type="email" value={addr.email} onChange={v => setAddr(p => ({...p, email: v}))} full />
            </div>
            <div className="sym-co-row">
              <Field label="Phone" value={addr.phone} onChange={v => setAddr(p => ({...p, phone: v}))} full />
            </div>
            <div className="sym-co-row">
              <Field label="Address *" value={addr.address_1} onChange={v => setAddr(p => ({...p, address_1: v}))} full />
            </div>
            <div className="sym-co-row">
              <Field label="City *" value={addr.city} onChange={v => setAddr(p => ({...p, city: v}))} />
              <Field label="State" value={addr.state} onChange={v => setAddr(p => ({...p, state: v}))} />
            </div>
            <div className="sym-co-row">
              <Field label="ZIP *" value={addr.postcode} onChange={v => setAddr(p => ({...p, postcode: v}))} />
              <div className="sym-co-field">
                <label>Country *</label>
                <select value={addr.country} onChange={e => setAddr(p => ({...p, country: e.target.value}))}>
                  <option value="US">United States</option><option value="CA">Canada</option>
                  <option value="GB">United Kingdom</option><option value="DE">Germany</option>
                  <option value="FR">France</option><option value="AU">Australia</option>
                  <option value="AM">Armenia</option>
                </select>
              </div>
            </div>
          </div>
          <div className="sym-co-nav">
            <button className="sym-btn sym-btn--ghost" onClick={prevStep}>← Back</button>
            <button className="sym-btn sym-btn--primary sym-co-next" onClick={handleAddressSubmit} disabled={busy}>
              {busy ? 'Saving...' : 'Continue →'}
            </button>
          </div>
        </div>
      )}

      {/* SHIPPING */}
      {step === 'shipping' && (
        <div className="sym-co-panel">
          <h3>Shipping Method</h3>
          {data.shipping_methods?.length > 0 ? (
            <div className="sym-co-shipping-list">
              {data.shipping_methods.map(m => (
                <label key={m.id} className={`sym-co-shipping-opt ${selectedShipping === m.id ? 'active' : ''}`}
                  onClick={() => setSelectedShipping(m.id)}>
                  <input type="radio" name="ship" value={m.id} checked={selectedShipping === m.id} readOnly />
                  <span className="sym-co-shipping-label">{m.label}</span>
                  <span className="sym-co-shipping-cost">{parseFloat(m.cost) > 0 ? cur + m.cost : 'FREE'}</span>
                </label>
              ))}
            </div>
          ) : (
            <p>No shipping methods available. Check your address.</p>
          )}
          <div className="sym-co-nav">
            <button className="sym-btn sym-btn--ghost" onClick={prevStep}>← Back</button>
            <button className="sym-btn sym-btn--primary sym-co-next" onClick={handleShippingSubmit} disabled={busy || !selectedShipping}>
              {busy ? 'Processing...' : 'Continue →'}
            </button>
          </div>
        </div>
      )}

      {/* PAYMENT */}
      {step === 'payment' && (
        <div className="sym-co-panel">
          <h3>Payment</h3>
          <div className="sym-co-summary">
            <div className="sym-co-summary-row"><span>Subtotal</span><span>{cur}{data.totals?.subtotal}</span></div>
            {data.needs_shipping && <div className="sym-co-summary-row"><span>Shipping</span><span>{cur}{data.totals?.shipping}</span></div>}
            {parseFloat(data.totals?.tax||0) > 0 && <div className="sym-co-summary-row"><span>Tax</span><span>{cur}{data.totals?.tax}</span></div>}
            <div className="sym-co-summary-row sym-co-summary-total"><span>Total</span><strong>{cur}{data.totals?.total}</strong></div>
          </div>

          {data.payment_gateways?.length > 0 && (
            <div className="sym-co-payment-list">
              <span className="sym-co-label-sm">Payment Method</span>
              {data.payment_gateways.map(gw => (
                <label key={gw.id} className={`sym-co-payment-opt ${selectedPayment === gw.id ? 'active' : ''}`}
                  onClick={() => setSelectedPayment(gw.id)}>
                  <input type="radio" name="pay" value={gw.id} checked={selectedPayment === gw.id} readOnly />
                  <div>
                    <span className="sym-co-payment-title">{gw.title}</span>
                    {gw.description && <span className="sym-co-payment-desc">{gw.description}</span>}
                  </div>
                </label>
              ))}
            </div>
          )}

          <div className="sym-co-nav">
            <button className="sym-btn sym-btn--ghost" onClick={prevStep}>← Back</button>
            <button className="sym-btn sym-btn--primary sym-co-next sym-co-place" onClick={handlePlaceOrder} disabled={busy}>
              {busy ? 'Placing Order...' : `Place Order — ${cur}${data.totals?.total}`}
            </button>
          </div>
        </div>
      )}

      {/* CONFIRM */}
      {step === 'confirm' && orderResult && (
        <div className="sym-co-panel sym-co-panel--confirm">
          <div className="sym-co-confirm-icon">✓</div>
          <h3>Order Placed!</h3>
          <div className="sym-co-confirm-details">
            <p>Order <strong>#{orderResult.order_id}</strong></p>
            <p>Total: <strong>{cur}{orderResult.total}</strong></p>
            <p>Payment: {orderResult.payment}</p>
          </div>
          {onDone && <button className="sym-btn sym-btn--primary" onClick={onDone}>Continue Shopping</button>}
        </div>
      )}
    </div>
  );
}

function Field({ label, value, onChange, type = 'text', full }) {
  return (
    <div className={`sym-co-field ${full ? 'sym-co-field--full' : ''}`}>
      <label>{label}</label>
      <input type={type} value={value} onChange={e => onChange(e.target.value)} />
    </div>
  );
}
