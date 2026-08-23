import React from 'react'
import { Linking, View } from 'react-native'
import { useApp } from '../lib/store'
import { Alert, Button, Card, Row, Screen, T } from '../ui/kit'
import { IcLock, IcClock, IcCheck } from '../ui/icons'
import { SPACE, RADIUS } from '../lib/theme'
import { fmtBoth, fmtMoney } from '../lib/format'
import { WEB_APP_URL } from '../lib/config'

/** جدار الدفع وشاشة الإيقاف — مختلفتان تمامًا: الأولى فيها زرّ دفع والثانية لا. */
export default function Blocked() {
  const { c, access, subscriber, plan, plans, signOut } = useApp()
  const suspended = access === 'suspended' || access === 'member_suspended'

  const suggested = plan || plans.find((p) => p.account_type === (subscriber?.account_type || 'teacher'))

  return (
    <Screen>
      <View style={{ alignItems: 'center', gap: 14, paddingTop: SPACE.s7 }}>
        <View style={{
          width: 76, height: 76, borderRadius: 24,
          backgroundColor: suspended ? c.dangerSoft : c.warnSoft,
          alignItems: 'center', justifyContent: 'center',
        }}>
          {suspended ? <IcLock size={34} color={c.danger} /> : <IcClock size={34} color={c.warn} />}
        </View>
        <T size={22} weight="700" align="center">
          {suspended
            ? (access === 'member_suspended' ? 'أوقف مدير حسابك دخولك' : 'حسابك موقوف مؤقّتًا')
            : subscriber?.status === 'expired' ? 'انتهى اشتراكك' : 'انتهت تجربتك المجانية'}
        </T>
        <T size={13.5} color={c.text2} align="center">
          {suspended
            ? 'ملفّاتك محفوظة كما هي. تواصل معنا لمعرفة السبب وإعادة التفعيل.'
            : `انتهت في ${fmtBoth(subscriber?.trial_ends_at)}. وكلّ ما أنشأته محفوظ ويعود إليك فور اشتراكك.`}
        </T>
      </View>

      {suspended ? (
        <>
          <Alert tone="danger">
            لن يفتح الدفع الحساب الموقوف — تواصل معنا أوّلًا لنعرف السبب ونعيد التفعيل.
          </Alert>
          <Button label="تواصل معنا" variant="primary"
            onPress={() => Linking.openURL(WEB_APP_URL + '/#/contact')} />
        </>
      ) : (
        <>
          {suggested && (
            <Card style={{ gap: 12, borderColor: c.primary }}>
              <T size={17} weight="700">{suggested.name_ar}</T>
              <T size={26} weight="700" color={c.primary}>{fmtMoney(suggested.price_sar)}</T>
              <T size={12} color={c.text3}>سنويًّا · شامل الضريبة</T>
              {(suggested.features_ar || []).slice(0, 6).map((f) => (
                <Row key={f} gap={9} style={{ alignItems: 'flex-start' }}>
                  <IcCheck size={15} color={c.primary} />
                  <T size={12.5} color={c.text2} style={{ flex: 1 }}>{f}</T>
                </Row>
              ))}
            </Card>
          )}
          <Button label="اشترك الآن" variant="primary"
            onPress={() => Linking.openURL(WEB_APP_URL + '/#/app/checkout')} />
          <Button label="شاهد كلّ الباقات" variant="soft"
            onPress={() => Linking.openURL(WEB_APP_URL + '/#/app/plans')} />
        </>
      )}

      <Button label="خروج" variant="secondary" onPress={signOut} />
    </Screen>
  )
}
