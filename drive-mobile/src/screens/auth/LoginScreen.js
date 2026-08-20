import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  KeyboardAvoidingView, Platform, ScrollView, Alert, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../../context/AuthContext';
import { Colors, BorderRadius } from '../../theme/colors';

export default function LoginScreen({ navigation }) {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Email dan password harus diisi');
      return;
    }

    setLoading(true);
    try {
      await login(email, password);
    } catch (error) {
      Alert.alert('Login Gagal', error.message || 'Email atau password salah');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        {/* Background Decoration */}
        <View style={styles.bgOrb1} />
        <View style={styles.bgOrb2} />

        {/* Logo */}
        <View style={styles.logoContainer}>
          <View style={styles.logoIcon}>
            <Ionicons name="cloud" size={36} color={Colors.gold} />
          </View>
          <Text style={styles.logoText}>Dekorasi Drive</Text>
          <Text style={styles.logoSub}>Cloud Storage Premium</Text>
        </View>

        {/* Card */}
        <View style={styles.card}>
          {/* Gold Line */}
          <View style={styles.goldLine} />

          <Text style={styles.cardTitle}>Sign In</Text>
          <Text style={styles.cardSubtitle}>Masuk ke akun Anda</Text>

          {/* Email */}
          <View style={styles.inputWrapper}>
            <Ionicons name="mail-outline" size={20} color={Colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              placeholder="Email"
              placeholderTextColor={Colors.textMuted}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
            />
          </View>

          {/* Password */}
          <View style={styles.inputWrapper}>
            <Ionicons name="lock-closed-outline" size={20} color={Colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder="Password"
              placeholderTextColor={Colors.textMuted}
              value={password}
              onChangeText={setPassword}
              secureTextEntry={!showPassword}
            />
            <TouchableOpacity onPress={() => setShowPassword(!showPassword)} style={styles.eyeBtn}>
              <Ionicons name={showPassword ? 'eye' : 'eye-off'} size={20} color={Colors.textMuted} />
            </TouchableOpacity>
          </View>

          {/* Button */}
          <TouchableOpacity
            style={[styles.button, loading && styles.buttonDisabled]}
            onPress={handleLogin}
            disabled={loading}
            activeOpacity={0.8}
          >
            {loading ? (
              <ActivityIndicator color={Colors.primary} />
            ) : (
              <>
                <Ionicons name="log-in-outline" size={20} color={Colors.primary} />
                <Text style={styles.buttonText}>Sign In</Text>
              </>
            )}
          </TouchableOpacity>

          {/* Register Link */}
          <View style={styles.registerRow}>
            <Text style={styles.registerText}>Belum punya akun? </Text>
            <TouchableOpacity onPress={() => navigation.navigate('Register')}>
              <Text style={styles.registerLink}>Daftar</Text>
            </TouchableOpacity>
          </View>
        </View>

        <Text style={styles.footer}>&copy; 2026 Dekorasi.me</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    position: 'relative',
  },
  bgOrb1: {
    position: 'absolute', top: -80, right: -80,
    width: 200, height: 200, borderRadius: 100,
    backgroundColor: 'rgba(212, 168, 67, 0.08)',
  },
  bgOrb2: {
    position: 'absolute', bottom: -60, left: -60,
    width: 160, height: 160, borderRadius: 80,
    backgroundColor: 'rgba(22, 42, 82, 0.4)',
  },
  logoContainer: { alignItems: 'center', marginBottom: 24 },
  logoIcon: {
    width: 72, height: 72, borderRadius: 36,
    backgroundColor: Colors.card,
    justifyContent: 'center', alignItems: 'center',
    borderWidth: 2, borderColor: Colors.gold,
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 8, elevation: 8,
  },
  logoText: {
    fontSize: 26, fontWeight: '800', color: Colors.textPrimary,
    marginTop: 12, letterSpacing: 1,
  },
  logoSub: { fontSize: 13, color: Colors.textMuted, marginTop: 2 },

  card: {
    width: '100%', maxWidth: 380,
    backgroundColor: 'rgba(22, 42, 82, 0.9)',
    borderRadius: BorderRadius.xl,
    padding: 28,
    borderWidth: 1, borderColor: 'rgba(212, 168, 67, 0.25)',
    shadowColor: Colors.gold,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 12,
    overflow: 'hidden',
  },
  goldLine: {
    position: 'absolute', top: 0, left: 0, right: 0,
    height: 3, backgroundColor: Colors.gold,
    borderTopLeftRadius: BorderRadius.xl,
    borderTopRightRadius: BorderRadius.xl,
  },
  cardTitle: {
    fontSize: 22, fontWeight: '700', color: Colors.textPrimary,
    textAlign: 'center', marginBottom: 4,
  },
  cardSubtitle: {
    fontSize: 13, color: Colors.textMuted,
    textAlign: 'center', marginBottom: 24,
  },

  inputWrapper: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: Colors.input,
    borderRadius: BorderRadius.lg,
    borderWidth: 1, borderColor: Colors.inputBorder,
    marginBottom: 14,
    paddingHorizontal: 14, height: 52,
    shadowColor: '#000', shadowOffset: { width: 2, height: 2 },
    shadowOpacity: 0.3, shadowRadius: 4, elevation: 2,
  },
  inputIcon: { marginRight: 10 },
  input: {
    flex: 1, color: Colors.textPrimary, fontSize: 15,
    height: '100%',
  },
  eyeBtn: { padding: 4 },

  button: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    backgroundColor: Colors.gold,
    borderRadius: BorderRadius.lg,
    height: 52, marginTop: 6,
    shadowColor: Colors.gold,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 8, elevation: 6,
  },
  buttonDisabled: { opacity: 0.7 },
  buttonText: {
    fontSize: 16, fontWeight: '700', color: Colors.primary,
    marginLeft: 8,
  },

  registerRow: {
    flexDirection: 'row', justifyContent: 'center',
    marginTop: 20,
  },
  registerText: { color: Colors.textMuted, fontSize: 14 },
  registerLink: { color: Colors.gold, fontSize: 14, fontWeight: '700' },

  footer: {
    color: Colors.textMuted, fontSize: 12,
    marginTop: 24, textAlign: 'center',
  },
});
