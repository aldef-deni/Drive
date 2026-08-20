import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, RefreshControl, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

export default function AdminDashboardScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const [stats, setStats] = useState(null);
  const [recentUsers, setRecentUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = async () => {
    try {
      const res = await api.getAdminDashboard();
      setStats(res.stats);
      setRecentUsers(res.recent_users);
    } catch (error) {
      console.log(error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { loadData(); }, []);

  const statCards = stats ? [
    { icon: 'people', label: 'Total Users', value: stats.total_users, color: Colors.info },
    { icon: 'checkmark-circle', label: 'Aktif', value: stats.active_users, color: Colors.success },
    { icon: 'time', label: 'Pending', value: stats.pending_users, color: Colors.warning },
    { icon: 'document', label: 'Total Files', value: stats.total_files, color: Colors.purple },
  ] : [];

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <FlatList
        data={[1]}
        keyExtractor={() => 'h'}
        contentContainerStyle={{ paddingBottom: 20 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadData(); }} tintColor={Colors.gold} />}
        ListHeaderComponent={() => (
          <View>
            {/* Stats Grid */}
            <View style={styles.statsGrid}>
              {loading ? (
                <ActivityIndicator color={Colors.gold} />
              ) : statCards.map((s, i) => (
                <View key={i} style={[styles.statCard, { borderLeftColor: s.color }]}>
                  <Ionicons name={s.icon} size={24} color={s.color} />
                  <Text style={styles.statValue}>{s.value}</Text>
                  <Text style={styles.statLabel}>{s.label}</Text>
                </View>
              ))}
            </View>

            {/* Storage Info */}
            {stats && (
              <View style={styles.storageCard}>
                <Ionicons name="cloud" size={20} color={Colors.gold} />
                <Text style={styles.storageText}>Total Storage: {stats.total_storage_formatted}</Text>
              </View>
            )}

            {/* Quick Actions */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Aksi Cepat</Text>
              <TouchableOpacity style={styles.actionCard}
                onPress={() => navigation.navigate('UserManagement')}>
                <Ionicons name="people" size={22} color={Colors.gold} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.actionTitle}>User Management</Text>
                  <Text style={styles.actionSubtitle}>Kelola semua user</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color={Colors.textMuted} />
              </TouchableOpacity>

              <TouchableOpacity style={styles.actionCard}
                onPress={() => navigation.navigate('HiddenSystem')}>
                <Ionicons name="eye-off" size={22} color={Colors.gold} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.actionTitle}>Hidden System</Text>
                  <Text style={styles.actionSubtitle}>Ganti kata kunci rahasia</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color={Colors.textMuted} />
              </TouchableOpacity>
            </View>

            {/* Recent Users */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>User Terbaru</Text>
              {recentUsers.map(u => (
                <View key={u.id} style={styles.userRow}>
                  <View style={[styles.userAvatar, { backgroundColor: u.is_active ? Colors.successBg : Colors.warningBg }]}>
                    <Text style={styles.userAvatarText}>{u.name[0].toUpperCase()}</Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.userName}>{u.name}</Text>
                    <Text style={styles.userEmail}>{u.email}</Text>
                  </View>
                  <View style={[styles.statusDot, { backgroundColor: u.is_active ? Colors.success : Colors.warning }]} />
                </View>
              ))}
            </View>
          </View>
        )}
        renderItem={() => null}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.primary },

  statsGrid: {
    flexDirection: 'row', flexWrap: 'wrap', gap: 10,
    padding: Spacing.lg,
  },
  statCard: {
    width: '47%', backgroundColor: Colors.card,
    borderRadius: BorderRadius.md, padding: 16,
    borderWidth: 1, borderColor: Colors.border,
    borderLeftWidth: 3,
  },
  statValue: { fontSize: 24, fontWeight: '800', color: Colors.textPrimary, marginTop: 8 },
  statLabel: { fontSize: 12, color: Colors.textMuted, marginTop: 2 },

  storageCard: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    marginHorizontal: Spacing.lg, marginBottom: Spacing.lg,
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 14, borderWidth: 1, borderColor: Colors.border,
  },
  storageText: { color: Colors.textSecondary, fontSize: 14 },

  section: { paddingHorizontal: Spacing.lg, marginBottom: 20 },
  sectionTitle: { color: Colors.gold, fontSize: 13, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 10 },

  actionCard: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 16, borderWidth: 1, borderColor: Colors.border,
  },
  actionTitle: { fontSize: 15, fontWeight: '600', color: Colors.textPrimary },
  actionSubtitle: { fontSize: 12, color: Colors.textMuted, marginTop: 2 },

  userRow: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 12, marginBottom: 8, borderWidth: 1, borderColor: Colors.border,
  },
  userAvatar: {
    width: 36, height: 36, borderRadius: 18, justifyContent: 'center', alignItems: 'center',
  },
  userAvatarText: { fontSize: 14, fontWeight: '700', color: Colors.gold },
  userName: { fontSize: 14, fontWeight: '600', color: Colors.textPrimary },
  userEmail: { fontSize: 12, color: Colors.textMuted },
  statusDot: { width: 10, height: 10, borderRadius: 5 },
});
