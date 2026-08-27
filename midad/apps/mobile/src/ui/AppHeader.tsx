import React, { useMemo, useState } from 'react'
import { Modal, Pressable, ScrollView, Text, View, Animated, Easing } from 'react-native'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { useNavigation } from '@react-navigation/native'
import { useApp } from '../lib/store'
import { useNotifications, Note } from '../lib/notifications'
import { RADIUS, SPACE, TYPE, fontFor, elevation } from '../lib/theme'
import { Avatar, T } from './kit'
import {
  IcMenu, IcBell, IcBack, IcClose, IcHome, IcLibrary, IcFiles, IcUser, IcCheck,
  IcCalendar, IcTable, IcSettings, IcLogout, IcMoon, IcSun, IcHelp, IcLogo,
} from './icons'

/* ═══════════ الهيدر ═══════════
   يمينًا: الصورة والتحيّة · يسارًا: الجرس وثلاث الشرطات.
   والصفُّ عاديٌّ: المحرّك RTL فيصفّ من اليمين من نفسه. */

function greeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'صباح الخير'
  if (h < 17) return 'مساء الخير'
  return 'مساء الخير'
}

function firstName(full?: string | null): string {
  if (!full) return 'بك'
  return String(full).trim().split(/\s+/)[0]
}

export function AppHeader({ title, back, subtitle }: {
  title?: string; back?: boolean; subtitle?: string
}) {
  const { c, profile } = useApp()
  const insets = useSafeAreaInsets()
  const nav = useNavigation<any>()
  const { notes, unread, markAllSeen } = useNotifications()
  const [drawer, setDrawer] = useState(false)
  const [bell, setBell] = useState(false)

  const openBell = () => { setBell(true); markAllSeen() }

  return (
    <>
      <View style={{
        paddingTop: insets.top + 6, paddingBottom: 12,
        paddingHorizontal: SPACE.s5, backgroundColor: c.bg,
        flexDirection: 'row', alignItems: 'center', gap: 12,
      }}>
        {back ? (
          <Pressable
            onPress={() => nav.goBack()} hitSlop={10}
            style={{
              width: 40, height: 40, borderRadius: RADIUS.md, backgroundColor: c.card,
              alignItems: 'center', justifyContent: 'center', ...elevation(c, 1),
            }}>
            {/* السهم يشير يمينًا في العربيّة — أي إلى الوراء */}
            <View style={{ transform: [{ scaleX: -1 }] }}><IcBack size={20} color={c.text} /></View>
          </Pressable>
        ) : (
          <Pressable onPress={() => nav.navigate('حسابي')} hitSlop={8}>
            <Avatar name={profile?.full_name || 'م'} size={42} uri={profile?.avatar_url} ring />
          </Pressable>
        )}

        <View style={{ flex: 1 }}>
          {title ? (
            <>
              <Text numberOfLines={1} style={{
                color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.h3,
                textAlign: 'right', writingDirection: 'rtl',
              }}>{title}</Text>
              {subtitle ? (
                <Text numberOfLines={1} style={{
                  color: c.text3, fontFamily: fontFor('500'), fontSize: TYPE.small,
                  textAlign: 'right', writingDirection: 'rtl', marginTop: 1,
                }}>{subtitle}</Text>
              ) : null}
            </>
          ) : (
            <>
              <Text numberOfLines={1} style={{
                color: c.text3, fontFamily: fontFor('500'), fontSize: TYPE.small,
                textAlign: 'right', writingDirection: 'rtl',
              }}>{greeting()}</Text>
              <Text numberOfLines={1} style={{
                color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.h3,
                textAlign: 'right', writingDirection: 'rtl', marginTop: 1,
              }}>{firstName(profile?.full_name)}</Text>
            </>
          )}
        </View>

        <HeaderBtn onPress={openBell} badge={unread}>
          <IcBell size={21} color={c.text2} />
        </HeaderBtn>
        <HeaderBtn onPress={() => setDrawer(true)}>
          <IcMenu size={21} color={c.text2} />
        </HeaderBtn>
      </View>

      <Drawer open={drawer} onClose={() => setDrawer(false)} />
      <NotesSheet open={bell} onClose={() => setBell(false)} notes={notes} />
    </>
  )
}

