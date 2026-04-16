import React, { useState, useEffect } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { getPage } from '../../utils/api.js';
import Icon, { AiAvatar } from '../shared/Icon.jsx';

const themeUrl = (window.symbioticData?.themeUrl || '');

const banners = {
  about: {
    icon: 'brain',
    title: 'Your AI-Powered Print Partner',
    sub: 'Professional printing meets intelligent technology.',
    image: themeUrl + '/assets/images/page-about.jpg',
  },
  contact: {
    icon: 'user',
    title: 'Get In Touch',
    sub: 'Our team and AI advisor are ready to help.',
    image: themeUrl + '/assets/images/page-contact.jpg',
  },
  faq: {
    icon: 'info',
    title: 'Frequently Asked Questions',
    sub: 'Quick answers to common questions. Or ask our AI for anything.',
    image: themeUrl + '/assets/images/page-faq.jpg',
  },
  'file-preparation-guide': {
    icon: 'document',
    title: 'File Preparation Guide',
    sub: 'Get your files print-ready with these specifications.',
    image: themeUrl + '/assets/images/page-fileprep.jpg',
  },
  'shipping-delivery': {
    icon: 'truck',
    title: 'Shipping & Delivery',
    sub: 'Production times, shipping methods, and tracking.',
    image: themeUrl + '/assets/images/page-shipping.jpg',
  },
  wholesale: {
    icon: 'package',
    title: 'Wholesale & Bulk Orders',
    sub: 'Volume pricing and dedicated account management.',
    image: themeUrl + '/assets/images/page-wholesale.jpg',
  },
};

export default function PageView() {
  const { state, navigate, sendMessage } = useWorkspace();
  const slug = state.viewParams?.slug || 'about';
  const [page, setPage] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    setPage(null);
    getPage(slug)
      .then(data => {
        if (data && data.length > 0) setPage(data[0]);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [slug]);

  const banner = banners[slug] || banners.about;

  if (loading) {
    return (
      <div className="sym-view sym-view--page">
        <div className="sym-page-banner sym-page-banner--with-image">
          <div className="sym-page-banner-overlay" />
          <div className="sym-page-banner-content">
            <div className="sym-skeleton" style={{ width: 200, height: 28, margin: '0 auto 8px' }} />
            <div className="sym-skeleton" style={{ width: 300, height: 16, margin: '0 auto' }} />
          </div>
        </div>
        <div className="sym-page-body-content">
          <div className="sym-skeleton sym-skeleton--row" />
          <div className="sym-skeleton sym-skeleton--row" />
          <div className="sym-skeleton sym-skeleton--row" style={{ width: '70%' }} />
        </div>
      </div>
    );
  }

  if (!page) {
    return (
      <div className="sym-view sym-view--page">
        <div className="sym-empty-state">
          <Icon name="alertCircle" size={40} />
          <p className="sym-empty-title">Page not found</p>
          <p>The page "{slug}" could not be loaded.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="sym-view sym-view--page">
      {/* Hero banner */}
      <div className="sym-page-banner sym-page-banner--with-image">
        {banner.image && <img src={banner.image} alt="" className="sym-page-banner-bg" />}
        <div className="sym-page-banner-overlay" />
        <div className="sym-page-banner-content">
          <div className="sym-page-banner-icon">
            <Icon name={banner.icon} size={28} />
          </div>
          <h1 className="sym-page-banner-title">{banner.title}</h1>
          <p className="sym-page-banner-sub">{banner.sub}</p>
        </div>
      </div>

      {/* Content */}
      <div className="sym-page-body-content sym-prose"
        dangerouslySetInnerHTML={{ __html: page.content?.rendered || '' }}
      />

      {/* CTA */}
      <div className="sym-page-cta-bar">
        <div className="sym-page-cta-card">
          <AiAvatar size={32} />
          <div>
            <strong>Need more help?</strong>
            <p>Our AI Print Advisor can answer any question about this topic instantly.</p>
          </div>
          <button className="sym-btn sym-btn--primary sym-page-cta-btn" onClick={() => {
            sendMessage(`I have a question about ${page.title?.rendered || slug}`);
          }}>
            Ask AI
          </button>
        </div>
      </div>
    </div>
  );
}
