<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import {
  createGroup,
  deleteGroup,
  getGoldenHour,
  getGroupCompatibility,
  getGroupMessages,
  getGroupSummary,
  joinGroup,
  loadGroups,
  leaveGroup,
  sendGroupMessage,
  updateGroup,
  state,
  pushToast,
} from '../store/appStore'

const filters = reactive({ search: '' })
const currentTab = ref('search') // 'search', 'create', 'my'
const isEditing = ref(false)
const editingGroupId = ref(null)

const form = reactive({
  title: '',
  topic: '',
  description: '',
  scheduleDay: '',
  scheduleTime: '',
  courseName: '',
  locationName: '',
  capacity: 5,
})

const scheduleError = ref('')
const selectedGroupId = ref('')
const detailPanel = ref(null)
const showChatModal = ref(false)
const currentChatGroup = ref(null)

const chatMessages = ref([])
const chatDraft = ref('')
const summary = ref(null)
const compatibility = ref(null)
const goldenHour = ref(null)
const loadingSummary = ref(false)
const refreshingSummary = ref(false)
const loadingChat = ref(false)
const pollingTimer = ref(null)

onMounted(async () => {
  await loadGroups()
})

onUnmounted(() => {
  stopPolling()
})

// Stop polling when modal is closed
watch(showChatModal, (newVal) => {
  if (!newVal) {
    stopPolling()
  }
})

function startPolling() {
  stopPolling()
  pollingTimer.value = setInterval(async () => {
    if (selectedGroupId.value && showChatModal.value) {
      try {
        const newMessages = await getGroupMessages(selectedGroupId.value)
        
        // Check if we have local optimistic messages (ID starts with 'temp-')
        const hasOptimistic = chatMessages.value.some(m => String(m.id).startsWith('temp-'))
        
        // Get the latest real message from local state
        const lastRealLocal = [...chatMessages.value].reverse().find(m => !String(m.id).startsWith('temp-'))
        const lastRealNew = newMessages[newMessages.length - 1]

        // Only update if:
        // 1. New messages count is different AND we don't have optimistic messages
        // 2. OR the last real message ID is different
        const shouldUpdate = (!hasOptimistic && newMessages.length !== chatMessages.value.length) || 
                           (lastRealNew && lastRealLocal && lastRealNew.id !== lastRealLocal.id) ||
                           (lastRealNew && !lastRealLocal)

        if (shouldUpdate) {
          const wasAtBottom = isChatAtBottom()
          
          if (hasOptimistic) {
            // Merge: keep optimistic messages at the end
            const optimisticMsgs = chatMessages.value.filter(m => String(m.id).startsWith('temp-'))
            chatMessages.value = [...newMessages, ...optimisticMsgs]
          } else {
            chatMessages.value = newMessages
          }
          
          if (wasAtBottom) {
            await nextTick()
            scrollToBottom()
          }
        }
      } catch (err) {
        console.error('Polling error:', err)
      }
    }
  }, 2000) // Poll every 2 seconds
}

function stopPolling() {
  if (pollingTimer.value) {
    clearInterval(pollingTimer.value)
    pollingTimer.value = null
  }
}

function isChatAtBottom() {
  const chatBox = document.querySelector('.chat-box')
  if (!chatBox) return false
  const threshold = 100 // px
  return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < threshold
}

function scrollToBottom() {
  const chatBox = document.querySelector('.chat-box')
  if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight
  }
}

const groups = computed(() => state.groups || [])
const myCreatedGroups = computed(() => groups.value.filter(g => g.owner_id === state.user?.id))
const myJoinedGroups = computed(() => groups.value.filter(g => 
  g.owner_id !== state.user?.id && isMember(g)
))

function isMember(group) {
  return (group.memberIds || group.members?.map((member) => member.id) || []).includes(state.user?.id)
}

function isOwner(group) {
  return group.owner_id === state.user?.id
}

async function searchGroups() {
  await loadGroups({ search: filters.search })
}

// Auto-reset when search is cleared
watch(
  () => filters.search,
  (newVal) => {
    if (newVal === '' || newVal === null) {
      loadGroups()
    }
  }
)

