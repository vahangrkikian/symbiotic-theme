import React, { useEffect, useRef } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon from '../shared/Icon.jsx';
import { t } from '../../utils/i18n.js';

export default function OrdersView() {
  const { state, sendMessage, navigate } = useWorkspace();
  const fetched = useRef(false);

  useEffect(() => {
    if (!fetched.current) {
      fetched.current = true;
      sendMessage('Show my recent orders');
    }
  }, []);

  // If no messages yet (still loading), show skeleton
  const hasOrderMessages = state.messages.some(m => m.extra?.orders || m.extra?.order);

  return (
    <div className="sym-view sym-view--orders">
      <h2 className="sym-view-title">{t('orders.title')}</h2>

      {!hasOrderMessages && state.messages.length <= 1 ? (
        <div className="sym-skeleton-group">
          <div className="sym-skeleton sym-skeleton--row" />
          <div className="sym-skeleton sym-skeleton--row" />
          <div className="sym-skeleton sym-skeleton--row" />
        </div>
      ) : !hasOrderMessages ? (
        <div className="sym-empty-state">
          <Icon name="orders" size={40} />
          <p className="sym-empty-title">No orders yet</p>
          <p>Once you place an order, you can track it here.</p>
          <button className="sym-btn sym-btn--primary" style={{ maxWidth: 240, margin: '16px auto 0' }} onClick={() => navigate('welcome')}>
            Start Shopping
          </button>
        </div>
      ) : (
        <p className="sym-text-secondary" style={{ fontSize: 'var(--sym-text-base)' }}>
          {t('orders.help')}
        </p>
      )}
    </div>
  );
}
