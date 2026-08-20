import React, { useState } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Alert,
  TextInput, ActivityIndicator, Modal,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

function getFileIcon(name) {
  const ext = name.split('.').pop().toLowerCase();
  const icons = {
    pdf: { name: 'document-text', color: '#ef4444' },
    doc: { name: 'document', color: '#3b82f6' }, docx: { name: 'document', color: '#3b82f6' },
    jpg: { name: 'image', color: '#8b5cf6' }, jpeg: { name: 'image', color: '#8b5cf6' }, png: { name: 'image', color: '#8b5cf6' },
    mp4: { name: 'videocam', color: '#ec4899' },
  };
  return icons[ext] || { name: 'document', color: Colors.textMuted };
}

export default function HiddenScreen() {
  const [verified, setVerified] = useState(false);
  const [password, setPassword] = useState('');
  const [files, setFiles] = useState([]);
  const [folders, setFolders] = useState([]);
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [unhidePassword, setUnhidePassword] = useState('');
  const [showUnhideModal, setShowUnhideModal] = useState(null);

  const handleVerify = async () => {
    if (!password) { Alert.alert('Error', 'Masukkan password'); return; }
    setVerifying(true);
    try {
      await api.verifyHiddenPassword(password);
      setVerified(true);
      loadHidden();
    } catch (error) {
      Alert.alert('Error', error.message || 'Password salah');
    } finally {
      setVerifying(false);
    }
  };

  const loadHidden = async () => {
    setLoading(true);
    try {
      const res = await api.getHidden();
      setFiles(res.files);
      setFolders(res.folders);
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleUnhide = async () => {
    if (!unhidePassword) { Alert.alert('Error', 'Masukkan password'); return; }
    try {
      if (showUnhideModal.type === 'File') {
        await api.unhideFile(showUnhideModal.item.id, unhidePassword);
      } else {
        await api.unhideFolder(showUnhideModal.item.id, unhidePassword);
      }
      setShowUnhideModal(null);
      setUnhidePassword('');
      loadHidden();
      Alert.alert('Berhasil', 'Item berhasil ditampilkan kembali');
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  if (!verified) {
    return (
      <View style={styles.container}>
        <View style={styles.gateContainer}>
          <View style={styles.gateIcon}>
            <Ionicons name="eye-off" size={48} color={Colors.gold} />
          </View>
          <Text style={styles.gateTitle}>Hidden System</Text>
          <Text style={styles.gateSubtitle}>Masukkan password untuk mengakses</Text>
          <TextInput
            style={styles.gateInput}
            placeholder="Password"
            placeholderTextColor={Colors.textMuted}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            autoFocus
          />
          <TouchableOpacity
            style={styles.gateButton}
            onPress={handleVerify}
            disabled={verifying}
          >
            {verifying ? (
              <ActivityIndicator color={Colors.primary} />
            ) : (
              <>
                <Ionicons name="key" size={20} color={Colors.primary} />
                <Text style={styles.gateButtonText}>Buka Hidden System</Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {loading ? (
        <ActivityIndicator size="large" color={Colors.gold} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={[1]}
          keyExtractor={() => 'h'}
          contentContainerStyle={{ paddingBottom: 20 }}
          renderItem={() => (
            <View>
              {folders.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>📁 Folder Tersembunyi ({folders.length})</Text>
                  {folders.map(f => (
                    <TouchableOpacity key={f.id} style={styles.itemRow}
                      onPress={() => setShowUnhideModal({ type: 'Folder', item: f })}>
                      <Ionicons name={f.is_locked ? 'lock-closed' : 'folder'} size={24} color={Colors.gold} />
                      <View style={{ flex: 1, marginLeft: 12 }}>
                        <Text style={styles.itemName}>{f.name}</Text>
                      </View>
                      <TouchableOpacity style={styles.unhideBtn}
                        onPress={() => setShowUnhideModal({ type: 'Folder', item: f })}>
                        <Text style={styles.unhideBtnText}>Tampilkan</Text>
                      </TouchableOpacity>
                    </TouchableOpacity>
                  ))}
                </View>
              )}

              {files.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>📄 File Tersembunyi ({files.length})</Text>
                  {files.map(f => {
                    const icon = getFileIcon(f.name);
                    return (
                      <TouchableOpacity key={f.id} style={styles.itemRow}
                        onPress={() => setShowUnhideModal({ type: 'File', item: f })}>
                        <View style={[styles.itemIcon, { backgroundColor: `${icon.color}20` }]}>
                          <Ionicons name={icon.name} size={22} color={icon.color} />
                        </View>
                        <View style={{ flex: 1, marginLeft: 12 }}>
                          <Text style={styles.itemName} numberOfLines={1}>{f.name}</Text>
                          <Text style={styles.itemMeta}>{f.size_formatted}</Text>
                        </View>
                        <TouchableOpacity style={styles.unhideBtn}
                          onPress={() => setShowUnhideModal({ type: 'File', item: f })}>
                          <Text style={styles.unhideBtnText}>Tampilkan</Text>
                        </TouchableOpacity>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              )}

              {folders.length === 0 && files.length === 0 && (
                <View style={styles.empty}>
                  <Ionicons name="eye-off-outline" size={48} color={Colors.textMuted} />
                  <Text style={styles.emptyText}>Tidak ada item tersembunyi</Text>
                </View>
              )}
            </View>
          )}
        />
      )}

      {/* Unhide Modal */}
      <Modal visible={!!showUnhideModal} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Tampilkan {showUnhideModal?.type}</Text>
            <Text style={styles.modalSubtitle}>{showUnhideModal?.item?.name}</Text>
            <TextInput style={styles.modalInput} placeholder="Masukkan password" placeholderTextColor={Colors.textMuted}
              value={unhidePassword} onChangeText={setUnhidePassword} secureTextEntry autoFocus />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalCancel} onPress={() => { setShowUnhideModal(null); setUnhidePassword(''); }}>
                <Text style={styles.modalCancelText}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.modalConfirm} onPress={handleUnhide}>
                <Text style={styles.modalConfirmText}>Tampilkan</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },
  gateContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
  gateIcon: {
    width: 96, height: 96, borderRadius: 48,
    backgroundColor: Colors.card, justifyContent: 'center', alignItems: 'center',
    borderWidth: 2, borderColor: Colors.gold, marginBottom: 20,
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 12, elevation: 8,
  },
  gateTitle: { fontSize: 24, fontWeight: '800', color: Colors.textPrimary, marginBottom: 8 },
  gateSubtitle: { fontSize: 14, color: Colors.textMuted, marginBottom: 24 },
  gateInput: {
    width: '100%', maxWidth: 320, backgroundColor: Colors.input,
    borderRadius: BorderRadius.lg, borderWidth: 1, borderColor: Colors.inputBorder,
    padding: 16, color: Colors.textPrimary, fontSize: 16, marginBottom: 16, textAlign: 'center',
  },
  gateButton: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    backgroundColor: Colors.gold, borderRadius: BorderRadius.lg,
    paddingVertical: 14, paddingHorizontal: 32,
    shadowColor: Colors.gold, shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3, shadowRadius: 8, elevation: 6,
  },
  gateButtonText: { fontSize: 16, fontWeight: '700', color: Colors.primary },

  section: { paddingHorizontal: Spacing.lg, marginBottom: 20 },
  sectionTitle: { color: Colors.gold, fontSize: 13, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 10 },
  itemRow: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 12, marginBottom: 8, borderWidth: 1, borderColor: Colors.border,
  },
  itemIcon: { width: 40, height: 40, borderRadius: 10, justifyContent: 'center', alignItems: 'center' },
  itemName: { color: Colors.textPrimary, fontSize: 14, fontWeight: '600' },
  itemMeta: { color: Colors.textMuted, fontSize: 11, marginTop: 2 },
  unhideBtn: { backgroundColor: Colors.goldMuted, borderRadius: BorderRadius.sm, paddingHorizontal: 12, paddingVertical: 6 },
  unhideBtnText: { color: Colors.gold, fontSize: 12, fontWeight: '600' },

  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { color: Colors.textMuted, fontSize: 14, marginTop: 8 },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'center', alignItems: 'center', padding: 20 },
  modalCard: { width: '100%', maxWidth: 340, backgroundColor: Colors.card, borderRadius: BorderRadius.lg, padding: 24, borderWidth: 1, borderColor: Colors.border },
  modalTitle: { fontSize: 18, fontWeight: '700', color: Colors.textPrimary, textAlign: 'center', marginBottom: 4 },
  modalSubtitle: { fontSize: 13, color: Colors.textMuted, textAlign: 'center', marginBottom: 16 },
  modalInput: { backgroundColor: Colors.input, borderRadius: BorderRadius.md, borderWidth: 1, borderColor: Colors.inputBorder, padding: 14, color: Colors.textPrimary, fontSize: 15, marginBottom: 16 },
  modalActions: { flexDirection: 'row', gap: 10 },
  modalCancel: { flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md, backgroundColor: Colors.surface, alignItems: 'center', borderWidth: 1, borderColor: Colors.border },
  modalCancelText: { color: Colors.textMuted, fontWeight: '600' },
  modalConfirm: { flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md, backgroundColor: Colors.gold, alignItems: 'center' },
  modalConfirmText: { color: Colors.primary, fontWeight: '700' },
});
