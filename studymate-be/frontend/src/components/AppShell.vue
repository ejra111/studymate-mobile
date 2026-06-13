<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { logout, state, markNotificationRead, markAllNotificationsRead, acceptInvite, rejectInvite } from '../store/appStore'

const route = useRoute()
const router = useRouter()
const showNotifPanel = ref(false)

const menu = computed(() => {
  const base = [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Grup Belajar', to: '/groups' },
    { label: 'Smart Match', to: '/matchmaking' }
  ]

  if (state.user?.role === 'admin') {
    base.push({ label: 'Admin', to: '/admin' })
  }

  return base
})

const unreadCount = computed(() => state.unreadNotificationCount || 0)
const notifications = computed(() => state.notifications || [])

function toggleNotifications() {
  showNotifPanel.value = !showNotifPanel.value
  if (showNotifPanel.value) {
    loadNotifications()
  }
}

function closeNotifPanel() {
  showNotifPanel.value = false
}

async function handleMarkRead(notif) {
  // If it's a study invite and pending, don't mark as read automatically just by clicking
  // so the notification stays unread/highlighted until acted upon,
  // OR mark as read but the buttons will stay because we changed the v-if.
  
  if (!notif.readAt) {
    await markNotificationRead(notif.id)
  }
  
  // If it's a study invite, maybe we don't want to close the panel or navigate away immediately
  // unless the user clicks the content specifically.
  
  // Handle navigation based on notification type
  if (notif.type === 'group_join' || notif.type === 'group_activity' || notif.type === 'group_created') {
    if (notif.data?.groupId) {
      router.push(`/groups`)
      closeNotifPanel()
    }
  } else if (notif.type === 'study_invite' || notif.type === 'invite_accepted') {
    router.push('/matchmaking')
    closeNotifPanel()
  } else if (notif.type === 'private_message') {
    router.push('/matchmaking')
    closeNotifPanel()
  }
}

async function handleMarkAllRead() {
  await markAllNotificationsRead()
}

async function handleAccept(id) {
  await acceptInvite(id)
}