// === Auto UPPERCASE handler ===
function autoUpperCase(field) {
  form[field] = String(form[field] || '').toUpperCase()
}

// === Schedule configuration ===
const VALID_DAYS = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU']

async function submitGroup() {
  if (!form.scheduleDay || !form.scheduleTime) {
    pushToast('Pilih hari dan jam terlebih dahulu.', 'error')
    return
  }

  try {
    const formattedSchedule = `${form.scheduleDay} ${form.scheduleTime}`
    const payload = {
      title: form.title,
      topic: form.topic,
      description: form.description,
      schedule: formattedSchedule,
      courseName: form.courseName.toUpperCase(),
      locationName: form.locationName,
      capacity: form.capacity,
    }

    if (isEditing.value && editingGroupId.value) {
      await updateGroup(editingGroupId.value, payload)
      isEditing.value = false
      editingGroupId.value = null
      currentTab.value = 'my'
    } else {
      await createGroup(payload)
      currentTab.value = 'search'
    }

    resetForm()
  } catch (error) {
    console.error(error)
  }
}

function resetForm() {
  Object.assign(form, {
    title: '',
    topic: '',
    description: '',
    scheduleDay: '',
    scheduleTime: '',
    courseName: '',
    locationName: '',
    capacity: 5,
  })
  isEditing.value = false
  editingGroupId.value = null
  scheduleError.value = ''
}

function startEdit(group) {
  isEditing.value = true
  editingGroupId.value = group.id
  currentTab.value = 'create'
  
  // Parse schedule (assuming "DAY HH:mm")
  const parts = group.schedule.split(' ')
  const day = VALID_DAYS.includes(parts[0]) ? parts[0] : ''
  const time = parts[1] || ''

  Object.assign(form, {
    title: group.title,
    topic: group.topic,
    description: group.description,
    scheduleDay: day,
    scheduleTime: time,
    courseName: group.course?.name || '',
    locationName: group.location?.name || '',
    capacity: group.capacity,
  })
}

async function handleDelete(groupId) {
  if (!confirm('Apakah Anda yakin ingin menghapus grup ini?')) return
  try {
    await deleteGroup(groupId)
  } catch (err) {
    console.error('Failed to delete group:', err)
  }
}

async function openRoom(group) {
  selectedGroupId.value = group.id
  currentChatGroup.value = group
  showChatModal.value = true
  
  // Reset previous data
  chatMessages.value = []
  summary.value = null
  compatibility.value = null
  goldenHour.value = null
  
  loadingChat.value = true
  loadingSummary.value = true
  
  // 1. Load chat messages first (High Priority)
  try {
    chatMessages.value = await getGroupMessages(group.id)
    loadingChat.value = false
    
    // Start real-time polling
    startPolling()
    
    // Scroll to bottom after loading messages
    await nextTick()
    scrollToBottom()
  } catch (err) {
    console.error('Error loading chat:', err)
    loadingChat.value = false
  }

  // 2. Load AI insights in background (Lower Priority)
  // We don't 'await' this so the modal stays open and responsive
  Promise.allSettled([
    getGroupSummary(group.id),
    getGroupCompatibility(group.id, true),
    getGoldenHour(group.id)
  ]).then((results) => {
    summary.value = results[0].status === 'fulfilled' ? results[0].value : null
    compatibility.value = results[1].status === 'fulfilled' ? results[1].value : null
    goldenHour.value = results[2].status === 'fulfilled' ? results[2].value : null
    loadingSummary.value = false
  }).catch(err => {
    console.error('Error loading insights:', err)
    loadingSummary.value = false
  })
}

async function handleLeave(groupId) {
  if (!confirm('Apakah Anda yakin ingin keluar dari grup ini?')) return
  try {
    await leaveGroup(groupId)
  } catch (err) {
    console.error('Failed to leave group:', err)
  }
}

async function handleJoin(group) {
  try {
    await joinGroup(group.id)
  } catch (error) {
    console.error(error)
  }
}

