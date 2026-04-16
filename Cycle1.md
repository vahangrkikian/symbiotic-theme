# Symbiotic Theme — Cycle 1: Market-Ready Finalization

**Project:** Symbiotic AI Shopping Theme for WooCommerce  
**Author:** Є pair — Vahan Hovhannisyan + Claude  
**Date:** April 2026  
**Status:** Cycle 1 — All 7 Phases Complete

---

## Context

The Symbiotic Theme is a WordPress + WooCommerce AI-first shopping interface where the AI acts as a **brand ambassador** — not a generic chatbot widget, but a deeply integrated partner that embodies the store's mission, voice, and knowledge.

**The goal**: Finalize this into a market-ready product for the global WordPress e-commerce ecosystem (ThemeForest and beyond).

**The reference pattern**: NUACA Gradaran AI — where an institution's static knowledge was made conversational through RAG + collections + trilingual support. The same pattern applies here: a store's knowledge (products + brand story + policies + FAQ) becomes conversational through the AI brand ambassador.

**Current state**: ~60% complete. Solid technical core, but significant gaps in brand ambassador depth, security hardening, documentation, and marketplace distribution requirements.

---

## Market Positioning

**No WordPress theme ships with a native AI shopping assistant.** Every existing solution is a bolt-on plugin (Tidio, WPBot, Zipchat). This creates the opportunity:

> **Symbiotic Theme** — The first WooCommerce theme where AI is a first-class citizen of the shopping experience, not an afterthought widget.

**Key differentiators:**
- AI woven into the theme design (not a floating bubble)
- Configurable brand personality (tone, values, knowledge)
- Full cart/checkout management via natural language
- Self-hosted AI (no SaaS dependency, no monthly fees beyond API costs)
- Provider-agnostic (OpenAI or Anthropic)

**Competitive landscape (2026):**

| Solution | Type | AI Quality | Brand Customization | Cart via Chat | Price Model |
|----------|------|-----------|-------------------|--------------|-------------|
| Tidio | Plugin | Medium (Lyro AI) | Color/name only | No | $29-$59/mo SaaS |
| WPBot | Plugin | Low (DialogFlow) | Basic template | No | $49 one-time + Google API |
| Zipchat AI | Plugin | Medium (GPT) | Minimal | Partial | $49/mo SaaS |
| **Symbiotic Theme** | **Theme + Plugin** | **High (Claude/GPT)** | **Full persona + knowledge** | **Yes, all operations** | **One-time + API costs** |

---

## Phase 1: Brand Ambassador Engine (HIGH PRIORITY)

Transform the generic "shopping assistant" into a configurable brand ambassador.

### 1.1 Brand Knowledge Base

**Problem**: AI only knows products. A real brand ambassador knows the story, policies, FAQ, values.

**Build**:
- New admin tab: **"Brand Knowledge"** in WooCommerce > AI Chatbot
- Sections: Brand Story, FAQ, Policies (return/shipping/warranty), Custom Knowledge
- Each section: textarea + toggle (active/inactive)
- On save: content is injected into system prompt context window
- **Pattern from NUACA**: Like collections/sources — structured knowledge the AI retrieves from

**Files to modify**:
- `wp-content/plugins/wc-ai-chatbot/includes/class-wcaic-admin.php` — add Brand Knowledge tab
- `wp-content/plugins/wc-ai-chatbot/includes/class-wcaic-ai-client.php` — inject knowledge into system prompt
- New: `class-wcaic-brand-knowledge.php` — CRUD for knowledge sections (wp_options storage)

### 1.2 Configurable AI Persona

**Problem**: System prompt is hardcoded with fixed rules ("no emoji", "prose only", "1-2 sentences").

**Build**:
- Admin UI: Persona configuration with spectrum sliders:
  - **Selling posture**: Supportive Guide ↔ Active Recommender
  - **Formality**: Casual/Friendly ↔ Professional/Polished
  - **Detail level**: Concise ↔ Detailed
  - **Proactivity**: Reactive Only ↔ Anticipatory
