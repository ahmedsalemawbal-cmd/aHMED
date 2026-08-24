import React, { useRef } from 'react'
import {
  ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Text, TextInput,
  TextInputProps, View, ViewStyle,
} from 'react-native'
import { useApp } from '../lib/store'
import { RADIUS, SPACE, TYPE, fontFor, elevation } from '../lib/theme'
import { initials } from '../lib/format'
import { IcFiles } from './icons'

export function T({ children, size = 14, weight = '400', color, style, numberOfLines, align }: {
  children: React.ReactNode; size?: number; weight?: '400' | '500' | '600' | '700'
  color?: string; style?: any; numberOfLines?: number; align?: 'auto' | 'left' | 'right' | 'center'
}) {
  const { c } = useApp()
  return (
    <Text
      numberOfLines={numberOfLines}
      style={[{
        fontSize: size, fontFamily: fontFor(weight), color: color || c.text,
        lineHeight: size * 1.75, textAlign: align || 'right', writingDirection: 'rtl',
      }, style]}>
      {children}
    </Text>
  )
}

export function Card({ children, style, onPress }: { children: React.ReactNode; style?: ViewStyle; onPress?: () => void }) {
  const { c } = useApp()
  const base: ViewStyle = {
    backgroundColor: c.card, borderRadius: RADIUS.lg, padding: SPACE.s5,
    ...elevation(c, 1),
  }
  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => [base, pressed && { opacity: 0.75 }, style]}>
        {children}
      </Pressable>
    )
  }
  return <View style={[base, style]}>{children}</View>
}

type BtnVariant = 'primary' | 'secondary' | 'soft' | 'danger' | 'ghost' | 'tint'
export function Button({ label, onPress, variant = 'secondary', loading, disabled, style, small, icon, tint }: {
  label?: string; onPress?: () => void; variant?: BtnVariant; loading?: boolean
  disabled?: boolean; style?: ViewStyle; small?: boolean; icon?: React.ReactNode; tint?: string
}) {
  const { c } = useApp()
  const map: Record<BtnVariant, { bg: string; fg: string; border: string }> = {
    primary: { bg: c.primary, fg: c.onPrimary, border: c.primary },
    secondary: { bg: c.card, fg: c.text, border: c.borderStrong },
    soft: { bg: c.primarySoft, fg: c.primarySoftFg, border: 'transparent' },
    danger: { bg: c.dangerSoft, fg: c.danger, border: 'transparent' },
    ghost: { bg: 'transparent', fg: c.primary, border: 'transparent' },
    tint: { bg: (tint || c.primary) + '1F', fg: tint || c.primary, border: 'transparent' },
  }
  const s = map[variant]
  const off = disabled || loading
  const iconOnly = !label
  return (
    <Pressable
      onPress={onPress} disabled={off}
      style={({ pressed }) => [{
        backgroundColor: s.bg, borderColor: s.border, borderWidth: 1,
        borderRadius: small ? RADIUS.sm : RADIUS.md,
        paddingVertical: small ? 9 : 12,
        paddingHorizontal: iconOnly ? 0 : (small ? 12 : 16),
        width: iconOnly ? (small ? 36 : 44) : undefined,
        minHeight: small ? 36 : 46, flexDirection: 'row-reverse',
        alignItems: 'center', justifyContent: 'center', gap: label && icon ? 7 : 0,
        opacity: off ? 0.5 : pressed ? 0.82 : 1,
      }, variant === 'primary' && !off ? elevation(c, 1) : null, style]}>
      {loading ? <ActivityIndicator size="small" color={s.fg} /> : icon}
      {label ? (
        <Text
          numberOfLines={1}
          adjustsFontSizeToFit
          minimumFontScale={0.82}
          style={{
            color: s.fg, fontFamily: fontFor('700'),
            fontSize: small ? TYPE.body : TYPE.base,
            writingDirection: 'rtl', flexShrink: 1,
          }}>
          {label}
        </Text>
      ) : null}
    </Pressable>
  )
}

export function Input({ label, help, error, style, ...rest }: TextInputProps & {
  label?: string; help?: string; error?: string
}) {
  const { c } = useApp()
  return (
    <View style={{ gap: 7 }}>
      {label ? <T size={12} weight="600" color={c.text2}>{label}</T> : null}
      <TextInput
        placeholderTextColor={c.text3}
        {...rest}
        style={[{
          borderWidth: 1, borderColor: error ? c.danger : c.border, borderRadius: RADIUS.sm,
          backgroundColor: c.cardAlt, color: c.text, paddingHorizontal: 14, paddingVertical: 12,
          fontSize: TYPE.lead, minHeight: 48, textAlign: 'right', writingDirection: 'rtl',
          fontFamily: fontFor('500'),
        }, style]}
      />
      {error ? <T size={11.5} color={c.danger}>{error}</T>
        : help ? <T size={11.5} color={c.text3}>{help}</T> : null}
    </View>
  )
}

