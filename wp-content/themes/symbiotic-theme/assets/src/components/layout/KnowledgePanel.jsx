import React from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon, { AiAvatar } from '../shared/Icon.jsx';
import { decodeHtml } from '../../utils/decode.js';

const categoryIconMap = {
  'business-cards':           'card',
  'banners-flags':            'banner',
  'labels-stickers-packaging':'sticker',
  'stickers':                 'sticker',
  'signs-banners-posters':    'sign',
  'flyers-brochures':         'flyer',
  'marketing-materials':      'flyer',
  'print-advertising-office': 'flyer',
  'display-signs':            'sign',
  'packaging':                'box',
  'shipping-boxes-mailers':   'box',
  'food-packaging':           'box',
  'product-boxes-pouches':    'box',
  'postcards':                'envelope',
  'envelopes-mailing':        'envelope',
  'stamps-ink':               'stamp',
  'magnets':                  'magnet',
  'car-decals-magnets':       'car',
  'car-signs':                'car',
  'car-stickers':             'car',
  'decals':                   'sticker',
  'flags':                    'flag',
  'posters':                  'poster',
  'posters-rigid-signs':      'poster',
  'rigid-signs-boards':       'sign',
  'folders-booklets':         'folder',
  'table-covers':             'table',
  'tabletop':                 'table',
  'displays-tents':           'table',
  'shopping-gift-bags':       'bag',
  'business-stationery':      'card',
  'office-supplies':          'document',
  'rack-cards':               'card',
  'tickets-vouchers':         'tag',
  'checks':                   'document',
  'bestsellers':              'star',
  'looking-for-more':         'layers',
};

function getCategoryIcon(slug) {
  return categoryIconMap[slug] || 'tag';
}

export default function KnowledgePanel() {
  const { state, dispatch, navigate, sendMessage } = useWorkspace();
  const data = window.symbioticData || {};
  const isOpen = state.leftOpen;

  return (
    <aside className={`sym-panel sym-panel--knowledge ${isOpen ? '' : 'sym-panel--collapsed'}`}>
      <button className="sym-panel-toggle" onClick={() => dispatch({ type: 'TOGGLE_LEFT' })} aria-label={isOpen ? 'Collapse' : 'Expand'}>
        <Icon name={isOpen ? 'chevronLeft' : 'chevronRight'} size={14} />
      </button>

      {/* Expanded */}
      <div className="sym-panel-body">
        {/* Brand */}
        <div className="sym-kp-brand">
          <img
            src={`${data.themeUrl || ''}/assets/images/tgm-logo-main.png`}
            alt={data.storeName || 'TGM Print'}
            className="sym-kp-brand-logo"
            onError={(e) => { e.target.style.display = 'none'; }}
          />
        </div>

        {/* Explore */}
        <div className="sym-kp-section">
          <span className="sym-kp-label">Explore</span>
          {[
            { view: 'home',     icon: 'home',     label: 'Home' },
            { view: 'products', icon: 'search',   label: 'Products' },
            { view: 'blog',     icon: 'document', label: 'Blog' },
          ].map(item => (
            <button
              key={item.view}
              className={`sym-kp-nav ${state.activeView === item.view ? 'sym-kp-nav--active' : ''}`}
              onClick={() => navigate(item.view)}
            >
              <Icon name={item.icon} size={16} />
              {item.label}
            </button>
          ))}
        </div>

        {/* Categories */}
        {state.categories.length > 0 && (
          <div className="sym-kp-section">
            <span className="sym-kp-label">Categories</span>
            {state.categories.slice(0, 8).map(cat => (
              <button key={cat.slug} className="sym-kp-nav" onClick={() => sendMessage(`Show me ${decodeHtml(cat.name)} products`)}>
                <Icon name={getCategoryIcon(cat.slug)} size={16} />
                {decodeHtml(cat.name)}
              </button>
            ))}
          </div>
        )}

        {/* Pages */}
        <div className="sym-kp-section">
          <span className="sym-kp-label">Pages</span>
          {[
            { slug: 'about',                  icon: 'brain',    label: 'About Us' },
            { slug: 'faq',                    icon: 'info',     label: 'FAQ' },
            { slug: 'file-preparation-guide', icon: 'document', label: 'File Prep Guide' },
            { slug: 'shipping-delivery',      icon: 'truck',    label: 'Shipping & Delivery' },
            { slug: 'wholesale',              icon: 'package',  label: 'Wholesale' },
            { slug: 'contact',                icon: 'user',     label: 'Contact' },
          ].map(p => (
            <button
              key={p.slug}
              className={`sym-kp-nav ${state.activeView === 'page' && state.viewParams?.slug === p.slug ? 'sym-kp-nav--active' : ''}`}
              onClick={() => navigate('page', { slug: p.slug })}
            >
              <Icon name={p.icon} size={16} />
              {p.label}
            </button>
          ))}
        </div>
      </div>

      {/* Collapsed strip */}
      <div className="sym-panel-strip">
        <div className="sym-strip-item" onClick={() => navigate('home')}><Icon name="home" size={18} /></div>
        <div className="sym-strip-item" onClick={() => navigate('products')}><Icon name="search" size={18} /></div>
        <div className="sym-strip-item" onClick={() => navigate('blog')}><Icon name="document" size={18} /></div>
        <div className="sym-strip-item" onClick={() => navigate('page', { slug: 'faq' })}><Icon name="info" size={18} /></div>
      </div>
    </aside>
  );
}
