import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { Appearance, I18nManager } from 'react-native'
import type { Session } from '@supabase/supabase-js'
import AsyncStorage from '@react-native-async-storage/async-storage'
import { supabase } from './supabase'
import { phoneToAuthEmail } from './config'
import type { Plan, Profile, Role, Subscriber } from './types'
import { DARK, LIGHT, Palette } from './theme'

export type Access = 'loading' | 'anon' | 'trial' | 'active' | 'expired' | 'suspended' | 'member_suspended'
export type ThemeMode = 'light' | 'dark' | 'auto'

interface Ctx {
  session: Session | null
  profile: Profile | null
  subscriber: Subscriber | null
  plan: Plan | null
  plans: Plan[]
  roles: Role[]
  access: Access
  trialDays: number
  ready: boolean
  c: Palette
  isDark: boolean
  themeMode: ThemeMode
  setThemeMode: (m: ThemeMode) => void
  signIn: (phone: string, password: string) => Promise<void>
  signOut: () => Promise<void>
  refresh: () => Promise<void>
}

const C = createContext<Ctx | null>(null)
export const useApp = () => {
  const v = useContext(C)
  if (!v) throw new Error('useApp خارج AppProvider')
  return v
}

const THEME_KEY = 'midad.theme'

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<Session | null>(null)
  const [profile, setProfile] = useState<Profile | null>(null)
  const [subscriber, setSubscriber] = useState<Subscriber | null>(null)
  const [plans, setPlans] = useState<Plan[]>([])
  const [roles, setRoles] = useState<Role[]>([])
  const [ready, setReady] = useState(false)
  const [themeMode, setThemeModeState] = useState<ThemeMode>('auto')
  const [systemDark, setSystemDark] = useState(Appearance.getColorScheme() === 'dark')

  useEffect(() => {
    // العربية من اليمين — بنيةً في التطبيق كلّه
    if (!I18nManager.isRTL) {
      I18nManager.allowRTL(true)
      I18nManager.forceRTL(true)
    }
    const sub = Appearance.addChangeListener(({ colorScheme }) => setSystemDark(colorScheme === 'dark'))
    AsyncStorage.getItem(THEME_KEY).then((v) => {
      if (v === 'light' || v === 'dark' || v === 'auto') setThemeModeState(v)
    })
    return () => sub.remove()
  }, [])

  const setThemeMode = useCallback((m: ThemeMode) => {
    setThemeModeState(m)
    AsyncStorage.setItem(THEME_KEY, m).catch(() => {})
  }, [])

  const isDark = themeMode === 'dark' || (themeMode === 'auto' && systemDark)
  const c = isDark ? DARK : LIGHT

  const loadPublic = useCallback(async () => {
    const [p, r] = await Promise.all([
      supabase.from('plans').select('*').eq('is_active', true).order('sort'),
      supabase.from('roles').select('key,name_ar,blurb_ar,sort').order('sort'),
    ])
    if (p.data) setPlans(p.data as Plan[])
    if (r.data) setRoles(r.data as Role[])
  }, [])

  const loadMe = useCallback(async (uid?: string) => {
    if (!uid) { setProfile(null); setSubscriber(null); return }
    const { data: prof } = await supabase.from('profiles').select('*').eq('id', uid).maybeSingle()
    setProfile((prof as Profile) || null)
    if (prof?.subscriber_id) {
      const { data: sub } = await supabase.from('subscribers').select('*').eq('id', prof.subscriber_id).maybeSingle()
      setSubscriber((sub as Subscriber) || null)
    } else setSubscriber(null)
  }, [])

  const refresh = useCallback(async () => {
    const { data } = await supabase.auth.getSession()
    setSession(data.session)
    await loadMe(data.session?.user?.id)
  }, [loadMe])

  useEffect(() => {
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
    setProfile(null); setSubscriber(null)
  }, [])

  const plan = useMemo(() => plans.find((p) => p.id === subscriber?.plan_id) || null, [plans, subscriber?.plan_id])

  const trialDays = useMemo(() => {
    if (!subscriber?.trial_ends_at) return 0
    return Math.max(0, Math.ceil((new Date(subscriber.trial_ends_at).getTime() - Date.now()) / 86400000))
  }, [subscriber?.trial_ends_at])

  const access: Access = useMemo(() => {
    if (!ready) return 'loading'
    if (!session) return 'anon'
    if (!profile) return 'anon'
    if (profile.status === 'suspended') return 'member_suspended'
    if (!subscriber) return 'anon'
    if (subscriber.status === 'suspended') return 'suspended'
    if (subscriber.status === 'active') return 'active'
    if (subscriber.status === 'expired') return 'expired'
    return trialDays > 0 ? 'trial' : 'expired'
  }, [ready, session, profile, subscriber, trialDays])

  return (
    <C.Provider value={{
      session, profile, subscriber, plan, plans, roles, access, trialDays, ready,
      c, isDark, themeMode, setThemeMode, signIn, signOut, refresh,
    }}>
      {children}
    </C.Provider>
  )
}
