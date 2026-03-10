/**
 * Frontend Live Tournaments — Vite entry point
 *
 * Renders LiveTournamentsApp into every .battleledger-live-tournaments-container
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import LiveTournamentsApp from './LiveTournamentsApp';
import type { LiveTournamentsProps } from './LiveTournamentsApp';
import './styles/live-tournaments.scss';

function init(): void {
  const containers = document.querySelectorAll<HTMLDivElement>('.battleledger-live-tournaments-container');

  containers.forEach((container) => {
    const propsAttr = container.getAttribute('data-props');
    if (!propsAttr) {
      console.error('BattleLedger Live Tournaments: missing data-props');
      return;
    }

    try {
      const props: LiveTournamentsProps = JSON.parse(propsAttr);

      // Configure apiFetch ONCE, outside React lifecycle
      if (props.nonce) apiFetch.use(apiFetch.createNonceMiddleware(props.nonce));
      if (props.apiUrl) apiFetch.use(apiFetch.createRootURLMiddleware(props.apiUrl));

      const root = createRoot(container);
      root.render(<LiveTournamentsApp {...props} />);
    } catch (err) {
      console.error('BattleLedger Live Tournaments: init error', err);
    }
  });
}

// Run when DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
