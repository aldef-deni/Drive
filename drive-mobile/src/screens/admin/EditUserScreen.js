import React, { useState, useEffect } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ScrollView, ActivityIndicator, Switch,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

export default function EditUserScreen({ route, navigation }) {
  const { userId } = route.params;
  const [user, setUser] = useState(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [storageQuotaGB, setStorageQuotaGB] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [role, setRole] = useState('user');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadUser();
  }, []);

  const loadUser = async () => {
    try {
      const res = await api.getAdminUser(userId);
      const u = res.user;
      setUser(u);
      setName(u.name);
      setEmail(u.email);
      setStorageQuotaGB(u.storage_quota_gb.toString());
      setIsActive(u.is_active);
      setRole(u.role);
    } catch (error) {
      Alert.alert('Error', error.message);
      navigation.goBack();
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!name || !email) {
      Alert.alert('Error', 'Nama dan email harus diisi');
      return;
    }

    setSaving(true);
    try {
      await api.updateAdminUser(userId, {
        name,
        email,
        storage_quota_gb: parseFloat(storageQuotaGB) || 10,
        is_active: isActive,
        role,
      });
      Alert.alert('Berhasil', 'User berhasil diupdate', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setSaving(false);
    }
  };

  const handleResetStorage = async () => {
    Alert.alert('Reset Storage', 'Reset storage user ini?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Reset', onPress: async () => {
          try {
            const res = await api.resetUserStorage(userId);
            Alert.alert('Berhasil', `Storage direset: ${res.storage_used_formatted}`);
            loadUser();
          } catch (error) {
            Alert.alert('Error', error.message);
          }
        },
      },
    ]);
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={Colors.gold} />
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: Spacing.lg, paddingBottom: 40 }}>
      {/* User Info Card */}
      <View style={styles.card}>
        <View style={styles.userHeader}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{user?.name?.[0]?.toUpperCase()}</Text>
          </View>
          <View>
            <Text style={styles.userName}>{user?.name}</Text>
            <Text style={styles.userEmail}>{user?.email}</Text>
          </View>
        </View>

        <View style={styles.storageInfo}>
          <Text style={styles.storageLabel}>Storage terpakai: {user?.storage_used_formatted}</Text>
          <View style={styles.storageBarBg}>
            <View style={[styles.storageBarFill, { width: `${user?.storage_percentage || 0}%` }]} />
          </View>
        </View>
      </View>

      {/* Edit Form */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Edit User</Text>

        <Text style={styles.label}>Nama</Text>
        <TextInput style={styles.input} value={name} onChangeText={setName} placeholderTextColor={Colors.textMuted} />

        <Text style={styles.label}>Email</Text>
        <TextInput style={styles.input} value={email} onChangeText={setEmail}
          keyboardType="email-address" placeholderTextColor={Colors.textMuted} />

        <Text style={styles.label}>Storage Kuota (GB)</Text>
        <TextInput style={styles.input} value={storageQuotaGB} onChangeText={setStorageQuotaGB}
          keyboardType="numeric" placeholderTextColor={Colors.textMuted} />

        <Text style={styles.label}>Role</Text>
        <View style={styles.roleRow}>
          <TouchableOpacity
            style={[styles.roleBtn, role === 'user' && styles.roleBtnActive]}
            onPress={() => setRole('user')}>
            <Text style={[styles.roleText, role === 'user' && styles.roleTextActive]}>User</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.roleBtn, role === 'admin' && styles.roleBtnActive]}
            onPress={() => setRole('admin')}>
            <Text style={[styles.roleText, role === 'admin' && styles.roleTextActive]}>Admin</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>Aktif</Text>
          <Switch value={isActive} onValueChange={setIsActive}
            trackColor={{ false: Colors.input, true: Colors.gold + '60' }}
            thumbColor={isActive ? Colors.gold : Colors.textMuted} />
        </View>
      </View>

      {/* Actions */}
      <TouchableOpacity style={styles.saveBtn} onPress={handleSave} disabled={saving}>
        {saving ? <ActivityIndicator color={Colors.primary} /> : <Text style={styles.saveBtnText}>Simpan Perubahan</Text>}
      </TouchableOpacity>

      <TouchableOpacity style={styles.resetBtn} onPress={handleResetStorage}>
        <Ionicons name="refresh" size={18} color={Colors.warning} />
        <Text style={styles.resetBtnText}>Reset Storage</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.primary },

  card: {
    backgroundColor: Colors.card, borderRadius: BorderRadius.lg,
    padding: 20, marginBottom: 16, borderWidth: 1, borderColor: Colors.border,
  },
  cardTitle: { fontSize: 16, fontWeight: '700', color: Colors.textPrimary, marginBottom: 16 },

  userHeader: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: Colors.goldMuted, justifyContent: 'center', alignItems: 'center' },
  avatarText: { fontSize: 20, fontWeight: '700', color: Colors.gold },
  userName: { fontSize: 16, fontWeight: '700', color: Colors.textPrimary },
  userEmail: { fontSize: 13, color: Colors.textMuted },

  storageInfo: { marginTop: 8 },
  storageLabel: { fontSize: 12, color: Colors.textMuted, marginBottom: 6 },
  storageBarBg: { height: 6, backgroundColor: Colors.input, borderRadius: 3 },
  storageBarFill: { height: 6, backgroundColor: Colors.gold, borderRadius: 3 },

  label: { color: Colors.textMuted, fontSize: 13, marginBottom: 6, marginTop: 8 },
  input: {
    backgroundColor: Colors.input, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.inputBorder,
    padding: 14, color: Colors.textPrimary, fontSize: 15,
  },

  roleRow: { flexDirection: 'row', gap: 10, marginTop: 4 },
  roleBtn: {
    flex: 1, paddingVertical: 10, borderRadius: BorderRadius.md,
    backgroundColor: Colors.input, alignItems: 'center', borderWidth: 1, borderColor: Colors.border,
  },
  roleBtnActive: { backgroundColor: Colors.goldMuted, borderColor: Colors.gold },
  roleText: { color: Colors.textMuted, fontWeight: '600' },
  roleTextActive: { color: Colors.gold },

  switchRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    marginTop: 16, paddingVertical: 8,
  },
  switchLabel: { color: Colors.textPrimary, fontSize: 15, fontWeight: '600' },

  saveBtn: {
    backgroundColor: Colors.gold, borderRadius: BorderRadius.md,
    paddingVertical: 14, alignItems: 'center', marginBottom: 12,
  },
  saveBtnText: { color: Colors.primary, fontSize: 16, fontWeight: '700' },

  resetBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
    paddingVertical: 14, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.warning, backgroundColor: Colors.warningBg,
  },
  resetBtnText: { color: Colors.warning, fontSize: 14, fontWeight: '600' },
});
