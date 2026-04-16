# WC AI Chatbot — Complete Plugin Reference

> Deep-reviewed: 2026-04-06 | Version: 1.2.0 | 23 files total

---

## Table of Contents

1. [Plugin Entry Point](#1-plugin-entry-point)
2. [File Map](#2-file-map)
3. [Bootstrap & Initialization](#3-bootstrap--initialization)
4. [REST API Endpoints](#4-rest-api-endpoints)
5. [AI Providers](#5-ai-providers)
6. [Function-Calling Loop](#6-function-calling-loop)
7. [Tool Definitions (8 tools)](#7-tool-definitions-8-tools)
8. [Tool Executor](#8-tool-executor)
9. [Attachments Pipeline](#9-attachments-pipeline)
10. [Frontend Widget (JS)](#10-frontend-widget-js)
11. [Product Cards & Carousel](#11-product-cards--carousel)
12. [Quick Replies](#12-quick-replies)
13. [Welcome Flow](#13-welcome-flow)
14. [SSE Streaming](#14-sse-streaming)
15. [Session & Conversation History](#15-session--conversation-history)
16. [Security Layer](#16-security-layer)
17. [Rate Limiting](#17-rate-limiting)
18. [Admin Settings](#18-admin-settings)
19. [Conversation Logger](#19-conversation-logger)
20. [Semantic Search (Embeddings)](#20-semantic-search-embeddings)
21. [CSS Architecture](#21-css-architecture)
22. [Hooks & Filters](#22-hooks--filters)
23. [Known Bugs Fixed](#23-known-bugs-fixed)
24. [Settings Storage Reference](#24-settings-storage-reference)

---

## 1. Plugin Entry Point

**File:** `wc-ai-chatbot.php` (172 lines)

- Defines constants:
  - `WCAIC_VERSION` = `'1.2.0'`
  - `WCAIC_FILE`, `WCAIC_PATH`, `WCAIC_URL`, `WCAIC_BASENAME`
- PSR-4 autoloader: maps `WCAIC_*` class names → `includes/class-wcaic-*.php`
- Activation hook: creates `wp_wcaic_conversation_log` DB table, sets default plugin settings
- Deactivation hook: clears all rate-limit transients
- Entry: `WCAIC_Plugin::get_instance()`

---

## 2. File Map

```
wc-ai-chatbot/
├── wc-ai-chatbot.php                    Main plugin file, constants, autoloader
│
├── includes/
│   ├── class-wcaic-plugin.php           Singleton bootstrap, hooks registration
│   ├── class-wcaic-rest-api.php         5 REST endpoints, chat orchestration
│   ├── class-wcaic-ai-client.php        Abstract base: function-call loop, attachments
│   ├── class-wcaic-ai-client-openai.php OpenAI completions + streaming
│   ├── class-wcaic-ai-client-anthropic.php Anthropic messages + streaming
│   ├── class-wcaic-ai-response.php      Normalized response object
│   ├── class-wcaic-tool-definitions.php 8 tool schemas (OpenAI + Anthropic format)
│   ├── class-wcaic-tool-executor.php    Tool execution, WooCommerce integration
│   ├── class-wcaic-security.php         Prompt injection detection (30+ patterns)
│   ├── class-wcaic-rate-limiter.php     Sliding window rate limiting
│   ├── class-wcaic-session-manager.php  Conversation history (WC session + transients)
│   ├── class-wcaic-conversation-logger.php Custom DB logging table
│   ├── class-wcaic-logger.php           WooCommerce logger integration
│   ├── class-wcaic-admin.php            Settings page, API key encryption
│   ├── class-wcaic-conv-log-admin.php   Admin: conversation log viewer
│   ├── class-wcaic-embeddings.php       Semantic search via OpenAI embeddings
│   └── class-wcaic-product-importer.php Browser-like HTTP product scraping
│
├── assets/
│   ├── js/
│   │   ├── chatbot-widget.js            Frontend chat widget (900+ lines, vanilla JS)
│   │   ├── admin-embeddings.js          Embeddings index admin UI
│   │   └── admin-importer.js            Product importer admin UI
│   └── css/
│       ├── chatbot-widget.css           Widget styles (300+ lines)
│       ├── chatbot-admin.css            Settings page styles
│       ├── admin-conv-log.css           Conversation log styles
│       ├── admin-embeddings.css         Embeddings page styles
│       └── admin-importer.css           Importer page styles
│
└── templates/
    ├── chat-widget.php                  Widget HTML (toggle button + panel)
    ├── admin-settings.php               Settings form
    ├── admin-embeddings.php             Embeddings manager form
    └── admin-importer.php               Importer form
```

---

## 3. Bootstrap & Initialization

**File:** `includes/class-wcaic-plugin.php` (398 lines)

Singleton. Loaded on `plugins_loaded`.

### What it does:
- Reads all settings from `wcaic_settings` option, caches them
- Registers:
  - `admin_menu` → Settings page + Conversation Log page
  - `rest_api_init` → 5 REST routes
  - `woocommerce_loaded` → `maybe_init_wc_session_for_rest()` — critical: starts WC session + cart for REST context
  - `wp_enqueue_scripts` → loads widget CSS + JS **only on WooCommerce pages** (shop, product, cart, checkout)
  - `wp_footer` → includes `templates/chat-widget.php`
  - `woocommerce_after_shop_loop_item` + `woocommerce_single_product_summary` → renders "Talk with AI" button
  - `before_woocommerce_init` → HPOS compatibility declaration
  - `wcaic_daily_cleanup` (cron) → prune old logs + expired transients

### `enqueue_frontend_assets()`:
- Enqueues `chatbot-widget.css` + `chatbot-widget.js`
- `wp_localize_script('wcaic-widget', 'wcaicData', [...])` passes to JS:
  - `restUrl`: REST base URL
  - `nonce`: `wp_create_nonce('wp_rest')`
  - `storeName`: blog name
  - `settings`: widget position, primary color, greeting, widget enabled flag
  - `isStreaming`: whether streaming endpoint should be used

### `maybe_init_wc_session_for_rest()`:
- Checks `is_rest_request()` + request matches `wcaic/v1`
- Calls `WC()->session->init()`, `WC()->cart->get_cart()` to bootstrap WC cart for REST

---

## 4. REST API Endpoints

**File:** `includes/class-wcaic-rest-api.php` (672 lines)

**Namespace:** `wcaic/v1`

All endpoints:
- Require `X-WP-Nonce` header with valid `wp_rest` nonce
- Verify nonce via `check_rest_nonce()` (called in `permission_callback`)
- Manage session ID: uses `WC()->session->get_session_id()` OR MD5 hash of user IP + User-Agent

---

### `POST /chat`

Non-streaming. Returns full reply in one response.

**Parameters:**
- `message` (string, required, 1–2000 chars) — sanitized with `sanitize_textarea_field()`

**Flow:**
1. Rate limit check (AI: 10/min)
2. Security screen via `WCAIC_Security::screen_message()`
3. Load conversation history from session
4. Append user message to history
5. Initialize AI client (`WCAIC_AI_Client::create()`) based on settings
6. Call `$client->process_chat($history, $tools, $settings)`
7. Append assistant reply to history
8. Save updated history to session
9. Log to DB (if logging enabled)
10. Return `{success, reply, attachments[], cart{item_count, total}}`

**Response shape:**
```json
{
  "success": true,
  "reply": "Found 3 running shoes.",
  "attachments": [
    {"type": "products", "products": [...], "total": 3, "query": "..."}
  ],
  "cart": {"item_count": 2, "total": "89.99"}
}
```

---

### `POST /stream`

SSE streaming. Emits tokens as they arrive from the AI provider.

**Parameters:** Same as `/chat`

**Response:** `Content-Type: text/event-stream`

**SSE Events emitted (in order):**
```
event: status
data: {"state":"thinking"}

event: token
data: {"text":"Found "}

event: token
data: {"text":"3 shoes."}

event: attachments
data: {"attachments":[{"type":"products","products":[...]}]}

event: done
data: {"cart":{"item_count":1,"total":"29.99"}}

event: error
data: {"message":"Rate limit exceeded","retry_after":45}
```

**Flow:** Same as `/chat` but calls `$client->process_chat_stream()` which internally:
- Runs function-calling loop
- Calls `emit_sse()` helper after each tool execution (no token emits during tool phase)
- Streams text tokens directly from AI provider's SSE feed
- Emits `attachments` event before `done`

---

### `POST /clear`

Clears conversation history for the current session.

**Flow:**
1. Gets session ID
2. Calls `WCAIC_Session_Manager::clear_conversation($session_id)`
3. Returns `{success: true, message: "Conversation cleared."}`

---

### `GET /cart`

Returns current WooCommerce cart state.

**Response:**
```json
{
  "success": true,
  "cart": {
    "item_count": 2,
    "total": "89.99"
  }
}
```

---

### `GET /welcome`

Returns top categories and brands for the welcome flow buttons.

**Response:**
```json
{
  "success": true,
  "categories": [
    {"name": "Audio", "slug": "audio", "count": 45},
    {"name": "Cameras", "slug": "cameras", "count": 28}
  ],
  "brands": [
    {"name": "Sony", "slug": "sony", "count": 18},
    {"name": "Bose", "slug": "bose", "count": 12}
  ]
}
```

Fetched once on first widget open, displayed as clickable chips.

---

## 5. AI Providers

### Abstract Base — `WCAIC_AI_Client` (470 lines)

**Factory:** `WCAIC_AI_Client::create($settings)` returns either `WCAIC_AI_Client_OpenAI` or `WCAIC_AI_Client_Anthropic`

**Key methods:**
- `process_chat($history, $tools, $settings)` — blocking function-calling loop
- `process_chat_stream($history, $tools, $settings)` — streaming variant
- `collect_attachment($tool_name, $tool_result)` — builds structured attachment from tool result
- `build_system_prompt($template, $context)` — replaces `{{store_name}}`, `{{categories}}`, `{{currency}}` etc.

**System prompt rules (default, 9 rules):**
1. No emoji
2. No bullet lists — prose only
3. 1–2 sentences max per reply
4. Always call `search_products` before recommending anything
5. Call tools for all actions (cart, details, etc.)
6. Never make up product names or prices
7. If a tool fails, say so
8. Focus on shopping — decline off-topic
9. Keep tone warm but brief

---

### OpenAI Client — `WCAIC_AI_Client_OpenAI` (494 lines)

| Detail | Value |
|--------|-------|
| Endpoint | `https://api.openai.com/v1/chat/completions` |
| Default model | `gpt-4o-mini` |
| Tool format | `{type: 'function', function: {name, description, parameters}}` |
| System prompt | First message in array: `{role: 'system', content: '...'}` |
| Tool calls in response | `message.tool_calls[].function.{name, arguments}` |
| Tool result format | `{role: 'tool', tool_call_id: '...', content: '<JSON string>'}` |
| Streaming | True SSE via `fopen()` + `stream_context_create()` |
| Stream format | `data: {"choices":[{"delta":{"content":"token"}}]}` |

**`send_openai_stream()`:**
- Opens stream via fopen with streaming context
- Reads line by line: `data: {...}` lines parsed as JSON
- Accumulates delta text and delta `tool_calls`
- On `finish_reason: tool_calls` → executes tools (not streamed), then re-enters stream
- On `finish_reason: stop` → emits final text, exits loop

**Error handling codes:**
- `429` → rate limit / quota exceeded
- `401` → invalid API key
- `403` → access denied
- `5xx` → server error

---

### Anthropic Client — `WCAIC_AI_Client_Anthropic` (642 lines)

| Detail | Value |
|--------|-------|
| Endpoint | `https://api.anthropic.com/v1/messages` |
| API version header | `anthropic-version: 2023-06-01` |
| Default model | `claude-sonnet-4-6` |
| Tool format | `{name, description, input_schema: {type, properties, required}}` |
| System prompt | Top-level `system` param (NOT in messages array) |
| Tool calls in response | `content[].type === 'tool_use'` → `{id, name, input}` |
| Tool result format | `{role: 'user', content: [{type: 'tool_result', tool_use_id, content: '<JSON>'}]}` |
| Streaming | True SSE via Anthropic stream protocol |

**SSE event types parsed:**
- `content_block_start` → new text or tool_use block (indexed)
- `content_block_delta` → `text_delta` (append text) or `input_json_delta` (append tool input JSON)
- `content_block_stop` → finalize block
- `message_delta` → `stop_reason` signals end

**`normalize_messages()`:**
- Ensures strict user/assistant alternation (Anthropic requirement)
- Merges consecutive same-role messages
- Strips any system-role messages from the array

**`fix_tool_use_inputs()`:**
- PHP `json_decode` converts `{}` → `[]` (empty array) by default
- This function casts all `tool_use.input` fields back to `stdClass` (becomes `{}` when re-encoded)
- Applied before every Anthropic API call

---

## 6. Function-Calling Loop

**Located in:** `WCAIC_AI_Client::process_chat()` (abstract base)

```
Max 5 iterations (configurable in settings)

ITERATION:
  1. Build request body (messages + tools + system prompt)
  2. Send to AI provider
  3. Parse response → WCAIC_AI_Response object
  
  IF response.tool_calls is not empty:
    a. Append assistant message (with tool_calls) to history
    b. For each tool_call:
       - Execute via WCAIC_Tool_Executor::execute($name, $args)
       - Call collect_attachment($name, $result) → builds attachment
       - Append tool result to history
    c. Loop back to step 1
    
  IF response.text is not empty:
    - Append assistant text to history
    - Return {text, attachments[], token_usage, loop_iterations}
    
  IF iteration limit reached:
    - Return last text (or empty) + whatever attachments were collected
```

**History management:**
- Before sending, history is capped to last 20 messages (configurable)
- System prompt prepended per-provider requirements

---

## 7. Tool Definitions (8 Tools)

**File:** `includes/class-wcaic-tool-definitions.php` (332 lines)

`WCAIC_Tool_Definitions::get_tools($format)` returns tools in `'openai'` or `'anthropic'` format.

---

### Tool 1: `search_products`

```
Purpose: Search WooCommerce products

Parameters:
  query      (string, optional) — keyword search
  category   (string, optional) — category slug
  brand      (string, optional) — brand/PA attribute slug
  min_price  (number, optional)
  max_price  (number, optional)
  on_sale    (boolean, optional) — filter to sale items only
  per_page   (integer, optional, default 5, max 10)

Returns:
  products[] — array of product objects (see below)
  total      — integer total matching products
  query      — echo of search params used
```

**Product object fields returned:**
```
id, name, slug, price, regular_price, sale_price,
on_sale, image_url, permalink, short_description,
category_names[], brand, rating, review_count,
in_stock, stock_status
```

---

### Tool 2: `get_product_details`

```
Purpose: Full product data including variations, gallery, attributes

Parameters:
  product_id (integer, required)

Returns:
  All search_products fields PLUS:
  description (full)
  gallery_images[]
  attributes{} (name → values[])
  variations[] (id, price, attributes, in_stock, stock_qty)
  sku
  weight
  dimensions
  reviews[] (rating, author, content)
```

---

### Tool 3: `add_to_cart`

```
Parameters:
  product_id   (integer, required)
  quantity     (integer, default 1)
  variation_id (integer, optional — for variable products)
  variation    (object, optional — e.g. {"attribute_pa_color": "blue"})

Returns:
  success         (boolean)
  product_name    (string)
  quantity_added  (integer)
  cart_total      (string, formatted)
  cart_count      (integer)
  
  If variable product without variation_id:
    available_variations[] (id, price, attributes)
```

---

### Tool 4: `remove_from_cart`

```
Parameters:
  item_key (string, required) — WC cart item key

Returns:
  success       (boolean)
  removed_item  (string) — product name
  cart_total    (string)
  cart_count    (integer)
```

---

### Tool 5: `update_cart_quantity`

```
Parameters:
  item_key (string, required)
  quantity (integer, required) — 0 removes the item

Returns:
  success       (boolean)
  product_name  (string)
  new_quantity  (integer)
  cart_total    (string)
  cart_count    (integer)
```

---

### Tool 6: `get_cart`

```
Parameters: none

Returns:
  items[] {
    item_key, product_id, name, quantity,
    price, line_total, image_url
  }
  item_count       (integer)
  totals {
    subtotal, discount, shipping, total
  }
  coupons_applied[] (code, discount_amount)
```

---

### Tool 7: `apply_coupon`

```
Parameters:
  coupon_code (string, required)

Returns:
  success         (boolean)
  coupon_code     (string)
  discount_amount (string, formatted)
  message         (string) — WC success/error message
```

---

### Tool 8: `get_checkout_url`

```
Parameters: none

Returns:
  checkout_url   (string)
  cart_summary {
    items[]  {name, quantity, line_total}
    total    (string)
  }
```

---

## 8. Tool Executor

**File:** `includes/class-wcaic-tool-executor.php` (797 lines)

### Execution path:

```php
WCAIC_Tool_Executor::execute($tool_name, $args)
  → validate $tool_name against whitelist (exactly 8 names)
  → check rate limit (30/min per session)
  → sanitize parameters per tool
  → call specific method
  → return result array OR WP_Error
```

### `search_products()` details:

1. Sanitize all params (`sanitize_title()` for category/brand, `absint()` for IDs, `floatval()` for prices)
2. **Redundancy detection**: if `$query` matches `$category` or `$brand` name → skip `'s'` (search query) param from WP_Query to avoid keyword-title mismatch returning 0 results
3. Build `WP_Query` args: `post_type=product`, `post_status=publish`, `tax_query` for category/brand, `meta_query` for price/stock, `meta_key=_sale_price` if `on_sale`
4. If `$query` not redundant: add `'s' => $query`
5. **Transient cache**: MD5 of all params → 5 minute cache
6. **Semantic search first**: if embeddings enabled and indexed_count > 0, try cosine similarity → falls back to WP_Query if no results above threshold (0.25)
7. **Batch optimization**: after getting post IDs, load all categories, brands, images in 3 queries (not N+1)
8. Return enriched product array

### `add_to_cart()` details:

1. `absint($product_id)`, `absint($quantity)`, `absint($variation_id)`
2. Validate product is `WC_Product` and `is_purchasable()`
3. If `$product->is_type('variable')`:
   - If no `variation_id` given → return `available_variations[]`
   - Validate variation is in stock
4. `WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation)`
5. `WC()->cart->calculate_totals()`
6. Return success data

---

## 9. Attachments Pipeline

**Defined in:** `WCAIC_AI_Client::collect_attachment()` (abstract base)

### Flow:

```
Tool executes → result array returned
  → collect_attachment($tool_name, $result) called
  → builds typed attachment object
  → pushed to $this->attachments[]

After loop ends:
  → attachments[] returned alongside AI reply text
  → REST endpoint adds to response body (non-stream)
  → OR emitted as 'attachments' SSE event (stream)
```

### Attachment types built per tool:

| Tool | Attachment type | Key fields |
|------|-----------------|------------|
| `search_products` | `products` | products[], total, query |
| `get_product_details` | `product_detail` | product{} |
| `add_to_cart` | `cart_action` | action: 'add', product_name, quantity_added, cart_total |
| `remove_from_cart` | `cart_action` | action: 'remove', removed_item |
| `update_cart_quantity` | `cart_action` | action: 'update', new_quantity |
| `get_cart` | `cart` | items[], item_count, totals{} |
| `apply_coupon` | `cart_action` | action: 'coupon', discount_amount |
| `get_checkout_url` | `checkout` | checkout_url, cart_summary{} |

### Frontend conversion — `buildExtraFromAttachments(attachments)`:

```javascript
// Called in JS after receiving API response
// Converts flat attachments[] → structured extra{} object on message

extra = {
  products: [...],          // from 'products' attachment
  productDetail: {...},     // from 'product_detail' attachment
  cart: {...},              // from 'cart' attachment
  cartAction: {...},        // from 'cart_action' attachment
  checkout: {...},          // from 'checkout' attachment
  quickReplies: [...]       // generated by buildQuickReplies(attachments)
}
```

---

## 10. Frontend Widget (JS)

**File:** `assets/js/chatbot-widget.js` (900+ lines) — Vanilla JS, no dependencies

### State object:

```javascript
const state = {
  open: false,                 // panel open/closed
  sending: false,              // awaiting AI response
  messages: [],                // [{role, content, extra, time}]
  sessionKey: 'wcaic_chat_' + location.hostname,
  welcomeLoaded: false,
  streamController: null       // AbortController for SSE
}
```

### Initialization (`DOMContentLoaded`):

1. `loadSession()` — restores messages from sessionStorage
2. Attaches event listeners:
   - Toggle button click → `toggleChat()`
   - Close button click → `closeChat()`
   - Clear button click → `clearSession()` + POST `/clear`
   - Input `keydown`: Enter (no shift) → `sendMessage()`
   - Product "Add to Cart" buttons (event delegation on `.wcaic-product-card`)
   - "Talk with AI" buttons (event delegation on `.wcaic-ask-ai`)

### `sendMessage()`:

1. Read textarea value, trim
2. Guard: `state.sending === true` → abort
3. `addMessage('user', text)` — renders user bubble immediately
4. Set `state.sending = true`, show typing indicator
5. If `wcaicData.isStreaming` → `sendViaStream(text)`
   else → `sendViaFetch(text)`

### `sendViaFetch(text)` — non-streaming:

```javascript
fetch(wcaicData.restUrl + 'wcaic/v1/chat', {
  method: 'POST',
  headers: {'Content-Type': 'application/json', 'X-WP-Nonce': wcaicData.nonce},
  body: JSON.stringify({message: text})
})
.then(r => r.json())
.then(data => {
  // data.reply → text message
  // data.attachments → buildExtraFromAttachments()
  addMessage('bot', data.reply, extra)
})
```

### `sendViaStream(text)` — SSE streaming:

```javascript
// Uses ReadableStream + TextDecoder
fetch('/wp-json/wcaic/v1/stream', {...})
.then(r => {
  const reader = r.body.getReader()
  // createStreamBubble() → temp div with blinking cursor
  // Read chunks → parseSSE() → dispatch by event type:
  //   'token'       → appendToStreamBubble(text)
  //   'attachments' → stash for finishStream()
  //   'done'        → finishStream(fullText, attachments, cartData)
  //   'error'       → showError(message)
  //   'status'      → updateTypingIndicator(state)
})
```

### `parseSSE(chunk)`:

- Splits on `\n\n` → individual events
- Each event: `event: <name>\ndata: <json>`
- Returns array of `{event, data}` objects

### `addMessage(role, content, extra)`:

1. Push to `state.messages`
2. Call `renderMessage(msg)`
3. `saveSession()`
4. Scroll to bottom

### `renderMessage(msg)`:

- `role === 'user'` → `renderBubble()`
- `role === 'bot'` + `extra.products` → `renderProducts()` (no text bubble if products)
- `role === 'bot'` + text → `renderBubble()`, then optionally `renderCartCard()`, `renderQuickReplies()`

### `renderBubble(text)`:

- Wraps in `.wcaic-bubble.wcaic-bubble--bot` or `--user`
- Inline markdown: `**bold**` → `<strong>`, `[text](url)` → `<a>`, `![alt](src)` → `<img>`, `\n` → `<br>`
- All text through `escHtml()` before markdown, links through `escAttr()`

---

## 11. Product Cards & Carousel

### `renderProducts(products, query)`:

```
If products.length >= 3:
  → carousel mode (.wcaic-carousel)
  → prev/next nav buttons (desktop only)
  → CSS scroll-snap
Else:
  → grid mode (.wcaic-product-grid)
```

### Product card HTML structure:

```html
<div class="wcaic-product-card">
  <div class="wcaic-card-image">
    <img src="{image_url}" loading="lazy" alt="{name}">
    <!-- If on_sale: -->
    <span class="wcaic-sale-badge">-{discount}%</span>
  </div>
  <div class="wcaic-card-body">
    <!-- If brand: -->
    <span class="wcaic-brand">{brand}</span>
    <h4 class="wcaic-card-name">{name}</h4>
    <div class="wcaic-price">
      <!-- If on sale: -->
      <del>{regular_price}</del>
      <span class="wcaic-sale-price">{sale_price}</span>
      <!-- Else: -->
      <span>{price}</span>
    </div>
    <!-- If rating > 0: -->
    <div class="wcaic-rating">
      {buildStarRating(rating)}
      <span>({review_count})</span>
    </div>
    <!-- If out of stock: -->
    <span class="wcaic-stock-badge">Out of Stock</span>
    <div class="wcaic-card-actions">
      <button class="wcaic-add-to-cart" data-product-id="{id}"
        {disabled if out of stock}>Add to Cart</button>
      <a href="{permalink}" class="wcaic-view-btn" target="_blank">View</a>
    </div>
  </div>
</div>
```

### `buildStarRating(rating)`:

- 5 stars, supports half-stars
- Uses Unicode: ★ (full), ½ (half), ☆ (empty)
- Returns HTML string

### Sale badge calculation:

```javascript
discount = Math.round((1 - sale_price / regular_price) * 100)
// Shown as "-30%"
```

### Carousel navigation:

- `[←]` button: `scrollLeft -= cardWidth + gap`
- `[→]` button: `scrollLeft += cardWidth + gap`
- CSS `scroll-snap-type: x mandatory` on wrapper
- Mobile: no nav arrows shown, touch swipe works natively

---

## 12. Quick Replies

### `buildQuickReplies(attachments)`:

Generates contextual suggestion chips based on last action:

| Last attachment type | Quick replies generated |
|---------------------|------------------------|
| `products` | "Show details", "Add first to cart", "Filter by price" |
| `cart_action` (add) | "View my cart", "Keep shopping", "Checkout" (link) |
| `cart_action` (remove/update) | "View updated cart", "Keep shopping" |
| `cart` | "Checkout now" (link), "Continue shopping", "Apply coupon" |
| `checkout` | Direct checkout link button |

### `renderQuickReplies(replies)`:

- Each reply: `.wcaic-quick-reply` pill button
- `type: 'message'` → triggers `sendMessage(text)` on click
- `type: 'link'` → `window.open(url)` on click
- Max 4 per message
- All quick replies removed when user sends a new message

---

## 13. Welcome Flow

### Trigger:

- `fetchWelcomeData()` called on first `openChat()` when `state.messages.length === 0`

### `fetchWelcomeData()`:

```javascript
fetch(wcaicData.restUrl + 'wcaic/v1/welcome', {
  headers: {'X-WP-Nonce': wcaicData.nonce}
})
.then(data => renderWelcomeButtons(data.categories, data.brands))
```

### `renderWelcomeButtons(categories, brands)`:

- Shows greeting message: `wcaicData.settings.greeting`
- Below greeting: section header "Browse by category" + category chip buttons
- Section header "Shop by brand" + brand chip buttons
- Each button click → `sendMessage('Show me {category/brand} products')`

---

## 14. SSE Streaming

### PHP side (`class-wcaic-rest-api.php` + Anthropic/OpenAI clients):

```php
// Headers set before any output:
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Emit helper:
function emit_sse($event, $data) {
  echo "event: {$event}\n";
  echo 'data: ' . wp_json_encode($data) . "\n\n";
  ob_flush();
  flush();
}
```

### OpenAI streaming (`send_openai_stream()`):

- Opens connection via `fopen()` with `http` stream context (`method: POST`, timeout 30s)
- Reads line by line until EOF or `[DONE]`
- Parses `data: {...}` lines as JSON
- Extracts `choices[0].delta.content` → emits `token` event
- Extracts `choices[0].delta.tool_calls` → accumulates, executes on `finish_reason: tool_calls`

### Anthropic streaming (`send_anthropic_stream()`):

- Same fopen approach
- Parses event types from Anthropic SSE protocol:
  - `content_block_start`: new block (text or tool_use), tracked by index
  - `content_block_delta`:
    - `text_delta` → emits `token` event
    - `input_json_delta` → appends to tool input JSON buffer
  - `content_block_stop`: finalizes block
  - `message_delta`: captures `stop_reason` (end_turn / tool_use)

### JS side (`sendViaStream()`):

- `ReadableStream` + `TextDecoder`
- `createStreamBubble()`: creates a temp bot bubble div with CSS blinking cursor (`::after`)
- `appendToStreamBubble(text)`: adds text content to the streaming bubble
- `finishStream(fullText, attachments, cartData)`:
  - Removes temp streaming bubble
  - Calls `addMessage('bot', fullText, buildExtraFromAttachments(attachments))`
  - Updates cart badge count from `cartData`

---

## 15. Session & Conversation History

### Storage backends (in priority order):

1. **WC Session** (`WC()->session->set/get`):
   - Key: `wcaic_conversation_{session_id}`
   - Available when WooCommerce session is active
2. **WordPress Transients** (`set_transient/get_transient`):
   - Key: `wcaic_conv_{MD5(session_id)[:20]}`  (≤172 char total, transient key limit)
   - Expiration: 2 hours

### `WCAIC_Session_Manager` methods:

```php
get_conversation($session_id)    // → array of message objects
save_conversation($session_id, $messages)  // auto-trims to 40 messages max
clear_conversation($session_id)  // removes from both WC session + transients
```

### Message format in history:

**OpenAI format:**
```json
[
  {"role": "user", "content": "Show me shoes"},
  {"role": "assistant", "content": null, "tool_calls": [...]},
  {"role": "tool", "tool_call_id": "call_abc", "content": "{...}"},
  {"role": "assistant", "content": "Found 3 shoes."}
]
```

**Anthropic format:**
```json
[
  {"role": "user", "content": "Show me shoes"},
  {"role": "assistant", "content": [{"type": "tool_use", "id": "...", "name": "search_products", "input": {}}]},
  {"role": "user", "content": [{"type": "tool_result", "tool_use_id": "...", "content": "{...}"}]},
  {"role": "assistant", "content": [{"type": "text", "text": "Found 3 shoes."}]}
]
```

### Browser-side persistence:

```javascript
saveSession()   // JSON.stringify(state.messages.slice(-30)) → sessionStorage
loadSession()   // parse + restoreMessages() → re-renders all bubbles
clearSession()  // sessionStorage.removeItem(state.sessionKey)
```

---

## 16. Security Layer

**File:** `includes/class-wcaic-security.php` (205 lines)

### `screen_message($message)` returns:

```php
{
  'safe'    => bool,
  'cleaned' => string,   // sanitized message
  'reply'   => string,   // denial message if not safe
  'reason'  => string    // internal reason code
}
```

### Detection categories:

**System override patterns (regex):**
- `ignore (previous|all|prior)? instructions?`
- `new instructions?:`
- `you are now (a|an|the)?`
- `forget (everything|all|what)`
- `act as (a|an|the)?`
- `pretend (to be|you are)`
- `your (new|actual|real|true) (role|purpose|instructions?|task)`
- `disregard (previous|your|all)`
- `override (your|all|previous)`

**Extraction patterns:**
- `(what is|show me|tell me|reveal|print|output|display|write out|repeat) your? (system prompt|instructions|context|rules|guidelines)`
- `initial (prompt|message|instructions?)`

**Jailbreak patterns:**
- `(dan|jailbreak|developer|god|unrestricted|unlimited) mode`
- `(no restrictions?|no (safety|ethical) guidelines?|bypass (safety|restrictions?))`

**Code injection patterns:**
- `<script`, `javascript:`, `onerror=`, `onload=`, `eval\s*\(`
- `document\.(cookie|location|write)`, `window\.(location|open)`

**Structural anomalies:**
- Delimiter sequences: `|||`, `-----`, `=====`, `#####`
- Base64 payloads: strings matching `[A-Za-z0-9+/]{100,}={0,2}`
- Special character ratio: > 30% non-alphanumeric (ignoring space, `.`, `,`, `!`, `?`, `'`, `"`, `(`, `)`)
- Max length: 2000 characters

---

## 17. Rate Limiting

**File:** `includes/class-wcaic-rate-limiter.php` (175 lines)

### Algorithm: Sliding Window

```php
// On each request:
$key = 'wcaic_rl_' . md5($session_id . '_ai');
$timestamps = get_transient($key);                  // array of Unix timestamps
$now = time();
$window = 60;                                        // 1 minute

// Remove timestamps outside window
$timestamps = array_filter($timestamps, fn($t) => ($now - $t) < $window);

if (count($timestamps) >= $limit) {
  $retry_after = $window - ($now - min($timestamps));
  return ['allowed' => false, 'retry_after' => $retry_after];
}

$timestamps[] = $now;
set_transient($key, $timestamps, $window * 2);
return ['allowed' => true, 'current' => count($timestamps)];
```

### Limits (defaults):

| Type | Limit | Key suffix |
|------|-------|------------|
| AI requests | 10/min per session | `_ai` |
| Tool calls | 30/min per session | `_tool` |

Both configurable via admin settings.

---

## 18. Admin Settings

**File:** `includes/class-wcaic-admin.php` (400+ lines)

**Menu location:** WooCommerce > AI Chatbot

**Settings registered via Settings API** (`wcaic_settings` option group):

### Section: AI Provider

| Field | Key | Type | Default |
|-------|-----|------|---------|
| Provider | `provider` | select | `openai` |
| OpenAI API Key | `openai_api_key` | password | — |
| OpenAI Model | `openai_model` | select | `gpt-4o-mini` |
| Anthropic API Key | `anthropic_api_key` | password | — |
| Anthropic Model | `anthropic_model` | select | `claude-sonnet-4-6` |

### Section: Chat Widget

| Field | Key | Type | Default |
|-------|-----|------|---------|
| Enable Widget | `widget_enabled` | checkbox | `1` |
| Widget Position | `widget_position` | select | `bottom-right` |
| Primary Color | `primary_color` | color | `#2563eb` |
| Greeting Message | `greeting` | text | `Hi! How can I help?` |
| Enable Streaming | `streaming_enabled` | checkbox | `1` |

### Section: Advanced

| Field | Key | Default |
|-------|-----|---------|
| Custom System Prompt | `system_prompt` | (default 9-rule prompt) |
| Max AI Requests/min | `ai_rate_limit` | `10` |
| Max Tool Calls/min | `tool_rate_limit` | `30` |
| Max Loop Iterations | `max_iterations` | `5` |
| Conversation Logging | `conversation_logging` | `1` |
| Max History Length | `max_history` | `20` |

### API Key Encryption:

- Keys stored in `wcaic_api_keys_encrypted` option as JSON
- Encrypted with `openssl_encrypt()`, cipher: `aes-256-cbc`
- Encryption key: SHA-256 of `SECURE_AUTH_KEY` (WordPress salt)
- IV: random 16 bytes, stored alongside ciphertext as `iv` field
- Display: shown as `sk-••••...{last4chars}` in settings form
- Never output to frontend JS

---

## 19. Conversation Logger

**File:** `includes/class-wcaic-conversation-logger.php` (300+ lines)

### DB Table: `wp_wcaic_conversation_log`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `session_id` | VARCHAR(64) | WC session or IP hash |
| `user_message` | TEXT | Latest user message |
| `ai_reply` | TEXT | Latest AI reply |
| `provider` | VARCHAR(20) | `openai` or `anthropic` |
| `model` | VARCHAR(50) | e.g. `gpt-4o-mini` |
| `messages` | LONGTEXT | Full JSON history |
| `flagged` | TINYINT(1) | 1 if any turn was flagged |
| `prompt_tokens` | INT | Cumulative prompt tokens |
| `completion_tokens` | INT | Cumulative completion tokens |
| `loop_iterations` | INT | Total tool call iterations |
| `message_count` | INT | Total turns in session |
| `ip_hash` | VARCHAR(64) | SHA-256 of user IP |
| `created_at` | DATETIME | First message time |
| `last_activity` | DATETIME | Last update time |

### UPSERT logic:

- If row with `session_id` exists: UPDATE (accumulate tokens/iterations, update messages/last_activity)
- If no row: INSERT new row
- Only writes when `conversation_logging = 1` in settings

### Prune:

- `prune_old($days)`: `DELETE FROM ... WHERE last_activity < DATE_SUB(NOW(), INTERVAL $days DAY)`
- Called by `wcaic_daily_cleanup` cron event

---

## 20. Semantic Search (Embeddings)

**File:** `includes/class-wcaic-embeddings.php` (200+ lines)

**Active only when:** `embedding_enabled = 1` AND at least 1 product indexed

### Model: `text-embedding-3-small` (OpenAI), 512 dimensions

### Indexing:

- `generate_embedding($product_id)`:
  - Builds product text: name + short_description + description + category_names + brand + attribute values + SKU
  - POST to `https://api.openai.com/v1/embeddings`
  - Stores result in postmeta: `_wcaic_embedding` (JSON array), `_wcaic_embedding_hash` (MD5 of input text for change detection)

### Search:

- `search_by_query($query, $per_page)`:
  - Generates embedding for query
  - Loads all indexed product embeddings from postmeta
  - Computes cosine similarity: `dot(a,b) / (|a| * |b|)`
  - Returns product IDs with similarity > 0.25, sorted by score

### Fallback:

If semantic returns no results (or feature disabled) → WP_Query keyword search used instead

---

## 21. CSS Architecture

**File:** `assets/css/chatbot-widget.css` (300+ lines)

### CSS Custom Properties (`:root`):

```css
--wcaic-primary: {from settings, default #2563eb}
--wcaic-dark: #0f0f13
--wcaic-bg: #ffffff
--wcaic-text: #1a1a2e
--wcaic-border: rgba(0,0,0,0.08)
--wcaic-shadow: 0 20px 60px rgba(0,0,0,0.15)
--wcaic-radius: 16px
--wcaic-font: system-ui, -apple-system, sans-serif
```

### Key components:

| Component | Class | Notes |
|-----------|-------|-------|
| Toggle button | `.wcaic-toggle` | 58px circle, spring animation, pulse ring |
| Unread badge | `.wcaic-badge` | Red circle top-right of toggle |
| Chat panel | `.wcaic-panel` | 380–860px, glassmorphism, slide-in animation |
| Header | `.wcaic-header` | Dark bg, store name + "Online" status dot |
| Messages | `.wcaic-messages` | Flex column, `overflow-y: auto` |
| Typing indicator | `.wcaic-typing` | 3 bouncing dots |
| User bubble | `.wcaic-bubble--user` | Primary color bg, white text, right-aligned |
| Bot bubble | `.wcaic-bubble--bot` | Light bg, dark text, left-aligned |
| Product grid | `.wcaic-product-grid` | CSS Grid, 2+ columns |
| Carousel | `.wcaic-carousel` | `overflow-x: scroll`, `scroll-snap-type: x mandatory` |
| Carousel nav | `.wcaic-carousel-nav` | Hidden on mobile |
| Product card | `.wcaic-product-card` | Image + body, hover shadow |
| Sale badge | `.wcaic-sale-badge` | Red pill, absolute top-left of image |
| Brand label | `.wcaic-brand` | Small uppercase text |
| Star rating | `.wcaic-stars` | Gold color |
| Quick reply | `.wcaic-quick-reply` | Pill button, border-only style |
| Welcome chips | `.wcaic-welcome-btn` | Category/brand chips |
| Input area | `.wcaic-input-area` | Sticky bottom, textarea + send btn |

### Animations:

- `@keyframes wcaic-panel-in`: slide up + fade in
- `@keyframes wcaic-bounce`: typing dots
- `@keyframes wcaic-pulse`: toggle button ring
- Streaming cursor: `.wcaic-streaming::after { content: '▋'; animation: blink 1s infinite }`

### Responsive (mobile ≤480px):

- Panel: `position: fixed; inset: 0; border-radius: 0`
- No carousel nav arrows
- Product cards: single column

---

## 22. Hooks & Filters

| Hook | Type | Callback | Purpose |
|------|------|----------|---------|
| `plugins_loaded` | action | `WCAIC_Plugin::get_instance()` | Bootstrap singleton |
| `admin_menu` | action | `add_settings_page()` | WC AI Chatbot + Conv Log menu items |
| `admin_init` | action | `register_settings()` | Settings API fields |
| `admin_enqueue_scripts` | action | `enqueue_admin_assets()` | Admin CSS/JS |
| `rest_api_init` | action | `register_routes()` | Register 5 REST endpoints |
| `woocommerce_loaded` | action | `maybe_init_wc_session_for_rest()` | Init WC cart for REST |
| `wp_enqueue_scripts` | action | `enqueue_frontend_assets()` | Widget CSS + JS |
| `wp_footer` | action | `render_chat_widget()` | Widget HTML |
| `woocommerce_after_shop_loop_item` | action | `render_ask_ai_button()` | "Talk with AI" on shop loop |
| `woocommerce_single_product_summary` | action | `render_ask_ai_button()` | "Talk with AI" on product page |
| `before_woocommerce_init` | action | `declare_hpos_compat()` | HPOS compatibility |
| `wcaic_daily_cleanup` (cron) | action | `daily_cleanup()` | Prune logs + transients |

---

## 23. Known Bugs Fixed

### Bug 1: Anthropic `tool_use.input` Serialization (`[]` vs `{}`)

**Problem:** Tools with no parameters (`get_cart`, `get_checkout_url`) have empty input `{}`.
PHP's `json_decode()` converts `{}` (empty object) to an array `[]`.
When re-serialized to JSON, `[]` is sent to Anthropic, which rejects it expecting an object.

**Fix:** `fix_tool_use_inputs()` in `class-wcaic-ai-client-anthropic.php`:
```php
foreach ($messages as &$message) {
    if (is_array($message['content'])) {
        foreach ($message['content'] as &$block) {
            if (isset($block['type']) && $block['type'] === 'tool_use') {
                $block['input'] = (object) $block['input']; // cast to stdClass
            }
        }
    }
}
```
Applied before every Anthropic API call.

---

### Bug 2: Category Browse Returning Zero Results

**Problem:** When user clicks "Audio" category chip, JS sends message "Show me Audio products".
AI calls `search_products(query="Audio", category="audio")`.
WP_Query with `'s' => 'Audio'` does a keyword search on post titles — products like "Sony WH-1000XM5" don't contain the word "Audio" in their title, so 0 results returned.

**Fix:** Redundancy detection in `WCAIC_Tool_Executor::search_products()`:
```php
// If query matches category name or brand name, omit the 's' param
$query_normalized = strtolower(trim($query));
$category_normalized = strtolower(trim($category));
$brand_normalized = strtolower(trim($brand));

if ($query_normalized === $category_normalized || $query_normalized === $brand_normalized) {
    // Skip adding 's' => $query to WP_Query args
}
```

---

## 24. Settings Storage Reference

| `wp_options` key | Content |
|------------------|---------|
| `wcaic_settings` | Serialized PHP array of all plugin settings |
| `wcaic_api_keys_encrypted` | JSON: `{"openai":{"key":"...","iv":"..."},"anthropic":{...}}` |
| `wcaic_version` | Plugin version string (for upgrade logic) |
| `wcaic_conv_log_db_version` | Schema version (`2`) |

| Transient key pattern | Content | Expiry |
|----------------------|---------|--------|
| `wcaic_conv_{hash}` | JSON-encoded message array | 2 hours |
| `wcaic_rl_{hash}_ai` | Array of timestamps | 2 minutes |
| `wcaic_rl_{hash}_tool` | Array of timestamps | 2 minutes |
| `wcaic_products_{md5}` | Cached search results | 5 minutes |

| WC Session key | Content |
|----------------|---------|
| `wcaic_conversation_{session_id}` | JSON-encoded message array |

| postmeta key | Content |
|-------------|---------|
| `_wcaic_embedding` | JSON float array (512 dims) |
| `_wcaic_embedding_hash` | MD5 of product text (change detection) |
