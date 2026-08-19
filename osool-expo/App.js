/**
 * أُصول — قشرة تطبيق وليّ الأمر.
 *
 * التطبيق نفسه يعيش في الإضافة على خادم المدرسة، وهذه قشرة تعرضه
 * وتعطيه ما لا يعطيه المتصفّح: أيقونة على الشاشة، وفتحًا بملء الشاشة،
 * وطريقًا إلى المتجر.
 *
 * **ولا يُحقن فيها عنوان مدرسة واحدة.** نسخة لكل مدرسة تعني بناءً
 * ورفعًا للمتجر مع كل عميل جديد؛ فالعنوان يُسأل عنه مرّة عند أول تشغيل
 * ويُحفظ — وبناء واحد يخدم كل المدارس.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator, Alert, BackHandler, Linking, Platform, RefreshControl,
  SafeAreaView, ScrollView, StatusBar, StyleSheet, Text, TextInput,
  TouchableOpacity, View,
} from 'react-native';
import { WebView } from 'react-native-webview';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';

const KEY = 'osool.site';

const C = {
  bg: '#f3f4fb', card: '#ffffff', ink: '#191b2e', ink2: '#585c78',
  muted: '#6e7290', line: 'rgba(25,27,46,.10)', pri: '#4453cc', onPri: '#ffffff',
  bad: '#b3332c', badTint: '#fbe3e1',
};

/**
 * يقبل «مدرستي.com» و«https://مدرستي.com/dashboard» معًا.
 * وليّ الأمر لا يكتب البروتوكول، وردّ «العنوان غير صالح» على عنوان
 * صحيح ناقص البادئة يوقفه عند أول شاشة.
 */
