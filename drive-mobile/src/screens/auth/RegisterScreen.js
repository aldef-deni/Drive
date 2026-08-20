import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  KeyboardAvoidingView, Platform, ScrollView, Alert, ActivityIndicator, Modal,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../../context/AuthContext';
import { Colors, BorderRadius } from '../../theme/colors';

export default function RegisterScreen({ navigation }) {
  const { register } = useAuth();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showSuccess, setShowSuccess] = useState(false);

  const handleRegister = async () => {
    if (!name || !email || !password || !passwordConfirmation) {
      Alert.alert('Error', 'Semua field harus diisi');
      return;
    }
    if (password !== passwordConfirmation) {
      Alert.alert('Error', 'Password tidak cocok');
      return;
    }

    setLoading(true);
    try {
      await register(name, email, password, passwordConfirmation);
      setShowSuccess(true);
    } catch (error) {
      Alert.alert('Registrasi Gagal', error.message || 'Terjadi kesalahan');
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
        <View style={styles.bgOrb1} />
        <View style={styles.bgOrb2} />

        {/* Logo */}
        <View style={styles.logoContainer}>
          <View style={styles.logoIcon}>
            <Ionicons name="cloud" size={36} color={Colors.gold} />
          </View>
          <Text style={styles.logoText}>Dekorasi Drive</Text>
        </View>

        {/* Card */}
        <View style={styles.card}>
          <View style={styles.goldLine} />
          <Text style={styles.cardTitle}>Buat Akun</Text>
          <Text style={styles.cardSubtitle}>Daftar untuk mulai menggunakan Drive</Text>

          {/* Name */}
          <View style={styles.inputWrapper}>
            <Ionicons name="person-outline" size={20} color={Colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={styles.input}
              placeholder="Nama Lengkap"
              placeholderTextColor={Colors.textMuted}
              value={name}
              onChangeText={setName}
            />
          </View>

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

          {/* Confirm Password */}
          <View style={styles.inputWrapper}>
            <Ionicons name="lock-closed-outline" size={20} color={Colors.textMuted} style={styles.inputIcon} />
            <TextInput
              style={[styles.input, { flex: 1 }]}
              placeholder="Konfirmasi Password"
              placeholderTextColor={Colors.textMuted}
              value={passwordConfirmation}
              onChangeText={setPasswordConfirmation}
              secureTextEntry={!showPassword}
            />
          </View>

          {/* Button */}
          <TouchableOpacity
            style={[styles.button, loading && styles.buttonDisabled]}
            onPress={handleRegister}
            disabled={loading}
            activeOpacity={0.8}
          >
            {loading ? (
              <ActivityIndicator color={Colors.primary} />
            ) : (
              <>
                <Ionicons name="person-add-outline" size={20} color={Colors.primary} />
                <Text style={styles.buttonText}>Daftar</Text>
              </>
            )}
          </TouchableOpacity>

          <View style={styles.loginRow}>
            <Text style={styles.loginText}>Sudah punya akun? </Text>
            <TouchableOpacity onPress={() => navigation.navigate('Login')}>
              <Text style={styles.loginLink}>Masuk</Text>
            </TouchableOpacity>
          </View>
        </View>

        <Text style={styles.footer}>&copy; 2026 Dekorasi.me</Text>
      </ScrollView>

      {/* Success Modal */}
      <Modal visible={showSuccess} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalIcon}>
              <Ionicons name="checkmark-circle" size={48} color={Colors.success} />
            </View>
            <Text style={styles.modalTitle}>Registrasi Berhasil!</Text>
            <Text style={styles.modalMessage}>
              Mohon tunggu untuk verifikasi Admin Dekorasi
            </Text>
            <TouchableOpacity style={styles.modalButton} onPress={() => { setShowSuccess(false); navigation.navigate('Login'); }}>
              <Text style={styles.modalButtonText}>Ke Halaman Login</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },
  scroll: {
    flexGrow: 1, justifyContent: 'center', alignItems: 'center',
    padding: 20, position: 'relative',
  },
  bgOrb1: {
    position: 'absolute', top: -80, left: -80,
    width: 200, height: 200, borderRadius: 100,
    backgroundColor: 'rgba(212, 168, 67, 0.08)',
  },
  bgOrb2: {
    position: 'absolute', bottom: -60, right: -60,
    width: 160, height: 160, borderRadius: 80,
    backgroundColor: 'rgba(22, 42, 82, 0.4)',
  },
  logoContainer: { alignItems: 'center', marginBottom: 20 },
  logoIcon: {
    width: 72, height: 72, borderRadius: 36,
    backgroundColor: Colors.card, justifyContent: 'center', alignItems: 'center',
    borderWidth: 2, borderColor: Colors.gold,
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 8, elevation: 8,
  },
  logoText: {
    fontSize: 26, fontWeight: '800', color: Colors.textPrimary,
    marginTop: 12, letterSpacing: 1,
  },
  card: {
    width: '100%', maxWidth: 380,
    backgroundColor: 'rgba(22, 42, 82, 0.9)',
    borderRadius: BorderRadius.xl, padding: 28,
    borderWidth: 1, borderColor: 'rgba(212, 168, 67, 0.25)',
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.15, shadowRadius: 20, elevation: 12,
    overflow: 'hidden',
  },
  goldLine: {
    position: 'absolute', top: 0, left: 0, right: 0, height: 3,
    backgroundColor: Colors.gold,
    borderTopLeftRadius: BorderRadius.xl, borderTopRightRadius: BorderRadius.xl,
  },
  cardTitle: { fontSize: 22, fontWeight: '700', color: Colors.textPrimary, textAlign: 'center', marginBottom: 4 },
  cardSubtitle: { fontSize: 13, color: Colors.textMuted, textAlign: 'center', marginBottom: 20 },

  inputWrapper: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: Colors.input, borderRadius: BorderRadius.lg,
    borderWidth: 1, borderColor: Colors.inputBorder, marginBottom: 12,
    paddingHorizontal: 14, height: 50,
    shadowColor: '#000', shadowOffset: { width: 2, height: 2 },
    shadowOpacity: 0.3, shadowRadius: 4, elevation: 2,
  },
  inputIcon: { marginRight: 10 },
  input: { flex: 1, color: Colors.textPrimary, fontSize: 15, height: '100%' },
  eyeBtn: { padding: 4 },

  button: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    backgroundColor: Colors.gold, borderRadius: BorderRadius.lg,
    height: 50, marginTop: 4,
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 8, elevation: 6,
  },
  buttonDisabled: { opacity: 0.7 },
  buttonText: { fontSize: 16, fontWeight: '700', color: Colors.primary, marginLeft: 8 },

  loginRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 16 },
  loginText: { color: Colors.textMuted, fontSize: 14 },
  loginLink: { color: Colors.gold, fontSize: 14, fontWeight: '700' },
  footer: { color: Colors.textMuted, fontSize: 12, marginTop: 20, textAlign: 'center' },

  // Modal
  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center', alignItems: 'center', padding: 20,
  },
  modalCard: {
    width: '100%', maxWidth: 340,
    backgroundColor: Colors.card, borderRadius: BorderRadius.xl,
    padding: 32, alignItems: 'center',
    borderWidth: 1, borderColor: Colors.border,
  },
  modalIcon: { marginBottom: 16 },
  modalTitle: { fontSize: 20, fontWeight: '700', color: Colors.textPrimary, marginBottom: 8 },
  modalMessage: { fontSize: 14, color: Colors.textSecondary, textAlign: 'center', marginBottom: 24, lineHeight: 20 },
  modalButton: {
    backgroundColor: Colors.gold, borderRadius: BorderRadius.lg,
    paddingVertical: 14, paddingHorizontal: 32, width: '100%',
    alignItems: 'center',
  },
  modalButtonText: { fontSize: 16, fontWeight: '700', color: Colors.primary },
});
