import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Alert, RefreshControl, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

export default function UserManagementScreen({ navigation }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadUsers = async () => {
    try {
      const res = await api.getAdminUsers();
      setUsers(res.users);
    } catch (error) {
      Alert.alert('Error', error.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { loadUsers(); }, []);

  const handleToggleStatus = async (user) => {
    try {
      const res = await api.toggleUserStatus(user.id);
      loadUsers();
      Alert.alert('Berhasil', res.message);
    } catch (error) {
      Alert.alert('Error', error.message);
    }
  };

  const handleDelete = (user) => {
    Alert.alert('Hapus User', `Hapus "${user.name}"?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus', style: 'destructive',
        onPress: async () => {
          try {
            await api.deleteAdminUser(user.id);
            loadUsers();
          } catch (error) { Alert.alert('Error', error.message); }
        },
      },
    ]);
  };

  const renderItem = ({ item }) => (
    <View style={styles.userCard}>
      <View style={styles.userHeader}>
        <View style={[styles.avatar, { backgroundColor: item.is_active ? Colors.successBg : Colors.warningBg }]}>
          <Text style={styles.avatarText}>{item.name[0].toUpperCase()}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.userName}>{item.name}</Text>
          <Text style={styles.userEmail}>{item.email}</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: item.is_active ? Colors.successBg : Colors.warningBg }]}>
          <Text style={[styles.statusText, { color: item.is_active ? Colors.success : Colors.warning }]}>
            {item.is_active ? 'Aktif' : 'Pending'}
          </Text>
        </View>
      </View>

      {/* Storage Bar */}
      <View style={styles.storageSection}>
        <Text style={styles.storageLabel}>Storage: {item.storage_used_formatted} / {item.storage_quota_gb} GB</Text>
        <View style={styles.storageBarBg}>
          <View style={[styles.storageBarFill, {
            width: `${item.storage_percentage}%`,
            backgroundColor: item.storage_percentage > 90 ? Colors.danger : Colors.gold,
          }]} />
        </View>
      </View>

      {/* Actions */}
      <View style={styles.userActions}>
        <TouchableOpacity style={styles.editBtn} onPress={() => navigation.navigate('EditUser', { userId: item.id })}>
          <Ionicons name="create-outline" size={16} color={Colors.gold} />
          <Text style={styles.editBtnText}>Edit</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.toggleBtn}
          onPress={() => handleToggleStatus(item)}>
          <Ionicons name={item.is_active ? 'pause' : 'play'} size={16} color={item.is_active ? Colors.warning : Colors.success} />
          <Text style={[styles.toggleBtnText, { color: item.is_active ? Colors.warning : Colors.success }]}>
            {item.is_active ? 'Nonaktifkan' : 'Aktifkan'}
          </Text>
        </TouchableOpacity>
        {item.role !== 'admin' && (
          <TouchableOpacity style={styles.deleteBtn} onPress={() => handleDelete(item)}>
            <Ionicons name="trash-outline" size={16} color={Colors.danger} />
          </TouchableOpacity>
        )}
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      {loading ? (
        <ActivityIndicator size="large" color={Colors.gold} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={users}
          keyExtractor={item => item.id.toString()}
          renderItem={renderItem}
          contentContainerStyle={{ padding: Spacing.lg, paddingBottom: 20 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadUsers(); }} tintColor={Colors.gold} />}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },

  userCard: {
    backgroundColor: Colors.card, borderRadius: BorderRadius.lg,
    padding: 16, marginBottom: 12, borderWidth: 1, borderColor: Colors.border,
  },
  userHeader: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
  avatarText: { fontSize: 18, fontWeight: '700', color: Colors.gold },
  userName: { fontSize: 15, fontWeight: '700', color: Colors.textPrimary },
  userEmail: { fontSize: 12, color: Colors.textMuted, marginTop: 1 },
  statusBadge: { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  statusText: { fontSize: 11, fontWeight: '600' },

  storageSection: { marginTop: 14 },
  storageLabel: { fontSize: 12, color: Colors.textMuted, marginBottom: 6 },
  storageBarBg: { height: 6, backgroundColor: Colors.input, borderRadius: 3 },
  storageBarFill: { height: 6, borderRadius: 3 },

  userActions: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    marginTop: 14, paddingTop: 14, borderTopWidth: 1, borderTopColor: Colors.border,
  },
  editBtn: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  editBtnText: { color: Colors.gold, fontSize: 13, fontWeight: '600' },
  toggleBtn: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  toggleBtnText: { fontSize: 13, fontWeight: '600' },
  deleteBtn: { marginLeft: 'auto', padding: 4 },
});
