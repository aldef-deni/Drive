import React, { createContext, useState, useEffect, useContext } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../config/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState(null);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const storedToken = await AsyncStorage.getItem('api_token');
      if (storedToken) {
        setToken(storedToken);
        const response = await api.me();
        setUser(response.user);
      }
    } catch (error) {
      await AsyncStorage.removeItem('api_token');
      setToken(null);
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  const login = async (email, password) => {
    const response = await api.login(email, password);
    if (response.success) {
      await AsyncStorage.setItem('api_token', response.token);
      setToken(response.token);
      setUser(response.user);
      return response;
    }
    throw new Error(response.message);
  };

  const register = async (name, email, password, passwordConfirmation) => {
    return await api.register(name, email, password, passwordConfirmation);
  };

  const logout = async () => {
    try {
      await api.logout();
    } catch (e) {
      // ignore
    }
    await AsyncStorage.removeItem('api_token');
    setToken(null);
    setUser(null);
  };

  const refreshUser = async () => {
    try {
      const response = await api.me();
      setUser(response.user);
    } catch (e) {
      // ignore
    }
  };

  const updateLocalUser = (updates) => {
    setUser(prev => ({ ...prev, ...updates }));
  };

  return (
    <AuthContext.Provider value={{
      user,
      token,
      loading,
      login,
      register,
      logout,
      refreshUser,
      updateLocalUser,
      isAuthenticated: !!token && !!user,
      isAdmin: user?.role === 'admin',
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
};
