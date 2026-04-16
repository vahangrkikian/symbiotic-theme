/**
 * Lightweight i18n for the Symbiotic Theme frontend.
 * Translations keyed by language code (2-letter ISO).
 * Falls back to English if key not found.
 */

const translations = {
  en: {
    'welcome.greeting': 'Welcome! How can I help you shop today?',
    'welcome.sub': 'I can search products, compare prices, manage your cart, track orders, and more.',
    'welcome.sale': "What's on sale?",
    'welcome.popular': 'Show popular products',
    'welcome.gift': 'Help me find a gift',
    'welcome.track': 'Track my order',
    'welcome.shipping': 'Shipping & returns info',
    'welcome.compare': 'Compare products',
    'welcome.categories': 'Shop by Category',
    'welcome.brands': 'Popular Brands',
    'welcome.items': 'items',
    'nav.home': 'Home',
    'nav.shop': 'Shop',
    'nav.cart': 'Cart',
    'nav.orders': 'Orders',
    'sidebar.newChat': 'New conversation',
    'sidebar.navigate': 'Navigate',
    'sidebar.chat': 'Chat',
    'sidebar.myOrders': 'My Orders',
    'sidebar.categories': 'Categories',
    'sidebar.quickBrowse': 'Quick Browse',
    'sidebar.brands': 'Brands',
    'sidebar.quickActions': 'Quick Actions',
    'chat.empty': 'Start a conversation — ask about products, compare items, or get help.',
    'chat.placeholder': 'Type a message...',
    'chat.addToCart': 'Add to Cart',
    'chat.addedToCart': 'Added to cart',
    'chat.removedFromCart': 'Removed from cart',
    'chat.cartUpdated': 'Cart updated',
    'chat.checkout': 'Checkout',
    'chat.completeOrder': 'Complete Your Order',
    'chat.continueShop': 'Continue Shopping',
    'search.placeholder': 'Search products or ask anything...',
    'search.productPlaceholder': 'Ask about this product...',
    'search.cartPlaceholder': 'Apply coupon or ask for help...',
    'search.orderPlaceholder': 'Track an order or ask a question...',
    'cart.title': 'Shopping Cart',
    'cart.loading': 'Loading cart...',
    'orders.title': 'My Orders',
    'orders.help': 'Ask the AI about your order status, tracking, or returns.',
    'context.searchResults': 'Search Results',
    'context.productsFound': 'products found',
    'context.cartSummary': 'Cart Summary',
    'context.aiContext': 'AI Context',
    'context.working': 'The AI is working with your request.',
    'context.viewCart': 'View full cart',
  },
  hy: {
    'welcome.greeting': '\u0532\u0561\u0580\u056B \u0563\u0561\u056C\u0578\u057D\u057F! \u053B\u0576\u0579\u057A\u0565\u057D \u056F\u0561\u0580\u0578\u0572 \u0565\u0574 \u0585\u0563\u0576\u0565\u056C\u0589',
    'nav.home': '\u0533\u056C\u056D\u0561\u057E\u0578\u0580',
    'nav.shop': '\u053D\u0561\u0576\u0578\u0582\u0569',
    'nav.cart': '\u0536\u0561\u0574\u0562\u056B\u0582\u0572',
    'nav.orders': '\u054A\u0561\u057F\u057E\u0565\u0580\u0576\u0565\u0580',
    'chat.placeholder': '\u0533\u0580\u0565\u0584 \u0570\u0561\u0572\u0578\u0580\u0564\u0561\u0563\u0580\u0578\u0582\u0569\u0575\u0578\u0582\u0576...',
    'chat.addToCart': '\u0531\u057E\u0565\u056C\u0561\u0581\u0576\u0565\u056C \u0566\u0561\u0574\u0562\u056B\u0582\u0572\u056B\u0576',
    'chat.checkout': '\u054E\u0573\u0561\u0580\u0565\u056C',
  },
};

translations.es = {
  'welcome.greeting': '\u00a1Bienvenido! \u00bfC\u00f3mo puedo ayudarte a comprar hoy?',
  'nav.home': 'Inicio',
  'nav.shop': 'Tienda',
  'nav.cart': 'Carrito',
  'nav.orders': 'Pedidos',
  'chat.placeholder': 'Escribe un mensaje...',
  'chat.addToCart': 'A\u00f1adir al carrito',
  'chat.checkout': 'Finalizar compra',
};

translations.fr = {
  'welcome.greeting': 'Bienvenue ! Comment puis-je vous aider ?',
  'nav.home': 'Accueil',
  'nav.shop': 'Boutique',
  'nav.cart': 'Panier',
  'nav.orders': 'Commandes',
  'chat.placeholder': '\u00c9crivez un message...',
  'chat.addToCart': 'Ajouter au panier',
  'chat.checkout': 'Passer la commande',
};

translations.de = {
  'welcome.greeting': 'Willkommen! Wie kann ich Ihnen beim Einkaufen helfen?',
  'nav.home': 'Startseite',
  'nav.shop': 'Shop',
  'nav.cart': 'Warenkorb',
  'nav.orders': 'Bestellungen',
  'chat.placeholder': 'Nachricht eingeben...',
  'chat.addToCart': 'In den Warenkorb',
  'chat.checkout': 'Zur Kasse',
};

translations.ru = {
  'welcome.greeting': '\u0414\u043e\u0431\u0440\u043e \u043f\u043e\u0436\u0430\u043b\u043e\u0432\u0430\u0442\u044c! \u0427\u0435\u043c \u043c\u043e\u0433\u0443 \u043f\u043e\u043c\u043e\u0447\u044c?',
  'nav.home': '\u0413\u043b\u0430\u0432\u043d\u0430\u044f',
  'nav.shop': '\u041c\u0430\u0433\u0430\u0437\u0438\u043d',
  'nav.cart': '\u041a\u043e\u0440\u0437\u0438\u043d\u0430',
  'nav.orders': '\u0417\u0430\u043a\u0430\u0437\u044b',
  'chat.placeholder': '\u041d\u0430\u043f\u0438\u0448\u0438\u0442\u0435 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435...',
  'chat.addToCart': '\u0412 \u043a\u043e\u0440\u0437\u0438\u043d\u0443',
  'chat.checkout': '\u041e\u0444\u043e\u0440\u043c\u0438\u0442\u044c',
};

/**
 * Get the current language code from WordPress data.
 */
export function getLang() {
  return (window.symbioticData?.langCode || 'en').toLowerCase();
}

/**
 * Check if current language is RTL.
 */
export function isRtl() {
  return window.symbioticData?.isRtl === true;
}

/**
 * Translate a key. Falls back to English, then to the key itself.
 */
export function t(key) {
  const lang = getLang();
  return translations[lang]?.[key] || translations.en?.[key] || key;
}

export default t;
