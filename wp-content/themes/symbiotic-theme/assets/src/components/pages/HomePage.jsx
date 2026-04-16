import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon, { AiAvatar } from '../shared/Icon.jsx';
import { decodeHtml } from '../../utils/decode.js';
import { fetchCalculatorConfig } from '../../utils/api.js';

export default function HomePage({ showProductsOnly = false }) {
  const { state, sendMessage, navigate } = useWorkspace();
  const data = window.symbioticData || {};
  const themeUrl = data.themeUrl || '';
  const [products, setProducts] = useState([]);
  const [productsLoading, setProductsLoading] = useState(true);

  // Fetch products on mount.
  useEffect(() => {
    const baseUrl = data.restUrl || '/wp-json/';
    fetch(baseUrl + 'symbiotic/v1/products', { headers: { 'X-WP-Nonce': data.nonce || '' } })
      .then(r => r.json())
      .then(prods => { if (Array.isArray(prods)) setProducts(prods); })
      .catch(() => {})
      .finally(() => setProductsLoading(false));
  }, []);

  const activeCategory = state.viewParams?.category || '';
  const activeCatObj = activeCategory ? state.categories.find(c => c.slug === activeCategory) : null;

  // Fetch products (optionally filtered by category).
  const [catProducts, setCatProducts] = useState([]);
  const [catLoading, setCatLoading] = useState(false);

  useEffect(() => {
    if (!showProductsOnly || !activeCategory) return;
    setCatLoading(true);
    setCatProducts([]);
    const baseUrl = data.restUrl || '/wp-json/';
    fetch(baseUrl + 'symbiotic/v1/products?category=' + encodeURIComponent(activeCategory), { headers: { 'X-WP-Nonce': data.nonce || '' } })
      .then(r => r.json())
      .then(prods => { if (Array.isArray(prods)) setCatProducts(prods); })
      .catch(() => {})
      .finally(() => setCatLoading(false));
  }, [showProductsOnly, activeCategory]);

  if (showProductsOnly) {
    // If a category is selected, show its products
    if (activeCategory) {
      return (
        <div className="sym-page">
          <section className="sym-section">
            <div className="sym-container">
              <div className="sym-section-head">
                <div>
                  <button className="sym-link-btn sym-link-btn--back" onClick={() => navigate('products')}>← All Categories</button>
                  <h2 className="sym-section-title">{activeCatObj ? decodeHtml(activeCatObj.name) : activeCategory}</h2>
                  <p className="sym-section-sub">{activeCatObj ? `${activeCatObj.count} products` : 'Browse products in this category'}</p>
                </div>
              </div>
              {catLoading ? (
                <div className="sym-loading-bar"><div className="sym-loading-bar-fill" /></div>
              ) : catProducts.length > 0 ? (
                <div className="sym-product-grid">
                  {catProducts.map(p => (
                    <button key={p.id} className="sym-shop-card" onClick={() => navigate('product', { productId: p.id })}>
                      <div className="sym-shop-card-img">
                        <img src={p.image_url} alt={p.name} loading="lazy" />
                        {p.has_calculator && <span className="sym-shop-card-badge">Configurable</span>}
                      </div>
                      <div className="sym-shop-card-body">
                        <h3 className="sym-shop-card-name">{p.name}</h3>
                        {p.short_description && <p className="sym-shop-card-desc">{p.short_description}</p>}
                        <span className="sym-shop-card-cta">{p.has_calculator ? 'Configure & Price →' : 'View Details →'}</span>
                      </div>
                    </button>
                  ))}
                </div>
              ) : (
                <p className="sym-empty-msg">No products found in this category.</p>
              )}
            </div>
          </section>
        </div>
      );
    }

    // No category selected — show category grid
    return (
      <div className="sym-page">
        <section className="sym-section">
          <div className="sym-container">
            <h2 className="sym-section-title">Products</h2>
            <p className="sym-section-sub">Browse our catalog or ask the AI assistant below for recommendations.</p>
            {state.categories.length > 0 && (
              <div className="sym-cat-grid">
                {state.categories.map((cat, i) => (
                  <button key={cat.slug} className="sym-cat-tile" onClick={() => navigate('products', { category: cat.slug })}>
                    <div className="sym-cat-tile-bg" style={{ background: catGradients[i % catGradients.length] }} />
                    <div className="sym-cat-tile-content">
                      <h3>{decodeHtml(cat.name)}</h3>
                      <span>{cat.count} products</span>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        </section>
      </div>
    );
  }

  return (
    <div className="sym-page">

      {/* ── Hero ── */}
      <section className="sym-hero">
        <div className="sym-hero-bg-layer">
          <div className="sym-hero-gradient" />
        </div>
        <div className="sym-hero-product-showcase">
          <img src={`${themeUrl}/assets/images/hero-business-cards.png`} alt="Premium Business Cards" className="sym-hero-product-img" />
        </div>
        <div className="sym-container sym-hero-content">
          <img src={`${themeUrl}/assets/images/tgm-logo-main.png`} alt="TGM Print" className="sym-hero-logo" />
          <div className="sym-hero-badge-pill">
            <span className="sym-dot sym-dot--live" /> AI Print Advisor Online
          </div>
          <h1 className="sym-hero-h1" dangerouslySetInnerHTML={{ __html: data.themeOptions?.heroTitle || 'Printing Solutions,<br/>Powered by AI' }} />
          <p className="sym-hero-p">{data.themeOptions?.heroSubtitle || 'Business cards, signage, marketing materials, and more. Our AI advisor helps you choose the perfect product, paper, and finish.'}</p>
          <div className="sym-hero-actions">
            <button className="sym-btn sym-btn--primary sym-btn--lg" onClick={() => {
              const el = document.getElementById('sym-products-section');
              if (el) el.scrollIntoView({ behavior: 'smooth' });
              else navigate('products');
            }}>
              Explore Products
            </button>
            <button className="sym-btn sym-btn--ghost sym-btn--lg" onClick={() => navigate('page', { slug: 'about' })}>
              Learn More
            </button>
          </div>
        </div>
      </section>

      {/* ── Trust row ── */}
      <section className="sym-trust-strip">
        <div className="sym-container sym-trust-inner">
          {[
            { icon: 'truck',   text: 'Free shipping over $75' },
            { icon: 'check',   text: 'Satisfaction guaranteed' },
            { icon: 'package', text: 'Rush next-day available' },
            { icon: 'globe',   text: '50+ countries worldwide' },
          ].map(b => (
            <div key={b.text} className="sym-trust-item">
              <Icon name={b.icon} size={18} />
              <span>{b.text}</span>
            </div>
          ))}
        </div>
      </section>

      {/* ── Categories ── */}
      {state.categories.length > 0 && (
        <section className="sym-section">
          <div className="sym-container">
            <div className="sym-section-head">
              <div>
                <h2 className="sym-section-title">Shop by Category</h2>
                <p className="sym-section-sub">Browse our complete printing catalog</p>
              </div>
              <button className="sym-link-btn" onClick={() => navigate('products')}>View all →</button>
            </div>
            <CategorySlider categories={state.categories.slice(0, 10)} themeUrl={themeUrl} navigate={navigate} />
          </div>
        </section>
      )}

      {/* ── Products Grid ── */}
      {products.length > 0 && (
        <section className="sym-section" id="sym-products-section">
          <div className="sym-container">
            <div className="sym-section-head">
              <div>
                <h2 className="sym-section-title">Our Products</h2>
                <p className="sym-section-sub">Click any product to configure and see pricing</p>
              </div>
            </div>
            <div className="sym-product-grid">
              {products.map(p => (
                <button key={p.id} className="sym-shop-card" onClick={() => navigate('product', { productId: p.id })}>
                  <div className="sym-shop-card-img">
                    <img src={p.image_url} alt={p.name} loading="lazy" />
                    {p.has_calculator && <span className="sym-shop-card-badge">Configurable</span>}
                  </div>
                  <div className="sym-shop-card-body">
                    <h3 className="sym-shop-card-name">{p.name}</h3>
                    {p.short_description && <p className="sym-shop-card-desc">{p.short_description}</p>}
                    <span className="sym-shop-card-cta">{p.has_calculator ? 'Configure & Price →' : 'View Details →'}</span>
                  </div>
                </button>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ── Quick AI prompts ── */}
      <section className="sym-section">
        <div className="sym-container">
          <h2 className="sym-section-title">Quick Actions</h2>
          <p className="sym-section-sub">Browse products or ask our AI advisor below</p>
          <div className="sym-prompt-grid">
            {[
              { icon: 'tag',     text: "What's on sale?",    action: () => navigate('products') },
              { icon: 'star',    text: 'All Products',       action: () => navigate('products') },
              { icon: 'gift',    text: 'Business Cards',     action: () => { const bc = products.find(p => p.name.toLowerCase().includes('business card')); if (bc) navigate('product', { productId: bc.id }); else navigate('products'); } },
              { icon: 'compare', text: 'Signs & Banners',    action: () => { const bn = products.find(p => p.name.toLowerCase().includes('banner') || p.name.toLowerCase().includes('sign')); if (bn) navigate('product', { productId: bn.id }); else navigate('products'); } },
              { icon: 'truck',   text: 'Shipping info',      action: () => navigate('page', { slug: 'shipping-delivery' }) },
              { icon: 'document',text: 'File prep guide',    action: () => navigate('page', { slug: 'file-preparation-guide' }) },
            ].map(q => (
              <button key={q.text} className="sym-prompt-card" onClick={q.action}>
                <Icon name={q.icon} size={20} />
                <span>{q.text}</span>
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* ── Testimonials ── */}
      <section className="sym-section sym-section--muted">
        <div className="sym-container">
          <h2 className="sym-section-title">What Customers Say</h2>
          <div className="sym-testimonial-grid">
            {[
              { name: 'Sarah M.', role: 'Marketing Director', text: 'The AI advisor helped me choose the perfect paper stock for our rebrand. Saved hours of research.', initial: 'S', color: '#9d33d6' },
              { name: 'James K.', role: 'Startup Founder', text: 'Ordered business cards, letterhead, and envelopes in one conversation. The AI suggested a complete package.', initial: 'J', color: '#3ac8d8' },
              { name: 'Lisa R.', role: 'Event Planner', text: 'Rush delivery on trade show banners came through perfectly. The file prep guidance was invaluable.', initial: 'L', color: '#11b196' },
            ].map(t => (
              <div key={t.name} className="sym-tcard">
                <div className="sym-tcard-stars">★★★★★</div>
                <p className="sym-tcard-text">"{t.text}"</p>
                <div className="sym-tcard-author">
                  <div className="sym-tcard-avatar" style={{ background: t.color }}>{t.initial}</div>
                  <div>
                    <strong>{t.name}</strong>
                    <span>{t.role}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Resources ── */}
      <section className="sym-section">
        <div className="sym-container">
          <h2 className="sym-section-title">Resources</h2>
          <div className="sym-resource-row">
            {[
              { slug: 'file-preparation-guide', icon: 'document', title: 'File Prep Guide' },
              { slug: 'faq',                    icon: 'info',     title: 'FAQ' },
              { slug: 'shipping-delivery',      icon: 'truck',    title: 'Shipping' },
              { slug: 'wholesale',              icon: 'package',  title: 'Wholesale' },
            ].map(r => (
              <button key={r.slug} className="sym-resource-tile" onClick={() => navigate('page', { slug: r.slug })}>
                <Icon name={r.icon} size={22} />
                <span>{r.title}</span>
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* ── Powered by ── */}
      <div className="sym-powered">
        <p>Powered by Symbiotic Theme</p>
      </div>
    </div>
  );
}

/* ── Category Slider ── */
function CategorySlider({ categories, themeUrl, navigate }) {
  const trackRef = useRef(null);
  const [canPrev, setCanPrev] = useState(false);
  const [canNext, setCanNext] = useState(false);

  const updateArrows = useCallback(() => {
    const el = trackRef.current;
    if (!el) return;
    setCanPrev(el.scrollLeft > 2);
    setCanNext(el.scrollLeft + el.clientWidth < el.scrollWidth - 2);
  }, []);

  useEffect(() => {
    const el = trackRef.current;
    if (!el) return;
    updateArrows();
    el.addEventListener('scroll', updateArrows, { passive: true });
    const ro = new ResizeObserver(updateArrows);
    ro.observe(el);
    return () => { el.removeEventListener('scroll', updateArrows); ro.disconnect(); };
  }, [updateArrows, categories]);

  const slide = (dir) => {
    const el = trackRef.current;
    if (!el) return;
    const card = el.querySelector('.sym-cat-tile');
    const step = card ? (card.offsetWidth + 16) * 2 : 400;
    el.scrollBy({ left: dir * step, behavior: 'smooth' });
  };

  return (
    <div className="sym-cat-slider">
      {canPrev && (
        <button className="sym-cat-slider-arrow sym-cat-slider-arrow--prev" onClick={() => slide(-1)} aria-label="Previous">
          <Icon name="chevronLeft" size={18} />
        </button>
      )}
      <div className="sym-cat-slider-track" ref={trackRef}>
        {categories.map((cat, i) => {
          const catImg = getCatImage(cat.slug, themeUrl);
          return (
            <button key={cat.slug} className="sym-cat-tile" onClick={() => navigate('products', { category: cat.slug })}>
              <div className="sym-cat-tile-bg" style={{ background: catGradients[i % catGradients.length] }}>
                {catImg && <img src={catImg} alt="" className="sym-cat-tile-photo" />}
              </div>
              <div className="sym-cat-tile-content">
                <h3>{decodeHtml(cat.name)}</h3>
                <span>{cat.count} products</span>
              </div>
            </button>
          );
        })}
      </div>
      {canNext && (
        <button className="sym-cat-slider-arrow sym-cat-slider-arrow--next" onClick={() => slide(1)} aria-label="Next">
          <Icon name="chevronRight" size={18} />
        </button>
      )}
    </div>
  );
}

// Category images use theme assets
const getCatImage = (slug, themeUrl) => {
  const map = {
    'business-cards': `${themeUrl}/assets/images/hero-business-cards.png`,
    'signs-banners-posters': `${themeUrl}/assets/images/banner-signage.webp`,
    'signage-banners': `${themeUrl}/assets/images/banner-signage.webp`,
    'labels-stickers-packaging': `${themeUrl}/assets/images/banner-decals.jpg`,
    'print-advertising-office': `${themeUrl}/assets/images/banner-bookmark.jpg`,
    'marketing-materials': `${themeUrl}/assets/images/banner-products.jpg`,
  };
  return map[slug] || null;
};
const catImages = {}; // Kept for backward compat

const catGradients = [
  'linear-gradient(135deg, #ede8f5 0%, #d9d0ea 100%)',
  'linear-gradient(135deg, #eaeae0 0%, #d8d8c8 100%)',
  'linear-gradient(135deg, #e0eaea 0%, #c8d8d8 100%)',
  'linear-gradient(135deg, #f0e8ea 0%, #e0d0d5 100%)',
  'linear-gradient(135deg, #e0e5ed 0%, #c8d0db 100%)',
  'linear-gradient(135deg, #edeae5 0%, #dbd5c8 100%)',
];