- Persona presets: "Luxury Boutique", "Friendly Local Shop", "Tech Expert", "Custom"
- Template variables expanded: `{{brand_story}}`, `{{faq}}`, `{{policies}}`, `{{persona_rules}}`, `{{prohibited_topics}}`
- Prohibited words/topics list (prevent competitor mentions, off-brand language)

**Files to modify**:
- `class-wcaic-admin.php` — persona UI
- `class-wcaic-ai-client.php` — dynamic system prompt assembly
- New: `class-wcaic-persona.php` — persona presets and template rendering

### 1.3 Escalation & Boundaries

**Build**:
- Configurable escalation rules: "If customer asks about X, show contact form / redirect to support"
- Off-topic handling: configurable response for non-shopping queries
- Max conversation length before suggesting human support

**Files to modify**:
- `class-wcaic-ai-client.php` — escalation detection
- `class-wcaic-admin.php` — escalation rules UI

---

## Phase 2: WooCommerce Tool Expansion (HIGH PRIORITY)

### 2.1 New Tools to Add

| Tool | Purpose | Priority |
|------|---------|----------|
| `get_order_status` | Track customer orders | Critical |
| `get_customer_orders` | List order history | Critical |
| `estimate_shipping` | Calculate shipping for cart | High |
| `get_store_policies` | Return/shipping/warranty info | High |
| `compare_products` | Side-by-side comparison | Medium |
| `get_recently_viewed` | Session-based browsing history | Medium |
| `get_recommendations` | "Customers also bought" | Medium |

### 2.2 Customer Authentication Context

- Detect logged-in WooCommerce customer
- Inject customer context into AI (name, order history summary, preferences)
- Verify order ownership before sharing order details
- Personalized greetings for returning customers

**Files to modify**:
- `class-wcaic-tool-definitions.php` — new tool schemas
- `class-wcaic-tool-executor.php` — new tool implementations
- `class-wcaic-rest-api.php` — customer context injection
- `class-wcaic-session-manager.php` — customer session binding

---

## Phase 3: Security Hardening (CRITICAL)

### Must-fix before release:

| Issue | Fix | File |
|-------|-----|------|
| Streaming endpoint has no rate limiting | Apply rate limiter to `/stream` handler | `class-wcaic-rest-api.php` |
| Session ID from IP+UA (fixation risk) | Use WC session ID first, fallback to secure hash with random salt | `class-wcaic-rest-api.php` |
| No order ownership verification | Check `current_user_id` matches order customer | `class-wcaic-tool-executor.php` |
| Error messages expose AI provider | Sanitize error responses to generic messages | `class-wcaic-ai-client-openai.php`, `class-wcaic-ai-client-anthropic.php` |
| Conversation logs unencrypted | Encrypt message content at rest | `class-wcaic-conversation-logger.php` |
| No CSP headers | Add Content-Security-Policy via `wp_headers` filter | `class-wcaic-plugin.php` |
| No max stream duration | Add timeout (120s) to streaming responses | `class-wcaic-rest-api.php` |

---

## Phase 4: Frontend Polish (HIGH PRIORITY)

### 4.1 Responsive Design
- Add breakpoints: 480px, 768px, 1024px, 1280px
- Mobile: full-screen chat, bottom-sheet product cards
- Tablet: 2-panel layout (hide sidebar)

### 4.2 Accessibility
- ARIA labels on all interactive elements
- `aria-live="polite"` on message list
- Semantic HTML (`<main>`, `<nav>`, `<section>`)
- Keyboard navigation (Tab order, Enter to select)
- Focus management (auto-focus input after bot reply)

### 4.3 UX Improvements
- Loading skeletons for products/cart
- React Error Boundary wrapper
- Image lazy-loading (Intersection Observer)
- Message list virtualization (for long conversations)
- Light mode option (toggle in TopBar)
- Typing indicator animation polish

### 4.4 Smart Quick Replies
- Context-aware suggestions based on conversation state
- Category chips from welcome screen persist as filter shortcuts
- "Show me similar", "Different color", "What size should I get?" contextual suggestions

**Files to modify**:
- `assets/src/` — all component files
- `assets/src/index.css` — responsive breakpoints, a11y styles, light theme

---

