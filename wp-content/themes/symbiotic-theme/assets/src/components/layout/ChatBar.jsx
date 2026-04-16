import React, { useState, useCallback, useRef } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import Icon, { AiAvatar } from '../shared/Icon.jsx';

export default function ChatBar() {
  const { state, sendMessage } = useWorkspace();
  const [value, setValue] = useState('');
  const [expanded, setExpanded] = useState(false);
  const inputRef = useRef(null);

  const handleSubmit = useCallback((e) => {
    e.preventDefault();
    if (!value.trim() || state.isStreaming) return;
    sendMessage(value.trim());
    setValue('');
  }, [value, sendMessage, state.isStreaming]);

  const handleKeyDown = useCallback((e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  }, [handleSubmit]);

  return (
    <div className={`sym-chatbar ${expanded || value ? 'sym-chatbar--expanded' : ''}`}>
      <div className="sym-chatbar-inner">
        {state.isThinking && (
          <div className="sym-chatbar-status">
            <AiAvatar size={18} />
            <span>AI is thinking...</span>
            <div className="sym-chatbar-dots"><span/><span/><span/></div>
          </div>
        )}
        <form className="sym-chatbar-form" onSubmit={handleSubmit}>
          <AiAvatar size={24} className="sym-chatbar-avatar" />
          <input
            ref={inputRef}
            type="text"
            className="sym-chatbar-input"
            placeholder="Ask about products, compare prices, track orders..."
            value={value}
            onChange={e => setValue(e.target.value)}
            onKeyDown={handleKeyDown}
            onFocus={() => setExpanded(true)}
            onBlur={() => { if (!value) setExpanded(false); }}
            disabled={state.isStreaming}
            aria-label="Chat with AI assistant"
            autoComplete="off"
          />
          <button
            type="submit"
            className="sym-chatbar-send"
            disabled={!value.trim() || state.isStreaming}
            aria-label="Send"
          >
            <Icon name="send" size={18} />
          </button>
        </form>
      </div>
    </div>
  );
}
