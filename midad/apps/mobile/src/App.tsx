import React, { useCallback } from 'react'
import { ActivityIndicator, View } from 'react-native'
import * as SplashScreen from 'expo-splash-screen'
import { useFonts, Cairo_400Regular, Cairo_500Medium, Cairo_600SemiBold, Cairo_700Bold } from '@expo-google-fonts/cairo'
import { NavigationContainer, DefaultTheme, DarkTheme } from '@react-navigation/native'
import { createNativeStackNavigator } from '@react-navigation/native-stack'
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { SafeAreaProvider } from 'react-native-safe-area-context'
import { StatusBar } from 'expo-status-bar'

import { AppProvider, useApp } from './lib/store'
import Login from './screens/Login'
import Dashboard from './screens/Dashboard'
import Library from './screens/Library'
import TemplateDetail from './screens/TemplateDetail'
import Editor from './screens/Editor'
import MyFiles from './screens/MyFiles'
import Noor from './screens/Noor'
import NoorTable from './screens/NoorTable'
import Account from './screens/Account'
import Blocked from './screens/Blocked'
import Attendance from './screens/Attendance'
import Timetable from './screens/Timetable'
import { IcHome, IcLibrary, IcFiles, IcUser, IcCheck } from './ui/icons'
import { fontFor, TYPE, RADIUS } from './lib/theme'

const Tab = createBottomTabNavigator()
const Stack = createNativeStackNavigator()

type TabIconFn = (p: { focused: boolean; color: string; size: number }) => React.ReactNode

function Tabs() {
  const { c } = useApp()
  return (
    <Tab.Navigator
      /* لا هيدر افتراضيّ: هو يُرسم LTR ويضع الرجوع في الجهة الخطأ.
         كلّ شاشةٍ ترسم AppHeader الخاصّ بها، عربيًّا من اليمين. */
      screenOptions={{
        headerShown: false,
        /* الشريط السفليّ يرسمه المُوجِّه بنفسه، ونحن ثبّتنا المحرّك على
           LTR، فيصفّ التبويبات من اليسار: «الرئيسية» في أقصى اليسار وهي
           أوّل ما تقصده العين. فنقلب الصفّ هنا، ويبقى ترتيب التصريح على
           حاله فلا تتبدّل الوجهات ولا الروابط العميقة. */
        tabBarStyle: {
          backgroundColor: c.card, borderTopWidth: 0, height: 64,
          paddingBottom: 9, paddingTop: 7, elevation: 0,
          flexDirection: 'row-reverse',
        },
        tabBarActiveTintColor: c.primary,
        tabBarInactiveTintColor: c.text3,
        tabBarLabelStyle: { fontSize: TYPE.micro, fontFamily: fontFor('600') },
        tabBarItemStyle: { paddingTop: 2 },
      }}>
      <Tab.Screen name="الرئيسية" component={Dashboard}
        options={{ tabBarIcon: ({ color, focused }) => <TabIcon focused={focused}><IcHome size={21} color={color} filled={focused} /></TabIcon> }} />
      <Tab.Screen name="القوالب" component={Library}
        options={{ tabBarIcon: ({ color, focused }) => <TabIcon focused={focused}><IcLibrary size={21} color={color} filled={focused} /></TabIcon> }} />
      <Tab.Screen name="الرصد" component={Attendance}
        options={{ tabBarIcon: ({ color, focused }) => <TabIcon focused={focused}><IcCheck size={21} color={color} /></TabIcon> }} />
      <Tab.Screen name="ملفّاتي" component={MyFiles}
        options={{ tabBarIcon: ({ color, focused }) => <TabIcon focused={focused}><IcFiles size={21} color={color} filled={focused} /></TabIcon> }} />
      <Tab.Screen name="حسابي" component={Account}
        options={{ tabBarIcon: ({ color, focused }) => <TabIcon focused={focused}><IcUser size={21} color={color} filled={focused} /></TabIcon> }} />
    </Tab.Navigator>
  )
}

/** التبويب النشط تحته وسادةٌ ملوّنة — إشارةٌ أوضح من تغيّر اللون وحده */
function TabIcon({ children, focused }: { children: React.ReactNode; focused: boolean }) {
  const { c } = useApp()
  return (
    <View style={{
      width: 46, height: 30, borderRadius: RADIUS.pill,
      backgroundColor: focused ? c.primarySoft : 'transparent',
      alignItems: 'center', justifyContent: 'center',
    }}>
      {children}
    </View>
  )
}

function Root() {
  const { access, ready, c, isDark } = useApp()

  const navTheme = {
    ...(isDark ? DarkTheme : DefaultTheme),
    colors: {
      ...(isDark ? DarkTheme : DefaultTheme).colors,
      background: c.bg, card: c.card, text: c.text, border: c.border, primary: c.primary,
    },
  }

  if (!ready || access === 'loading') {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: c.bg }}>
        <ActivityIndicator size="large" color={c.primary} />
      </View>
    )
  }

  return (
    <NavigationContainer theme={navTheme}>
      <StatusBar style={isDark ? 'light' : 'dark'} />
      <Stack.Navigator
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: c.bg },
          /* الدفع من اليمين — فالرجوع في العربيّة إلى اليمين */
          animation: 'slide_from_left',
        }}>
        {access === 'anon' ? (
          <Stack.Screen name="Login" component={Login} options={{ headerShown: false }} />
        ) : access === 'expired' || access === 'suspended' || access === 'member_suspended' ? (
          <Stack.Screen name="Blocked" component={Blocked} />
        ) : (
          <>
            <Stack.Screen name="Tabs" component={Tabs} options={{ headerShown: false }} />
            <Stack.Screen name="TemplateDetail" component={TemplateDetail} />
            <Stack.Screen name="Editor" component={Editor} />
            <Stack.Screen name="Timetable" component={Timetable} />
            <Stack.Screen name="Noor" component={Noor} />
            <Stack.Screen name="NoorTable" component={NoorTable} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  )
}

SplashScreen.preventAutoHideAsync().catch(() => {})

export default function App() {
  const [fontsLoaded, fontError] = useFonts({
    Cairo_400Regular, Cairo_500Medium, Cairo_600SemiBold, Cairo_700Bold,
  })

  const onReady = useCallback(() => {
    if (fontsLoaded || fontError) SplashScreen.hideAsync().catch(() => {})
  }, [fontsLoaded, fontError])

  // لا نرسم قبل وصول Cairo — وإلّا ومض النصّ بخطّ النظام ثمّ قفز
  if (!fontsLoaded && !fontError) return null

  return (
    <SafeAreaProvider onLayout={onReady}>
      <AppProvider>
        <Root />
      </AppProvider>
    </SafeAreaProvider>
  )
}
