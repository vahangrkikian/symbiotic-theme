import React, { createContext, useContext, useReducer, useCallback, useEffect } from 'react';
import { workspaceReducer, initialState } from './workspaceReducer.js';
import { postChat, postStream, getWelcome, getCart, getCartDirect } from '../utils/api.js';
import { readStream } from '../utils/sse.js';

const WorkspaceContext = createContext(null);

export function useWorkspace() {
  const ctx = useContext(WorkspaceContext);
  if (!ctx) throw new Error('useWorkspace must be used within WorkspaceProvider');
  return ctx;
}

/**
 * Build extra data from AI attachments for inline rendering.
 */
function buildExtraFromAttachments(attachments) {
  if (!Array.isArray(attachments) || !attachments.length) return {};
  const extra = {};
  let lastAttachment = null;

  for (const att of attachments) {
    lastAttachment = att;
    switch (att.type) {
      case 'products':       extra.products      = att;        break;
      case 'product_detail': extra.productDetail  = att.product; break;
      case 'cart':           extra.cart           = att.data;   break;
      case 'cart_action':    extra.cartAction     = att;        break;
      case 'checkout':       extra.checkout       = att.data;   break;
      case 'order':          extra.order          = att.data;   break;
      case 'orders':         extra.orders         = att.data;   break;
      case 'policies':       extra.policies       = att.data;   break;
      case 'comparison':     extra.comparison     = att.data;   break;
      case 'shipping':       extra.shipping       = att.data;   break;
      case 'calculator':     extra.calculator     = att.data;   break;
    }
  }

  extra.lastAttachment = lastAttachment;
  extra.quickReplies = buildQuickReplies(attachments);
  return extra;
}

function buildQuickReplies(attachments) {
  if (!attachments?.length) return [];
  const last = attachments[attachments.length - 1];
  const replies = [];
  switch (last.type) {
    case 'products':
      replies.push({ type: 'message', text: 'Tell me more about the first one' });
      replies.push({ type: 'message', text: 'Show me more products' });
      break;
    case 'cart_action':
      if (last.action === 'add') {
        replies.push({ type: 'message', text: 'View my cart' });
        replies.push({ type: 'message', text: 'Keep shopping' });
        replies.push({ type: 'message', text: 'Checkout now' });
      }
      break;
    case 'cart':
      replies.push({ type: 'message', text: 'Checkout' });
      replies.push({ type: 'message', text: 'Continue shopping' });
      replies.push({ type: 'message', text: 'Estimate shipping' });
      break;
    case 'comparison':
      replies.push({ type: 'message', text: 'Tell me more about these' });
      break;
    case 'orders':
      replies.push({ type: 'message', text: 'Track my latest order' });
      break;
    case 'shipping':
      replies.push({ type: 'message', text: 'Proceed to checkout' });
      break;
    case 'calculator':
      replies.push({ type: 'message', text: 'What paper stock do you recommend?' });
      replies.push({ type: 'message', text: 'What are the turnaround options?' });
      break;
  }
  return replies.slice(0, 3);
}

/**
 * Handle common commands locally without calling the AI.
 * Returns true if handled, false to pass through to AI.
 */
function handleLocalCommand(text, dispatch, navigate) {
  // Cart commands.
  if (/\b(show|view|see|open|my)\b.*\b(cart|basket)\b/i.test(text) || text === 'view my cart' || text === 'show my cart') {
    dispatch({ type: 'APPEND_BOT_MESSAGE', content: 'Here\'s your cart.', extra: {} });
    navigate('cart');
    return true;
  }

  // Checkout commands.
  if (/\b(checkout|check out|proceed|place.?order|pay now|complete.?order)\b/i.test(text)) {
    dispatch({ type: 'APPEND_BOT_MESSAGE', content: 'Let\'s complete your order.', extra: {} });
    navigate('checkout');
    return true;
  }

  // Browse/products commands.
  if (/^(browse|products|show products|all products|shop)$/i.test(text)) {
    dispatch({ type: 'APPEND_BOT_MESSAGE', content: 'Here are our products.', extra: {} });
    navigate('products');
    return true;
  }

  return false;
}

