import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet, Alert,
  ScrollView, ActivityIndicator, Modal, Image,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../../context/AuthContext';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';
import * as ImagePicker from 'expo-image-picker';

export default function ProfileScreen() {
  const { user, refreshUser, logout } = useAuth();
  const insets = useSafeAreaInsets();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(user?.name || '');
  const [email, setEmail] = useState(user?.email || '');
  const [showPassword, setShowPassword] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const storageUsedGB = ((user?.storage_used || 0) / (1024 * 1024 * 1024)).toFixed(2);
  const storageQuotaGB = ((user?.storage_quota || 0) / (1024 * 1024 * 1024)).toFixed(2);
  const storagePercent = user?.storage_quota > 0
    ? Math.min(100, ((user.storage_used || 0) / user.storage_quota) * 100) : 0;

  const handleUpdateProfile = async () => {
    if (!name || !email) { Alert.alert('Error', 'Nama dan email harus diisi'); return; }
    setLoading(true);
    try {
      await api.updateProfile(name, email);
      refreshUser();
      setEditing(false);
      Alert.alert('Berhasil', 'Profile berhasil diperbarui');
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleChangePassword = async () => {
    if (!currentPassword || !newPassword || !confirmPassword) {
      Alert.alert('Error', 'Semua field password harus diisi'); return;
    }
    if (newPassword !== confirmPassword) {
      Alert.alert('Error', 'Password baru tidak cocok'); return;
    }
    setLoading(true);
    try {
      await api.updatePassword(currentPassword, newPassword, confirmPassword);
      setShowPassword(false);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      Alert.alert('Berhasil', 'Password berhasil diubah');
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setLoading(false);
    }
  };

  const handlePickAvatar = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });

    if (!result.canceled) {
      setLoading(true);
      try {
        await api.uploadAvatar(result.assets[0].uri);
        refreshUser();
        Alert.alert('Berhasil', 'Avatar berhasil diubah');
      } catch (error) {
        Alert.alert('Error', error.message);
      } finally {
        setLoading(false);
      }
    }
  };

  return (
    <ScrollView style={[styles.container, { paddingTop: insets.top }]}
      contentContainerStyle={{ paddingBottom: 40 }}>
      {/* Avatar */}
      <View style={styles.avatarSection}>
        <TouchableOpacity onPress={handlePickAvatar} style={styles.avatarContainer}>
          {user?.avatar ? (
            <Image source={{ uri: user.avatar.startsWith('http') ? user.avatar : `${api.baseUrl}/storage/${user.avatar}` }}
              style={styles.avatar} />
          ) : (
            <View style={[styles.avatar, styles.avatarPlaceholder]}>
              <Text style={styles.avatarText}>{(user?.name || 'U')[0].toUpperCase()}</Text>
            </View>
          )}
          <View style={styles.avatarBadge}>
            <Ionicons name="camera" size={14} color={Colors.primary} />
          </View>
        </TouchableOpacity>
        <Text style={styles.userName}>{user?.name}</Text>
        <Text style={styles.userEmail}>{user?.email}</Text>
        {user?.role === 'admin' && (
          <View style={styles.adminBadge}>
            <Ionicons name="shield" size={12} color={Colors.primary} />
            <Text style={styles.adminText}>Admin</Text>
          </View>
        )}
      </View>

      {/* Storage */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Ionicons name="cloud" size={18} color={Colors.gold} />
          <Text style={styles.cardTitle}>Storage</Text>
        </View>
        <View style={styles.storageBarBg}>
          <View style={[styles.storageBarFill, { width: `${storagePercent}%` }]} />
        </View>
        <Text style={styles.storageText}>{storageUsedGB} GB / {storageQuotaGB} GB terpakai</Text>
      </View>

      {/* Profile Info */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Ionicons name="person" size={18} color={Colors.gold} />
          <Text style={styles.cardTitle}>Informasi Profile</Text>
        </View>
        {editing ? (
          <>
            <TextInput style={styles.input} placeholder="Nama" placeholderTextColor={Colors.textMuted}
              value={name} onChangeText={setName} />
            <TextInput style={styles.input} placeholder="Email" placeholderTextColor={Colors.textMuted}
              value={email} onChangeText={setEmail} keyboardType="email-address" />
            <View style={styles.btnRow}>
              <TouchableOpacity style={styles.btnSecondary} onPress={() => { setEditing(false); setName(user?.name); setEmail(user?.email); }}>
                <Text style={styles.btnSecondaryText}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.btnPrimary} onPress={handleUpdateProfile} disabled={loading}>
                {loading ? <ActivityIndicator color={Colors.primary} size="small" /> : <Text style={styles.btnPrimaryText}>Simpan</Text>}
              </TouchableOpacity>
            </View>
          </>
        ) : (
          <>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Nama</Text>
              <Text style={styles.infoValue}>{user?.name}</Text>
            </View>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Email</Text>
              <Text style={styles.infoValue}>{user?.email}</Text>
            </View>
            <TouchableOpacity style={styles.editBtn} onPress={() => setEditing(true)}>
              <Ionicons name="create-outline" size={16} color={Colors.gold} />
              <Text style={styles.editBtnText}>Edit Profile</Text>
            </TouchableOpacity>
          </>
        )}
      </View>

      {/* Password */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Ionicons name="lock-closed" size={18} color={Colors.gold} />
          <Text style={styles.cardTitle}>Ubah Password</Text>
        </View>
        <TextInput style={styles.input} placeholder="Password saat ini" placeholderTextColor={Colors.textMuted}
          value={currentPassword} onChangeText={setCurrentPassword} secureTextEntry />
        <TextInput style={styles.input} placeholder="Password baru" placeholderTextColor={Colors.textMuted}
          value={newPassword} onChangeText={setNewPassword} secureTextEntry />
        <TextInput style={styles.input} placeholder="Konfirmasi password baru" placeholderTextColor={Colors.textMuted}
          value={confirmPassword} onChangeText={setConfirmPassword} secureTextEntry />
        <TouchableOpacity style={styles.btnPrimary} onPress={handleChangePassword} disabled={loading}>
          {loading ? <ActivityIndicator color={Colors.primary} size="small" /> : <Text style={styles.btnPrimaryText}>Ubah Password</Text>}
        </TouchableOpacity>
      </View>

      {/* Logout */}
      <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
        <Ionicons name="log-out-outline" size={20} color={Colors.danger} />
        <Text style={styles.logoutText}>Logout</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },

  avatarSection: { alignItems: 'center', paddingVertical: 24 },
  avatarContainer: { position: 'relative' },
  avatar: { width: 96, height: 96, borderRadius: 48, borderWidth: 3, borderColor: Colors.gold },
  avatarPlaceholder: { backgroundColor: Colors.card, justifyContent: 'center', alignItems: 'center' },
  avatarText: { fontSize: 36, fontWeight: '700', color: Colors.gold },
  avatarBadge: {
    position: 'absolute', bottom: 0, right: 0,
    width: 28, height: 28, borderRadius: 14,
    backgroundColor: Colors.gold, justifyContent: 'center', alignItems: 'center',
    borderWidth: 2, borderColor: Colors.primary,
  },
  userName: { fontSize: 20, fontWeight: '700', color: Colors.textPrimary, marginTop: 12 },
  userEmail: { fontSize: 14, color: Colors.textMuted, marginTop: 2 },
  adminBadge: {
    flexDirection: 'row', alignItems: 'center', gap: 4,
    backgroundColor: Colors.goldMuted, borderRadius: 12,
    paddingHorizontal: 10, paddingVertical: 4, marginTop: 8,
  },
  adminText: { color: Colors.gold, fontSize: 12, fontWeight: '600' },

  card: {
    marginHorizontal: Spacing.lg, marginBottom: Spacing.lg,
    backgroundColor: Colors.card, borderRadius: BorderRadius.lg,
    padding: 20, borderWidth: 1, borderColor: Colors.border,
  },
  cardHeader: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 },
  cardTitle: { fontSize: 16, fontWeight: '700', color: Colors.textPrimary },

  storageBarBg: { height: 8, backgroundColor: Colors.input, borderRadius: 4, marginBottom: 8 },
  storageBarFill: { height: 8, backgroundColor: Colors.gold, borderRadius: 4 },
  storageText: { fontSize: 13, color: Colors.textMuted, textAlign: 'center' },

  infoRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: Colors.border,
  },
  infoLabel: { color: Colors.textMuted, fontSize: 14 },
  infoValue: { color: Colors.textPrimary, fontSize: 14, fontWeight: '600' },

  editBtn: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    marginTop: 12, paddingVertical: 8,
  },
  editBtnText: { color: Colors.gold, fontSize: 14, fontWeight: '600' },

  input: {
    backgroundColor: Colors.input, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.inputBorder,
    padding: 14, color: Colors.textPrimary, fontSize: 15, marginBottom: 12,
  },

  btnRow: { flexDirection: 'row', gap: 10 },
  btnSecondary: {
    flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md,
    backgroundColor: Colors.surface, alignItems: 'center', borderWidth: 1, borderColor: Colors.border,
  },
  btnSecondaryText: { color: Colors.textMuted, fontWeight: '600' },
  btnPrimary: {
    paddingVertical: 12, borderRadius: BorderRadius.md,
    backgroundColor: Colors.gold, alignItems: 'center',
  },
  btnPrimaryText: { color: Colors.primary, fontWeight: '700' },

  logoutBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
    marginHorizontal: Spacing.lg, paddingVertical: 14,
    borderRadius: BorderRadius.md, borderWidth: 1, borderColor: Colors.danger,
    backgroundColor: Colors.dangerBg,
  },
  logoutText: { color: Colors.danger, fontSize: 16, fontWeight: '600' },
});