async function handleSend() {
  const messageText = chatDraft.value.trim()
  if (!selectedGroupId.value || !messageText) return
  
  const tempId = 'temp-' + Date.now()
  const optimisticMessage = {
    id: tempId,
    message: messageText,
    createdAt: new Date().toISOString(),
    user: state.user
  }
  
  // Clear input immediately
  chatDraft.value = ''
  
  // Optimistic add (appears immediately like a normal message)
  chatMessages.value.push(optimisticMessage)
  await nextTick()
  scrollToBottom()
  
  try {
    const message = await sendGroupMessage(selectedGroupId.value, messageText)
    
    // Replace optimistic message with real one from server
    const index = chatMessages.value.findIndex(m => m.id === tempId)
    if (index !== -1) {
      chatMessages.value[index] = message
    }
    
    // De-duplicate in case polling already caught it
    const uniqueMessages = []
    const seenIds = new Set()
    for (const m of chatMessages.value) {
      if (!seenIds.has(m.id)) {
        seenIds.add(m.id)
        uniqueMessages.push(m)
      }
    }
    chatMessages.value = uniqueMessages
  } catch (error) {
    console.error('Failed to send message:', error)
    // Remove optimistic message only on true failure
    chatMessages.value = chatMessages.value.filter(m => m.id !== tempId)
    // Restore text so user doesn't lose it
    chatDraft.value = messageText
    pushToast('Gagal mengirim pesan. Silakan coba lagi.', 'error')
  }
}

async function handleRefreshSummary() {
  if (!selectedGroupId.value || refreshingSummary.value) return
  refreshingSummary.value = true
  try {
    summary.value = await getGroupSummary(selectedGroupId.value, true)
    pushToast('Rangkuman chat berhasil diperbarui!', 'success')
  } catch (err) {
    pushToast('Gagal memperbarui rangkuman.', 'error')
    console.error(err)
  } finally {
    refreshingSummary.value = false
  }
}
</script>

