import React from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import './ui/midad.css'
import { AppProvider } from './lib/store'
import App from './App'

createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <HashRouter>
      <AppProvider>
        <App />
      </AppProvider>
    </HashRouter>
  </React.StrictMode>
)
