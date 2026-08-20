import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Alert, Modal,
  TextInput, ActivityIndicator, RefreshControl, Dimensions, Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../../context/AuthContext';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';
import * as DocumentPicker from 'expo-document-picker';

const { width } = Dimensions.get('window');

function getFileIcon(name) {
  const ext = name.split('.').pop().toLowerCase();
  const icons = {
    pdf: { name: 'document-text', color: '#ef4444' },
    doc: { name: 'document', color: '#3b82f6' },
    docx: { name: 'document', color: '#3b82f6' },
    xls: { name: 'grid', color: '#10b981' },
    xlsx: { name: 'grid', color: '#10b981' },
    ppt: { name: 'easel', color: '#f59e0b' },
    pptx: { name: 'easel', color: '#f59e0b' },
    jpg: { name: 'image', color: '#8b5cf6' },
    jpeg: { name: 'image', color: '#8b5cf6' },
    png: { name: 'image', color: '#8b5cf6' },
    webp: { name: 'image', color: '#8b5cf6' },
    mp4: { name: 'videocam', color: '#ec4899' },
    mp3: { name: 'musical-notes', color: '#6366f1' },
    zip: { name: 'archive', color: '#eab308' },
    txt: { name: 'document-outline', color: Colors.textMuted },
  };
  return icons[ext] || { name: 'document', color: Colors.textMuted };
}

