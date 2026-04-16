import React, { useState } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon, { AiAvatar } from '../shared/Icon.jsx';

export default function SiteHeader() {
  const { state, navigate } = useWorkspace();
  const [mobileOpen, setMobileOpen] = useState(false);
  const data = window.symbioticData || {};

  const navItems = [
    { view: 'home',     label: 'Home' },
    { view: 'products', label: 'Products' },
    { view: 'page',     label: 'About',   params: { slug: 'about' } },
    { view: 'blog',     label: 'Blog' },
    { view: 'page',     label: 'FAQ',     params: { slug: 'faq' } },
    { view: 'page',     label: 'Contact', params: { slug: 'contact' } },
  ];

  const handleNav = (item) => {
    navigate(item.view, item.params || {});
    setMobileOpen(false);
  };

  const isActive = (item) => {
    if (item.view === 'home' && state.activeView === 'home') return true;
    if (item.view === 'products' && state.activeView === 'products') return true;
    if (item.view === 'blog' && state.activeView === 'blog') return true;
    if (item.view === 'page' && state.activeView === 'page' && state.viewParams?.slug === item.params?.slug) return true;
    return false;
  };

  return (
    <header className="sym-header">
      <div className="sym-header-inner">
        {/* Logo */}
        <button className="sym-header-logo" onClick={() => navigate('home')}>
          <AiAvatar size={28} />
          <span>{data.storeName || 'PrintPro'}</span>
        </button>

        {/* Desktop nav */}
        <nav className="sym-header-nav" aria-label="Main navigation">
          {navItems.map(item => (
            <button
              key={item.label}
              className={`sym-header-link ${isActive(item) ? 'sym-header-link--active' : ''}`}
              onClick={() => handleNav(item)}
            >
              {item.label}
            </button>
          ))}
        </nav>

        {/* Right actions */}
        <div className="sym-header-actions">
          <button className="sym-header-cart" onClick={() => navigate('cart')} aria-label="Cart">
            <Icon name="cart" size={20} />
            {state.cartCount > 0 && <span className="sym-header-cart-badge">{state.cartCount}</span>}
          </button>
          <button className="sym-header-hamburger" onClick={() => setMobileOpen(!mobileOpen)} aria-label="Menu">
            <Icon name={mobileOpen ? 'close' : 'more'} size={22} />
          </button>
        </div>
      </div>

      {/* Mobile nav overlay */}
      {mobileOpen && (
        <div className="sym-mobile-nav">
          {navItems.map(item => (
            <button
              key={item.label}
              className={`sym-mobile-nav-item ${isActive(item) ? 'sym-mobile-nav-item--active' : ''}`}
              onClick={() => handleNav(item)}
            >
              {item.label}
            </button>
          ))}
          <button className="sym-mobile-nav-item" onClick={() => { navigate('cart'); setMobileOpen(false); }}>
            Cart {state.cartCount > 0 && `(${state.cartCount})`}
          </button>
        </div>
      )}
    </header>
  );
}
