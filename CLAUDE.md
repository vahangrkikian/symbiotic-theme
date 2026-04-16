# Symbiotic Theme — Claude Project Context

> **Sync file for Claude Console & Claude Code CLI**
> Last updated: 2026-04-13 | Cycle 1 Complete | Theme v1.0.0 | Plugin v1.2.0

---

## What This Project Is

**Symbiotic Theme** is the first WordPress + WooCommerce theme with a native AI shopping assistant — not a bolt-on widget, but a deeply integrated conversational commerce experience. The AI acts as a configurable **brand ambassador** that knows the store's products, policies, voice, and values.

**Authors:** Vahan Hovhannisyan + Claude (pair programming)
**License:** GPL-2.0-or-later
**Requirements:** WordPress 6.4+, PHP 8.1+, WooCommerce 8.0+

---

## Architecture Overview

The project has **two main components** inside a standard WordPress installation:

### 1. Theme — `wp-content/themes/symbiotic-theme/`
- **React 18 SPA** frontend built with **Vite 5.4**
- 3-panel workspace: left sidebar (history/nav) + center (AI responses) + right sidebar (context)
- Source: `assets/src/` (24 JSX components)
- Build output: `dist/main.js` + `dist/main.css`
- PHP classes in `inc/`: Admin settings, Assets (Vite enqueue), REST API, WC dependency check
- CSS variables system: `--sym-*` prefix for all theme tokens

### 2. Plugin — `wp-content/plugins/wc-ai-chatbot/`
- **Pure PHP + vanilla JS** (no build step)
- 20 PHP classes with PSR-4 autoloader (`WCAIC_*` prefix)
- 5 REST endpoints under `/wcaic/v1/` namespace
- AI function-calling loop with 8 WooCommerce tools
- Supports OpenAI (GPT-4o) and Anthropic (Claude) providers
- SSE streaming for real-time token delivery
- Security: prompt injection detection (30+ patterns), rate limiting, API key encryption

---

## File Map (Key Paths)

```
wp-content/
├── themes/symbiotic-theme/
│   ├── functions.php              Theme bootstrap, loads 4 classes
│   ├── style.css                  Theme metadata header
│   ├── inc/
│   │   ├── class-symbiotic-admin.php       Settings page + color/layout config
│   │   ├── class-symbiotic-assets.php      Vite bundle enqueuing + wp_localize_script
│   │   ├── class-symbiotic-rest.php        REST: /theme-data, /navigation
│   │   └── class-symbiotic-wc-check.php    WooCommerce dependency enforcement
│   ├── assets/
│   │   ├── src/                   React source (entry: index.jsx)
│   │   │   ├── App.jsx            Root component, CSS variable injection
│   │   │   ├── components/
│   │   │   │   ├── layout/        SiteLayout, SiteHeader, ChatBar, AiResponseZone, BeingPanel, KnowledgePanel
│   │   │   │   ├── views/         ChatView, WelcomeView, CartView, OrdersView, BlogView, PageView
│   │   │   │   ├── pages/         HomePage, PageRouter
│   │   │   │   └── shared/        Icon
│   │   │   ├── context/           WorkspaceContext + workspaceReducer
│   │   │   ├── hooks/             Custom React hooks
│   │   │   └── utils/             api.js, sse.js, markdown.js, decode.js, i18n.js
│   │   ├── package.json           React 18.3, Vite 5.4
│   │   └── vite.config.js         Builds to ../dist/
│   ├── dist/                      Compiled assets (main.js + main.css)
│   ├── front-page.php, page.php, singular.php, 404.php, index.php
│   ├── header.php, footer.php, woocommerce.php
│   └── child-theme/               Child theme template
│
└── plugins/wc-ai-chatbot/
    ├── wc-ai-chatbot.php          Entry: constants, autoloader, activation
    ├── includes/
    │   ├── class-wcaic-plugin.php           Singleton bootstrap
    │   ├── class-wcaic-rest-api.php         5 REST endpoints, chat orchestration
    │   ├── class-wcaic-ai-client.php        Abstract: function-call loop, system prompt
    │   ├── class-wcaic-ai-client-openai.php OpenAI provider
    │   ├── class-wcaic-ai-client-anthropic.php  Anthropic provider
    │   ├── class-wcaic-ai-response.php      Normalized response DTO
    │   ├── class-wcaic-tool-definitions.php 8 tool schemas (dual format)
    │   ├── class-wcaic-tool-executor.php    Tool execution engine
    │   ├── class-wcaic-brand-knowledge.php  Knowledge base CRUD (wp_options)
    │   ├── class-wcaic-persona.php          4 presets + custom persona + spectrum sliders
    │   ├── class-wcaic-security.php         Prompt injection detection
    │   ├── class-wcaic-rate-limiter.php     Sliding window limiter
    │   ├── class-wcaic-session-manager.php  WC session + transient history
    │   ├── class-wcaic-conversation-logger.php  DB logging (wp_wcaic_conversation_log)
    │   ├── class-wcaic-logger.php           WC logger integration
    │   ├── class-wcaic-admin.php            Settings UI, API key encryption
    │   ├── class-wcaic-conv-log-admin.php   Conversation log viewer
    │   ├── class-wcaic-embeddings.php       Semantic search (OpenAI embeddings)
    │   ├── class-wcaic-language.php         Multilingual support
    │   └── class-wcaic-product-importer.php Product scraping/import
    ├── assets/js/                  chatbot-widget.js, admin-embeddings.js, admin-importer.js
    ├── assets/css/                 Widget + admin stylesheets
    └── templates/                  admin-settings.php, chat-widget.php, etc.
```

