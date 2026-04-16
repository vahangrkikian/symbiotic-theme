import React from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon, { AiAvatar } from '../shared/Icon.jsx';
import { t } from '../../utils/i18n.js';

// Category images (Unsplash — free to use)
const catImages = {
  'business-cards': 'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=400&h=250&fit=crop',
  'signage-banners': 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=400&h=250&fit=crop',
  'marketing-materials': 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400&h=250&fit=crop',
  'labels-stickers-packaging': 'https://images.unsplash.com/photo-1589384267710-7a25bc5b4862?w=400&h=250&fit=crop',
  'print-advertising-office': 'https://images.unsplash.com/photo-1568702846914-96b305d2aaeb?w=400&h=250&fit=crop',
};

// Gradient fallbacks per category
const catGradients = [
  'linear-gradient(135deg, #1a1520 0%, #2d1f3d 100%)',
  'linear-gradient(135deg, #1a1a15 0%, #2d2a1f 100%)',
  'linear-gradient(135deg, #151a1a 0%, #1f2d2a 100%)',
  'linear-gradient(135deg, #1a1517 0%, #2d1f24 100%)',
  'linear-gradient(135deg, #15171a 0%, #1f242d 100%)',
  'linear-gradient(135deg, #1a1815 0%, #2d271f 100%)',
];