## Phase 5: Admin & Configuration UX (MEDIUM PRIORITY)

### 5.1 Setup Wizard
- First-run wizard: Choose AI provider → Enter API key → Select persona preset → Done
- Validates API key connectivity before saving
- Shows example conversation preview

### 5.2 Theme Admin Improvements
- Live preview of color changes (already partially exists)
- Layout diagram (already exists, refine)
- Move from custom admin page → WordPress Customizer API integration (ThemeForest standard)

### 5.3 Analytics Dashboard
- Conversations per day chart
- Most asked questions
- Conversion tracking (chat → add-to-cart → checkout)
- Token usage and cost estimation
- Top products asked about

**Files to modify**:
- `class-symbiotic-admin.php` — Customizer migration
- `class-wcaic-admin.php` — setup wizard, analytics
- `class-wcaic-conversation-logger.php` — analytics queries
- New: `class-wcaic-analytics.php` — dashboard data

---

## Phase 6: Theme Distribution (CRITICAL for marketplace)

### 6.1 Required Files
- `screenshot.png` (1200×900px) — theme preview
- `readme.txt` — WordPress.org format
- `LICENSE` — GPL-2.0-or-later
- `languages/symbiotic-theme.pot` — translation template
- `child-theme/` — starter child theme

### 6.2 Documentation
- Installation guide (PHP, MySQL, WooCommerce prerequisites)
- API key setup (OpenAI / Anthropic walkthrough)
- Brand Knowledge configuration guide
- Persona customization guide
- Troubleshooting FAQ
- Hook & filter reference
- Changelog

### 6.3 Demo Content
- One-click demo import (products, categories, brand knowledge)
- Sample persona presets
- Example FAQ and policies

### 6.4 Code Quality
- Run WPCS (WordPress Coding Standards) linter
- Escape all output (`esc_html`, `esc_attr`, `esc_url`)
- Generate `.pot` file with WP-CLI
- Verify all `__()` / `_e()` calls have correct text domain
- React: add PropTypes or convert to TypeScript

**Files to create**:
- `screenshot.png`
- `readme.txt`
- `LICENSE`
- `languages/symbiotic-theme.pot`
- `child-theme/` directory
- `docs/` directory with markdown guides

---

## Phase 7: Multi-Language AI (MEDIUM PRIORITY)

Inspired by NUACA's trilingual approach:
- Detect customer locale via `get_locale()` or browser language
- Per-language persona calibration (not just translation — adaptation)
- System prompt translated per language
- React frontend i18n (extract all hardcoded strings)
- RTL support for Arabic/Hebrew markets

---

## Implementation Order

```
Week 1-2:  Phase 3 (Security) + Phase 1.1-1.2 (Brand Knowledge + Persona)
Week 3-4:  Phase 2 (WooCommerce tools) + Phase 1.3 (Escalation)
Week 5-6:  Phase 4 (Frontend polish)
Week 7-8:  Phase 5 (Admin UX + Analytics)
Week 9-10: Phase 6 (Distribution files + Documentation)
Week 11:   Phase 7 (Multi-language)
Week 12:   Testing, QA, ThemeForest submission
```

---

## Verification Plan

1. **Security**: Run OWASP ZAP scan on all REST endpoints
2. **Functionality**: Test all 8+7 tools with real WooCommerce products
3. **Responsive**: Test on Chrome DevTools (iPhone SE, iPad, Desktop)
4. **Accessibility**: Run axe-core audit, keyboard-only navigation test
5. **Performance**: Lighthouse score > 80 on all categories
6. **WPCS**: Run `phpcs --standard=WordPress` on all theme/plugin PHP
7. **i18n**: Verify `.pot` file includes all translatable strings
8. **Documentation**: Follow setup guide on fresh WordPress install
9. **AI Quality**: Test persona presets with 20+ shopping scenarios
10. **Streaming**: Load test SSE endpoint (10 concurrent streams)

---

## Key Architectural Decisions

