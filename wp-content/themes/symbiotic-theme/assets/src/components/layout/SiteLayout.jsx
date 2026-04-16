import React, { useRef, useEffect } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import KnowledgePanel from './KnowledgePanel.jsx';
import BeingPanel from './BeingPanel.jsx';
import PageRouter from '../pages/PageRouter.jsx';
import AiResponseZone from './AiResponseZone.jsx';
import ChatBar from './ChatBar.jsx';

export default function SiteLayout() {
  const { state } = useWorkspace();
  const aiZoneRef = useRef(null);

  useEffect(() => {
    if (state.messages.length > 0 && aiZoneRef.current) {
      setTimeout(() => aiZoneRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
    }
  }, [state.messages.length]);

  return (
    <div className={`sym-shell ${!state.leftOpen ? 'sym-shell--left-collapsed' : ''} ${!state.rightOpen ? 'sym-shell--right-collapsed' : ''}`}>
      {/* LEFT — Knowledge */}
      <KnowledgePanel />

      {/* MIDDLE — Experience */}
      <div className="sym-experience">
        <div className="sym-experience-scroll">
          <PageRouter />
          {state.messages.length > 0 && (
            <div ref={aiZoneRef}>
              <AiResponseZone />
            </div>
          )}
        </div>
        <ChatBar />
      </div>

      {/* RIGHT — Being/Presence */}
      <BeingPanel />
    </div>
  );
}
