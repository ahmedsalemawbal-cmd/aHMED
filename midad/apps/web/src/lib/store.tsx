import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import type { Session } from '@supabase/supabase-js'
import { supabase } from './supabase'
import { phoneToAuthEmail, normalizePhone } from './config'
import type {
  GeneralSettings, Plan, Profile, PublicPaymentSettings, Role, Subscriber,
} from './types'
import { applyTheme, getTheme, ThemeMode } from './theme'

export type AccessState = 'loading' | 'anon' | 'trial' | 'active' | 'expired' | 'suspended' | 'member_suspended'

interface AppValue {
  session: Session | null
  profile: Profile | null
  subscriber: Subscriber | null
  plan: Plan | null
  plans: Plan[]
  roles: Role[]
  general: GeneralSettings
  payment: PublicPaymentSettings
  trialDays: number
  access: AccessState
  isAdmin: boolean
  ready: boolean
  theme: ThemeMode
  setTheme: (t: ThemeMode) => void
  refresh: () => Promise<void>
  signIn: (phone: string, password: string) => Promise<void>
  signOut: () => Promise<void>
  toast: (msg: string, kind?: 'ok' | 'danger') => void
}

const Ctx = createContext<AppValue | null>(null)
export const useApp = () => {
  const v = useContext(Ctx)
  if (!v) throw new Error('useApp خارج AppProvider')
  return v
}

