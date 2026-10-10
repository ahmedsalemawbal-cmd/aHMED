import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { registerSW } from 'virtual:pwa-register';
import App from './App';
import { captureInstallPrompt } from './lib/install-prompt';
import './styles/index.css';

registerSW({ immediate: true });
// before rendering: Chrome may offer installation right after load
captureInstallPrompt(window);

const root = document.getElementById('root');
if (!root) throw new Error('#root missing in index.html');
createRoot(root).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
