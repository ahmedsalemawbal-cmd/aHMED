import React from 'react'
import {
  ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput,
  TextInputProps, View, ViewStyle,
} from 'react-native'
import { useApp } from '../lib/store'
import { RADIUS, SPACE, fontFor } from '../lib/theme'
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
    backgroundColor: c.card, borderColor: c.border, borderWidth: 1,
    borderRadius: RADIUS.lg, padding: SPACE.s5,
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

type BtnVariant = 'primary' | 'secondary' | 'soft' | 'danger' | 'ghost'
export function Button({ label, onPress, variant = 'secondary', loading, disabled, style, small, icon }: {
  label: string; onPress?: () => void; variant?: BtnVariant; loading?: boolean
  disabled?: boolean; style?: ViewStyle; small?: boolean; icon?: React.ReactNode
}) {
  const { c } = useApp()
  const map: Record<BtnVariant, { bg: string; fg: string; border: string }> = {
    primary: { bg: c.accent, fg: c.onAccent, border: c.accent },
    secondary: { bg: c.card, fg: c.text, border: c.borderStrong },
    soft: { bg: c.accentSoft, fg: c.accentSoftFg, border: 'transparent' },
    danger: { bg: c.dangerSoft, fg: c.danger, border: 'transparent' },
    ghost: { bg: 'transparent', fg: c.accent, border: 'transparent' },
  }
  const s = map[variant]
  const off = disabled || loading
  return (
    <Pressable
      onPress={onPress} disabled={off}
      style={({ pressed }) => [{
        backgroundColor: s.bg, borderColor: s.border, borderWidth: 1,
        borderRadius: small ? RADIUS.sm : RADIUS.md,
        paddingVertical: small ? 9 : 13, paddingHorizontal: small ? 14 : 20,
        minHeight: small ? 38 : 48, flexDirection: 'row-reverse',
        alignItems: 'center', justifyContent: 'center', gap: 8,
        opacity: off ? 0.55 : pressed ? 0.82 : 1,
      }, style]}>
      {loading ? <ActivityIndicator size="small" color={s.fg} /> : icon}
      <Text style={{ color: s.fg, fontFamily: fontFor('700'), fontSize: small ? 13 : 14.5, writingDirection: 'rtl' }}>
        {label}
      </Text>
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
          borderWidth: 1, borderColor: error ? c.danger : c.border, borderRadius: 11,
          backgroundColor: c.card, color: c.text, paddingHorizontal: 13, paddingVertical: 12,
          fontSize: 14.5, minHeight: 48, textAlign: 'right', writingDirection: 'rtl',
          fontFamily: fontFor('500'),
        }, style]}
      />
      {error ? <T size={11.5} color={c.danger}>{error}</T>
        : help ? <T size={11.5} color={c.text3}>{help}</T> : null}
    </View>
  )
}

export function Badge({ label, tone = 'neutral' }: {
  label: string; tone?: 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'accent'
}) {
  const { c } = useApp()
  const map = {
    neutral: { bg: c.sunken, fg: c.text2 },
    success: { bg: c.successSoft, fg: c.success },
    warn: { bg: c.warnSoft, fg: c.warn },
    danger: { bg: c.dangerSoft, fg: c.danger },
    info: { bg: c.infoSoft, fg: c.info },
    accent: { bg: c.accentSoft, fg: c.accentSoftFg },
  }[tone]
  return (
    <View style={{ backgroundColor: map.bg, borderRadius: RADIUS.pill, paddingHorizontal: 11, paddingVertical: 4, alignSelf: 'flex-start' }}>
      <Text style={{ color: map.fg, fontSize: 11.5, fontFamily: fontFor('600'), writingDirection: 'rtl' }}>{label}</Text>
    </View>
  )
}

export function Avatar({ name, size = 40 }: { name: string; size?: number }) {
  const { c } = useApp()
  return (
    <View style={{
      width: size, height: size, borderRadius: size / 2, backgroundColor: c.accentSoft,
      alignItems: 'center', justifyContent: 'center',
    }}>
      <Text style={{ color: c.accentSoftFg, fontFamily: fontFor('700'), fontSize: size * 0.34 }}>{initials(name)}</Text>
    </View>
  )
}

export function Progress({ value, max = 100, tone }: { value: number; max?: number; tone?: 'warn' | 'danger' }) {
  const { c } = useApp()
  const pct = Math.max(0, Math.min(100, (value / (max || 1)) * 100))
  const color = tone === 'danger' ? c.danger : tone === 'warn' ? c.warn : c.accent
  return (
    <View style={{ height: 8, borderRadius: RADIUS.pill, backgroundColor: c.sunken, overflow: 'hidden' }}>
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
      <ActivityIndicator color={c.accent} size="large" />
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

export function Alert({ children, tone = 'info' }: {
  children: React.ReactNode; tone?: 'info' | 'warn' | 'danger' | 'success' | 'accent'
}) {
  const { c } = useApp()
  const map = {
    info: { bg: c.infoSoft, fg: c.info },
    warn: { bg: c.warnSoft, fg: c.warn },
    danger: { bg: c.dangerSoft, fg: c.danger },
    success: { bg: c.successSoft, fg: c.success },
    accent: { bg: c.accentSoft, fg: c.accentSoftFg },
  }[tone]
  return (
    <View style={{ backgroundColor: map.bg, borderRadius: RADIUS.md, padding: 13 }}>
      <Text style={{ color: map.fg, fontSize: 13, lineHeight: 22, writingDirection: 'rtl', textAlign: 'right', fontFamily: fontFor('500') }}>
        {children}
      </Text>
    </View>
  )
}

export function Screen({ children, scroll = true, refreshControl }: {
  children: React.ReactNode; scroll?: boolean; refreshControl?: React.ReactElement
}) {
  const { c } = useApp()
  if (!scroll) return <View style={{ flex: 1, backgroundColor: c.bg }}>{children}</View>
  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ padding: SPACE.s4, paddingBottom: SPACE.s8, gap: SPACE.s4 }}
      keyboardShouldPersistTaps="handled"
      refreshControl={refreshControl}>
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
