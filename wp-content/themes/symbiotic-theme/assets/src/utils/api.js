/**
 * Base fetch wrapper using symbioticData.restUrl + nonce header.
 */
const getData = () => window.symbioticData || {};

function getHeaders(extra = {}) {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce':   getData().nonce || '',
    ...extra,
  };
}

function baseUrl() {
  return getData().restUrl || '/wp-json/';
}

async function request(path, options = {}) {
  const res = await fetch(baseUrl() + path, {
    headers: getHeaders(),
    ...options,
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: res.statusText }));
    throw new Error(err.message || 'Request failed');
  }
  return res.json();
}

export function postChat(message) {
  return request('wcaic/v1/chat', {
    method: 'POST',
    body:   JSON.stringify({ message }),
  });
}

export function postStream(message) {
  return fetch(baseUrl() + 'wcaic/v1/stream', {
    method:  'POST',
    headers: getHeaders(),
    body:    JSON.stringify({ message }),
  });
}

export function getCart() {
  return request('wcaic/v1/cart');
}

export function getWelcome() {
  return request('wcaic/v1/welcome');
}

export function postClear() {
  return request('wcaic/v1/clear', { method: 'POST' });
}

// WordPress content API
export function getPage(slug) {
  return request('wp/v2/pages?slug=' + encodeURIComponent(slug) + '&_fields=id,title,content,slug');
}

export function getPosts(page = 1, perPage = 12) {
  return request('wp/v2/posts?page=' + page + '&per_page=' + perPage + '&_embed');
}

export function getPost(slug) {
  return request('wp/v2/posts?slug=' + encodeURIComponent(slug) + '&_embed');
}

// Products API
export function getProducts(params = {}) {
  const qs = new URLSearchParams({ per_page: '12', status: 'publish', ...params }).toString();
  return request('wc/store/v1/products?' + qs).catch(() =>
    // Fallback: use WP REST
    request('wp/v2/product?' + qs + '&_fields=id,title,excerpt,featured_media,meta').catch(() => [])
  );
}

export function getProductCalculator(productId) {
  return request('wcaic/v1/chat', {
    method: 'POST',
    body: JSON.stringify({ message: '__internal_get_calculator_' + productId }),
  }).catch(() => null);
}

// Direct REST endpoint for calculator data (bypasses AI)
export function fetchCalculatorConfig(productId) {
  return request('symbiotic/v1/calculator/' + productId);
}

// Direct cart API (bypasses AI)
export function getCartDirect() {
  return request('symbiotic/v1/cart');
}

export function removeCartItem(key) {
  return request('symbiotic/v1/cart/remove', {
    method: 'POST',
    body: JSON.stringify({ key }),
  });
}

export function updateCartItem(key, quantity) {
  return request('symbiotic/v1/cart/update', {
    method: 'POST',
    body: JSON.stringify({ key, quantity }),
  });
}

// Checkout API
export function getCheckout() {
  return request('symbiotic/v1/checkout');
}
export function setCheckoutAddress(address) {
  return request('symbiotic/v1/checkout/address', { method: 'POST', body: JSON.stringify(address) });
}
export function setCheckoutShipping(methodId) {
  return request('symbiotic/v1/checkout/shipping', { method: 'POST', body: JSON.stringify({ method_id: methodId }) });
}
export function placeOrder(paymentMethod) {
  return request('symbiotic/v1/checkout/place-order', { method: 'POST', body: JSON.stringify({ payment_method: paymentMethod }) });
}

export function addCalculatorToCart(productId, selections, calculatedPrice) {
  return request('symbiotic/v1/add-to-cart', {
    method: 'POST',
    body: JSON.stringify({ product_id: productId, selections, calculated_price: calculatedPrice }),
  });
}
