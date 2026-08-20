import Constants from 'expo-constants';
import AsyncStorage from '@react-native-async-storage/async-storage';

const { apiUrl } = Constants.expoConfig?.extra || {};

// Use production URL or fallback to localhost
const BASE_URL = apiUrl || 'http://127.0.0.1:8000';

class ApiService {
  constructor() {
    this.baseUrl = BASE_URL;
  }

  async getToken() {
    return await AsyncStorage.getItem('api_token');
  }

  async request(method, endpoint, data = null, isFormData = false) {
    const token = await this.getToken();
    const headers = {};

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (!isFormData) {
      headers['Content-Type'] = 'application/json';
      headers['Accept'] = 'application/json';
    }

    const config = {
      method,
      headers,
    };

    if (data) {
      if (isFormData) {
        config.body = data;
      } else {
        config.body = JSON.stringify(data);
      }
    }

    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, config);

      // Handle file downloads
      if (response.headers.get('content-type')?.includes('application/')) {
        const text = await response.text();
        try {
          return JSON.parse(text);
        } catch {
          return { raw: text, status: response.status };
        }
      }

      const json = await response.json();

      if (!response.ok) {
        throw { status: response.status, ...json };
      }

      return json;
    } catch (error) {
      if (error.status) throw error;
      throw { message: 'Network error. Periksa koneksi internet Anda.' };
    }
  }

  // Auth
  login(email, password) {
    return this.request('POST', '/api/login', { email, password });
  }

  register(name, email, password, passwordConfirmation) {
    return this.request('POST', '/api/register', {
      name, email, password, password_confirmation: passwordConfirmation,
    });
  }

  logout() {
    return this.request('POST', '/api/logout');
  }

  me() {
    return this.request('GET', '/api/me');
  }

  // Drive
  getDrive(folder = '/', search = '', showHidden = false) {
    let params = `folder=${encodeURIComponent(folder)}`;
    if (search) params += `&search=${encodeURIComponent(search)}`;
    if (showHidden) params += `&show_hidden=true`;
    return this.request('GET', `/api/drive?${params}`);
  }

  uploadFile(fileUri, fileName, fileType, folder = '/', isLocked = false, lockPassword = null) {
    const formData = new FormData();
    formData.append('file', {
      uri: fileUri,
      name: fileName,
      type: fileType || 'application/octet-stream',
    });
    formData.append('folder', folder);
    if (isLocked) {
      formData.append('is_locked', '1');
      if (lockPassword) formData.append('lock_password', lockPassword);
    }
    return this.request('POST', '/api/drive/upload', formData, true);
  }

  deleteFile(fileId) {
    return this.request('DELETE', `/api/drive/file/${fileId}`);
  }

  downloadFile(fileId) {
    return this.request('GET', `/api/drive/file/${fileId}/download`);
  }

  downloadEncrypted(fileId, password) {
    return this.request('POST', `/api/drive/file/${fileId}/download-encrypted`, { password });
  }

  createFolder(name, parentPath = '/') {
    return this.request('POST', '/api/drive/folder/create', { name, parent_path: parentPath });
  }

  deleteFolder(folderId) {
    return this.request('DELETE', `/api/drive/folder/${folderId}`);
  }

  lockFile(fileId, password) {
    return this.request('POST', `/api/drive/file/${fileId}/lock`, { password });
  }

  unlockFile(fileId, password) {
    return this.request('POST', `/api/drive/file/${fileId}/unlock`, { password });
  }

  lockFolder(folderId, password) {
    return this.request('POST', `/api/drive/folder/${folderId}/lock`, { password });
  }

  unlockFolder(folderId, password) {
    return this.request('POST', `/api/drive/folder/${folderId}/unlock`, { password });
  }

  shareFile(fileId, password = null, downloadLimit = null) {
    return this.request('POST', `/api/drive/file/${fileId}/share`, {
      password, download_limit: downloadLimit,
    });
  }

  unshareFile(fileId) {
    return this.request('POST', `/api/drive/file/${fileId}/unshare`);
  }

  toggleVisibility(fileId) {
    return this.request('POST', `/api/drive/file/${fileId}/toggle-visibility`);
  }

  toggleFolderVisibility(folderId) {
    return this.request('POST', `/api/drive/folder/${folderId}/toggle-visibility`);
  }

  getFileInfo(fileId) {
    return this.request('GET', `/api/drive/file/${fileId}/info`);
  }

  moveFile(fileId, folder) {
    return this.request('POST', `/api/drive/file/${fileId}/move`, { folder });
  }

  moveFolder(folderId, parentPath) {
    return this.request('POST', `/api/drive/folder/${folderId}/move`, { parent_path: parentPath });
  }

  // Hidden
  getHidden() {
    return this.request('GET', '/api/drive/hidden');
  }

  verifyHiddenPassword(password) {
    return this.request('POST', '/api/drive/hidden/verify', { password });
  }

  unhideFile(fileId, password) {
    return this.request('POST', `/api/drive/hidden/${fileId}/unhide`, { password });
  }

  unhideFolder(folderId, password) {
    return this.request('POST', `/api/drive/hidden/${folderId}/unhide`, { password });
  }

  // Profile
  getProfile() {
    return this.request('GET', '/api/profile');
  }

  updateProfile(name, email) {
    return this.request('PUT', '/api/profile', { name, email });
  }

  updatePassword(currentPassword, password, passwordConfirmation) {
    return this.request('PUT', '/api/profile/password', {
      current_password: currentPassword, password, password_confirmation: passwordConfirmation,
    });
  }

  uploadAvatar(fileUri) {
    const formData = new FormData();
    formData.append('avatar', {
      uri: fileUri,
      name: 'avatar.jpg',
      type: 'image/jpeg',
    });
    return this.request('POST', '/api/profile/avatar', formData, true);
  }

  // Notifications
  getNotifications() {
    return this.request('GET', '/api/notifications');
  }

  markNotificationRead(id) {
    return this.request('POST', `/api/notifications/${id}/read`);
  }

  markAllRead() {
    return this.request('POST', '/api/notifications/read-all');
  }

  // Admin
  getAdminDashboard() {
    return this.request('GET', '/api/admin/dashboard');
  }

  getAdminUsers() {
    return this.request('GET', '/api/admin/users');
  }

  getAdminUser(id) {
    return this.request('GET', `/api/admin/users/${id}`);
  }

  updateAdminUser(id, data) {
    return this.request('PUT', `/api/admin/users/${id}`, data);
  }

  deleteAdminUser(id) {
    return this.request('DELETE', `/api/admin/users/${id}`);
  }

  toggleUserStatus(id) {
    return this.request('POST', `/api/admin/users/${id}/toggle-status`);
  }

  resetUserStorage(id) {
    return this.request('POST', `/api/admin/users/${id}/reset-storage`);
  }

  // Share
  getShareInfo(token) {
    return this.request('GET', `/api/share/${token}`);
  }

  verifyShare(token, password = null) {
    return this.request('POST', `/api/share/${token}/verify`, { password });
  }
}

export default new ApiService();
