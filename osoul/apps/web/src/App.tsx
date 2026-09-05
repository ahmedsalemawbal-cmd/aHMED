import { Suspense, lazy } from 'react'
import { Route, Routes } from 'react-router-dom'
import SiteLayout from './pages/site/SiteLayout'
import Home from './pages/site/Home'
import { FullLoader } from './ui/kit'

/**
 * ══ المسارات ══
 *
 * الصفحةُ الأولى مستوردةٌ ثابتًا، وما بعدها مؤجَّل. والسببُ أنّ الزائرَ
 * يصل إلى الرئيسة أوّلًا في الغالب، فلا يُحمَّل ما لن يراه.
 *
 * والمساراتُ **حقيقيّةٌ لا مجزّأةٌ بعلامة#** — لأنّ هذا موقعٌ يُؤرشَف:
 * `/fire-resistant-doors/` يُفهرسها جوجل، و`/#/fire-resistant-doors` لا.
 */
const NotFound = lazy(() => import('./pages/site/NotFound'))

export default function App() {
  return (
    <Suspense fallback={<FullLoader />}>
      <Routes>
        <Route element={<SiteLayout />}>
          <Route index element={<Home />} />
          <Route path="*" element={<NotFound />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