export default function DriveScreen({ navigation }) {
  const { user, refreshUser, logout } = useAuth();
  const insets = useSafeAreaInsets();
  const [folders, setFolders] = useState([]);
  const [files, setFiles] = useState([]);
  const [currentFolder, setCurrentFolder] = useState('/');
  const [breadcrumbs, setBreadcrumbs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [viewMode, setViewMode] = useState('small'); // small, large, list
  // Mode ungkap: kata kunci rahasia disimpan sementara agar item tersembunyi
  // tetap tampil ketika berpindah folder.
  const [revealKeyword, setRevealKeyword] = useState('');
  const [hiddenRevealed, setHiddenRevealed] = useState(false);

  // Modals
  const [showNewFolder, setShowNewFolder] = useState(false);
  const [newFolderName, setNewFolderName] = useState('');
  const [showUpload, setShowUpload] = useState(false);
  const [uploadLocked, setUploadLocked] = useState(false);
  const [uploadPassword, setUploadPassword] = useState('');
  const [uploading, setUploading] = useState(false);
  const [showActions, setShowActions] = useState(null);
  const [showPasswordModal, setShowPasswordModal] = useState(null);
  const [actionPassword, setActionPassword] = useState('');

  const loadData = useCallback(async (folder = currentFolder, search = '', keyword = revealKeyword) => {
    try {
      const res = await api.getDrive(folder, search, keyword);
      setFolders(res.folders);
      setFiles(res.files);
      setBreadcrumbs(res.breadcrumbs);
      setHiddenRevealed(!!res.hidden_revealed);

      // Kata kunci benar: simpan supaya mode ungkap bertahan, dan kosongkan
      // kolom pencarian karena kata kunci bukan kata pencarian biasa.
      if (res.hidden_revealed && search) {
        setRevealKeyword(search);
        setSearchQuery('');
      }

      if (res.user) refreshUser();
    } catch (error) {
      Alert.alert('Error', error.message || 'Gagal memuat data');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [currentFolder, revealKeyword]);

  useEffect(() => { loadData(); }, []);

  const onRefresh = () => {
    setRefreshing(true);
    loadData();
  };

  const handleSearch = (text) => {
    setSearchQuery(text);
    if (text.length >= 2 || text.length === 0) {
      loadData(currentFolder, text);
    }
  };

  const stopReveal = () => {
    setRevealKeyword('');
    setHiddenRevealed(false);
    loadData(currentFolder, '', '');
  };

  const navigateToFolder = (folder) => {
    setCurrentFolder(folder.path);
    loadData(folder.path);
  };

  const navigateToBreadcrumb = (path) => {
    setCurrentFolder(path);
    loadData(path);
  };

  const handleCreateFolder = async () => {
    if (!newFolderName.trim()) return;
    try {
      await api.createFolder(newFolderName.trim(), currentFolder);
      setShowNewFolder(false);
      setNewFolderName('');
      loadData();
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  const handleUpload = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        type: '*/*',
        copyToCacheDirectory: true,
      });

      if (!result.canceled && result.assets[0]) {
        const file = result.assets[0];
        setShowUpload(false);

        if (uploadLocked && !uploadPassword) {
          Alert.alert('Error', 'Masukkan password untuk mengunci file');
          return;
        }

        setUploading(true);
        await api.uploadFile(
          file.uri, file.name, file.mimeType,
          currentFolder, uploadLocked, uploadPassword
        );
        setUploading(false);
        setUploadLocked(false);
        setUploadPassword('');
        loadData();
        Alert.alert('Berhasil', 'File berhasil diupload');
      }
    } catch (error) {
      setUploading(false);
      Alert.alert('Error', error.message || 'Gagal upload file');
    }
  };

  const handleDelete = async (type, item) => {
    Alert.alert('Hapus', `Hapus ${type} "${item.name}"?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus', style: 'destructive',
        onPress: async () => {
          try {
            if (type === 'File') await api.deleteFile(item.id);
            else await api.deleteFolder(item.id);
            loadData();
          } catch (error) {
            Alert.alert('Error', error.message);
          }
        },
      },
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
      Alert.alert('Berhasil', `Berhasil ${action === 'lock' ? 'mengunci' : 'membuka'} ${type.toLowerCase()}`);
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  const handleShare = async (item) => {
    try {
      const res = await api.shareFile(item.id);
      setShowActions(null);
      Alert.alert('Link Share', res.share_url, [{ text: 'OK' }]);
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  const handleUnshare = async (item) => {
    Alert.alert('Unshare', `Batalkan share "${item.name}"?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Unshare', style: 'destructive',
        onPress: async () => {
          try {
            await api.unshareFile(item.id);
            loadData();
          } catch (error) {
            Alert.alert('Error', error.message);
          }
        },
      },
    ]);
  };

  const handleToggleVisibility = async (type, item) => {
    try {
      if (type === 'File') await api.toggleVisibility(item.id);
      else await api.toggleFolderVisibility(item.id);
      loadData();
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  const storageUsedGB = ((user?.storage_used || 0) / (1024 * 1024 * 1024)).toFixed(2);
  const storageQuotaGB = ((user?.storage_quota || 0) / (1024 * 1024 * 1024)).toFixed(2);
  const storagePercent = user?.storage_quota > 0
    ? Math.min(100, ((user.storage_used || 0) / user.storage_quota) * 100)
    : 0;

  const renderFolder = ({ item }) => (
    <TouchableOpacity
      style={styles.folderCard}
      onPress={() => navigateToFolder(item)}
      onLongPress={() => setShowActions({ type: 'Folder', item })}
      activeOpacity={0.7}
    >
      <View style={styles.folderIcon}>
        <Ionicons name={item.is_locked ? 'lock-closed' : 'folder'} size={28} color={Colors.gold} />
      </View>
      <Text style={styles.folderName} numberOfLines={1}>{item.name}</Text>
      {item.is_locked && <Ionicons name="lock-closed" size={12} color={Colors.warning} />}
      {item.is_hidden && <Ionicons name="eye-off" size={12} color={Colors.textMuted} />}
    </TouchableOpacity>
  );

  const renderFile = ({ item }) => {
    const icon = getFileIcon(item.name);
    return (
      <TouchableOpacity
        style={styles.fileRow}
        onLongPress={() => setShowActions({ type: 'File', item })}
        activeOpacity={0.7}
      >
        <View style={[styles.fileIcon, { backgroundColor: `${icon.color}20` }]}>
          <Ionicons name={icon.name} size={22} color={icon.color} />
        </View>
        <View style={styles.fileInfo}>
          <Text style={styles.fileName} numberOfLines={1}>{item.name}</Text>
          <Text style={styles.fileMeta}>{item.size_formatted} • {new Date(item.created_at).toLocaleDateString('id-ID')}</Text>
        </View>
        <View style={styles.fileBadges}>
          {item.is_locked && <Ionicons name="lock-closed" size={14} color={Colors.warning} style={{ marginRight: 4 }} />}
          {item.is_shared && <Ionicons name="link" size={14} color={Colors.info} />}
        </View>
      </TouchableOpacity>
    );
  };

  const renderHeader = () => (
    <View>
      {/* Storage Bar */}
      <View style={styles.storageCard}>
        <View style={styles.storageHeader}>
          <Ionicons name="cloud" size={18} color={Colors.gold} />
          <Text style={styles.storageText}>{storageUsedGB} GB / {storageQuotaGB} GB</Text>
        </View>
        <View style={styles.storageBarBg}>
          <View style={[styles.storageBarFill, { width: `${storagePercent}%` }]} />
        </View>
      </View>

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search" size={18} color={Colors.textMuted} style={{ marginRight: 8 }} />
        <TextInput
          style={styles.searchInput}
          placeholder="Pencarian File"
          placeholderTextColor={Colors.textMuted}
          value={searchQuery}
          onChangeText={handleSearch}
        />
        {searchQuery ? (
          <TouchableOpacity onPress={() => handleSearch('')}>
            <Ionicons name="close-circle" size={18} color={Colors.textMuted} />
          </TouchableOpacity>
        ) : null}
      </View>

      {/* Banner mode rahasia */}
      {hiddenRevealed && (
        <View style={styles.revealBanner}>
          <Ionicons name="eye" size={16} color={Colors.gold} />
          <Text style={styles.revealText}>
            Mode rahasia aktif — item tersembunyi ikut ditampilkan
          </Text>
          <TouchableOpacity onPress={stopReveal} hitSlop={8}>
            <Ionicons name="close" size={16} color={Colors.gold} />
          </TouchableOpacity>
        </View>
      )}

      {/* Breadcrumbs */}
      <FlatList
        horizontal
        data={breadcrumbs}
        keyExtractor={(_, i) => i.toString()}
        showsHorizontalScrollIndicator={false}
        style={styles.breadcrumbContainer}
        renderItem={({ item, index }) => (
          <TouchableOpacity onPress={() => navigateToBreadcrumb(item.path)}>
            <Text style={[styles.breadcrumb, index === breadcrumbs.length - 1 && styles.breadcrumbActive]}>
              {item.name}{index < breadcrumbs.length - 1 ? ' / ' : ''}
            </Text>
          </TouchableOpacity>
        )}
      />

      {/* Action Bar */}
      <View style={styles.actionBar}>
        <TouchableOpacity style={styles.actionBtn} onPress={() => setShowNewFolder(true)}>
          <Ionicons name="folder-open" size={18} color={Colors.gold} />
          <Text style={styles.actionText}>Baru</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionBtn} onPress={() => setShowUpload(true)}>
          <Ionicons name="cloud-upload" size={18} color={Colors.gold} />
          <Text style={styles.actionText}>Upload</Text>
        </TouchableOpacity>
        <View style={styles.viewToggle}>
          {['small', 'large', 'list'].map(mode => (
            <TouchableOpacity key={mode} onPress={() => setViewMode(mode)} style={[styles.viewBtn, viewMode === mode && styles.viewBtnActive]}>
              <Ionicons name={mode === 'list' ? 'list' : mode === 'small' ? 'grid' : 'square'} size={16} color={viewMode === mode ? Colors.primary : Colors.textMuted} />
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {/* Folders Section */}
      {folders.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>📁 Folder ({folders.length})</Text>
          <View style={[styles.folderGrid, viewMode === 'large' && styles.folderGridLarge, viewMode === 'list' && styles.folderGridList]}>
            {folders.map(f => (
              <TouchableOpacity
                key={f.id}
                style={[styles.folderCardItem, viewMode === 'large' && styles.folderCardLarge, viewMode === 'list' && styles.folderCardList]}
                onPress={() => navigateToFolder(f)}
                onLongPress={() => setShowActions({ type: 'Folder', item: f })}
              >
                <Ionicons name={f.is_locked ? 'lock-closed' : 'folder'} size={viewMode === 'large' ? 40 : viewMode === 'list' ? 22 : 28} color={Colors.gold} />
                <Text style={[styles.folderName, viewMode === 'large' && styles.folderNameLarge]} numberOfLines={1}>{f.name}</Text>
                {f.is_locked && <Ionicons name="lock-closed" size={10} color={Colors.warning} />}
              </TouchableOpacity>
            ))}
          </View>
        </View>
      )}

      {/* Files Section */}
      {files.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>📄 File ({files.length})</Text>
          {viewMode === 'list' ? (
            files.map(f => renderFile({ item: f }))
          ) : (
            <View style={styles.fileGrid}>
              {files.map(f => {
                const icon = getFileIcon(f.name);
                return (
                  <TouchableOpacity
                    key={f.id}
                    style={styles.fileGridItem}
                    onLongPress={() => setShowActions({ type: 'File', item: f })}
                  >
                    <View style={[styles.fileGridIcon, { backgroundColor: `${icon.color}20` }]}>
                      <Ionicons name={icon.name} size={viewMode === 'large' ? 32 : 24} color={icon.color} />
                    </View>
                    <Text style={styles.fileGridName} numberOfLines={2}>{f.name}</Text>
                    <Text style={styles.fileGridSize}>{f.size_formatted}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}
        </View>
      )}

      {/* Empty State */}
      {!loading && folders.length === 0 && files.length === 0 && (
        <View style={styles.empty}>
          <Ionicons name="cloud-outline" size={64} color={Colors.textMuted} />
          <Text style={styles.emptyText}>Folder kosong</Text>
          <Text style={styles.emptySubtext}>Upload file atau buat folder baru</Text>
        </View>
      )}
    </View>
  );

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={logout} style={styles.headerBtn}>
          <Ionicons name="log-out-outline" size={22} color={Colors.textMuted} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Dekorasi Drive</Text>
        <TouchableOpacity onPress={() => navigation.navigate('Notifications')} style={styles.headerBtn}>
          <Ionicons name="notifications-outline" size={22} color={Colors.textMuted} />
        </TouchableOpacity>
      </View>

      {loading ? (
        <ActivityIndicator size="large" color={Colors.gold} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={[1]}
          renderItem={renderHeader}
          keyExtractor={() => 'header'}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{ paddingBottom: 20 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={Colors.gold} />}
        />
      )}

      {uploading && (
        <View style={styles.uploadOverlay}>
          <View style={styles.uploadCard}>
            <ActivityIndicator size="large" color={Colors.gold} />
            <Text style={styles.uploadText}>Mengupload file...</Text>
          </View>
        </View>
      )}

      {/* New Folder Modal */}
      <Modal visible={showNewFolder} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>📁 Folder Baru</Text>
            <TextInput
              style={styles.modalInput}
              placeholder="Nama folder"
              placeholderTextColor={Colors.textMuted}
              value={newFolderName}
              onChangeText={setNewFolderName}
              autoFocus
            />
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

      {/* Upload Modal */}
      <Modal visible={showUpload} transparent animationType="fade">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>📤 Upload File</Text>
            <TouchableOpacity style={styles.uploadLockToggle} onPress={() => setUploadLocked(!uploadLocked)}>
              <Ionicons name={uploadLocked ? 'lock-closed' : 'lock-open'} size={20} color={uploadLocked ? Colors.warning : Colors.textMuted} />
              <Text style={styles.uploadLockText}>{uploadLocked ? 'File akan dikunci' : 'Kunci file?'}</Text>
            </TouchableOpacity>
            {uploadLocked && (
              <TextInput
                style={styles.modalInput}
                placeholder="Password lock"
                placeholderTextColor={Colors.textMuted}
                value={uploadPassword}
                onChangeText={setUploadPassword}
                secureTextEntry
              />
            )}
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalCancel} onPress={() => { setShowUpload(false); setUploadLocked(false); setUploadPassword(''); }}>
                <Text style={styles.modalCancelText}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.modalConfirm} onPress={handleUpload}>
                <Text style={styles.modalConfirmText}>Pilih File</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Actions Modal */}
      <Modal visible={!!showActions} transparent animationType="fade">
        <TouchableOpacity style={styles.modalOverlay} activeOpacity={1} onPress={() => setShowActions(null)}>
          <View style={styles.actionsCard}>
            <Text style={styles.actionsTitle}>
              {showActions?.type === 'File' ? '📄' : '📁'} {showActions?.item?.name}
            </Text>
            {showActions?.type === 'File' && (
              <>
                {!showActions.item.is_locked && !showActions.item.is_shared && (
                  <TouchableOpacity style={styles.actionItem} onPress={() => handleShare(showActions.item)}>
                    <Ionicons name="share-outline" size={20} color={Colors.info} />
                    <Text style={styles.actionItemText}>Share</Text>
                  </TouchableOpacity>
                )}
                {showActions.item.is_shared && (
                  <TouchableOpacity style={styles.actionItem} onPress={() => handleUnshare(showActions.item)}>
                    <Ionicons name="close-circle-outline" size={20} color={Colors.warning} />
                    <Text style={[styles.actionItemText, { color: Colors.warning }]}>Unshare</Text>
                  </TouchableOpacity>
                )}
                {!showActions.item.is_locked ? (
                  <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleLock('File', showActions.item); }}>
                    <Ionicons name="lock-closed-outline" size={20} color={Colors.warning} />
                    <Text style={styles.actionItemText}>Lock</Text>
                  </TouchableOpacity>
                ) : (
                  <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleUnlock('File', showActions.item); }}>
                    <Ionicons name="lock-open-outline" size={20} color={Colors.success} />
                    <Text style={[styles.actionItemText, { color: Colors.success }]}>Unlock</Text>
                  </TouchableOpacity>
                )}
                <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleToggleVisibility('File', showActions.item); }}>
                  <Ionicons name="eye-off-outline" size={20} color={Colors.purple} />
                  <Text style={styles.actionItemText}>Sembunyikan</Text>
                </TouchableOpacity>
              </>
            )}
            {showActions?.type === 'Folder' && (
              <>
                {!showActions.item.is_locked ? (
                  <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleLock('Folder', showActions.item); }}>
                    <Ionicons name="lock-closed-outline" size={20} color={Colors.warning} />
                    <Text style={styles.actionItemText}>Lock</Text>
                  </TouchableOpacity>
                ) : (
                  <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleUnlock('Folder', showActions.item); }}>
                    <Ionicons name="lock-open-outline" size={20} color={Colors.success} />
                    <Text style={[styles.actionItemText, { color: Colors.success }]}>Unlock</Text>
                  </TouchableOpacity>
                )}
                <TouchableOpacity style={styles.actionItem} onPress={() => { setShowActions(null); handleToggleVisibility('Folder', showActions.item); }}>
                  <Ionicons name="eye-off-outline" size={20} color={Colors.purple} />
                  <Text style={styles.actionItemText}>Sembunyikan</Text>
                </TouchableOpacity>
              </>
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
            <Text style={styles.modalTitle}>
              {showPasswordModal?.action === 'lock' ? '🔒 Kunci' : '🔓 Buka'} {showPasswordModal?.type}
            </Text>
            <TextInput
              style={styles.modalInput}
              placeholder="Masukkan password"
              placeholderTextColor={Colors.textMuted}
              value={actionPassword}
              onChangeText={setActionPassword}
              secureTextEntry
              autoFocus
            />
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
  header: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    paddingHorizontal: Spacing.lg, paddingVertical: Spacing.md,
    backgroundColor: Colors.secondary, borderBottomWidth: 1, borderBottomColor: Colors.border,
  },
  headerBtn: { padding: 8 },
  headerTitle: { fontSize: 18, fontWeight: '800', color: Colors.gold, letterSpacing: 0.5 },

  storageCard: {
    margin: Spacing.lg, padding: Spacing.md,
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.border,
  },
  storageHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  storageText: { color: Colors.textSecondary, fontSize: 13, marginLeft: 8 },
  storageBarBg: { height: 6, backgroundColor: Colors.input, borderRadius: 3 },
  storageBarFill: { height: 6, backgroundColor: Colors.gold, borderRadius: 3 },

  searchContainer: {
    flexDirection: 'row', alignItems: 'center',
    marginHorizontal: Spacing.lg, marginBottom: Spacing.sm,
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.border, paddingHorizontal: 12, height: 44,
  },
  searchInput: { flex: 1, color: Colors.textPrimary, fontSize: 14 },
  revealBanner: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    backgroundColor: Colors.goldMuted,
    borderWidth: 1, borderColor: Colors.gold,
    borderRadius: BorderRadius.md,
    paddingHorizontal: 12, paddingVertical: 10,
    marginHorizontal: Spacing.lg, marginBottom: Spacing.sm,
  },
  revealText: { flex: 1, color: Colors.gold, fontSize: 12, fontWeight: '500' },

  breadcrumbContainer: { paddingHorizontal: Spacing.lg, marginBottom: Spacing.sm },
  breadcrumb: { color: Colors.textMuted, fontSize: 13 },
  breadcrumbActive: { color: Colors.gold, fontWeight: '700' },

  actionBar: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: Spacing.lg,
    marginBottom: Spacing.md, gap: 8,
  },
  actionBtn: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.sm,
    paddingHorizontal: 12, paddingVertical: 8,
    borderWidth: 1, borderColor: Colors.border, gap: 6,
  },
  actionText: { color: Colors.gold, fontSize: 12, fontWeight: '600' },
  viewToggle: { flexDirection: 'row', marginLeft: 'auto', backgroundColor: Colors.card, borderRadius: BorderRadius.sm, borderWidth: 1, borderColor: Colors.border },
  viewBtn: { paddingHorizontal: 10, paddingVertical: 6 },
  viewBtnActive: { backgroundColor: Colors.gold, borderRadius: BorderRadius.sm },

  section: { paddingHorizontal: Spacing.lg, marginBottom: Spacing.lg },
  sectionTitle: { color: Colors.gold, fontSize: 13, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 10 },

  folderGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  folderGridLarge: { gap: 12 },
  folderGridList: { flexDirection: 'column', gap: 0 },

  folderCardItem: {
    width: (width - 44) / 4, alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 12, borderWidth: 1, borderColor: Colors.border,
  },
  folderCardLarge: { width: (width - 44) / 3, padding: 16 },
  folderCardList: {
    width: '100%', flexDirection: 'row', alignItems: 'center',
    padding: 12, borderRadius: BorderRadius.sm, borderWidth: 0,
    borderBottomWidth: 1, borderBottomColor: Colors.border,
  },
  folderName: { color: Colors.textPrimary, fontSize: 11, marginTop: 6, textAlign: 'center', maxWidth: '100%' },
  folderNameLarge: { fontSize: 13, marginTop: 8 },

  fileGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  fileGridItem: {
    width: (width - 44) / 4, alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 10, borderWidth: 1, borderColor: Colors.border,
  },
  fileGridIcon: {
    width: 48, height: 48, borderRadius: 12,
    justifyContent: 'center', alignItems: 'center',
  },
  fileGridName: { color: Colors.textPrimary, fontSize: 11, marginTop: 6, textAlign: 'center' },
  fileGridSize: { color: Colors.textMuted, fontSize: 10, marginTop: 2 },

  fileRow: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 12, marginBottom: 8, borderWidth: 1, borderColor: Colors.border,
  },
  fileIcon: {
    width: 40, height: 40, borderRadius: 10,
    justifyContent: 'center', alignItems: 'center', marginRight: 12,
  },
  fileInfo: { flex: 1 },
  fileName: { color: Colors.textPrimary, fontSize: 14, fontWeight: '600' },
  fileMeta: { color: Colors.textMuted, fontSize: 11, marginTop: 2 },
  fileBadges: { flexDirection: 'row', alignItems: 'center' },

  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { color: Colors.textSecondary, fontSize: 16, fontWeight: '600', marginTop: 12 },
  emptySubtext: { color: Colors.textMuted, fontSize: 13, marginTop: 4 },

  uploadOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: Colors.overlay, justifyContent: 'center', alignItems: 'center',
  },
  uploadCard: {
    backgroundColor: Colors.card, borderRadius: BorderRadius.lg,
    padding: 32, alignItems: 'center', borderWidth: 1, borderColor: Colors.border,
  },
  uploadText: { color: Colors.textPrimary, marginTop: 12, fontSize: 14 },

  // Modals
  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center', alignItems: 'center', padding: 20,
  },
  modalCard: {
    width: '100%', maxWidth: 340, backgroundColor: Colors.card,
    borderRadius: BorderRadius.lg, padding: 24, borderWidth: 1, borderColor: Colors.border,
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: Colors.textPrimary, textAlign: 'center', marginBottom: 16 },
  modalInput: {
    backgroundColor: Colors.input, borderRadius: BorderRadius.md,
    borderWidth: 1, borderColor: Colors.inputBorder,
    padding: 14, color: Colors.textPrimary, fontSize: 15, marginBottom: 16,
  },
  modalActions: { flexDirection: 'row', gap: 10 },
  modalCancel: {
    flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md,
    backgroundColor: Colors.surface, alignItems: 'center', borderWidth: 1, borderColor: Colors.border,
  },
  modalCancelText: { color: Colors.textMuted, fontWeight: '600' },
  modalConfirm: {
    flex: 1, paddingVertical: 12, borderRadius: BorderRadius.md,
    backgroundColor: Colors.gold, alignItems: 'center',
  },
  modalConfirmText: { color: Colors.primary, fontWeight: '700' },

  uploadLockToggle: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    padding: 12, backgroundColor: Colors.surface, borderRadius: BorderRadius.md,
    marginBottom: 12, borderWidth: 1, borderColor: Colors.border,
  },
  uploadLockText: { color: Colors.textSecondary, fontSize: 14 },

  actionsCard: {
    width: '100%', maxWidth: 300, backgroundColor: Colors.card,
    borderRadius: BorderRadius.lg, borderWidth: 1, borderColor: Colors.border,
    overflow: 'hidden',
  },
  actionsTitle: {
    fontSize: 15, fontWeight: '700', color: Colors.textPrimary,
    padding: 16, borderBottomWidth: 1, borderBottomColor: Colors.border,
  },
  actionItem: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    padding: 14, paddingHorizontal: 16,
    borderBottomWidth: 1, borderBottomColor: Colors.border,
  },
  actionItemText: { color: Colors.textPrimary, fontSize: 14, fontWeight: '500' },
});