export function Badge({ label, tone = 'neutral', color }: {
  label: string
  tone?: 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'primary'
  color?: string
}) {
  const { c } = useApp()
  const map = {
    neutral: { bg: c.sunken, fg: c.text2 },
    success: { bg: c.successSoft, fg: c.success },
    warn: { bg: c.warnSoft, fg: c.warn },
    danger: { bg: c.dangerSoft, fg: c.danger },
    info: { bg: c.infoSoft, fg: c.info },
    primary: { bg: c.primarySoft, fg: c.primarySoftFg },
  }[tone]
  const bg = color ? color + '1F' : map.bg
  const fg = color || map.fg
  return (
    <View style={{
      backgroundColor: bg, borderRadius: RADIUS.pill,
      paddingHorizontal: 10, paddingVertical: 4, alignSelf: 'flex-start',
    }}>
      <Text numberOfLines={1} style={{
        color: fg, fontSize: TYPE.small, fontFamily: fontFor('600'), writingDirection: 'rtl',
      }}>{label}</Text>
    </View>
  )
}

export function Avatar({ name, size = 40, uri, ring }: {
  name: string; size?: number; uri?: string | null; ring?: boolean
}) {
  const { c } = useApp()
  const box: ViewStyle = {
    width: size, height: size, borderRadius: size / 2,
    backgroundColor: c.primarySoft, alignItems: 'center', justifyContent: 'center',
    overflow: 'hidden',
    ...(ring ? { borderWidth: 2, borderColor: c.primary } : null),
  }
  if (uri) return <View style={box}><Image source={{ uri }} style={{ width: '100%', height: '100%' }} /></View>
  return (
    <View style={box}>
      <Text style={{ color: c.primarySoftFg, fontFamily: fontFor('700'), fontSize: size * 0.36 }}>
        {initials(name)}
      </Text>
    </View>
  )
}

export function Progress({ value, max = 100, tone }: { value: number; max?: number; tone?: 'warn' | 'danger' }) {
  const { c } = useApp()
  const pct = Math.max(0, Math.min(100, (value / (max || 1)) * 100))
  const color = tone === 'danger' ? c.danger : tone === 'warn' ? c.warn : c.primary
  return (
    <View style={{ height: 9, borderRadius: RADIUS.pill, backgroundColor: c.sunken, overflow: 'hidden' }}>
      <View style={{ height: '100%', width: `${pct}%`, backgroundColor: color, borderRadius: RADIUS.pill }} />
    </View>
  )
}

export function Empty({ title, line, action, art }: {
  title: string; line?: string; action?: React.ReactNode; art?: React.ReactNode
}) {
  const { c } = useApp()
  return (
    <View style={{ alignItems: 'center', paddingVertical: SPACE.s8, paddingHorizontal: SPACE.s5, gap: SPACE.s3 }}>
      <View style={{
        width: 72, height: 72, borderRadius: RADIUS.xxl, backgroundColor: c.sunken,
        alignItems: 'center', justifyContent: 'center',
      }}>
        {art || <IcFiles size={30} color={c.text3} />}
      </View>
      <T size={17} weight="700" align="center">{title}</T>
      {line ? <T size={13.5} color={c.text2} align="center">{line}</T> : null}
      {action}
    </View>
  )
}

export function Loading({ label }: { label?: string }) {
  const { c } = useApp()
  return (
    <View style={{ paddingVertical: SPACE.s8, alignItems: 'center', gap: 12 }}>
      <ActivityIndicator color={c.primary} size="large" />
      {label ? <T size={13} color={c.text3}>{label}</T> : null}
    </View>
  )
}

export function ErrorView({ message, onRetry }: { message?: string; onRetry?: () => void }) {
  const { c } = useApp()
  return (
    <View style={{ paddingVertical: SPACE.s7, alignItems: 'center', gap: SPACE.s3 }}>
      <T size={16} weight="700" align="center">حدث خلل</T>
      <T size={13} color={c.text2} align="center">{message || 'تحقّق من اتّصالك ثمّ حاول مرّة أخرى.'}</T>
      {onRetry ? <Button label="حاول مرّة أخرى" variant="primary" onPress={onRetry} /> : null}
    </View>
  )
}

