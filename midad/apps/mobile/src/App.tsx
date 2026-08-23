import React from 'react'
import { ActivityIndicator, View, I18nManager } from 'react-native'
import { NavigationContainer, DefaultTheme, DarkTheme } from '@react-navigation/native'
import { createNativeStackNavigator } from '@react-navigation/native-stack'
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { SafeAreaProvider } from 'react-native-safe-area-context'
import { StatusBar } from 'expo-status-bar'
import { Text } from 'react-native'

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

const Tab = createBottomTabNavigator()
const Stack = createNativeStackNavigator()

function TabIcon({ label, focused, color }: { label: string; focused: boolean; color: string }) {
  return <Text style={{ fontSize: focused ? 20 : 18, color }}>{label}</Text>
}

function Tabs() {
  const { c } = useApp()
  return (
    <Tab.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: c.card },
        headerTitleStyle: { color: c.text, fontWeight: '700' },
        headerTintColor: c.text,
        tabBarStyle: { backgroundColor: c.card, borderTopColor: c.border, height: 62, paddingBottom: 8, paddingTop: 6 },
        tabBarActiveTintColor: c.accent,
        tabBarInactiveTintColor: c.text3,
        tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
      }}>
      <Tab.Screen name="الرئيسية" component={Dashboard}
        options={{ tabBarIcon: (p) => <TabIcon label="🏠" {...p} /> }} />
      <Tab.Screen name="المكتبة" component={Library}
        options={{ tabBarIcon: (p) => <TabIcon label="📚" {...p} /> }} />
      <Tab.Screen name="ملفّاتي" component={MyFiles}
        options={{ tabBarIcon: (p) => <TabIcon label="📄" {...p} /> }} />
      <Tab.Screen name="جداول نور" component={Noor}
        options={{ tabBarIcon: (p) => <TabIcon label="📊" {...p} /> }} />
      <Tab.Screen name="حسابي" component={Account}
        options={{ tabBarIcon: (p) => <TabIcon label="👤" {...p} /> }} />
    </Tab.Navigator>
  )
}

function Root() {
  const { access, ready, c, isDark } = useApp()

  const navTheme = {
    ...(isDark ? DarkTheme : DefaultTheme),
    colors: {
      ...(isDark ? DarkTheme : DefaultTheme).colors,
      background: c.bg, card: c.card, text: c.text, border: c.border, primary: c.accent,
    },
  }

  if (!ready || access === 'loading') {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: c.bg }}>
        <ActivityIndicator size="large" color={c.accent} />
      </View>
    )
  }

  return (
    <NavigationContainer theme={navTheme}>
      <StatusBar style={isDark ? 'light' : 'dark'} />
      <Stack.Navigator
        screenOptions={{
          headerStyle: { backgroundColor: c.card },
          headerTitleStyle: { color: c.text, fontWeight: '700' },
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
            <Stack.Screen name="NoorTable" component={NoorTable} options={{ title: 'الجدول' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  )
}

export default function App() {
  return (
    <SafeAreaProvider>
      <AppProvider>
        <Root />
      </AppProvider>
    </SafeAreaProvider>
  )
}
