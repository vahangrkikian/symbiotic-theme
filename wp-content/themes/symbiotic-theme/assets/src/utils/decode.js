/**
 * Decode HTML entities in a string.
 * Used for WooCommerce API data that returns encoded names.
 */
const textarea = typeof document !== 'undefined' ? document.createElement('textarea') : null;

export function decodeHtml(str) {
  if (!str) return '';
  if (!textarea) return str;
  textarea.innerHTML = str;
  return textarea.value;
}
