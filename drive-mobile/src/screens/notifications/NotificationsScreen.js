import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Alert, RefreshControl, ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../config/api';
import { Colors, BorderRadius, Spacing } from '../../theme/colors';

function getNotifColor(color) {
  const map = {
    green: Colors.success, red: Colors.danger, amber: Colors.warning,
    blue: Colors.info, purple: Colors.purple, pink: Colors.pink,
  };
  return map[color] || Colors.textMuted;
}

function getNotifBg(color) {
  const map = {
    green: Colors.successBg, red: Colors.dangerBg, amber: Colors.warningBg,
    blue: Colors.infoBg, purple: Colors.purpleBg,
  };
  return map[color] || Colors.card;
}

export default function NotificationsScreen() {
  const insets = useSafeAreaInsets();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const res = await api.getNotifications();
      setNotifications(res.notifications);
      setUnreadCount(res.unread_count);
    } catch (error) {
      console.log(error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { loadData(); }, []);

  const handleMarkRead = async (id) => {
    try {
      await api.markNotificationRead(id);
      setNotifications(prev => prev.map(n => n.id === id ? { ...n, is_read: true } : n));
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (error) { console.log(error); }
  };

  const handleMarkAllRead = async () => {
    try {
      await api.markAllRead();
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch (error) { console.log(error); }
  };

  const renderItem = ({ item }) => (
    <TouchableOpacity
      style={[styles.notifCard, !item.is_read && styles.notifUnread]}
      onPress={() => handleMarkRead(item.id)}
    >
      <View style={[styles.notifIcon, { backgroundColor: getNotifBg(item.color) }]}>
        <Ionicons name={item.icon || 'notifications'} size={20} color={getNotifColor(item.color)} />
      </View>
      <View style={styles.notifContent}>
        <Text style={styles.notifTitle}>{item.title}</Text>
        <Text style={styles.notifMessage} numberOfLines={2}>{item.message}</Text>
        <Text style={styles.notifTime}>{item.time_ago}</Text>
      </View>
      {!item.is_read && <View style={styles.unreadDot} />}
    </TouchableOpacity>
  );

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Notifikasi</Text>
        {unreadCount > 0 && (
          <TouchableOpacity onPress={handleMarkAllRead}>
            <Text style={styles.markAllRead}>Tandai semua dibaca</Text>
          </TouchableOpacity>
        )}
      </View>

      {loading ? (
        <ActivityIndicator size="large" color={Colors.gold} style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={notifications}
          keyExtractor={item => item.id.toString()}
          renderItem={renderItem}
          contentContainerStyle={{ padding: Spacing.lg, paddingBottom: 20 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadData(); }} tintColor={Colors.gold} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Ionicons name="notifications-off-outline" size={48} color={Colors.textMuted} />
              <Text style={styles.emptyText}>Tidak ada notifikasi</Text>
            </View>
          }
        />
      )}
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
  headerTitle: { fontSize: 18, fontWeight: '800', color: Colors.gold },
  markAllRead: { color: Colors.gold, fontSize: 12, fontWeight: '600' },

  notifCard: {
    flexDirection: 'row', alignItems: 'flex-start',
    backgroundColor: Colors.card, borderRadius: BorderRadius.md,
    padding: 14, marginBottom: 10, borderWidth: 1, borderColor: Colors.border,
  },
  notifUnread: { borderColor: Colors.gold + '40' },
  notifIcon: {
    width: 40, height: 40, borderRadius: 10,
    justifyContent: 'center', alignItems: 'center', marginRight: 12,
  },
  notifContent: { flex: 1 },
  notifTitle: { fontSize: 14, fontWeight: '700', color: Colors.textPrimary },
  notifMessage: { fontSize: 13, color: Colors.textSecondary, marginTop: 2, lineHeight: 18 },
  notifTime: { fontSize: 11, color: Colors.textMuted, marginTop: 4 },
  unreadDot: {
    width: 8, height: 8, borderRadius: 4,
    backgroundColor: Colors.gold, marginTop: 4, marginLeft: 8,
  },

  empty: { alignItems: 'center', marginTop: 60 },
  emptyText: { color: Colors.textMuted, fontSize: 14, marginTop: 8 },
});