function HeaderBtn({ children, onPress, badge }: {
  children: React.ReactNode; onPress: () => void; badge?: number
}) {
  const { c } = useApp()
  return (
    <Pressable
      onPress={onPress} hitSlop={8}
      style={({ pressed }) => ({
        width: 40, height: 40, borderRadius: RADIUS.md, backgroundColor: c.card,
        alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.7 : 1,
        ...elevation(c, 1),
      })}>
      {children}
      {badge ? (
        <View style={{
          position: 'absolute', top: 5, left: 5, minWidth: 17, height: 17,
          borderRadius: 10, backgroundColor: c.danger, alignItems: 'center',
          justifyContent: 'center', paddingHorizontal: 4,
          borderWidth: 2, borderColor: c.card,
        }}>
          <Text style={{ color: '#fff', fontSize: 9.5, fontFamily: fontFor('700') }}>
            {badge > 9 ? '٩+' : String(badge)}
          </Text>
        </View>
      ) : null}
    </Pressable>
  )
}

/* ═══════════ الدُّرج ═══════════ */

interface Item { label: string; icon: React.ReactNode; go?: string; onPress?: () => void; tint?: string }

function Drawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { c, profile, subscriber, plan, signOut, themeMode, setThemeMode, isDark } = useApp()
  const nav = useNavigation<any>()
  const insets = useSafeAreaInsets()
  const slide = useMemo(() => new Animated.Value(0), [])

  React.useEffect(() => {
    Animated.timing(slide, {
      toValue: open ? 1 : 0, duration: open ? 240 : 180,
      easing: open ? Easing.out(Easing.cubic) : Easing.in(Easing.cubic),
      useNativeDriver: true,
    }).start()
  }, [open, slide])

  const groups: { title: string; items: Item[] }[] = [
    {
      title: 'العمل اليوميّ',
      items: [
        { label: 'الرئيسيّة', icon: <IcHome size={19} color={c.tint.violet} />, go: 'الرئيسية', tint: c.tint.violet },
        { label: 'رصد الحضور', icon: <IcCheck size={19} color={c.tint.teal} />, go: 'الرصد', tint: c.tint.teal },
        { label: 'جدولي', icon: <IcCalendar size={19} color={c.tint.blue} />, go: 'Timetable', tint: c.tint.blue },
      ],
    },
    {
      title: 'المستندات',
      items: [
        { label: 'القوالب', icon: <IcLibrary size={19} color={c.tint.amber} />, go: 'القوالب', tint: c.tint.amber },
        { label: 'ملفّاتي', icon: <IcFiles size={19} color={c.tint.rose} />, go: 'ملفّاتي', tint: c.tint.rose },
        { label: 'جداول نور', icon: <IcTable size={19} color={c.tint.lime} />, go: 'Noor', tint: c.tint.lime },
      ],
    },
    {
      title: 'الحساب',
      items: [
        { label: 'حسابي والاشتراك', icon: <IcUser size={19} color={c.text2} />, go: 'حسابي' },
        { label: 'الإعدادات', icon: <IcSettings size={19} color={c.text2} />, go: 'حسابي' },
        { label: 'مساعدة', icon: <IcHelp size={19} color={c.text2} />, go: 'حسابي' },
      ],
    },
  ]

  const goto = (name?: string) => {
    onClose()
    if (!name) return
    setTimeout(() => { try { nav.navigate(name) } catch {} }, 190)
  }

  return (
    <Modal visible={open} transparent animationType="none" onRequestClose={onClose} statusBarTranslucent>
      <Animated.View style={{ flex: 1, backgroundColor: '#0006', opacity: slide }}>
        <Pressable style={{ flex: 1 }} onPress={onClose} />
      </Animated.View>
      <Animated.View style={{
        position: 'absolute', top: 0, bottom: 0, right: 0, width: '82%', maxWidth: 340,
        backgroundColor: c.card, paddingTop: insets.top + SPACE.s5,
        paddingBottom: insets.bottom + SPACE.s4,
        borderTopLeftRadius: RADIUS.xxl, borderBottomLeftRadius: RADIUS.xxl,
        transform: [{ translateX: slide.interpolate({ inputRange: [0, 1], outputRange: [360, 0] }) }],
      }}>
        {/* ترويسة الدُّرج */}
        <View style={{
          paddingHorizontal: SPACE.s5, paddingBottom: SPACE.s4,
          flexDirection: 'row', alignItems: 'center', gap: 12,
        }}>
          <Avatar name={profile?.full_name || 'م'} size={48} uri={profile?.avatar_url} ring />
          <View style={{ flex: 1 }}>
            <Text numberOfLines={1} style={{
              color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.lead,
              textAlign: 'right', writingDirection: 'rtl',
            }}>{profile?.full_name || 'مستخدم مِداد'}</Text>
            <Text numberOfLines={1} style={{
              color: c.text3, fontFamily: fontFor('500'), fontSize: TYPE.small,
              textAlign: 'right', writingDirection: 'rtl', marginTop: 2,
            }}>{subscriber?.name || plan?.name_ar || 'حساب'}</Text>
          </View>
          <Pressable onPress={onClose} hitSlop={10} style={{ padding: 4 }}>
            <IcClose size={20} color={c.text3} />
          </Pressable>
        </View>

        <View style={{ height: 1, backgroundColor: c.border, marginHorizontal: SPACE.s5 }} />

        <ScrollView contentContainerStyle={{ paddingVertical: SPACE.s4 }} showsVerticalScrollIndicator={false}>
          {groups.map((g) => (
            <View key={g.title} style={{ marginBottom: SPACE.s4 }}>
              <Text style={{
                color: c.text3, fontFamily: fontFor('600'), fontSize: TYPE.caption,
                textAlign: 'right', writingDirection: 'rtl',
                paddingHorizontal: SPACE.s5, marginBottom: 8, letterSpacing: 0.2,
              }}>{g.title}</Text>
              {g.items.map((it) => (
                <Pressable
                  key={it.label}
                  onPress={it.onPress ? () => { onClose(); it.onPress!() } : () => goto(it.go)}
                  style={({ pressed }) => ({
                    flexDirection: 'row', alignItems: 'center', gap: 12,
                    paddingHorizontal: SPACE.s5, paddingVertical: 12,
                    backgroundColor: pressed ? c.sunken : 'transparent',
                  })}>
                  <View style={{
                    width: 34, height: 34, borderRadius: RADIUS.sm,
                    backgroundColor: it.tint ? it.tint + '1A' : c.sunken,
                    alignItems: 'center', justifyContent: 'center',
                  }}>{it.icon}</View>
                  <Text style={{
                    color: c.text, fontFamily: fontFor('600'), fontSize: TYPE.base,
                    textAlign: 'right', writingDirection: 'rtl', flex: 1,
                  }}>{it.label}</Text>
                </Pressable>
              ))}
            </View>
          ))}
        </ScrollView>

        <View style={{ height: 1, backgroundColor: c.border, marginHorizontal: SPACE.s5 }} />

        {/* المظهر والخروج */}
        <View style={{
          flexDirection: 'row', alignItems: 'center', gap: 12,
          paddingHorizontal: SPACE.s5, paddingTop: SPACE.s4,
        }}>
          <Pressable
            onPress={() => setThemeMode(isDark ? 'light' : 'dark')}
            style={({ pressed }) => ({
              flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1,
              backgroundColor: pressed ? c.sunken : c.cardAlt,
              borderRadius: RADIUS.sm, paddingVertical: 12, paddingHorizontal: 12,
            })}>
            {isDark ? <IcSun size={18} color={c.text2} /> : <IcMoon size={18} color={c.text2} />}
            <Text style={{
              color: c.text2, fontFamily: fontFor('600'), fontSize: TYPE.body,
              writingDirection: 'rtl',
            }}>{isDark ? 'الوضع الفاتح' : 'الوضع الداكن'}</Text>
          </Pressable>
          <Pressable
            onPress={() => { onClose(); setTimeout(() => signOut(), 200) }}
            style={({ pressed }) => ({
              flexDirection: 'row', alignItems: 'center', gap: 8,
              backgroundColor: pressed ? c.dangerSoft : c.cardAlt,
              borderRadius: RADIUS.sm, paddingVertical: 12, paddingHorizontal: 12,
            })}>
            <IcLogout size={18} color={c.danger} />
            <Text style={{
              color: c.danger, fontFamily: fontFor('600'), fontSize: TYPE.body,
              writingDirection: 'rtl',
            }}>خروج</Text>
          </Pressable>
        </View>

        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, justifyContent: 'center', paddingTop: SPACE.s4 }}>
          <IcLogo size={16} color={c.text3} />
          <Text style={{ color: c.text3, fontSize: TYPE.caption, fontFamily: fontFor('500') }}>مِداد</Text>
        </View>
      </Animated.View>
    </Modal>
  )
}

