/**
 * Safe inline markdown renderer.
 * Escapes HTML first, then applies markdown substitutions.
 * Links validated to http/https only.
 */
export function escHtml(str) {
  return String(str)
    .replace(/&/g,  '&amp;')
    .replace(/</g,  '&lt;')
    .replace(/>/g,  '&gt;')
    .replace(/"/g,  '&quot;')
    .replace(/'/g,  '&#039;');
}

export function renderInlineMarkdown(text) {
  if (!text) return '';
  let safe = escHtml(text);

  // Bold
  safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  // Italic
  safe = safe.replace(/\*([^*]+?)\*/g, '<em>$1</em>');
  // Code
  safe = safe.replace(/`([^`]+?)`/g, '<code>$1</code>');
  // Links — only http/https
  safe = safe.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    (_, label, url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`
  );
  // Newlines
  safe = safe.replace(/\n/g, '<br>');

  return safe;
}
