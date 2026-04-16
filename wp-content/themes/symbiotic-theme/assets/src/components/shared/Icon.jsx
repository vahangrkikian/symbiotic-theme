import React from 'react';

/**
 * Material Design-inspired flat icon system.
 * All icons are 24x24 viewBox, stroke-based, 1.5px weight.
 * Rendered at the size prop (default 20px).
 */

const paths = {
  // Navigation
  home:         'M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1V9.5z',
  chat:         'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z',
  cart:         'M9 22a1 1 0 100-2 1 1 0 000 2zm11 0a1 1 0 100-2 1 1 0 000 2zM1 1h4l2.68 13.39a1 1 0 001 .81h9.72a1 1 0 001-.76L23 6H6',
  orders:       'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9h6m-6 4h6',
  search:       'M21 21l-5.2-5.2M10 17a7 7 0 100-14 7 7 0 000 14z',

  // Actions
  plus:         'M12 5v14M5 12h14',
  send:         'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
  chevronLeft:  'M15 18l-6-6 6-6',
  chevronRight: 'M9 18l6-6-6-6',
  close:        'M18 6L6 18M6 6l12 12',
  more:         'M12 13a1 1 0 100-2 1 1 0 000 2zm7 0a1 1 0 100-2 1 1 0 000 2zM5 13a1 1 0 100-2 1 1 0 000 2z',

  // Commerce
  tag:          'M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82zM7 7h.01',
  star:         'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
  gift:         'M20 12v10H4V12M2 7h20v5H2zm10-5a3 3 0 00-3 3h6a3 3 0 00-3-3z',
  truck:        'M1 3h15v13H1zM16 8h4l3 3v5h-7V8zM5.5 21a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm13 0a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
  package:      'M16.5 9.4l-9-5.19M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16zM3.27 6.96L12 12.01l8.73-5.05M12 22.08V12',
  compare:      'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4',

  // UI
  user:         'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
  settings:     'M12.22 2h-.44a2 2 0 00-2 2v.18a2 2 0 01-1 1.73l-.43.25a2 2 0 01-2 0l-.15-.08a2 2 0 00-2.73.73l-.22.38a2 2 0 00.73 2.73l.15.1a2 2 0 011 1.72v.51a2 2 0 01-1 1.74l-.15.09a2 2 0 00-.73 2.73l.22.38a2 2 0 002.73.73l.15-.08a2 2 0 012 0l.43.25a2 2 0 011 1.73V20a2 2 0 002 2h.44a2 2 0 002-2v-.18a2 2 0 011-1.73l.43-.25a2 2 0 012 0l.15.08a2 2 0 002.73-.73l.22-.39a2 2 0 00-.73-2.73l-.15-.08a2 2 0 01-1-1.74v-.5a2 2 0 011-1.74l.15-.09a2 2 0 00.73-2.73l-.22-.38a2 2 0 00-2.73-.73l-.15.08a2 2 0 01-2 0l-.43-.25a2 2 0 01-1-1.73V4a2 2 0 00-2-2z',
  globe:        'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z',
  document:     'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M16 13H8m8 4H8m2-8H8',
  brain:        'M12 2a7 7 0 017 7c0 2.38-1.19 4.47-3 5.74V17a2 2 0 01-2 2h-4a2 2 0 01-2-2v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 017-7zM9 21h6',

  // Status
  check:        'M20 6L9 17l-5-5',
  alertCircle:  'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 8v4m0 4h.01',
  info:         'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4m0-4h.01',

  // Print categories
  card:         'M3 5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm3 4h4m-4 3h8m-8 3h5',
  banner:       'M4 3h16v14l-3-2-2.5 2L12 15l-2.5 2L7 15l-3 2V3z',
  sticker:      'M15.5 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8.5L15.5 3zM14 3v6h7',
  sign:         'M4 6h16v10H4V6zm8 10v5M8 21h8',
  flyer:        'M6 2h12a1 1 0 011 1v18a1 1 0 01-1 1H6a1 1 0 01-1-1V3a1 1 0 011-1zm2 5h8m-8 3h8m-8 3h5',
  box:          'M21 8V5a1 1 0 00-1-1H4a1 1 0 00-1 1v3m18 0H3m18 0v11a1 1 0 01-1 1H4a1 1 0 01-1-1V8m8 4h2',
  stamp:        'M5 21h14M7 16h10M8 12V8a4 4 0 118 0v4',
  magnet:       'M6 3v7a6 6 0 1012 0V3M6 3h3m9 0h-3M6 8h3m9 0h-3',
  car:          'M5 17h1a2 2 0 012-2h8a2 2 0 012 2h1a1 1 0 001-1v-4l-3-5H7L4 12v4a1 1 0 001 1zm2.5 2a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm9 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
  folder:       'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
  table:        'M3 3h18v18H3V3zm0 6h18M3 15h18M9 9v12M15 9v12',
  flag:         'M4 15s1-1 4-1 5 2 8 1V3s-3 1-8-1-4 1-4 1v12zm0 7v-7',
  poster:       'M4 3h16a1 1 0 011 1v16a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm3 5h10M7 10h10M7 14h6',
  bag:          'M6 2L3 7v13a1 1 0 001 1h16a1 1 0 001-1V7l-3-5H6zm0 5h12m-9 4a3 3 0 006 0',
  envelope:     'M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm0 2l8 5 8-5',
  layers:       'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
};

const Icon = React.memo(function Icon({ name, size = 20, className = '', ...props }) {
  const d = paths[name];
  if (!d) return null;

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={`sym-icon ${className}`}
      aria-hidden="true"
      {...props}
    >
      <path d={d} />
    </svg>
  );
});

export default Icon;

/**
 * Brand avatar — TGM Print logo or fallback SVG.
 */
export const AiAvatar = React.memo(function AiAvatar({ size = 28, className = '' }) {
  const logoUrl = (window.symbioticData?.themeOptions?.botAvatarUrl) ||
    (document.querySelector('link[rel="icon"]')?.href);

  // Use logo image if available, fallback to SVG mark
  if (logoUrl) {
    return (
      <img
        src={logoUrl}
        alt=""
        width={size}
        height={size}
        className={`sym-ai-avatar ${className}`}
        aria-hidden="true"
        style={{ borderRadius: '50%', objectFit: 'contain', background: 'var(--sym-primary-bg)' }}
      />
    );
  }

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 28 28"
      fill="none"
      className={`sym-ai-avatar ${className}`}
      aria-hidden="true"
    >
      <circle cx="14" cy="14" r="13" fill="var(--sym-primary)" />
      <text x="14" y="18" textAnchor="middle" fill="#fff" fontSize="12" fontWeight="700" fontFamily="Outfit,sans-serif">T</text>
    </svg>
  );
});
