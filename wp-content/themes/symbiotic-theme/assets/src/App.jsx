import React, { useEffect } from 'react';
import { WorkspaceProvider } from './context/WorkspaceContext.jsx';
import SiteLayout from './components/layout/SiteLayout.jsx';

export default function App() {
  const data  = window.symbioticData || {};
  const theme = data.themeOptions   || {};

  useEffect(() => {
    const root = document.documentElement;
    const set  = (v, val) => root.style.setProperty(v, val);
    const clear = (v) => root.style.removeProperty(v);

    // Theme mode FIRST — determines which CSS tokens are active
    const mode = theme.themeMode || 'dark';
    root.setAttribute('data-theme', mode);

    // Primary color always applies (same in both modes)
    const primary = theme.colorPrimary || data.primaryColor || '#9d33d6';
    set('--sym-primary', primary);
    set('--sym-primary-hover', shadeColor(primary, -15));
    set('--sym-user-bubble', primary);
    set('--sym-accent', tintColor(primary, 40));

    if (mode === 'dark') {
      // Dark mode: apply admin color overrides
      set('--sym-bg',         theme.colorBg        || '#1a1b1f');
      set('--sym-surface',    theme.colorSurface   || '#222328');
      set('--sym-surface-2',  theme.colorSurface2  || '#2b2c31');
      set('--sym-text',       theme.colorText      || 'rgba(255,255,255,0.93)');
      set('--sym-text-muted', theme.colorTextMuted || 'rgba(255,255,255,0.38)');
      set('--sym-bot-bubble', theme.colorBotBubble || '#282830');
    } else {
      // Light mode: clear inline overrides so CSS [data-theme="light"] rules take effect
      ['--sym-bg','--sym-surface','--sym-surface-2','--sym-text','--sym-text-muted','--sym-bot-bubble',
       '--sym-text-secondary','--sym-text-disabled','--sym-border','--sym-border-2',
       '--sym-surface-3','--sym-primary-bg','--sym-primary-border',
       '--sym-shadow-sm','--sym-shadow-md','--sym-shadow-lg'
      ].forEach(clear);
    }

    // Layout (both modes)
    if (theme.borderRadius) set('--sym-radius', theme.borderRadius + 'px');
    if (theme.baseFontSize) set('--sym-font-size', theme.baseFontSize + 'px');

    // Font (both modes)
    if (theme.fontFamily) {
      set('--sym-font', `'${theme.fontFamily}', system-ui, sans-serif`);
    }

    // RTL support
    if (data.isRtl) {
      document.documentElement.setAttribute('dir', 'rtl');
      document.body.classList.add('sym-rtl');
    }
  }, [theme, data.primaryColor, data.isRtl]);

  return (
    <WorkspaceProvider>
      <SiteLayout />
    </WorkspaceProvider>
  );
}

function shadeColor(hex, percent) {
  const n = parseInt(hex.replace('#', ''), 16);
  const r = Math.max(0, Math.min(255, ((n >> 16) & 0xff) + Math.round(255 * percent / 100)));
  const g = Math.max(0, Math.min(255, ((n >> 8)  & 0xff) + Math.round(255 * percent / 100)));
  const b = Math.max(0, Math.min(255, (n         & 0xff) + Math.round(255 * percent / 100)));
  return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
}

function tintColor(hex, percent) {
  return shadeColor(hex, percent);
}
