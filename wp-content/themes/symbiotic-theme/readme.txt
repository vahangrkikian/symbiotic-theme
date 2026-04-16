=== Symbiotic Theme ===
Contributors: symbiotic
Tags: woocommerce, ai, chatbot, shopping, react, e-commerce
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WooCommerce shopping interface. A full-screen React workspace where customers shop through conversation with an AI brand ambassador.

== Description ==

Symbiotic Theme transforms WooCommerce into a conversational commerce experience. Instead of browsing catalogs, customers talk to your AI brand ambassador — a configurable AI personality that knows your products, policies, and brand voice.

**Key Features:**

* **AI Brand Ambassador** — Not a generic chatbot. A configurable persona that speaks in your brand's voice, knows your FAQ, policies, and product catalog.
* **13 AI Shopping Tools** — Search products, compare items, manage cart, track orders, estimate shipping, apply coupons — all via natural language.
* **3-Panel Workspace** — Left sidebar (navigation/history), center body (multi-modal content), right sidebar (contextual intelligence).
* **Inline Rich Cards** — AI responses include product cards, comparison tables, order status, shipping estimates, and checkout CTAs directly in the conversation.
* **Dual AI Provider** — Choose OpenAI (GPT-4o) or Anthropic (Claude) with encrypted API key storage.
* **Brand Knowledge Base** — Upload your brand story, FAQ, shipping/return policies. The AI references these to answer accurately.
* **Persona Presets** — Friendly Shop, Luxury Boutique, Tech Expert, Enthusiastic Guide — or create a custom persona with spectrum sliders.
* **Real-time Streaming** — SSE streaming for instant token-by-token AI responses.
* **Dark Theme** — Premium dark interface with gold accent (fully customizable colors).
* **Security** — Rate limiting, prompt injection detection, encrypted API keys, CSP headers.

**Requires:** WooCommerce 8.0+ and WC AI Chatbot plugin (included).

== Installation ==

1. Upload the `symbiotic-theme` folder to `/wp-content/themes/`
2. Upload the `wc-ai-chatbot` plugin folder to `/wp-content/plugins/`
3. Activate WooCommerce, then the WC AI Chatbot plugin, then the Symbiotic Theme
4. Go to Appearance > Symbiotic Theme — the setup wizard will guide you through:
   - Choose AI provider (Anthropic or OpenAI)
   - Enter your API key
   - Select a persona preset
5. Visit your site — the AI workspace is live

== Frequently Asked Questions ==

= Do I need an API key? =
Yes. You need either an OpenAI API key or an Anthropic API key. The AI assistant uses this to generate responses.

= How much does it cost to run? =
The theme itself is a one-time purchase. API costs depend on usage — typically $0.01-$0.05 per conversation for GPT-4o-mini, or $0.03-$0.15 for Claude Sonnet.

= Can I customize the AI's personality? =
Yes. Go to Appearance > Symbiotic Theme > AI Persona. Choose a preset or configure custom rules, prohibited topics, selling posture, formality, and more.

= Can I add my own brand knowledge? =
Yes. Go to Appearance > Symbiotic Theme > Brand Knowledge. Add your brand story, FAQ, shipping policy, return policy, warranty info, and custom knowledge.

= Does it work on mobile? =
Yes. The interface is responsive — sidebars collapse on tablet, single-column on mobile.

== Changelog ==

= 1.0.0 =
* Initial release
* 3-panel workspace interface (left sidebar, center body, right context)
* 13 AI shopping tools with function calling
* Brand knowledge base (6 sections)
* AI persona engine (4 presets + custom)
* Dual provider support (OpenAI + Anthropic)
* SSE streaming
* Security hardening (rate limiting, injection detection, encrypted keys)
* Setup wizard for first-run configuration
* Gold/dark branded admin interface