export function WorkspaceProvider({ children }) {
  const [state, dispatch] = useReducer(workspaceReducer, initialState);
  const symbioticData = window.symbioticData || {};

  // ── Initialize: fetch welcome data + cart on mount ──
  useEffect(() => {
    // Auto-navigate to product if URL is a product page.
    const initialProductId = symbioticData.initialProductId;
    if (initialProductId && parseInt(initialProductId, 10) > 0) {
      dispatch({ type: 'NAVIGATE', view: 'product', params: { productId: parseInt(initialProductId, 10) } });
    }

    getWelcome()
      .then(resp => {
        if (resp.success) {
          dispatch({ type: 'SET_WELCOME_DATA', categories: resp.categories || [], brands: resp.brands || [] });
        }
      })
      .catch(() => {});

    getCartDirect()
      .then(data => {
        if (data && data.item_count !== undefined) {
          dispatch({ type: 'SET_CART', data, count: data.item_count || 0 });
        }
      })
      .catch(() => {});
  }, []);

  // ── Sync cart after bot messages with cart-related attachments ──
  useEffect(() => {
    const last = state.messages[state.messages.length - 1];
    if (last?.role === 'bot' && !last.isStreaming && (last.extra?.cart || last.extra?.cartAction || last.extra?.checkout)) {
      getCart()
        .then(resp => {
          if (resp.success && resp.cart) {
            dispatch({ type: 'SET_CART', data: resp.cart, count: resp.cart.item_count || 0 });
          }
        })
        .catch(() => {});
    }
  }, [state.messages]);

  // ── Navigation ──
  const navigate = useCallback((view, params) => {
    dispatch({ type: 'NAVIGATE', view, params });
  }, []);

  const goBack = useCallback(() => {
    dispatch({ type: 'GO_BACK' });
  }, []);

  // ── Send Message ──
  const sendMessage = useCallback((text) => {
    if (!text?.trim()) return;
    dispatch({ type: 'SET_ERROR', error: null });
    dispatch({ type: 'APPEND_USER_MESSAGE', text });

    // Local command detection — handle without AI for instant response.
    const lower = text.toLowerCase().trim();
    if (handleLocalCommand(lower, dispatch, navigate)) return;

    if (symbioticData.isStreaming) {
      sendViaStream(text, dispatch);
    } else {
      sendViaFetch(text, dispatch);
    }
  }, [state.activeView, symbioticData.isStreaming, navigate]);

  const clearChat = useCallback(() => {
    dispatch({ type: 'CLEAR_CHAT' });
  }, []);

  const value = {
    state, dispatch,
    navigate, goBack, sendMessage, clearChat,
  };

  return (
    <WorkspaceContext.Provider value={value}>
      {children}
    </WorkspaceContext.Provider>
  );
}

// ── Chat send helpers (module-level to avoid stale closures) ──

async function sendViaFetch(text, dispatch) {
  dispatch({ type: 'SET_THINKING', value: true });
  try {
    const resp = await postChat(text);
    if (resp.success) {
      const extra = buildExtraFromAttachments(resp.attachments || []);
      dispatch({ type: 'APPEND_BOT_MESSAGE', content: resp.reply || '', extra });
    } else {
      dispatch({ type: 'APPEND_BOT_MESSAGE', content: 'Something went wrong. Please try again.' });
    }
  } catch (err) {
    dispatch({ type: 'APPEND_BOT_MESSAGE', content: 'Network error. Please check your connection.' });
  } finally {
    dispatch({ type: 'SET_THINKING', value: false });
  }
}

async function sendViaStream(text, dispatch) {
  dispatch({ type: 'SET_THINKING', value: true });
  dispatch({ type: 'START_STREAMING' });

  let fullText = '';
  let stashedAtt = [];

  try {
    const response = await postStream(text);
    if (!response.ok || !response.body) throw new Error('Stream unavailable');

    await readStream(response, {
      onStatus: () => dispatch({ type: 'SET_THINKING', value: false }),
      onToken: (token) => {
        fullText += token;
        dispatch({ type: 'UPDATE_STREAMING', content: fullText });
      },
      onAttachments: (atts) => { stashedAtt = atts || []; },
      onDone: () => {
        const extra = buildExtraFromAttachments(stashedAtt);
        dispatch({ type: 'FINALIZE_STREAMING', content: fullText, extra });
      },
      onError: (d) => {
        dispatch({ type: 'SET_ERROR', error: d.message || 'Streaming error' });
      },
    });
  } catch (err) {
    dispatch({ type: 'SET_ERROR', error: err.message });
  }
}