export default function WelcomeView() {
  const { state, sendMessage, navigate } = useWorkspace();
  const data = window.symbioticData || {};
  const greeting = data.themeOptions?.welcomeOverride || data.settings?.greeting || t('welcome.greeting');

  const quickActions = [
    { text: t('welcome.sale'),     icon: 'tag' },
    { text: t('welcome.popular'),  icon: 'star' },
    { text: t('welcome.gift'),     icon: 'gift' },
    { text: t('welcome.track'),    icon: 'package' },
    { text: t('welcome.shipping'), icon: 'truck' },
    { text: t('welcome.compare'),  icon: 'compare' },
  ];

  return (
    <div className="sym-view sym-view--welcome">

      {/* ── Hero with background image ── */}
      <div className="sym-welcome-hero">
        <div className="sym-hero-bg">
          <img
            src="https://images.unsplash.com/photo-1588681664899-f142ff2dc9b1?w=1200&h=400&fit=crop&q=80"
            alt="" className="sym-hero-bg-img" loading="eager"
          />
        </div>
        <div className="sym-hero-content">
          <div className="sym-hero-badge">
            <span className="sym-hero-badge-dot" />
            AI Print Advisor Online
          </div>
          <div className="sym-welcome-avatar">
            <AiAvatar size={56} />
          </div>
          <h1 className="sym-welcome-title">{greeting}</h1>
          <p className="sym-welcome-sub">{t('welcome.sub')}</p>
        </div>
      </div>

      {/* ── Quick Actions ── */}
      <div className="sym-welcome-actions">
        {quickActions.map(q => (
          <button key={q.text} className="sym-welcome-action" onClick={() => sendMessage(q.text)}>
            <span className="sym-welcome-action-icon">
              <Icon name={q.icon} size={18} />
            </span>
            <span>{q.text}</span>
          </button>
        ))}
      </div>

      {/* ── Trust Badges ── */}
      <div className="sym-trust-row">
        {[
          { icon: 'truck',  text: 'Free shipping over $75' },
          { icon: 'check',  text: 'Satisfaction guaranteed' },
          { icon: 'package',text: 'Rush next-day available' },
          { icon: 'globe',  text: 'Ships to 50+ countries' },
        ].map(b => (
          <div key={b.text} className="sym-trust-badge">
            <Icon name={b.icon} size={16} />
            <span>{b.text}</span>
          </div>
        ))}
      </div>

      {/* ── Featured Categories with Images ── */}
      {state.categories.length > 0 ? (
        <div className="sym-welcome-section">
          <div className="sym-section-header">
            <h2 className="sym-welcome-section-title">{t('welcome.categories')}</h2>
            <button className="sym-section-link" onClick={() => sendMessage('Show me all categories')}>View all →</button>
          </div>
          <div className="sym-cat-cards">
            {state.categories.slice(0, 6).map((cat, i) => (
              <button key={cat.slug} className="sym-cat-card" onClick={() => sendMessage(`Show me ${cat.name} products`)}>
                <div className="sym-cat-card-img" style={{
                  backgroundImage: catImages[cat.slug]
                    ? `url(${catImages[cat.slug]})`
                    : catGradients[i % catGradients.length]
                }}>
                  <div className="sym-cat-card-overlay" />
                </div>
                <div className="sym-cat-card-body">
                  <span className="sym-cat-card-name">{cat.name}</span>
                  <span className="sym-cat-card-count">{cat.count} products</span>
                </div>
              </button>
            ))}
          </div>
        </div>
      ) : (
        <div className="sym-welcome-section">
          <h2 className="sym-welcome-section-title">{t('welcome.categories')}</h2>
          <div className="sym-skeleton-group">
            {[1,2,3,4].map(i => <div key={i} className="sym-skeleton sym-skeleton--row" />)}
          </div>
        </div>
      )}

      {/* ── Featured Banner ── */}
      <div className="sym-promo-banner">
        <img
          src="https://images.unsplash.com/photo-1562654501-a0ccc0fc3fb1?w=800&h=300&fit=crop&q=80"
          alt="Premium printing" className="sym-promo-banner-img" loading="lazy"
        />
        <div className="sym-promo-banner-content">
          <h3>Premium Business Cards</h3>
          <p>Starting at $19.99 — 30+ paper stocks, 10+ finishes</p>
          <button className="sym-promo-btn" onClick={() => sendMessage('Show me premium business cards')}>
            Explore →
          </button>
        </div>
      </div>

      {/* ── Brands ── */}
      {state.brands.length > 0 && (
        <div className="sym-welcome-section">
          <h2 className="sym-welcome-section-title">{t('welcome.brands')}</h2>
          <div className="sym-welcome-brands">
            {state.brands.map(b => (
              <button key={b.slug} className="sym-welcome-brand" onClick={() => sendMessage(`Show me ${b.name} products`)}>
                {b.name}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* ── Testimonials with photos ── */}
      <div className="sym-welcome-section">
        <h2 className="sym-welcome-section-title">What Customers Say</h2>
        <div className="sym-testimonials">
          {[
            { name: 'Sarah M.', role: 'Marketing Director', text: 'The AI advisor helped me choose the perfect paper stock for our rebrand. Saved hours of research.', img: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop&crop=face' },
            { name: 'James K.', role: 'Startup Founder', text: 'Ordered business cards, letterhead, and envelopes in one conversation. The AI suggested a complete package.', img: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&crop=face' },
            { name: 'Lisa R.', role: 'Event Planner', text: 'Rush delivery on trade show banners came through perfectly. The file prep guidance was invaluable.', img: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&h=80&fit=crop&crop=face' },
          ].map(person => (
            <div key={person.name} className="sym-testimonial-card">
              <div className="sym-testimonial-stars">★★★★★</div>
              <p className="sym-testimonial-text">"{person.text}"</p>
              <div className="sym-testimonial-author">
                <img src={person.img} alt={person.name} className="sym-testimonial-photo" loading="lazy" />
                <div>
                  <strong>{person.name}</strong>
                  <span>{person.role}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* ── Newsletter CTA ── */}
      <div className="sym-newsletter">
        <div className="sym-newsletter-inner">
          <Icon name="star" size={24} />
          <div className="sym-newsletter-text">
            <strong>Stay Updated</strong>
            <p>Get printing tips, design guides, and exclusive offers delivered to your inbox.</p>
          </div>
          <button className="sym-btn sym-btn--primary sym-newsletter-btn" onClick={() => sendMessage('I want to subscribe to your newsletter')}>
            Subscribe
          </button>
        </div>
      </div>

      {/* ── Resources ── */}
      <div className="sym-welcome-section">
        <h2 className="sym-welcome-section-title">Resources</h2>
        <div className="sym-resource-grid">
          {[
            { slug: 'file-preparation-guide', icon: 'document', title: 'File Prep Guide', desc: 'Get your files print-ready' },
            { slug: 'faq', icon: 'info', title: 'FAQ', desc: 'Quick answers to common questions' },
            { slug: 'shipping-delivery', icon: 'truck', title: 'Shipping Info', desc: 'Delivery times and methods' },
            { slug: 'about', icon: 'brain', title: 'About PrintPro', desc: 'Our story and values' },
          ].map(r => (
            <button key={r.slug} className="sym-resource-card" onClick={() => navigate('page', { slug: r.slug })}>
              <div className="sym-resource-icon"><Icon name={r.icon} size={20} /></div>
              <div>
                <strong>{r.title}</strong>
                <span>{r.desc}</span>
              </div>
            </button>
          ))}
        </div>
      </div>

    </div>
  );
}
