import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles/main.scss';
import apiFetch from '@wordpress/api-fetch';

// --- 1. Define Settings Interface ---
interface BattleLedgerData {
  ajaxUrl: string;
  restUrl: string;
  restNamespace: string;
  nonce: string;
  pluginUrl: string;
  currentUser: number;
  isWooCommerceActive: boolean;
  isDev: boolean;
}

declare global {
  interface Window {
    battleLedgerData: BattleLedgerData;
  }
}

// --- 2. CONFIGURE API ---
const data = window.battleLedgerData || {};

// Configure nonce for authentication
if (data.nonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(data.nonce));
}

// Configure root URL for REST API
if (data.restUrl) {
  apiFetch.use(apiFetch.createRootURLMiddleware(data.restUrl));
}

// --- 3. Mount App ---
const rootElement = document.getElementById('battle-ledger-root');

if (rootElement) {
  const root = ReactDOM.createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
} else {
  console.error('BattleLedger: Root element not found');
}