<template>
  <section class="groups-shell">
    <!-- Tab Navigation -->
    <nav class="tab-nav glass-card">
      <button 
        class="tab-btn" 
        :class="{ active: currentTab === 'search' }"
        @click="currentTab = 'search'"
      >
        🔍 Cari Grup
      </button>
      <button 
        class="tab-btn" 
        :class="{ active: currentTab === 'create' }"
        @click="currentTab = 'create'"
      >
        {{ isEditing ? '✏️ Edit Grup' : '➕ Buat Grup' }}
      </button>
      <button 
        class="tab-btn" 
        :class="{ active: currentTab === 'my' }"
        @click="currentTab = 'my'"
      >
        👤 Grup Saya
      </button>
    </nav>

    <div class="tab-content">
      <!-- CREATE / EDIT TAB -->
      <article v-if="currentTab === 'create'" class="panel glass-card">
        <p class="eyebrow">{{ isEditing ? 'Edit grup belajar' : 'Buat grup belajar' }}</p>
        <h1>{{ isEditing ? 'Perbarui informasi grup' : 'Buar Grup Belajar Baru' }}</h1>
        
        <div class="form-grid-inner">
          <div class="field-group">
            <label>Judul Grup</label>
            <input v-model="form.title" placeholder="Contoh: Belajar AI" />
          </div>
          <div class="field-group">
            <label>Topik</label>
            <input v-model="form.topic" placeholder="Contoh: Machine Learning" />
          </div>
          <div class="field-group">
            <label>Mata Kuliah</label>
            <input v-model="form.courseName" placeholder="Contoh: Kecerdasan Buatan" />
          </div>
          <div class="field-group">
            <label>Tempat Belajar</label>
            <input v-model="form.locationName" placeholder="Contoh: Perpustakaan Pusat" />
          </div>
          <div class="field-group schedule-group full-span">
            <label>Jadwal Belajar</label>
            <div class="schedule-ux-wrapper">
              <div class="day-picker">
                <button 
                  v-for="day in VALID_DAYS" 
                  :key="day"
                  class="day-chip"
                  :class="{ selected: form.scheduleDay === day }"
                  @click="form.scheduleDay = day"
                >
                  {{ day.slice(0,3) }}
                </button>
              </div>
              <div class="time-picker-custom">
                <input v-model="form.scheduleTime" type="time" class="time-input-modern" />
                <div class="quick-times">
                  <button v-for="t in ['08:00', '10:00', '13:00', '15:00', '19:00']" :key="t" @click="form.scheduleTime = t" class="time-chip">
                    {{ t }}
                  </button>
                </div>
              </div>
            </div>
            <small class="hint">Pilih hari dan tentukan jam belajar (bisa pilih cepat).</small>
          </div>
          <div class="field-group">
            <label>Kapasitas</label>
            <input v-model.number="form.capacity" type="number" min="2" max="50" placeholder="Kapasitas" />
          </div>
          <div class="field-group full-span">
            <label>Deskripsi</label>
            <textarea v-model="form.description" rows="3" placeholder="Deskripsi singkat grup"></textarea>
          </div>
        </div>
        
        <div class="form-actions">
          <button class="primary-btn submit-btn" @click="submitGroup">
            {{ isEditing ? 'Simpan Perubahan' : 'Buat Grup Sekarang' }}
          </button>
          <button v-if="isEditing" class="secondary-btn cancel-btn" @click="resetForm(); currentTab = 'my'">
            Batal
          </button>
        </div>
      </article>

      <!-- SEARCH ALL TAB -->
      <article v-if="currentTab === 'search'" class="panel glass-card">
        <div class="toolbar">
          <div>
            <p class="eyebrow">Eksplorasi</p>
            <h2>Cari grup yang relevan</h2>
          </div>
          <div class="search-box">
            <input v-model="filters.search" placeholder="Cari judul, topik, deskripsi" @keyup.enter="searchGroups" class="search-input" />
            <button class="primary-btn small-btn" @click="searchGroups">Cari</button>
          </div>
        </div>

        <div class="group-list" v-if="groups.length">
          <div v-for="group in groups" :key="group.id" class="group-card-item">
            <div class="group-info">
              <strong>{{ group.title }}</strong>
              <p class="group-topic">{{ group.topic }}</p>
              <div class="group-meta">
                <span class="meta-tag">{{ group.course?.name }}</span>
                <span class="meta-tag">{{ group.location?.name }}</span>
                <span class="meta-tag schedule-tag">📅 {{ group.schedule }}</span>
              </div>
            </div>
            <div class="card-actions">
              <span class="seat-badge">Sisa {{ group.seatsLeft ?? 0 }} Kursi</span>
              <template v-if="isMember(group)">
                <span class="joined-badge">Sudah Bergabung</span>
                <button class="open-btn" @click="openRoom(group)">Buka Room</button>
              </template>
              <button v-else class="join-btn" @click="handleJoin(group)">Gabung</button>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">
          <p>Belum ada grup ditemukan.</p>
        </div>
      </article>

      <!-- MY GROUPS TAB -->
      <article v-if="currentTab === 'my'" class="panel glass-card">
        <div class="toolbar">
          <div>
            <p class="eyebrow">Manajemen</p>
            <h2>Grup Saya</h2>
          </div>
        </div>

        <!-- Section: Grup Saya Buat -->
        <div class="group-section">
          <h3 class="section-title">Grup yang Saya Buat</h3>
          <div class="group-list" v-if="myCreatedGroups.length">
            <div v-for="group in myCreatedGroups" :key="group.id" class="group-card-item">
              <div class="group-info">
                <strong>{{ group.title }}</strong>
                <p class="group-topic">{{ group.topic }}</p>
                <div class="group-meta">
                  <span class="meta-tag">{{ group.course?.name }}</span>
                  <span class="meta-tag">{{ group.location?.name }}</span>
                  <span class="meta-tag schedule-tag">📅 {{ group.schedule }}</span>
                </div>
              </div>
              <div class="card-actions">
                <button class="edit-btn" @click="startEdit(group)">Edit</button>
                <button class="delete-btn" @click="handleDelete(group.id)">Hapus</button>
                <button class="open-btn" @click="openRoom(group)">Buka Room</button>
              </div>
            </div>
          </div>
          <div v-else class="empty-state-mini">
            <p>Belum ada grup yang Anda buat.</p>
          </div>
        </div>

        <!-- Section: Grup yang Saya Ikuti -->
        <div class="group-section" style="margin-top: 32px;">
          <h3 class="section-title">Grup yang Saya Ikuti</h3>
          <div class="group-list" v-if="myJoinedGroups.length">
            <div v-for="group in myJoinedGroups" :key="group.id" class="group-card-item">
              <div class="group-info">
                <strong>{{ group.title }}</strong>
                <p class="group-topic">{{ group.topic }}</p>
                <div class="group-meta">
                  <span class="meta-tag">{{ group.course?.name }}</span>
                  <span class="meta-tag">{{ group.location?.name }}</span>
                  <span class="meta-tag schedule-tag">📅 {{ group.schedule }}</span>
                </div>
              </div>
              <div class="card-actions">
                <button class="leave-btn" @click="handleLeave(group.id)">Keluar Grup</button>
                <button class="open-btn" @click="openRoom(group)">Buka Room</button>
              </div>
            </div>
          </div>
          <div v-else class="empty-state-mini">
            <p>Belum ada grup yang Anda ikuti.</p>
          </div>
        </div>
      </article>
    </div>

    <!-- Modal Chat Group (Pop-up) -->
    <div class="modal-overlay" v-if="showChatModal" @click.self="showChatModal = false">
      <div class="modal-content glass-card chat-modal">
        <header class="modal-header">
          <div>
            <p class="eyebrow">Room Chat Grup</p>
            <h2>{{ currentChatGroup?.title }}</h2>
          </div>
          <button class="close-modal-btn" @click="showChatModal = false">✕</button>
        </header>

        <div class="modal-body scrollbar-custom">
          <div class="detail-grid">
            <!-- Left: Group Info & AI Insight -->
            <div class="insight-section">
              <div class="group-detail-info glass-card" style="padding: 16px; margin-bottom: 20px; background: rgba(255,255,255,0.02);">
                <div class="detail-meta-grid">
                  <div class="meta-item">
                    <span class="label">Topik</span>
                    <span class="val">{{ currentChatGroup?.topic }}</span>
                  </div>
                  <div class="meta-item">
                    <span class="label">Mata Kuliah</span>
                    <span class="val">{{ currentChatGroup?.course?.name }}</span>
                  </div>
                  <div class="meta-item">
                    <span class="label">Lokasi</span>
                    <span class="val">{{ currentChatGroup?.location?.name }}</span>
                  </div>
                  <div class="meta-item">
                    <span class="label">Kapasitas</span>
                    <span class="val">{{ currentChatGroup?.members?.length }} / {{ currentChatGroup?.capacity }}</span>
                  </div>
                </div>
                <div class="detail-desc" style="margin-top: 12px; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                  {{ currentChatGroup?.description }}
                </div>
              </div>

              <div class="insight-header">
                <div>
                  <p class="eyebrow">AI Group Insight</p>
                  <h3>Golden Hour & Compatibility</h3>
                </div>
                <button
                  class="refresh-summary-btn"
                  :class="{ refreshing: refreshingSummary }"
                  @click="handleRefreshSummary"
                  :disabled="refreshingSummary"
                  title="Refresh rangkuman chat"
                >
                  {{ refreshingSummary ? '⏳' : '🔄' }}
                </button>
              </div>
              
              <div class="insight-stack">
                <div v-if="loadingSummary" class="insight-loading">
                  <div class="spinner-mini"></div>
                  <p>Menganalisis diskusi...</p>
                </div>
                
                <template v-else>
                  <div class="insight-card-item" v-if="goldenHour">
                    <div class="insight-icon">🕒</div>
                    <div class="insight-content">
                      <strong>{{ goldenHour.headline }}</strong>
                      <p>Slot terbaik: <span>{{ goldenHour.bestSlot || 'Belum ada' }}</span> · Cakupan {{ goldenHour.coverage || 0 }}%</p>
                    </div>
                  </div>
                  
                  <div class="insight-card-item" v-if="compatibility">
                    <div class="insight-icon">✨</div>
                    <div class="insight-content">
                      <strong>Smart Match Score: <span class="score-text">{{ compatibility.score }}</span></strong>
                      <p>{{ compatibility.narrative }}</p>
                    </div>
                  </div>

                  <div class="insight-card-item" v-if="summary">
                    <div class="insight-icon">📝</div>
                    <div class="insight-content">
                      <strong>{{ summary.headline }}</strong>
                      <p>{{ summary.summary }}</p>
                      <div class="chip-row">
                        <span v-for="keyword in summary.keywords" :key="keyword" class="chip">{{ keyword }}</span>
                      </div>
                    </div>
                  </div>

                  <div v-if="!summary && !compatibility && !goldenHour" class="empty-insight">
                    <p>Belum ada insight AI tersedia.</p>
                  </div>
                </template>
              </div>
            </div>

            <!-- Chat Section -->
            <div class="chat-section">
              <p class="eyebrow">Chat grup</p>
              <div class="chat-container-modal">
                <div v-if="loadingChat" class="chat-loading">
                  <div class="spinner-mini"></div>
                  <p>Memuat percakapan...</p>
                </div>
                <div v-else class="chat-box scrollbar-custom">
                  <div class="chat-spacer" style="margin-top: auto;"></div>
                  <div v-if="chatMessages.length === 0" class="empty-chat">
                    <p>Belum ada pesan. Sapa teman belajarmu!</p>
                  </div>
                  <div v-for="message in chatMessages" :key="message.id" class="message-item" :class="{ 'own-message': message.user?.id === state.user?.id }">
                    <div class="message-row">
                      <div v-if="message.user?.id !== state.user?.id" class="message-avatar" :style="{ background: message.user?.avatarColor || '#6366f1' }">
                        <img v-if="message.user?.avatarUrl || message.user?.avatar_url" :src="message.user?.avatarUrl || message.user?.avatar_url" alt="Avatar" />
                        <span v-else>{{ message.user?.name?.charAt(0).toUpperCase() }}</span>
                      </div>
                      <div class="message-bubble">
                        <strong v-if="message.user?.id !== state.user?.id">{{ message.user?.name || 'User' }}</strong>
                        <p>{{ message.message }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="chat-compose">
                  <input v-model="chatDraft" placeholder="Ketik pesan…" @keyup.enter="handleSend" class="chat-input" :disabled="loadingChat" />
                  <button class="send-btn" @click="handleSend" :disabled="loadingChat">Kirim</button>
                </div>
              </div>

              <!-- Member List Section -->
              <div class="member-list-mini">
                <p class="eyebrow">Anggota Grup</p>
                <div class="member-grid-mini">
                  <div 
                    v-for="member in currentChatGroup?.members" 
                    :key="member.id" 
                    class="member-avatar-mini"
                    :style="{ background: member.avatarColor || member.avatar_color || '#6366f1' }"
                    :title="member.name"
                  >
                    <img v-if="member.avatarUrl || member.avatar_url" :src="member.avatarUrl || member.avatar_url" alt="Avatar" class="member-img-mini" />
                    <span v-else>{{ member.name?.charAt(0).toUpperCase() }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.groups-shell { display: grid; gap: 20px; }
.panel { padding: 24px; position: relative; }
h1, h2 { color: white; margin: 0; }
.eyebrow { font-size: 11px; font-weight: 600; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 8px; }

/* Tab Navigation */
.tab-nav {
  display: flex;
  gap: 8px;
  padding: 8px;
  margin-bottom: 8px;
}
.tab-btn {
  flex: 1;
  padding: 12px;
  border: 0;
  background: transparent;
  color: #94a3b8;
  font-weight: 700;
  cursor: pointer;
  border-radius: 12px;
  transition: all 0.2s;
}
.tab-btn:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
}
.tab-btn.active {
  background: var(--primary);
  color: white;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.form-grid-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }
.full-span { grid-column: span 2; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 12px; color: #94a3b8; }
.hint { font-size: 10px; color: #64748b; }
.error-hint { color: #f87171; font-weight: 600; }
.submit-btn { padding: 14px 28px; border-radius: 12px; flex: 1; }
.cancel-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--line); color: #94a3b8; padding: 14px 28px; border-radius: 12px; cursor: pointer; }
.form-actions { display: flex; gap: 12px; margin-top: 24px; }

/* Schedule UX Modern */
.schedule-ux-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: rgba(15, 23, 42, 0.4);
  padding: 16px;
  border-radius: 16px;
  border: 1px solid var(--line);
}
.day-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.day-chip {
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--line);
  color: #94a3b8;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.day-chip:hover { border-color: var(--primary); color: white; }
.day-chip.selected { background: var(--primary); color: white; border-color: var(--primary); }

.time-picker-custom {
  display: flex;
  align-items: center;
  gap: 16px;
}
.time-input-modern {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--line);
  color: white;
  padding: 10px 16px;
  border-radius: 12px;
  outline: none;
  font-size: 16px;
  font-weight: 700;
}
.quick-times {
  display: flex;
  gap: 6px;
}
.time-chip {
  padding: 6px 10px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--line);
  color: #64748b;
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
}
.time-chip:hover { color: white; border-color: #818cf8; }

.toolbar { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; gap: 16px; }
.search-box { display: flex; gap: 8px; flex: 1; max-width: 400px; }
.search-input { flex: 1; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--line); color: white; padding: 10px 16px; border-radius: 12px; outline: none; }
.small-btn { padding: 10px 20px; border-radius: 12px; }