1. **Plugin + Theme model** (keep them separate) — theme handles UI/layout, plugin handles AI logic. This allows the plugin to work with other themes too (future revenue stream).
2. **Brand Knowledge in wp_options** (not custom tables) — simple, exportable, no migration headaches.
3. **System prompt assembly** (not RAG) — for brand knowledge, direct injection is better than vector search. The knowledge base is small enough to fit in context window. RAG reserved for very large FAQ/policy sets (Phase 7+).
4. **React stays as vanilla JSX** (no TypeScript migration now) — ship faster, convert later.
5. **CSS variables over Tailwind** — already established, no build tool change needed.

---

## Existing Codebase Reference

### Theme Files (`wp-content/themes/symbiotic-theme/`)
| File | Purpose | Lines |
|------|---------|-------|
| `style.css` | Theme declaration | 11 |
| `functions.php` | Bootstrap, WC support, class loading | 74 |
| `inc/class-symbiotic-admin.php` | Theme settings admin (5 tabs: colors, layout, chat, typography, advanced) | 796 |
| `inc/class-symbiotic-assets.php` | Enqueue React bundle, localize `symbioticData` | ~130 |
| `inc/class-symbiotic-rest.php` | Theme REST endpoints (theme-data, navigation) | ~90 |
| `inc/class-symbiotic-wc-check.php` | WooCommerce dependency validation | ~50 |
| `front-page.php`, `page.php`, `singular.php` | Template stubs (React handles rendering) | ~3 each |

### Plugin Files (`wp-content/plugins/wc-ai-chatbot/includes/`)
| File | Purpose | Lines |
|------|---------|-------|
| `class-wcaic-plugin.php` | Singleton bootstrap, hooks | 398 |
| `class-wcaic-rest-api.php` | 5 REST endpoints (chat, stream, clear, cart, welcome) | ~300 |
| `class-wcaic-ai-client.php` | Abstract AI client, function-call loop, system prompt | ~200 |
| `class-wcaic-ai-client-openai.php` | OpenAI completions + streaming | ~250 |
| `class-wcaic-ai-client-anthropic.php` | Anthropic messages + streaming | ~280 |
| `class-wcaic-tool-definitions.php` | 8 tool schemas (OpenAI + Anthropic format) | 142 |
| `class-wcaic-tool-executor.php` | Tool execution, WooCommerce integration | 523 |
| `class-wcaic-security.php` | Prompt injection detection (30+ patterns) | 131 |
| `class-wcaic-rate-limiter.php` | Sliding window rate limiting | 59 |
| `class-wcaic-session-manager.php` | Conversation history (WC session + transients) | ~100 |
| `class-wcaic-conversation-logger.php` | DB logging table | ~120 |
| `class-wcaic-admin.php` | Plugin settings page (4 tabs) | 308 |
| `class-wcaic-embeddings.php` | Semantic search via OpenAI embeddings | ~150 |

### React Frontend (`wp-content/themes/symbiotic-theme/assets/src/`)
| Component | Purpose |
|-----------|---------|
| `App.jsx` | Root, CSS variable injection |
| `ChatInterface.jsx` | Layout orchestrator |
| `LeftPanel.jsx` | Chat panel |
| `RightPanel.jsx` | Product browsing panel |
| `RightSidebar.jsx` | Navigation sidebar |
| `TopBar.jsx` | Header (bot identity, cart) |
| `WelcomeScreen.jsx` | Greeting, category/brand chips |
| `MessageList.jsx` | Chat history |
| `MessageBubble.jsx` | Message rendering |
| `InputBar.jsx` | Text input |
| `ProductPanel.jsx` | Product card grid/carousel |
| `ProductDetailPanel.jsx` | Single product view |
| `CartSidebar.jsx` | Cart overlay |
| `CheckoutPanel.jsx` | Checkout flow |
| `QuickReplies.jsx` | Suggested actions |
| `hooks/useChat.js` | Chat state management |
| `hooks/useCart.js` | Cart state |
| `utils/api.js` | REST client |
| `utils/sse.js` | SSE parser |

---

**Prepared by:** Vahan Hovhannisyan + Claude (Є pair)  
**Date:** April 8, 2026  
**Next:** Begin Phase 1 (Brand Ambassador) + Phase 3 (Security) in parallel