---

## Tech Stack Summary

| Layer | Technology | Notes |
|-------|-----------|-------|
| CMS | WordPress 6.9 | Standard WP installation |
| E-commerce | WooCommerce 9.0 | Full cart/checkout integration |
| Frontend | React 18.3 + Vite 5.4 | SPA with SSE streaming |
| Backend | PHP 8.1+ | OOP with PSR-4 autoloader |
| AI | OpenAI GPT-4o / Anthropic Claude | Function-calling with 8 tools |
| Database | MySQL | WP tables + custom `wp_wcaic_conversation_log` |
| Styling | CSS custom properties | `--sym-*` tokens, dark mode primary |
| State | React Context + useReducer | WorkspaceContext pattern |
| Streaming | Server-Sent Events | `/wcaic/v1/stream` endpoint |

---

## AI Tools (Function-Calling)

The chatbot exposes 8 tools to the AI model:

1. `search_products` — keyword + filters (category, brand, price range, sale)
2. `get_product_details` — full info with variations and reviews
3. `add_to_cart` — add with quantity and variation
4. `remove_from_cart` — remove by cart item key
5. `update_cart_quantity` — adjust quantities
6. `get_cart` — current cart contents and totals
7. `apply_coupon` — redeem coupon codes
8. `get_checkout_url` / `get_cart_url` — generate direct links

---

## Admin Customization

Settings live in `wp_options['wcaic_settings']` (plugin) and theme mods (theme).

**Plugin admin tabs:** General (provider/model/key) | Brand Knowledge (story/FAQ/policies) | Persona (presets + sliders) | Colors | Layout | Advanced (custom CSS)

**Persona presets:** Luxury Boutique, Friendly Local Shop, Tech Expert, Enthusiastic Guide, Custom

**Persona spectrum sliders:** Selling Posture, Formality, Detail Level, Proactivity

---

## Development Commands

```bash
# Frontend build (from theme assets directory)
cd wp-content/themes/symbiotic-theme/assets
npm install
npm run build        # Production build → ../dist/
npm run dev          # Vite dev server with HMR

# No build step for the plugin — plain PHP + vanilla JS
```

---

## Key Design Decisions

- **Theme + Plugin split**: Theme handles UI/layout, plugin handles AI logic. This allows the plugin to potentially work with other themes, and the theme to work without the plugin (with degraded UX).
- **No SaaS dependency**: Store owners bring their own API key. No recurring platform fees.
- **Provider-agnostic**: Abstract AI client base class with OpenAI and Anthropic implementations. Adding a new provider means one new class.
- **SSE over WebSockets**: Simpler to deploy on shared hosting (no persistent connections needed). Uses standard HTTP with chunked responses.
- **React SPA in WordPress**: The theme suppresses WooCommerce default styles and renders everything through React. WordPress serves as a headless CMS + API backend.
- **Session via WooCommerce**: Conversation history piggybacks on WC sessions rather than custom auth, ensuring cart context is always available.

---

## Naming Conventions

- **Theme PHP classes:** `Symbiotic_*` prefix → `inc/class-symbiotic-*.php`
- **Plugin PHP classes:** `WCAIC_*` prefix → `includes/class-wcaic-*.php`
- **Theme CSS variables:** `--sym-*` prefix
- **Plugin REST namespace:** `wcaic/v1`
- **Theme REST namespace:** `symbiotic/v1`
- **React components:** PascalCase, organized by `layout/`, `views/`, `pages/`, `shared/`
- **JS utilities:** camelCase, in `utils/` directory

---

## Existing Documentation

| File | Purpose |
|------|---------|
| `Cycle1.md` | Development roadmap — 7 phases, all complete |
| `PLUGIN_COMPLETE_REFERENCE.md` | 23-section deep technical reference for the plugin |
| `wp-content/themes/symbiotic-theme/readme.txt` | Theme marketplace description |

---

## Current Status

- **Cycle 1:** Complete (all 7 phases — brand ambassador, tool expansion, security, frontend, admin, docs, marketplace)
- **Market-ready:** Theme and plugin are functional and documented
- **No git repo:** Project is not version-controlled yet
- **Next potential work (Cycle 2):** Semantic search expansion, advanced analytics, multi-provider failover, performance optimization

---

## Important Patterns to Follow

- When modifying PHP classes, follow existing PSR-4 autoloader conventions
- All REST endpoints must include nonce verification (`wp_verify_nonce`)
- Rate limiting applies to all public-facing AI endpoints
- System prompt is dynamically assembled from: persona config + brand knowledge + tool definitions
- Frontend state flows through `WorkspaceContext` — avoid prop drilling
- All user-facing strings should use i18n functions (`__()`, `_e()`) with `symbiotic-theme` or `wc-ai-chatbot` text domains
- The Vite config outputs to `../dist/` — always run `npm run build` after frontend changes
- Plugin settings use a single serialized option (`wcaic_settings`) — access via `get_option('wcaic_settings')`
