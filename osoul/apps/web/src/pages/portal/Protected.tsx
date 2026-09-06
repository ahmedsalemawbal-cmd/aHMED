import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useApp } from '../../lib/store'
import type { Role } from '../../lib/store'
import { FullLoader } from '../../ui/kit'

/**
 * حارسُ المسار.
 *
 * وهو **راحةٌ لا أمان**: يمنع رسمَ شاشةٍ لا تعني صاحبَها. أمّا البيانات
 * فتحرسها السياساتُ في القاعدة — فلو نُزع هذا الحارسُ كلُّه لَرأى
 * المندوبُ شاشةَ المدير **فارغة**، لا مملوءةً بما ليس له.
 *
 *     الحارسُ في الواجهة يمنع الحيرة، والحارسُ في القاعدة يمنع التسرّب.
 */
export default function Protected(
  { children, allow }: { children: ReactNode; allow?: Role[] },
) {
  const { ready, session, role } = useApp()
  const loc = useLocation()

  if (!ready) return <FullLoader />
  if (!session) return <Navigate to="/login" replace state={{ from: loc.pathname }} />
  if (allow && allow.length && !allow.includes(role)) {
    // لا يُقال «ممنوع» لمن دخل بحسابه — يُساق إلى ما يعنيه
    return <Navigate to="/dashboard" replace />
  }
  return <>{children}</>
}
