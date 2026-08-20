import React from 'react';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { Colors } from '../theme/colors';

// Auth Screens
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';

// Drive Screens
import DriveScreen from '../screens/drive/DriveScreen';
import FolderScreen from '../screens/drive/FolderScreen';

// Profile
import ProfileScreen from '../screens/profile/ProfileScreen';

// Notifications
import NotificationsScreen from '../screens/notifications/NotificationsScreen';

// Admin
import AdminDashboardScreen from '../screens/admin/AdminDashboardScreen';
import UserManagementScreen from '../screens/admin/UserManagementScreen';
import EditUserScreen from '../screens/admin/EditUserScreen';

// Hidden System (admin)
import HiddenSystemScreen from '../screens/admin/HiddenSystemScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();
const DriveStack = createNativeStackNavigator();
const AdminStack = createNativeStackNavigator();

function DriveStackScreen() {
  return (
    <DriveStack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: Colors.primary },
        headerTintColor: Colors.gold,
        headerTitleStyle: { color: Colors.textPrimary, fontWeight: '700' },
      }}
    >
      <DriveStack.Screen name="DriveMain" component={DriveScreen} options={{ headerShown: false }} />
      <DriveStack.Screen name="Folder" component={FolderScreen} options={{ title: 'Folder' }} />
    </DriveStack.Navigator>
  );
}

function AdminStackScreen() {
  return (
    <AdminStack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: Colors.primary },
        headerTintColor: Colors.gold,
        headerTitleStyle: { color: Colors.textPrimary, fontWeight: '700' },
      }}
    >
      <AdminStack.Screen name="AdminMain" component={AdminDashboardScreen} options={{ title: 'Admin Panel' }} />
      <AdminStack.Screen name="UserManagement" component={UserManagementScreen} options={{ title: 'User Management' }} />
      <AdminStack.Screen name="EditUser" component={EditUserScreen} options={{ title: 'Edit User' }} />
      <AdminStack.Screen name="HiddenSystem" component={HiddenSystemScreen} options={{ title: 'Hidden System' }} />
    </AdminStack.Navigator>
  );
}

function MainTabs() {
  const { isAdmin } = useAuth();

  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarStyle: {
          backgroundColor: Colors.primary,
          borderTopColor: Colors.border,
          borderTopWidth: 1,
          height: 60,
          paddingBottom: 8,
          paddingTop: 4,
        },
        tabBarActiveTintColor: Colors.gold,
        tabBarInactiveTintColor: Colors.textMuted,
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;
          if (route.name === 'Drive') iconName = focused ? 'folder' : 'folder-outline';
          else if (route.name === 'Notifications') iconName = focused ? 'notifications' : 'notifications-outline';
          else if (route.name === 'Profile') iconName = focused ? 'person' : 'person-outline';
          else if (route.name === 'Admin') iconName = focused ? 'shield' : 'shield-outline';
          return <Ionicons name={iconName} size={size} color={color} />;
        },
      })}
    >
      <Tab.Screen name="Drive" component={DriveStackScreen} />
      <Tab.Screen name="Notifications" component={NotificationsScreen} options={{ title: 'Notifikasi' }} />
      <Tab.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile' }} />
      {isAdmin && (
        <Tab.Screen name="Admin" component={AdminStackScreen} />
      )}
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color={Colors.gold} />
      </View>
    );
  }

  return (
    <NavigationContainer
      theme={{
        dark: true,
        colors: {
          primary: Colors.gold,
          background: Colors.primary,
          card: Colors.card,
          text: Colors.textPrimary,
          border: Colors.border,
          notification: Colors.gold,
        },
      }}
    >
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!isAuthenticated ? (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="Register" component={RegisterScreen} />
          </>
        ) : (
          <Stack.Screen name="Main" component={MainTabs} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: Colors.primary,
  },
});
