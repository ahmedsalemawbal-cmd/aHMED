import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { AppProvider } from './lib/store'
import App from './App'
import './ui/osoul.css'

createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    {/*
      و`basename` ليس تفصيلًا: بدونه يبني المُوجِّهُ روابطَه من الجذر
      (`/doors`) بينما الموقعُ يسكن تحت `/aHMED/` — فكلُّ ضغطةٍ تقفز
      خارجَ الموقع إلى صفحةٍ لا وجودَ لها.

          الأساسُ الذي لا يُمرَّر إلى المُوجِّه يجعل كلَّ رابطٍ يقفز خارجه.

      و`BASE_URL` يضعه Vite من `base` نفسِه، فلا يُكتب المسارُ مرّتين.
    */}
    <BrowserRouter basename={import.meta.env.BASE_URL}>
      <AppProvider>
        <App />
      </AppProvider>
    </BrowserRouter>
  </React.StrictMode>,
)