.group-list { display: grid; gap: 12px; }
.group-card-item { background: rgba(255, 255, 255, 0.03); border: 1px solid var(--line); padding: 16px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; }
.group-card-item:hover { transform: translateY(-2px); border-color: rgba(99, 102, 241, 0.3); }
.group-info strong { font-size: 16px; color: white; display: block; margin-bottom: 4px; }
.group-topic { font-size: 13px; color: #94a3b8; margin: 0 0 8px; }
.group-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.meta-tag { font-size: 10px; font-weight: 700; color: #64748b; background: rgba(255, 255, 255, 0.05); padding: 4px 10px; border-radius: 6px; }
.schedule-tag { color: #818cf8; background: rgba(99, 102, 241, 0.1); }

.card-actions { display: flex; align-items: center; gap: 8px; }
.seat-badge { font-size: 11px; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 6px 12px; border-radius: 8px; }
.join-btn, .open-btn, .edit-btn, .delete-btn, .leave-btn { padding: 8px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; border: 0; transition: all 0.2s; font-size: 12px; }
.join-btn { background: rgba(255, 255, 255, 0.05); color: white; border: 1px solid var(--line); }
.open-btn { background: var(--primary); color: white; }
.edit-btn { background: rgba(99, 102, 241, 0.1); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); }
.delete-btn { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.delete-btn:hover { background: #ef4444; color: white; }
.leave-btn { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.leave-btn:hover { background: #f59e0b; color: white; }

.section-title { font-size: 16px; font-weight: 700; color: white; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.section-title::before { content: ''; display: block; width: 4px; height: 16px; background: var(--primary); border-radius: 2px; }

.joined-badge { font-size: 11px; font-weight: 700; color: #94a3b8; background: rgba(255, 255, 255, 0.05); padding: 6px 12px; border-radius: 8px; border: 1px solid var(--line); }

.empty-state-mini { padding: 32px; text-align: center; background: rgba(255, 255, 255, 0.01); border: 1px dashed var(--line); border-radius: 16px; color: #64748b; font-size: 13px; }

.empty-state { text-align: center; padding: 60px; color: #64748b; }

/* Modal Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(3, 7, 18, 0.85);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-content {
  width: 100%;
  max-width: 1100px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--line);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.modal-header {
  padding: 24px;
  border-bottom: 1px solid var(--line);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.close-modal-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--line);
  color: #94a3b8;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.close-modal-btn:hover { background: #ef4444; color: white; border-color: #ef4444; }

.modal-loading {
  padding: 100px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  color: #94a3b8;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.chat-container-modal {
  display: flex;
  flex-direction: column;
  height: 500px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--line);
  border-radius: 20px;
  margin-top: 12px;
  overflow: hidden;
}

.scrollbar-custom::-webkit-scrollbar { width: 6px; }
.scrollbar-custom::-webkit-scrollbar-track { background: transparent; }
.scrollbar-custom::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
.scrollbar-custom::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

.detail-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.meta-item { display: flex; flex-direction: column; gap: 2px; }
.meta-item .label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.meta-item .val { font-size: 13px; font-weight: 600; color: white; }

.member-list-mini { margin-top: 20px; }
.member-grid-mini { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.member-avatar-mini { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: white; border: 2px solid rgba(255,255,255,0.1); overflow: hidden; }
.member-img-mini { width: 100%; height: 100%; object-fit: cover; }
.insight-card-item { display: flex; gap: 16px; padding: 16px; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--line); border-radius: 16px; }
.insight-icon { font-size: 20px; }
.insight-content strong { display: block; color: white; margin-bottom: 4px; font-size: 14px; }
.insight-content p { font-size: 13px; color: #94a3b8; line-height: 1.5; margin: 0; }
.score-text { color: #818cf8; font-weight: 800; }
.chip-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.chip { font-size: 11px; font-weight: 700; color: #cbd5e1; background: rgba(255, 255, 255, 0.05); padding: 4px 10px; border-radius: 6px; }

.ai-source-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.15);
  padding: 3px 8px;
  border-radius: 6px;
  margin-top: 6px;
}

.action-items { margin-top: 10px; }
.action-label { font-size: 12px; font-weight: 700; color: #818cf8; margin: 0 0 6px; }
.action-items ul { margin: 0; padding-left: 16px; }
.action-items li { font-size: 12px; color: #94a3b8; margin-bottom: 3px; }

.chat-section { display: flex; flex-direction: column; height: 100%; }
.chat-container { display: flex; flex-direction: column; height: 450px; background: rgba(0, 0, 0, 0.2); border: 1px solid var(--line); border-radius: 20px; margin-top: 20px; overflow: hidden; }
.chat-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.message-item { display: flex; flex-direction: column; }
.message-row { display: flex; gap: 10px; align-items: flex-end; }
.message-avatar { width: 28px; height: 28px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: white; flex-shrink: 0; }
.message-avatar img { width: 100%; height: 100%; object-fit: cover; }
.message-bubble { width: fit-content; max-width: 75%; padding: 10px 16px; border-radius: 16px; background: rgba(255, 255, 255, 0.05); position: relative; }
.message-bubble strong { display: block; font-size: 11px; color: #818cf8; margin-bottom: 4px; }
.message-bubble p { font-size: 14px; color: #cbd5e1; margin: 0; line-height: 1.5; overflow-wrap: break-word; word-break: normal; white-space: pre-wrap; }
.own-message { align-items: flex-end; }
.own-message .message-row { justify-content: flex-end; width: 100%; }
.own-message .message-bubble { background: #312e81; border-bottom-right-radius: 4px; }

.chat-compose { padding: 16px; display: flex; gap: 8px; background: rgba(15, 23, 42, 0.4); border-top: 1px solid var(--line); }
.chat-input { flex: 1; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--line); color: white; padding: 10px 16px; border-radius: 12px; outline: none; font-size: 14px; }
.send-btn { background: var(--primary); color: white; border: 0; padding: 0 20px; border-radius: 12px; font-weight: 600; cursor: pointer; }

.loading-overlay { position: absolute; inset: 0; background: rgba(3, 7, 18, 0.8); z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; border-radius: 20px; backdrop-filter: blur(4px); }
.spinner { width: 40px; height: 40px; border: 4px solid rgba(99, 102, 241, 0.1); border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }
.spinner-mini { width: 20px; height: 20px; border: 2px solid rgba(99, 102, 241, 0.1); border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }

.insight-loading, .chat-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px;
  color: #64748b;
  font-size: 13px;
}

.empty-insight, .empty-chat {
  padding: 40px;
  text-align: center;
  color: #475569;
  font-size: 13px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.muted-text { color: #475569; font-size: 14px; text-align: center; padding: 40px; }

@media (max-width: 1000px) {
  .grid-layout, .detail-grid { grid-template-columns: 1fr; }
}
</style>