/* ═══════════ ورقة التنبيهات ═══════════ */

function NotesSheet({ open, onClose, notes }: { open: boolean; onClose: () => void; notes: Note[] }) {
  const { c } = useApp()
  const insets = useSafeAreaInsets()
  const nav = useNavigation<any>()
  const tones: Record<Note['tone'], { bg: string; fg: string }> = {
    info: { bg: c.infoSoft, fg: c.info },
    warn: { bg: c.warnSoft, fg: c.warn },
    danger: { bg: c.dangerSoft, fg: c.danger },
    success: { bg: c.successSoft, fg: c.success },
  }
  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{ flex: 1, backgroundColor: '#0006' }} onPress={onClose} />
      <View style={{
        position: 'absolute', left: 0, right: 0, bottom: 0,
        backgroundColor: c.card, borderTopLeftRadius: RADIUS.xxl, borderTopRightRadius: RADIUS.xxl,
        paddingTop: SPACE.s4, paddingBottom: insets.bottom + SPACE.s5, maxHeight: '72%',
      }}>
        <View style={{ width: 40, height: 4, borderRadius: 2, backgroundColor: c.border, alignSelf: 'center' }} />
        <Text style={{
          color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.h3,
          textAlign: 'right', writingDirection: 'rtl',
          paddingHorizontal: SPACE.s5, paddingVertical: SPACE.s4,
        }}>التنبيهات</Text>
        {notes.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: SPACE.s7, paddingHorizontal: SPACE.s5 }}>
            <IcBell size={34} color={c.text3} />
            <Text style={{
              color: c.text3, fontFamily: fontFor('500'), fontSize: TYPE.body,
              marginTop: 12, textAlign: 'center', writingDirection: 'rtl',
            }}>لا شيء يحتاج انتباهك الآن.</Text>
          </View>
        ) : (
          <ScrollView contentContainerStyle={{ paddingHorizontal: SPACE.s5, gap: 8 }} showsVerticalScrollIndicator={false}>
            {notes.map((n) => {
              const t = tones[n.tone]
              return (
                <Pressable
                  key={n.id}
                  onPress={() => { onClose(); if (n.go) setTimeout(() => { try { nav.navigate(n.go!) } catch {} }, 180) }}
                  style={({ pressed }) => ({
                    backgroundColor: pressed ? c.sunken : c.cardAlt, borderRadius: RADIUS.md,
                    padding: 12, flexDirection: 'row', gap: 12, alignItems: 'flex-start',
                  })}>
                  <View style={{
                    width: 8, height: 8, borderRadius: 4, backgroundColor: t.fg, marginTop: 8,
                  }} />
                  <View style={{ flex: 1 }}>
                    <Text style={{
                      color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.body,
                      textAlign: 'right', writingDirection: 'rtl',
                    }}>{n.title}</Text>
                    <Text style={{
                      color: c.text2, fontFamily: fontFor('400'), fontSize: TYPE.small, lineHeight: 21,
                      textAlign: 'right', writingDirection: 'rtl', marginTop: 4,
                    }}>{n.body}</Text>
                  </View>
                </Pressable>
              )
            })}
          </ScrollView>
        )}
      </View>
    </Modal>
  )
}
