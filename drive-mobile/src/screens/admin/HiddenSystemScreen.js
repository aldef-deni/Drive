import React, { useEffect, useState } from 'react';
import {
  View, Text, ScrollView, TextInput, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

/**
 * Hidden System — halaman untuk mengganti kata kunci rahasia yang dipakai
 * memunculkan file/folder tersembunyi lewat kolom pencarian Drive.
 * Halaman ini tidak menampilkan daftar file tersembunyi.
 */
export default function HiddenSystemScreen() {
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [currentPassword, setCurrentPassword] = useState('');
  const [keyword, setKeyword] = useState('');
  const [keywordConfirm, setKeywordConfirm] = useState('');
  const [showKeyword, setShowKeyword] = useState(false);

  const loadStatus = async () => {
    try {
      const res = await api.getHiddenKeyword();
      setStatus(res);
    } catch (error) {
      Alert.alert('Error', error.message || 'Gagal memuat status');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadStatus(); }, []);

  const handleSave = async () => {
    if (!currentPassword) { Alert.alert('Error', 'Masukkan password admin'); return; }
    if (keyword.trim().length < 4) { Alert.alert('Error', 'Kata kunci minimal 4 karakter'); return; }
    if (keyword !== keywordConfirm) { Alert.alert('Error', 'Konfirmasi kata kunci tidak sama'); return; }

    setSaving(true);
    try {
      await api.updateHiddenKeyword(currentPassword, keyword.trim());
      setCurrentPassword('');
      setKeyword('');
      setKeywordConfirm('');
      await loadStatus();
      Alert.alert('Berhasil', 'Kata kunci rahasia berhasil diperbarui');
    } catch (error) {
      Alert.alert('Error', error.message || 'Gagal menyimpan kata kunci');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={Colors.gold} />
      </View>
    );
  }

  const isDefault = status?.is_default;

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: Spacing.lg }}>
      {/* Penjelasan konsep */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View style={styles.iconBox}>
            <Ionicons name="eye-off" size={20} color={Colors.gold} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.cardTitle}>Kata Kunci Rahasia</Text>
            <Text style={styles.cardSubtitle}>
              Diketik di kolom pencarian Drive untuk memunculkan file dan folder yang disembunyikan.
            </Text>
          </View>
        </View>

        <View style={styles.steps}>
          {[
            'Tekan lama file atau folder, pilih Sembunyikan. Item hilang dari daftar dan pencarian biasa.',
            'Ketik kata kunci rahasia di kolom pencarian untuk memunculkannya kembali.',
            'Selama mode rahasia menyala, pilih Tampilkan untuk mengembalikan item ke Drive.',
          ].map((text, i) => (
            <View key={i} style={styles.stepRow}>
              <View style={styles.stepBadge}><Text style={styles.stepNumber}>{i + 1}</Text></View>
              <Text style={styles.stepText}>{text}</Text>
            </View>
          ))}
        </View>
      </View>

      {/* Status */}
      <View style={[styles.statusCard, { borderColor: isDefault ? Colors.warning : Colors.success }]}>
        <Ionicons
          name={isDefault ? 'warning' : 'shield-checkmark'}
          size={20}
          color={isDefault ? Colors.warning : Colors.success}
        />
        <View style={{ flex: 1 }}>
          <Text style={[styles.statusTitle, { color: isDefault ? Colors.warning : Colors.success }]}>
            {isDefault ? 'Masih memakai kata kunci bawaan' : 'Kata kunci sudah diganti'}
          </Text>
          <Text style={styles.statusSubtitle}>
            {isDefault
              ? 'Kata kunci bawaan diketahui banyak orang, sebaiknya segera diganti.'
              : `Terakhir diperbarui ${new Date(status.updated_at).toLocaleString('id-ID')}`}
          </Text>
        </View>
      </View>

      {/* Form */}
      <View style={styles.card}>
        <Text style={styles.formTitle}>Ganti Kata Kunci</Text>

        <Text style={styles.label}>Password Admin</Text>
        <TextInput
          style={styles.input}
          value={currentPassword}
          onChangeText={setCurrentPassword}
          placeholder="Password login Anda"
          placeholderTextColor={Colors.textMuted}
          secureTextEntry
        />

        <Text style={styles.label}>Kata Kunci Baru</Text>
        <View style={styles.inputRow}>
          <TextInput
            style={[styles.input, { flex: 1, marginBottom: 0 }]}
            value={keyword}
            onChangeText={setKeyword}
            placeholder="Minimal 4 karakter"
            placeholderTextColor={Colors.textMuted}
            secureTextEntry={!showKeyword}
            autoCapitalize="none"
          />
          <TouchableOpacity style={styles.eyeBtn} onPress={() => setShowKeyword(v => !v)}>
            <Ionicons name={showKeyword ? 'eye-off' : 'eye'} size={18} color={Colors.textMuted} />
          </TouchableOpacity>
        </View>

        <Text style={styles.label}>Ulangi Kata Kunci Baru</Text>
        <TextInput
          style={styles.input}
          value={keywordConfirm}
          onChangeText={setKeywordConfirm}
          placeholder="Ketik ulang kata kunci baru"
          placeholderTextColor={Colors.textMuted}
          secureTextEntry={!showKeyword}
          autoCapitalize="none"
        />

        <View style={styles.noteBox}>
          <Ionicons name="information-circle" size={16} color={Colors.gold} />
          <Text style={styles.noteText}>
            Kata kunci berlaku untuk seluruh pengguna dan hanya membuka file tersembunyi milik
            masing-masing akun. Kata kunci lama langsung tidak berlaku dan tidak bisa dipulihkan.
          </Text>
        </View>

        <TouchableOpacity
          style={[styles.saveBtn, saving && { opacity: 0.6 }]}
          onPress={handleSave}
          disabled={saving}
        >
          {saving
            ? <ActivityIndicator color={Colors.primary} />
            : <Text style={styles.saveBtnText}>Simpan Kata Kunci</Text>}
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.secondary },
  center: { flex: 1, backgroundColor: Colors.secondary, alignItems: 'center', justifyContent: 'center' },

  card: {
    backgroundColor: Colors.card,
    borderRadius: BorderRadius.lg,
    borderWidth: 1,
    borderColor: Colors.border,
    padding: Spacing.lg,
    marginBottom: Spacing.lg,
  },
  cardHeader: { flexDirection: 'row', gap: Spacing.md, marginBottom: Spacing.lg },
  iconBox: {
    width: 44, height: 44, borderRadius: BorderRadius.md,
    backgroundColor: Colors.goldMuted, alignItems: 'center', justifyContent: 'center',
  },
  cardTitle: { color: Colors.textPrimary, fontSize: 16, fontWeight: '700' },
  cardSubtitle: { color: Colors.textMuted, fontSize: 12, marginTop: 4, lineHeight: 18 },

  steps: { gap: Spacing.md },
  stepRow: { flexDirection: 'row', gap: Spacing.md, alignItems: 'flex-start' },
  stepBadge: {
    width: 22, height: 22, borderRadius: BorderRadius.full,
    backgroundColor: Colors.cardLight, alignItems: 'center', justifyContent: 'center',
  },
  stepNumber: { color: Colors.gold, fontSize: 11, fontWeight: '700' },
  stepText: { flex: 1, color: Colors.textSecondary, fontSize: 13, lineHeight: 19 },

  statusCard: {
    flexDirection: 'row', gap: Spacing.md, alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.lg,
    borderWidth: 1, padding: Spacing.lg, marginBottom: Spacing.lg,
  },
  statusTitle: { fontSize: 13, fontWeight: '700' },
  statusSubtitle: { color: Colors.textMuted, fontSize: 11, marginTop: 2, lineHeight: 16 },

  formTitle: { color: Colors.textPrimary, fontSize: 16, fontWeight: '700', marginBottom: Spacing.lg },
  label: { color: Colors.textSecondary, fontSize: 13, fontWeight: '500', marginBottom: 6 },
  input: {
    backgroundColor: Colors.input,
    borderWidth: 1, borderColor: Colors.inputBorder,
    borderRadius: BorderRadius.md,
    paddingHorizontal: Spacing.lg, paddingVertical: Spacing.md,
    color: Colors.textPrimary, fontSize: 14,
    marginBottom: Spacing.lg,
  },
  inputRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, marginBottom: Spacing.lg },
  eyeBtn: {
    width: 44, height: 44, borderRadius: BorderRadius.md,
    backgroundColor: Colors.cardLight, alignItems: 'center', justifyContent: 'center',
  },

  noteBox: {
    flexDirection: 'row', gap: Spacing.sm,
    backgroundColor: Colors.cardLight, borderRadius: BorderRadius.md,
    padding: Spacing.md, marginBottom: Spacing.lg,
  },
  noteText: { flex: 1, color: Colors.textMuted, fontSize: 11, lineHeight: 17 },

  saveBtn: {
    backgroundColor: Colors.gold, borderRadius: BorderRadius.md,
    paddingVertical: Spacing.md, alignItems: 'center',
  },
  saveBtnText: { color: Colors.primary, fontSize: 15, fontWeight: '700' },
});
