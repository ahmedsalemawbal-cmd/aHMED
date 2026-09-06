import { Suspense, lazy } from 'react'
import { Route, Routes } from 'react-router-dom'
import SiteLayout from './pages/site/SiteLayout'
import Protected from './pages/portal/Protected'
import Home from './pages/site/Home'
import { FullLoader } from './ui/kit'

/**
 * ══ المسارات ══
 *
 * الرئيسةُ والهيكلُ مستوردان ثابتًا، وما بعدهما مؤجَّل — فالزائرُ يصل
 * إلى الرئيسة أوّلًا في الغالب، فلا يُحمَّل ما لن يراه.
 *
 * والمساراتُ **حقيقيّةٌ لا مجزّأةٌ بعلامة#** لأنّ هذا موقعٌ يُؤرشَف:
 * `/fire-resistant-doors/` يفهرسها جوجل، و`/#/…` لا.
 *
 * وكلُّ مسارٍ عامٍّ يُضاف هنا **يُضاف في `routes.json` أيضًا** — وإلّا
 * عمل للزائر ولم يوجد لجوجل. (البندُ الأخير في CONTRACT.md.)
 */
const About          = lazy(() => import('./pages/site/About'))
const Services       = lazy(() => import('./pages/site/Services'))
const Projects       = lazy(() => import('./pages/site/Projects'))
const Contact        = lazy(() => import('./pages/site/Contact'))
const Privacy        = lazy(() => import('./pages/site/Privacy'))
const ProductArchive = lazy(() => import('./pages/site/ProductArchive'))
const ProductDetail  = lazy(() => import('./pages/site/ProductDetail'))
const Quote          = lazy(() => import('./pages/site/Quote'))
const NotFound       = lazy(() => import('./pages/site/NotFound'))

// ── الحساباتُ واللوحة ──
const Login           = lazy(() => import('./pages/auth/Login'))
const Signup          = lazy(() => import('./pages/auth/Signup'))
const PartnerRegister = lazy(() => import('./pages/auth/PartnerRegister'))
const SetPassword     = lazy(() => import('./pages/auth/SetPassword'))
const PortalLayout    = lazy(() => import('./pages/portal/PortalLayout'))
const Dashboard       = lazy(() => import('./pages/portal/Dashboard'))
const Quotes          = lazy(() => import('./pages/portal/Quotes'))

export default function App() {
  return (
    <Suspense fallback={<FullLoader />}>
      <Routes>
        <Route element={<SiteLayout />}>
          <Route index element={<Home />} />
          <Route path="about"    element={<About />} />
          <Route path="services" element={<Services />} />
          <Route path="projects" element={<Projects />} />
          <Route path="contact"  element={<Contact />} />
          <Route path="privacy"  element={<Privacy />} />

          {/* العائلاتُ الثلاثُ مساراتٌ صريحةٌ لا معاملٌ واحد: العنوانُ
              `/doors` يُكتب في الروابط وفي `routes.json` وفي خريطة
              الموقع، ومعاملٌ حرٌّ يقبل `/anything` فيُرسَل إلى جوجل
              عددٌ لا نهائيٌّ من الصفحات الفارغة. */}
          <Route path="doors"  element={<ProductArchive group="doors" />} />
          <Route path="gypsum" element={<ProductArchive group="gypsum" />} />
          <Route path="strut"  element={<ProductArchive group="strut" />} />

          <Route path="product/:slug" element={<ProductDetail />} />
          <Route path="quote"         element={<Quote />} />

          {/* الحساباتُ داخلَ هيكل الموقع: الداخلُ زائرٌ حتّى يدخل */}
          <Route path="login"            element={<Login />} />
          <Route path="signup"           element={<Signup />} />
          <Route path="partner-register" element={<PartnerRegister />} />
          <Route path="set-password"     element={<SetPassword />} />

          <Route path="*" element={<NotFound />} />
        </Route>

        {/*
          اللوحةُ **خارجَ** هيكل الموقع: لها رأسُها الجانبيُّ وتملأ الشاشة.
          وحارسُ المسار راحةٌ لا أمان — البياناتُ تحرسها السياسات.
        */}
        <Route path="/dashboard" element={<Protected><PortalLayout /></Protected>}>
          <Route index element={<Dashboard />} />
          <Route path="quotes" element={<Quotes />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
