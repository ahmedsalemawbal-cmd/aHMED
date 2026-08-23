import React, { useCallback } from 'react'
import { ActivityIndicator, View, I18nManager } from 'react-native'
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
import { IcHome, IcLibrary, IcFiles, IcTable, IcUser, IcCheck } from './ui/icons'
import { fontFor } from './lib/theme'

const Tab = createBottomTabNavigator()
const Stack = createNativeStackNavigator()

type TabIconFn = (p: { focused: boolean; color: string; size: number }) => React.ReactNode

function Tabs() {
  const { c } = useApp()
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: c.card },
        headerTitleStyle: { color: c.text, fontFamily: fontFor('700'), fontSize: 17 },
        headerTintColor: c.text,
        tabBarStyle: { backgroundColor: c.card, borderTopColor: c.border, height: 62, paddingBottom: 8, paddingTop: 6 },
        tabBarActiveTintColor: c.primary,
        tabBarInactiveTintColor: c.text3,
        tabBarLabelStyle: { fontSize: 11, fontFamily: fontFor('600') },
      }}>
      <Tab.Screen name="الرئيسية" component={Dashboard}
        options={{ tabBarIcon: ({ color, focused }) => <IcHome size={22} color={color} filled={focused} /> }} />
      <Tab.Screen name="القوالب" component={Library}
        options={{ tabBarIcon: ({ color, focused }) => <IcLibrary size={22} color={color} filled={focused} /> }} />
      <Tab.Screen name="الرصد" component={Attendance}
        options={{ tabBarIcon: ({ color, focused }) => <IcCheck size={22} color={color} /> }} />
      <Tab.Screen name="ملفّاتي" component={MyFiles}
        options={{ tabBarIcon: ({ color, focused }) => <IcFiles size={22} color={color} filled={focused} /> }} />
      <Tab.Screen name="حسابي" component={Account}
        options={{ tabBarIcon: ({ color, focused }) => <IcUser size={22} color={color} filled={focused} /> }} />
    </Tab.Navigator>
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
          headerStyle: { backgroundColor: c.card },
          headerTitleStyle: { color: c.text, fontFamily: fontFor('700'), fontSize: 17 },
          headerTintColor: c.text,
          headerBackTitleVisible: false,
          contentStyle: { backgroundColor: c.bg },
        }}>
        {access === 'anon' ? (
          <Stack.Screen name="Login" component={Login} options={{ headerShown: false }} />
        ) : access === 'expired' || access === 'suspended' || access === 'member_suspended' ? (
          <Stack.Screen name="Blocked" component={Blocked} options={{ title: 'مِداد' }} />
        ) : (
          <>
            <Stack.Screen name="Tabs" component={Tabs} options={{ headerShown: false }} />
            <Stack.Screen name="TemplateDetail" component={TemplateDetail} options={{ title: 'القالب' }} />
            <Stack.Screen name="Editor" component={Editor} options={{ title: 'الملفّ' }} />
            <Stack.Screen name="Timetable" component={Timetable} options={{ title: 'جدولي' }} />
            <Stack.Screen name="Noor" component={Noor} options={{ title: 'جداول نور' }} />
            <Stack.Screen name="NoorTable" component={NoorTable} options={{ title: 'الجدول' }} />
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
