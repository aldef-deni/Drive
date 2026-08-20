import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Alert, Modal,
  TextInput, ActivityIndicator, RefreshControl, Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';
import * as DocumentPicker from 'expo-document-picker';

const { width } = Dimensions.get('window');

function getFileIcon(name) {
  const ext = name.split('.').pop().toLowerCase();
  const icons = {
    pdf: { name: 'document-text', color: '#ef4444' },
    doc: { name: 'document', color: '#3b82f6' }, docx: { name: 'document', color: '#3b82f6' },
    xls: { name: 'grid', color: '#10b981' }, xlsx: { name: 'grid', color: '#10b981' },
    jpg: { name: 'image', color: '#8b5cf6' }, jpeg: { name: 'image', color: '#8b5cf6' }, png: { name: 'image', color: '#8b5cf6' },
    mp4: { name: 'videocam', color: '#ec4899' },
    mp3: { name: 'musical-notes', color: '#6366f1' },
    zip: { name: 'archive', color: '#eab308' },
  };
  return icons[ext] || { name: 'document', color: Colors.textMuted };
}

export default function FolderScreen({ route, navigation }) {
  const { folder } = route.params;
  const [folders, setFolders] = useState([]);
  const [files, setFiles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showNewFolder, setShowNewFolder] = useState(false);
  const [newFolderName, setNewFolderName] = useState('');
  const [showActions, setShowActions] = useState(null);
  const [showPasswordModal, setShowPasswordModal] = useState(null);
  const [actionPassword, setActionPassword] = useState('');
  const [uploading, setUploading] = useState(false);
  const [viewMode, setViewMode] = useState('small');

  navigation.setOptions({ title: folder.name });

  const loadData = useCallback(async () => {
    try {
      const res = await api.getDrive(folder.path);
      setFolders(res.folders);
      setFiles(res.files);
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [folder.path]);

  useEffect(() => { loadData(); }, []);

  const handleUpload = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({ type: '*/*', copyToCacheDirectory: true });
      if (!result.canceled && result.assets[0]) {
        const file = result.assets[0];
        setUploading(true);
        await api.uploadFile(file.uri, file.name, file.mimeType, folder.path);
        setUploading(false);
        loadData();
        Alert.alert('Berhasil', 'File berhasil diupload');
      }
    } catch (error) {
      setUploading(false);
      Alert.alert('Error', error.message);
    }
  };

  const handleDelete = async (type, item) => {
    Alert.alert('Hapus', `Hapus ${type} "${item.name}"?`, [
      { text: 'Batal', style: 'cancel' },
      { text: 'Hapus', style: 'destructive', onPress: async () => {
        try {
          if (type === 'File') await api.deleteFile(item.id);
          else await api.deleteFolder(item.id);
          loadData();
        } catch (error) { Alert.alert('Error', error.message); }
      }},
    ]);
  };

  const handleLock = (type, item) => {
    setShowPasswordModal({ type, item, action: 'lock' });
    setActionPassword('');
  };

  const handleUnlock = (type, item) => {
    setShowPasswordModal({ type, item, action: 'unlock' });
    setActionPassword('');
  };

  const submitPassword = async () => {
    if (!actionPassword || actionPassword.length < 4) {
      Alert.alert('Error', 'Password minimal 4 karakter');
      return;
    }
    const { type, item, action } = showPasswordModal;
    try {
      if (type === 'File') {
        if (action === 'lock') await api.lockFile(item.id, actionPassword);
        else await api.unlockFile(item.id, actionPassword);
      } else {
        if (action === 'lock') await api.lockFolder(item.id, actionPassword);
        else await api.unlockFolder(item.id, actionPassword);
      }
      setShowPasswordModal(null);
      loadData();
    } catch (error) { Alert.alert('Error', error.message); }
  };

  const handleCreateFolder = async () => {
    if (!newFolderName.trim()) return;
    try {
      await api.createFolder(newFolderName.trim(), folder.path);
      setShowNewFolder(false);
      setNewFolderName('');
      loadData();
    } catch (error) { Alert.alert('Error', error.message); }
  };

  return (
    <View style={styles.container}>
      {loading ? (
        <ActivityIndicator size="large" color={Colors.gold} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={[1]}
          keyExtractor={() => 'h'}
          contentContainerStyle={{ paddingBottom: 20 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadData(); }} tintColor={Colors.gold} />}
          renderItem={() => (
            <View>
              {/* Action Bar */}
              <View style={styles.actionBar}>
                <TouchableOpacity style={styles.actionBtn} onPress={() => setShowNewFolder(true)}>
                  <Ionicons name="folder-open" size={16} color={Colors.gold} />
                  <Text style={styles.actionText}>Folder</Text>
                </TouchableOpacity>
                <TouchableOpacity style={styles.actionBtn} onPress={handleUpload}>
                  <Ionicons name="cloud-upload" size={16} color={Colors.gold} />
                  <Text style={styles.actionText}>Upload</Text>
                </TouchableOpacity>
                <View style={styles.viewToggle}>
                  {['small', 'list'].map(mode => (
                    <TouchableOpacity key={mode} onPress={() => setViewMode(mode)} style={[styles.viewBtn, viewMode === mode && styles.viewBtnActive]}>
                      <Ionicons name={mode === 'list' ? 'list' : 'grid'} size={14} color={viewMode === mode ? Colors.primary : Colors.textMuted} />
                    </TouchableOpacity>
                  ))}
                </View>
              </View>

              {/* Folders */}
              {folders.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>📁 Folder ({folders.length})</Text>
                  <View style={styles.folderGrid}>
                    {folders.map(f => (
                      <TouchableOpacity key={f.id}
                        style={[styles.folderCard, viewMode === 'list' && styles.folderCardList]}
                        onPress={() => navigation.push('Folder', { folder: f })}
                        onLongPress={() => setShowActions({ type: 'Folder', item: f })}
                      >
                        <Ionicons name={f.is_locked ? 'lock-closed' : 'folder'} size={viewMode === 'list' ? 22 : 28} color={Colors.gold} />
                        <Text style={styles.itemName} numberOfLines={1}>{f.name}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
              )}

              {/* Files */}
              {files.length > 0 && (
                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>📄 File ({files.length})</Text>
                  {viewMode === 'list' ? files.map(f => {
                    const icon = getFileIcon(f.name);
                    return (
                      <TouchableOpacity key={f.id} style={styles.fileRow} onLongPress={() => setShowActions({ type: 'File', item: f })}>
                        <View style={[styles.fileIcon, { backgroundColor: `${icon.color}20` }]}>
                          <Ionicons name={icon.name} size={22} color={icon.color} />
                        </View>
                        <View style={{ flex: 1 }}>
                          <Text style={styles.fileName} numberOfLines={1}>{f.name}</Text>
                          <Text style={styles.fileMeta}>{f.size_formatted}</Text>
                        </View>
                      </TouchableOpacity>
                    );
                  }) : (
                    <View style={styles.fileGrid}>
                      {files.map(f => {
                        const icon = getFileIcon(f.name);
                        return (
                          <TouchableOpacity key={f.id} style={styles.fileGridItem}
                            onLongPress={() => setShowActions({ type: 'File', item: f })}>
                            <View style={[styles.fileGridIcon, { backgroundColor: `${icon.color}20` }]}>
                              <Ionicons name={icon.name} size={24} color={icon.color} />
                            </View>
                            <Text style={styles.fileGridName} numberOfLines={2}>{f.name}</Text>
                          </TouchableOpacity>
                        );
                      })}
                    </View>
                  )}
                </View>
              )}

              {folders.length === 0 && files.length === 0 && (
                <View style={styles.empty}>
                  <Ionicons name="folder-open-outline" size={48} color={Colors.textMuted} />
                  <Text style={styles.emptyText}>Folder kosong</Text>
                </View>
              )}
            </View>
          )}
        />
      )}

      {uploading && (
        <View style={styles.uploadOverlay}>
          <ActivityIndicator size="large" color={Colors.gold} />
          <Text style={{ color: Colors.textPrimary, marginTop: 12 }}>Mengupload...</Text>
        </View>
      )}

      {/* New Folder Modal */}
      <Modal visible={showNewFolder} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Folder Baru</Text>
            <TextInput style={styles.modalInput} placeholder="Nama folder" placeholderTextColor={Colors.textMuted}
              value={newFolderName} onChangeText={setNewFolderName} autoFocus />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalCancel} onPress={() => setShowNewFolder(false)}>
                <Text style={styles.modalCancelText}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.modalConfirm} onPress={handleCreateFolder}>
                <Text style={styles.modalConfirmText}>Buat</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Actions */}
      <Modal visible={!!showActions} transparent animationType="fade">
        <TouchableOpacity style={styles.modalOverlay} activeOpacity={1} onPress={() => setShowActions(null)}>
          <View style={styles.actionsCard}>
            <Text style={styles.actionsTitle}>{showActions?.item?.name}</Text>
            {showActions?.type === 'File' && !showActions.item.is_locked && (
              <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleLock('File', showActions.item); }}>
                <Ionicons name="lock-closed-outline" size={20} color={Colors.warning} />
                <Text style={styles.actionItemText}>Lock</Text>
              </TouchableOpacity>
            )}
            {showActions?.type === 'File' && showActions.item.is_locked && (
              <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleUnlock('File', showActions.item); }}>
                <Ionicons name="lock-open-outline" size={20} color={Colors.success} />
                <Text style={[styles.actionItemText, { color: Colors.success }]}>Unlock</Text>
              </TouchableOpacity>
            )}
            {showActions?.type === 'Folder' && !showActions.item.is_locked && (
              <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleLock('Folder', showActions.item); }}>
                <Ionicons name="lock-closed-outline" size={20} color={Colors.warning} />
                <Text style={styles.actionItemText}>Lock Folder</Text>
              </TouchableOpacity>
            )}
            <TouchableOpacity style={[styles.actionItem, { borderBottomWidth: 0 }]} onPress={() => { setShowActions(null); handleDelete(showActions?.type, showActions?.item); }}>
              <Ionicons name="trash-outline" size={20} color={Colors.danger} />
              <Text style={[styles.actionItemText, { color: Colors.danger }]}>Hapus</Text>
            </TouchableOpacity>
          </View>
        </TouchableOpacity>
      </Modal>

      {/* Password Modal */}
      <Modal visible={!!showPasswordModal} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{showPasswordModal?.action === 'lock' ? '🔒 Kunci' : '🔓 Buka'}</Text>
            <TextInput style={styles.modalInput} placeholder="Password" placeholderTextColor={Colors.textMuted}
              value={actionPassword} onChangeText={setActionPassword} secureTextEntry autoFocus />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalCancel} onPress={() => setShowPasswordModal(null)}>
                <Text style={styles.modalCancelText}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.modalConfirm} onPress={submitPassword}>
                <Text style={styles.modalConfirmText}>OK</Text>
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
  actionBar: {
    flexDirection: 'row', alignItems: 'center', padding: Spacing.lg, gap: 8,
  },
  actionBtn: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    backgroundColor: Colors.card, borderRadius: BorderRadius.sm,
    paddingHorizontal: 12, paddingVertical: 8, borderWidth: 1, borderColor: Colors.border,
  },
  actionText: { color: Colors.gold, fontSize: 12, fontWeight: '600' },
  viewToggle: { flexDirection: 'row', marginLeft: 'auto', backgroundColor: Colors.card, borderRadius: BorderRadius.sm, borderWidth: 1, borderColor: Colors.border },
  viewBtn: { paddingHorizontal: 10, paddingVertical: 6 },
  viewBtnActive: { backgroundColor: Colors.gold },

  section: { paddingHorizontal: Spacing.lg, marginBottom: 16 },
  sectionTitle: { color: Colors.gold, fontSize: 13, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 10 },
  folderGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  folderCard: {
    width: (width - 44) / 4, alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 12, borderWidth: 1, borderColor: Colors.border,
  },
  folderCardList: { width: '100%', flexDirection: 'row', padding: 12, borderRadius: 0, borderBottomWidth: 1, borderBottomColor: Colors.border },
  itemName: { color: Colors.textPrimary, fontSize: 11, marginTop: 6, textAlign: 'center' },

  fileGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  fileGridItem: {
    width: (width - 44) / 4, alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 10, borderWidth: 1, borderColor: Colors.border,
  },
  fileGridIcon: { width: 48, height: 48, borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
  fileGridName: { color: Colors.textPrimary, fontSize: 11, marginTop: 6, textAlign: 'center' },

  fileRow: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: Colors.card,
    borderRadius: BorderRadius.md, padding: 12, marginBottom: 8, borderWidth: 1, borderColor: Colors.border,
  },
  fileIcon: { width: 40, height: 40, borderRadius: 10, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  fileName: { color: Colors.textPrimary, fontSize: 14, fontWeight: '600' },
  fileMeta: { color: Colors.textMuted, fontSize: 11, marginTop: 2 },

  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { color: Colors.textMuted, fontSize: 14, marginTop: 8 },

  uploadOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: Colors.overlay, justifyContent: 'center', alignItems: 'center' },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'center', alignItems: 'center', padding: 20 },
  modalCard: { width: '100%', maxWidth: 340, backgroundColor: Colors.card, borderRadius: BorderRadius.lg, padding: 24, borderWidth: 1, borderColor: Colors.border },
  modalTitle: { fontSize: 18, fontWeight: '700', color: Colors.textPrimary, textAlign: 'center', marginBottom: 16 },
  modalInput: { backgroundColor: Colors.input, borderRadius: BorderRadius.md, borderWidth: 1, borderColor: Colors.inputBorder, padding: 14, color: Colors.textPrimary, fontSize: 15, marginBottom: 16 },
  modalActions: { flexDirection: 'row', gap: 10 },
  modalCancel: { flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md, backgroundColor: Colors.surface, alignItems: 'center', borderWidth: 1, borderColor: Colors.border },
  modalCancelText: { color: Colors.textMuted, fontWeight: '600' },
  modalConfirm: { flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md, backgroundColor: Colors.gold, alignItems: 'center' },
  modalConfirmText: { color: Colors.primary, fontWeight: '700' },

  actionsCard: { width: '100%', maxWidth: 300, backgroundColor: Colors.card, borderRadius: BorderRadius.lg, borderWidth: 1, borderColor: Colors.border, overflow: 'hidden' },
  actionsTitle: { fontSize: 15, fontWeight: '700', color: Colors.textPrimary, padding: 16, borderBottomWidth: 1, borderBottomColor: Colors.border },
  actionItem: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: Colors.border },
  actionItemText: { color: Colors.textPrimary, fontSize: 14, fontWeight: '500' },
});