const DEFAULT_GENERAL: GeneralSettings = {
  platform_name: 'مِداد', tagline: 'منصّة الملفّات المدرسية والجداول',
  whatsapp: '966500000000', email: 'support@midad.sa', working_hours: 'الأحد — الخميس · 9 صباحًا إلى 5 مساءً',
}
const DEFAULT_PAYMENT: PublicPaymentSettings = {
  payments_enabled: false, beneficiary: '', bank: '', iban: '', tax_rate: 0, tax_number: '', show_tax: false,
}

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<Session | null>(null)
  const [profile, setProfile] = useState<Profile | null>(null)
  const [subscriber, setSubscriber] = useState<Subscriber | null>(null)
  const [plans, setPlans] = useState<Plan[]>([])
  const [roles, setRoles] = useState<Role[]>([])
  const [general, setGeneral] = useState<GeneralSettings>(DEFAULT_GENERAL)
  const [payment, setPayment] = useState<PublicPaymentSettings>(DEFAULT_PAYMENT)
  const [isAdmin, setIsAdmin] = useState(false)
  const [ready, setReady] = useState(false)
  const [theme, setThemeState] = useState<ThemeMode>(getTheme())
  const [toastMsg, setToastMsg] = useState<{ msg: string; kind: 'ok' | 'danger' } | null>(null)
  const toastTimer = useRef<any>(null)

  const toast = useCallback((msg: string, kind: 'ok' | 'danger' = 'ok') => {
    setToastMsg({ msg, kind })
    clearTimeout(toastTimer.current)
    toastTimer.current = setTimeout(() => setToastMsg(null), 2800)
  }, [])

  const setTheme = useCallback((t: ThemeMode) => {
    setThemeState(t); applyTheme(t)
    supabase.auth.getUser().then(({ data }) => {
      if (data.user) supabase.from('profiles').update({ theme_pref: t }).eq('id', data.user.id).then(() => {})
    })
  }, [])

  const loadPublic = useCallback(async () => {
    const [p, r, s] = await Promise.all([
      supabase.from('plans').select('*').eq('is_active', true).order('sort'),
      supabase.from('roles').select('*').order('sort'),
      supabase.from('platform_settings').select('key,value'),
    ])
    if (p.data) setPlans(p.data as Plan[])
    if (r.data) setRoles(r.data as Role[])
    for (const row of (s.data || []) as any[]) {
      if (row.key === 'general') setGeneral({ ...DEFAULT_GENERAL, ...row.value })
      if (row.key === 'payment_public') setPayment({ ...DEFAULT_PAYMENT, ...row.value })
    }
  }, [])

  const loadMe = useCallback(async (uid: string | undefined) => {
    if (!uid) { setProfile(null); setSubscriber(null); setIsAdmin(false); return }
    const { data: prof } = await supabase.from('profiles').select('*').eq('id', uid).maybeSingle()
    setProfile((prof as Profile) || null)
    if (prof?.theme_pref && ['light', 'dark', 'auto'].includes(prof.theme_pref)) {
      setThemeState(prof.theme_pref as ThemeMode); applyTheme(prof.theme_pref as ThemeMode)
    }
    if (prof?.subscriber_id) {
      const { data: sub } = await supabase.from('subscribers').select('*').eq('id', prof.subscriber_id).maybeSingle()
      setSubscriber((sub as Subscriber) || null)
    } else setSubscriber(null)
    const { data: adm } = await supabase.from('platform_admins').select('user_id').eq('user_id', uid).maybeSingle()
    setIsAdmin(!!adm)
  }, [])

  const refresh = useCallback(async () => {
    const { data } = await supabase.auth.getSession()
    setSession(data.session)
    await loadMe(data.session?.user?.id)
  }, [loadMe])

  useEffect(() => {
    applyTheme(getTheme())
    let alive = true
    ;(async () => {
      const { data } = await supabase.auth.getSession()
      if (!alive) return
      setSession(data.session)
      await Promise.all([loadPublic(), loadMe(data.session?.user?.id)])
      if (alive) setReady(true)
    })()
    const { data: sub } = supabase.auth.onAuthStateChange((_e, s) => {
      setSession(s)
      loadMe(s?.user?.id)
    })
    return () => { alive = false; sub.subscription.unsubscribe() }
  }, [loadPublic, loadMe])

  const signIn = useCallback(async (phone: string, password: string) => {
    const { error } = await supabase.auth.signInWithPassword({ email: phoneToAuthEmail(phone), password })
    if (error) {
      if (/rate|too many/i.test(error.message)) throw new Error('محاولاتٌ كثيرة — انتظر دقيقة ثمّ حاول مرّة أخرى.')
      throw new Error('بيانات الدخول غير صحيحة')
    }
    const { data } = await supabase.auth.getUser()
    if (data.user) {
      await supabase.from('profiles').update({ last_login_at: new Date().toISOString() }).eq('id', data.user.id)
      await loadMe(data.user.id)
    }
  }, [loadMe])

  const signOut = useCallback(async () => {
    await supabase.auth.signOut()
    setProfile(null); setSubscriber(null); setIsAdmin(false)
  }, [])

  const plan = useMemo(
    () => plans.find((p) => p.id === subscriber?.plan_id) || null,
    [plans, subscriber?.plan_id])

  const trialDays = useMemo(() => {
    if (!subscriber?.trial_ends_at) return 0
    const ms = new Date(subscriber.trial_ends_at).getTime() - Date.now()
    return Math.max(0, Math.ceil(ms / 86400000))
  }, [subscriber?.trial_ends_at])

  const access: AccessState = useMemo(() => {
    if (!ready) return 'loading'
    if (!session) return 'anon'
    if (isAdmin && !profile?.subscriber_id) return 'active'
    if (!profile) return 'anon'
    if (profile.status === 'suspended') return 'member_suspended'
    if (!subscriber) return 'anon'
    if (subscriber.status === 'suspended') return 'suspended'
    if (subscriber.status === 'active') return 'active'
    if (subscriber.status === 'expired') return 'expired'
    return trialDays > 0 ? 'trial' : 'expired'
  }, [ready, session, profile, subscriber, trialDays, isAdmin])

  const value: AppValue = {
    session, profile, subscriber, plan, plans, roles, general, payment,
    trialDays, access, isAdmin, ready, theme, setTheme, refresh, signIn, signOut, toast,
  }

  return (
    <Ctx.Provider value={value}>
      {children}
      {toastMsg && (
        <div className={'mdd-toast' + (toastMsg.kind === 'danger' ? ' mdd-toast--danger' : '')} role="status">
          <span>{toastMsg.msg}</span>
        </div>
      )}
    </Ctx.Provider>
  )
}

export { normalizePhone }