async function handleReject(id) {
  await rejectInvite(id)
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const now = Date.now()
  const diff = now - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Baru saja'
  if (mins < 60) return `${mins} menit lalu`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} jam lalu`
  const days = Math.floor(hours / 24)
  return `${days} hari lalu`
}

function handleLogout() {
  logout()
  router.push('/login')
}
</script>

<template>
  <div class="shell">
    <aside class="sidebar">
      <div class="brand-header">
        <div>
          <p class="eyebrow" style="margin:0; font-size: 10px;">STUDYMATE</p>
          <h1 class="logo-title">StudyMate</h1>
        </div>
      </div>

      <nav class="nav">
        <RouterLink
          v-for="item in menu"
          :key="item.to"
          :to="item.to"
          class="nav-link"
          :class="{ active: route.path === item.to }"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <RouterLink to="/profile" class="sidebar-user">
          <div class="avatar" :style="{ background: state.user?.avatarColor || state.user?.avatar_color || '#10b981' }">
            <img v-if="state.user?.avatarUrl || state.user?.avatar_url" :src="state.user?.avatarUrl || state.user?.avatar_url" alt="Avatar" class="avatar-img-sidebar" />
            <span v-else>{{ state.user?.name?.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="user-info-mini">
            <span class="topbar-name">{{ state.user?.name }}</span>
            <span class="view-profile-text">Lihat Profil</span>
          </div>
        </RouterLink>

        <div class="footer-actions">
          <button class="notif-bell" @click="toggleNotifications" title="Notifikasi">
            🔔
            <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </button>
          <button class="logout-btn" @click="handleLogout">Logout</button>
        </div>
      </div>
    </aside>

    <main class="main-content">
      <slot />
    </main>

    <!-- Notification Panel Overlay -->
    <div v-if="showNotifPanel" class="notif-overlay" @click="closeNotifPanel"></div>
    <div v-if="showNotifPanel" class="notif-panel glass-card">
      <header class="notif-header">
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3>🔔 Notifikasi</h3>
          <button class="mark-all-btn" @click="loadNotifications" style="padding: 2px 6px; font-size: 9px;">Muat Ulang</button>
        </div>
        <div class="notif-header-actions">
          <button v-if="unreadCount > 0" class="mark-all-btn" @click="handleMarkAllRead">Tandai Semua Dibaca</button>
          <button class="close-notif" @click="closeNotifPanel">×</button>
        </div>
      </header>
      <div class="notif-body">
        <div v-if="notifications.length === 0" class="notif-empty">
          <p>Belum ada notifikasi.</p>
        </div>
        <div
          v-for="notif in notifications"
          :key="notif.id"
          class="notif-item"
          :class="{ unread: !notif.readAt }"
          @click="handleMarkRead(notif)"
          style="display: flex; align-items: flex-start; gap: 12px; padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.05);"
        >
          <div class="notif-sender-avatar" :style="{ background: notif.sender?.avatarColor || notif.sender?.avatar_color || '#6366f1' }" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; overflow: hidden;">
            <img v-if="notif.sender?.avatarUrl || notif.sender?.avatar_url" :src="notif.sender?.avatarUrl || notif.sender?.avatar_url" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" />
            <span v-else>{{ notif.sender?.name?.charAt(0).toUpperCase() || '?' }}</span>
          </div>
          <div class="notif-content" style="flex: 1;">
            <p class="notif-message" style="margin: 0 0 4px; font-size: 13px; color: #cbd5e1;">{{ notif.message }}</p>
            <div v-if="notif.type === 'study_invite' && (!notif.data || !notif.data.status || notif.data.status === 'pending')" class="notif-actions" style="display: flex; gap: 8px; margin-top: 8px;">
              <button class="accept-btn" @click.stop="handleAccept(notif.id)">Terima</button>
              <button class="reject-btn" @click.stop="handleReject(notif.id)">Tolak</button>
            </div>
            <span class="notif-time" style="font-size: 11px; color: #64748b;">{{ timeAgo(notif.createdAt) }}</span>
          </div>
          <div v-if="!notif.readAt" class="notif-unread-dot" style="width: 8px; height: 8px; background: #6366f1; border-radius: 50%; margin-top: 6px;"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.nav-icon { font-size: 14px; }

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s;
  border: 1px solid transparent;
}
.sidebar-user:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.1);
}

.avatar {
  overflow: hidden; /* Ensure image doesn't overflow rounded corners */
}

.avatar-img-sidebar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info-mini {
  display: flex;
  flex-direction: column;
}

.topbar-name {
  font-size: 13px;
  font-weight: 700;
  color: white;
}

.view-profile-text {
  font-size: 10px;
  color: #64748b;
  font-weight: 600;
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.notif-bell {
  position: relative;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 8px 10px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}
.notif-bell:hover {
  background: rgba(99, 102, 241, 0.15);
  border-color: rgba(99, 102, 241, 0.3);
}

.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: white;
  font-size: 9px;
  font-weight: 800;
  min-width: 16px;
  height: 16px;
  border-radius: 99px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

.notif-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0,0,0,0.3);
}

.notif-panel {
  position: fixed;
  top: 80px;
  right: 24px;
  width: 380px;
  max-height: 500px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(99, 102, 241, 0.3);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
  animation: slideIn 0.25s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}

.notif-header {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--line);
  background: rgba(99, 102, 241, 0.05);
}
.notif-header h3 { margin: 0; font-size: 15px; color: white; }

.notif-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mark-all-btn {
  background: transparent;
  border: 1px solid rgba(99, 102, 241, 0.3);
  color: #818cf8;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.mark-all-btn:hover {
  background: rgba(99, 102, 241, 0.15);
}

.close-notif {
  background: transparent;
  border: 0;
  color: #94a3b8;
  font-size: 22px;
  cursor: pointer;
  line-height: 1;
}

.notif-body {
  flex: 1;
  overflow-y: auto;
  max-height: 400px;
}

.notif-empty {
  padding: 40px 20px;
  text-align: center;
}
.notif-empty p { color: #64748b; font-size: 14px; }

.notif-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
}
.notif-item:hover { background: rgba(255, 255, 255, 0.03); }
.notif-item.unread { background: rgba(99, 102, 241, 0.06); }

.notif-sender-avatar {
  width: 36px;
  height: 36px;
  min-width: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 14px;
  overflow: hidden;
}

.notif-img-mini {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.notif-content { flex: 1; }
.notif-message {
  font-size: 13px;
  color: #cbd5e1;
  margin: 0 0 4px;
  line-height: 1.4;
}
.notif-time {
  font-size: 11px;
  color: #64748b;
}

.notif-unread-dot {
  width: 8px;
  height: 8px;
  background: #6366f1;
  border-radius: 50%;
  flex-shrink: 0;
}

.notif-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  margin-bottom: 4px;
}

.accept-btn, .reject-btn {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid var(--line);
  transition: all 0.2s;
}

.accept-btn {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

.accept-btn:hover {
  opacity: 0.9;
}

.reject-btn {
  background: rgba(255, 255, 255, 0.05);
  color: #94a3b8;
}

.reject-btn:hover {
  background: rgba(255, 0, 0, 0.1);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.3);
}
</style>
