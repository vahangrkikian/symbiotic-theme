/**
 * Workspace state reducer — manages all workspace state transitions.
 */

export const initialState = {
  // Layout
  leftOpen: true,
  rightOpen: !!(window.symbioticData?.themeOptions?.showRightSidebar),

  // View routing
  activeView: 'home',     // 'home' | 'products' | 'page' | 'blog' | 'cart' | 'orders'
  viewHistory: [],
  viewParams: {},

  // Chat
  messages: [],
  isStreaming: false,
  isThinking: false,
  error: null,

  // Conversations (localStorage-backed)
  conversations: [],
  activeConversationId: null,

  // Cart
  cartCount: 0,
  cartData: null,

  // Context (right sidebar)
  lastAttachment: null,

  // Welcome data
  categories: [],
  brands: [],
};

export function workspaceReducer(state, action) {
  switch (action.type) {

    // ── View Navigation ──
    case 'NAVIGATE':
      return {
        ...state,
        viewHistory: [...state.viewHistory, { view: state.activeView, params: state.viewParams }],
        activeView: action.view,
        viewParams: action.params || {},
      };

    case 'GO_BACK': {
      const prev = state.viewHistory[state.viewHistory.length - 1];
      if (!prev) return { ...state, activeView: 'welcome', viewParams: {} };
      return {
        ...state,
        viewHistory: state.viewHistory.slice(0, -1),
        activeView: prev.view,
        viewParams: prev.params || {},
      };
    }

    // ── Panel Toggles ──
    case 'TOGGLE_LEFT':
      return { ...state, leftOpen: !state.leftOpen };

    case 'TOGGLE_RIGHT':
      return { ...state, rightOpen: !state.rightOpen };

    case 'TOGGLE_LEFT_SIDEBAR':
      return { ...state, leftOpen: !state.leftOpen };

    case 'TOGGLE_RIGHT_SIDEBAR':
      return { ...state, rightOpen: !state.rightOpen };

    // ── Chat Messages ──
    case 'APPEND_USER_MESSAGE':
      return {
        ...state,
        messages: [...state.messages, {
          id: Date.now(),
          role: 'user',
          content: action.text,
          extra: {},
        }],
      };

    case 'APPEND_BOT_MESSAGE':
      return {
        ...state,
        messages: [...state.messages, {
          id: Date.now() + 1,
          role: 'bot',
          content: action.content,
          extra: action.extra || {},
          isStreaming: false,
        }],
        lastAttachment: action.extra?.lastAttachment || state.lastAttachment,
      };

    case 'START_STREAMING': {
      const placeholderId = Date.now() + 1;
      return {
        ...state,
        isStreaming: true,
        streamingMsgId: placeholderId,
        messages: [...state.messages, {
          id: placeholderId,
          role: 'bot',
          content: '',
          extra: {},
          isStreaming: true,
        }],
      };
    }

    case 'UPDATE_STREAMING':
      return {
        ...state,
        messages: state.messages.map(m =>
          m.id === state.streamingMsgId
            ? { ...m, content: action.content, isStreaming: true }
            : m
        ),
      };

    case 'FINALIZE_STREAMING':
      return {
        ...state,
        isStreaming: false,
        isThinking: false,
        messages: state.messages.map(m =>
          m.id === state.streamingMsgId
            ? { ...m, content: action.content, extra: action.extra || {}, isStreaming: false }
            : m
        ),
        lastAttachment: action.extra?.lastAttachment || state.lastAttachment,
      };

    case 'SET_THINKING':
      return { ...state, isThinking: action.value };

    case 'SET_ERROR':
      return { ...state, error: action.error, isStreaming: false, isThinking: false };

    case 'CLEAR_CHAT':
      return { ...state, messages: [], error: null, isStreaming: false, isThinking: false, lastAttachment: null };

    // ── Cart ──
    case 'SET_CART':
      return { ...state, cartData: action.data, cartCount: action.count ?? state.cartCount };

    case 'SET_CART_COUNT':
      return { ...state, cartCount: action.count };

    // ── Context ──
    case 'SET_ATTACHMENT':
      return { ...state, lastAttachment: action.attachment };

    // ── Welcome Data ──
    case 'SET_WELCOME_DATA':
      return { ...state, categories: action.categories || [], brands: action.brands || [] };

    // ── Conversations ──
    case 'SET_CONVERSATIONS':
      return { ...state, conversations: action.conversations };

    case 'SET_ACTIVE_CONVERSATION':
      return { ...state, activeConversationId: action.id };

    default:
      return state;
  }
}