export function Alert({ children, tone = 'info', icon }: {
  children: React.ReactNode
  tone?: 'info' | 'warn' | 'danger' | 'success' | 'primary'
  icon?: React.ReactNode
}) {
  const { c } = useApp()
  const map = {
    info: { bg: c.infoSoft, fg: c.info },
    warn: { bg: c.warnSoft, fg: c.warn },
    danger: { bg: c.dangerSoft, fg: c.danger },
    success: { bg: c.successSoft, fg: c.success },
    primary: { bg: c.primarySoft, fg: c.primarySoftFg },
  }[tone]
  return (
    <View style={{
      backgroundColor: map.bg, borderRadius: RADIUS.md, padding: 13,
      flexDirection: 'row-reverse', alignItems: 'flex-start', gap: icon ? 9 : 0,
      borderRightWidth: 3, borderRightColor: map.fg,
    }}>
      {icon}
      <Text style={{
        color: map.fg, fontSize: TYPE.body, lineHeight: 22, flex: 1,
        writingDirection: 'rtl', textAlign: 'right', fontFamily: fontFor('500'),
      }}>
        {children}
      </Text>
    </View>
  )
}

export function Screen({ children, scroll = true, refreshControl, header, pad }: {
  children: React.ReactNode; scroll?: boolean; refreshControl?: React.ReactElement
  /** يُرسم ثابتًا فوق المحتوى — لا يتحرّك مع التمرير */
  header?: React.ReactNode
  pad?: number
}) {
  const { c } = useApp()
  const body = scroll ? (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{
        paddingHorizontal: pad ?? SPACE.s5, paddingTop: header ? 0 : SPACE.s4,
        paddingBottom: SPACE.s8, gap: SPACE.s4,
      }}
      keyboardShouldPersistTaps="handled"
      showsVerticalScrollIndicator={false}
      refreshControl={refreshControl}>
      {children}
    </ScrollView>
  ) : (
    <View style={{ flex: 1, backgroundColor: c.bg }}>{children}</View>
  )
  if (!header) return body
  return <View style={{ flex: 1, backgroundColor: c.bg }}>{header}{body}</View>
}

/** قسمٌ بعنوان وإجراءٍ اختياريّ على يساره */
export function Section({ title, action, children, gap = SPACE.s3 }: {
  title: string; action?: React.ReactNode; children: React.ReactNode; gap?: number
}) {
  const { c } = useApp()
  return (
    <View style={{ gap }}>
      <View style={{ flexDirection: 'row-reverse', alignItems: 'center', justifyContent: 'space-between' }}>
        <Text style={{
          color: c.text, fontFamily: fontFor('700'), fontSize: TYPE.h3,
          textAlign: 'right', writingDirection: 'rtl',
        }}>{title}</Text>
        {action}
      </View>
      {children}
    </View>
  )
}

/**
 * شريطٌ يمرّ أفقيًّا — والعربيّة تبدأ من اليمين، فنقلب الترتيب ونبدأ التمرير
 * من أقصى اليمين كي لا يظهر أوّل عنصرٍ مقصوصًا.
 */
export function HScroll({ children, gap = 10, pad = SPACE.s5 }: {
  children: React.ReactNode; gap?: number; pad?: number
}) {
  const ref = useRef<ScrollView>(null)
  /* `row-reverse` يضع أوّل عنصرٍ في أقصى يمين المحتوى، والمحرّك يفتح
     الشريط على أقصى يساره — فيرى المستخدم آخر العناصر ويظنّ الترتيب
     مقلوبًا. فنقفز إلى الطرف الآخر بلا حركةٍ مرئيّة عند كلّ تغيّرٍ في
     المقاس: أوّل رسمٍ، وتبدّل البيانات، ودوران الجهاز.
     ولا نفعلها إن كان المحتوى أضيق من الشريط — لا طرفَ يُقفز إليه. */
  const settle = (w: number, _h: number) => {
    if (w > 0) ref.current?.scrollToEnd({ animated: false })
  }
  return (
    <ScrollView
      ref={ref}
      horizontal
      showsHorizontalScrollIndicator={false}
      onContentSizeChange={settle}
      style={{ marginHorizontal: -pad }}
      contentContainerStyle={{
        flexDirection: 'row-reverse', gap, paddingHorizontal: pad, alignItems: 'stretch',
      }}>
      {children}
    </ScrollView>
  )
}

export function Row({ children, gap = 10, style }: { children: React.ReactNode; gap?: number; style?: ViewStyle }) {
  return <View style={[{ flexDirection: 'row-reverse', alignItems: 'center', gap }, style]}>{children}</View>
}

export function Divider() {
  const { c } = useApp()
  return <View style={{ height: 1, backgroundColor: c.border }} />
}

export const s = StyleSheet.create({
  fill: { flex: 1 },
  center: { alignItems: 'center', justifyContent: 'center' },
})
