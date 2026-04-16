# Symbiotic Theme

**The first WordPress + WooCommerce theme with a native AI shopping assistant.**

Not a bolt-on chatbot widget — a deeply integrated conversational commerce experience where customers shop through conversation with an AI brand ambassador that knows your products, policies, voice, and values.

![WordPress](https://img.shields.io/badge/WordPress-6.4+-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0+-purple)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4)
![React](https://img.shields.io/badge/React-18.3-61DAFB)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

---

## Features

- **AI Brand Ambassador** — Configurable persona that speaks in your brand's voice with knowledge of your catalog, FAQ, and policies
- **13 AI Shopping Tools** — Search products, compare items, manage cart, track orders, estimate shipping, apply coupons — all via natural language
- **3-Panel Workspace** — Left sidebar (navigation/history) + center body (AI responses with rich cards) + right sidebar (contextual intelligence)
- **Dual AI Provider** — OpenAI (GPT-4o) or Anthropic (Claude) with encrypted API key storage
- **Real-time Streaming** — SSE streaming for instant token-by-token AI responses
- **Brand Knowledge Base** — Upload your brand story, FAQ, shipping/return policies for accurate AI answers
- **Persona Engine** — 4 presets (Luxury Boutique, Friendly Shop, Tech Expert, Enthusiastic Guide) + custom persona with spectrum sliders
- **Security** — Rate limiting, prompt injection detection (30+ patterns), encrypted API keys, CSP headers
- **Dark Theme** — Premium dark interface with gold accent (fully customizable colors)
- **Mobile Responsive** — Sidebars collapse on tablet, single-column on mobile

## Architecture

The project has two components:

### Theme — `wp-content/themes/symbiotic-theme/`
React 18 SPA frontend built with Vite 5.4. Renders a 3-panel workspace that replaces the standard WooCommerce storefront. PHP backend handles settings, asset loading, and REST endpoints.

### Plugin — `wp-content/plugins/wc-ai-chatbot/`
Pure PHP + vanilla JS (no build step). Handles all AI logic: provider abstraction, function-calling loop with 8 WooCommerce tools, SSE streaming, session management, conversation logging, and security.

### Plugin — `wp-content/plugins/sqft-pricing/`
WooCommerce pricing plugin for square-footage-based products with a formula calculation engine.

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.1+
- OpenAI or Anthropic API key

## Installation

1. Clone this repository into your WordPress installation root:
   ```bash
   git clone https://github.com/vahangrkikian/symbiotic-theme.git /path/to/wordpress/
   ```

2. Install & build the React frontend:
   ```bash
   cd wp-content/themes/symbiotic-theme/assets
   npm install
   npm run build
   ```

3. Activate WooCommerce, then the **WC AI Chatbot** plugin, then the **Symbiotic Theme**

4. Go to **Appearance → Symbiotic Theme** and configure:
   - Choose AI provider (OpenAI or Anthropic)
   - Enter your API key
   - Select a persona preset
   - Add your brand knowledge

5. Visit your site — the AI workspace is live

## Development

```bash
# Start Vite dev server with HMR
cd wp-content/themes/symbiotic-theme/assets
npm run dev

# Production build
npm run build
```

The plugin requires no build step — plain PHP + vanilla JS.

## AI Tools

The AI assistant has access to these WooCommerce tools via function calling:

| Tool | Description |
|------|-------------|
| `search_products` | Keyword search with filters (category, brand, price range, sale) |
| `get_product_details` | Full product info with variations and reviews |
| `add_to_cart` | Add products with quantity and variation |
| `remove_from_cart` | Remove items by cart key |
| `update_cart_quantity` | Adjust item quantities |
| `get_cart` | Current cart contents and totals |
| `apply_coupon` | Redeem coupon codes |
| `get_checkout_url` | Generate direct checkout/cart links |

## Tech Stack

| Layer | Technology |
|-------|-----------|
| CMS | WordPress 6.9 |
| E-commerce | WooCommerce 9.0 |
| Frontend | React 18.3 + Vite 5.4 |
| Backend | PHP 8.1+ (OOP, PSR-4) |
| AI | OpenAI GPT-4o / Anthropic Claude |
| Streaming | Server-Sent Events |
| State | React Context + useReducer |
| Styling | CSS Custom Properties (`--sym-*` tokens) |

## Authors

**Vahan Hovhannisyan** — [hovhannisyan.ai](https://hovhannisyan.ai)

Built with Claude (pair programming).

## License

GPL-2.0-or-later — see [LICENSE](wp-content/themes/symbiotic-theme/LICENSE)
