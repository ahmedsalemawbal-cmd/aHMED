import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import type { Session } from '@supabase/supabase-js'
import { supabase } from './supabase'
import { applyLang, readLang, t as translate } from './i18n'
import type { Lang } from './i18n'
import { applyTheme, readTheme } from './theme'
import type { Theme } from './theme'

export type Role = 'admin' | 'branch' | 'rep' | 'partner' | 'customer' | 'employee' | ''

export type Profile = {
  id: string
  full_name: string
  role: Role
  phone: string | null
  active: boolean
}

type Ctx = {
  session: Session | null
  profile: Profile | null
  role: Role
  ready: boolean
  lang: Lang
  setLang: (l: Lang) => void
  t: (ar: string) => string
  theme: Theme
  setTheme: (x: Theme) => void
  refresh: () => Promise<void>
  signOut: () => Promise<void>
}

const AppCtx = createContext<Ctx | null>(null)

export function useApp() {
  const v = useContext(AppCtx)
  if (!v) throw new Error('useApp خارج المزوّد')
  return v
}

export function AppProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null)
  const [profile, setProfile] = useState<Profile | null>(null)
  const [ready, setReady] = useState(false)
  const [lang, setLangState] = useState<Lang>('ar')
  const [theme, setThemeState] = useState<Theme>('system')

  // اللغةُ والسمةُ تُطبَّقان فورًا — قبل أيّ نداءِ شبكة
  useEffect(() => {
    const l = readLang(); applyLang(l); setLangState(l)
    const th = readTheme(); applyTheme(th); setThemeState(th)
  }, [])

  const loadProfile = useCallback(async (s: Session | null) => {
    if (!s) { setProfile(null); return }
    const { data } = await supabase
      .from('profiles')
      .select('id,full_name,role,phone,active')
      .eq('id', s.user.id)
      .maybeSingle()
    setProfile((data as Profile) ?? null)
  }, [])

  useEffect(() => {
    let alive = true

    /*
     * حارسُ مهلة.
     *
     * لو تعطّلت الشبكةُ أو تأخّرت القاعدةُ بقي الموقعُ على شاشة تحميلٍ أبديّة
     * — والزائرُ الذي جاء يقرأ عن الأبواب لا شأن له بجلستنا أصلًا. فبعد ثلاث
     * ثوانٍ يُفتح الموقعُ زائرًا، ويُكمَّل الملفُّ الشخصيُّ حين يصل.
     *
     *     الموقعُ العامُّ لا ينتظر تسجيلَ الدخول.
     */
    const bail = setTimeout(() => { if (alive) setReady(true) }, 3000)

    supabase.auth.getSession().then(async ({ data }) => {
      if (!alive) return
      setSession(data.session)
      await loadProfile(data.session)
      if (alive) { clearTimeout(bail); setReady(true) }
    })

    const { data: sub } = supabase.auth.onAuthStateChange(async (_e, s) => {
      if (!alive) return
      setSession(s)
      await loadProfile(s)
    })

    return () => { alive = false; clearTimeout(bail); sub.subscription.unsubscribe() }
  }, [loadProfile])

  const setLang = useCallback((l: Lang) => { applyLang(l); setLangState(l) }, [])
  const setTheme = useCallback((x: Theme) => { applyTheme(x); setThemeState(x) }, [])
  const refresh = useCallback(async () => { await loadProfile(session) }, [loadProfile, session])
  const signOut = useCallback(async () => { await supabase.auth.signOut() }, [])

  const value = useMemo<Ctx>(() => ({
    session, profile,
    role: profile?.role ?? '',
    ready, lang, setLang,
    // `lang` في التبعيّات كي يُعاد الرسمُ عند تبديل اللغة —
    // فـ`translate` تقرأ لغةً على مستوى الوحدة لا من الحالة
    t: (ar: string) => translate(ar),
    theme, setTheme, refresh, signOut,
  }), [session, profile, ready, lang, theme, setLang, setTheme, refresh, signOut])

  return <AppCtx.Provider value={value}>{children}</AppCtx.Provider>
}