function normalize(raw) {
  let s = String(raw || '').trim().replace(/\s/g, '');
  if (!s) return null;
  if (!/^https?:\/\//i.test(s)) s = 'https://' + s;
  try {
    const u = new URL(s);
    if (!u.hostname.includes('.')) return null;
    // مسار التطبيق ثابت في الإضافة — فلا يُطلب من الأب أن يعرفه
    if (u.pathname === '/' || u.pathname === '') u.pathname = '/dashboard/app';
    return u.toString().replace(/\/$/, '');
  } catch {
    return null;
  }
}

export default function App() {
  const [site, setSite] = useState(undefined);   // undefined = لم يُقرأ بعد
  const [typed, setTyped] = useState('');
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(true);
  const [offline, setOffline] = useState(false);
  const [canBack, setCanBack] = useState(false);
  const web = useRef(null);

  useEffect(() => {
    AsyncStorage.getItem(KEY)
      .then((v) => setSite(v || normalize(Constants.expoConfig?.extra?.defaultSite) || null))
      .catch(() => setSite(null));
  }, []);

  /* زرّ الرجوع في أندرويد يخرج من التطبيق بدل أن يرجع صفحة — وهو أسوأ
     ما يفعله غلافُ ويب: يفقد الأب مكانه بضغطة عرضية. */
  useEffect(() => {
    if (Platform.OS !== 'android') return undefined;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (canBack && web.current) { web.current.goBack(); return true; }
      return false;
    });
    return () => sub.remove();
  }, [canBack]);

  const save = useCallback(async () => {
    const url = normalize(typed);
    if (!url) { setErr('تأكّد من العنوان — مثال: madrasati.com'); return; }
    await AsyncStorage.setItem(KEY, url);
    setErr('');
    setOffline(false);
    setLoading(true);
    setSite(url);
  }, [typed]);

  const forget = useCallback(() => {
    Alert.alert('تغيير المدرسة', 'سيُطلب منك عنوان المدرسة من جديد.', [
      { text: 'إلغاء', style: 'cancel' },
      {
        text: 'تغيير',
        style: 'destructive',
        onPress: async () => { await AsyncStorage.removeItem(KEY); setTyped(''); setSite(null); },
      },
    ]);
  }, []);

  if (site === undefined) {
    return (
      <SafeAreaView style={[s.fill, s.mid]}>
        <ActivityIndicator size="large" color={C.pri} />
      </SafeAreaView>
    );
  }

  // ── أول تشغيل: عنوان المدرسة ──────────────────────────────────────
  if (!site) {
    return (
      <SafeAreaView style={s.fill}>
        <StatusBar barStyle="dark-content" backgroundColor={C.bg} />
        <ScrollView contentContainerStyle={s.pad} keyboardShouldPersistTaps="handled">
          <View style={s.mark}><Text style={s.markT}>أ</Text></View>

          <Text style={s.h1}>أُصول</Text>
          <Text style={s.sub}>تطبيق وليّ الأمر</Text>

          <Text style={s.label}>عنوان موقع المدرسة</Text>
          <TextInput
            style={s.input}
            value={typed}
            onChangeText={(t) => { setTyped(t); setErr(''); }}
            placeholder="madrasati.com"
            placeholderTextColor={C.muted}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
            textAlign="right"
            onSubmitEditing={save}
            returnKeyType="go"
          />
          <Text style={s.hint}>تجده في الرسالة التي وصلتك من المدرسة.</Text>

          {err ? <View style={s.err}><Text style={s.errT}>{err}</Text></View> : null}

          <TouchableOpacity style={s.btn} onPress={save} activeOpacity={0.85}>
            <Text style={s.btnT}>متابعة</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // ── انقطاع الشبكة ─────────────────────────────────────────────────
  if (offline) {
    return (
      <SafeAreaView style={s.fill}>
        <ScrollView
          contentContainerStyle={[s.pad, s.mid]}
          refreshControl={
            <RefreshControl
              refreshing={false}
              onRefresh={() => { setOffline(false); setLoading(true); }}
              tintColor={C.pri}
            />
          }
        >
          <Text style={s.h2}>تعذّر الوصول إلى المدرسة</Text>
          <Text style={s.hint}>تأكّد من اتصالك بالإنترنت، ثم اسحب للأسفل للمحاولة.</Text>
          <TouchableOpacity style={s.quiet} onPress={forget} activeOpacity={0.85}>
            <Text style={s.quietT}>تغيير عنوان المدرسة</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // ── التطبيق ───────────────────────────────────────────────────────
  return (
    <SafeAreaView style={s.fill}>
      <StatusBar barStyle="dark-content" backgroundColor={C.bg} />

      <WebView
        ref={web}
        source={{ uri: site }}
        style={s.fill}
        // الجلسة تبقى بعد إغلاق التطبيق — فلا يُطلب الدخول كل مرة
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        domStorageEnabled
        javaScriptEnabled
        allowsBackForwardNavigationGestures
        pullToRefreshEnabled
        setSupportMultipleWindows={false}
        onNavigationStateChange={(n) => setCanBack(n.canGoBack)}
        onLoadEnd={() => setLoading(false)}
        onError={() => { setLoading(false); setOffline(true); }}
        onHttpError={({ nativeEvent }) => {
          // 404 على مسار التطبيق يعني عنوانًا صحيحًا بلا إضافة مُفعَّلة
          if (nativeEvent.statusCode >= 500) setOffline(true);
        }}
        /* الروابط الخارجية تفتح في المتصفّح لا داخل التطبيق: رابط بنك
           أو خريطة يفتح داخل الغلاف يفقد الأب طريق العودة. */
        onShouldStartLoadWithRequest={(req) => {
          try {
            const target = new URL(req.url);
            const home = new URL(site);
            if (target.hostname !== home.hostname) { Linking.openURL(req.url); return false; }
          } catch { /* عنوان غير قابل للتحليل: يُترك للمتصفّح الداخلي */ }
          return true;
        }}
      />

      {loading ? (
        <View style={s.veil} pointerEvents="none">
          <ActivityIndicator size="large" color={C.pri} />
        </View>
      ) : null}
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  fill:   { flex: 1, backgroundColor: C.bg },
  mid:    { alignItems: 'center', justifyContent: 'center' },
  pad:    { padding: 28, paddingTop: 64, gap: 4 },

  mark:   { width: 60, height: 60, borderRadius: 18, backgroundColor: C.pri,
            alignItems: 'center', justifyContent: 'center', marginBottom: 20 },
  markT:  { color: C.onPri, fontSize: 30, fontWeight: '700' },

  h1:     { fontSize: 27, fontWeight: '700', color: C.ink, textAlign: 'right' },
  h2:     { fontSize: 19, fontWeight: '700', color: C.ink, textAlign: 'center', marginBottom: 8 },
  sub:    { fontSize: 14, color: C.muted, textAlign: 'right', marginBottom: 30 },

  label:  { fontSize: 13, fontWeight: '600', color: C.ink2, textAlign: 'right', marginBottom: 7 },
  input:  { borderWidth: 1, borderColor: C.line, borderRadius: 16, backgroundColor: C.card,
            paddingVertical: 14, paddingHorizontal: 16, fontSize: 15, color: C.ink },
  hint:   { fontSize: 12, color: C.muted, textAlign: 'right', marginTop: 8 },

  err:    { backgroundColor: C.badTint, borderRadius: 14, padding: 12, marginTop: 14 },
  errT:   { color: C.bad, fontSize: 13, fontWeight: '600', textAlign: 'right' },

  btn:    { backgroundColor: C.pri, borderRadius: 16, paddingVertical: 15,
            alignItems: 'center', marginTop: 24 },
  btnT:   { color: C.onPri, fontSize: 15, fontWeight: '700' },

  quiet:  { marginTop: 22, paddingVertical: 12, alignItems: 'center' },
  quietT: { color: C.pri, fontSize: 14, fontWeight: '600' },

  veil:   { ...StyleSheet.absoluteFillObject, backgroundColor: C.bg,
            alignItems: 'center', justifyContent: 'center' },
});
