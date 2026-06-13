<script setup>
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import { joinGroup, loadMatches, pushToast, sendStudyInvite, state, getPrivateMessages, sendPrivateMessage, loadDashboard } from '../store/appStore'

const searchQuery = ref('')
let debounceTimer = null

// Private Chat State
const selectedFriend = ref(null)
const privateChatMessages = ref([])
const privateChatDraft = ref('')
const isChatLoading = ref(false)

onMounted(async () => {
  await Promise.all([loadMatches(), loadDashboard()])
})

watch(searchQuery, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(async () => {
    await loadMatches({ search: val })
  }, 400)
})

const isLoading = computed(() => state.loading.matches)
const partnerMatches = computed(() => state.matches?.partnerMatches || [])
const smartMeta = computed(() => state.matches?.smartMatchMeta)
const friends = computed(() => {
  const list = state.dashboard?.friends || []
  const query = searchQuery.value.toLowerCase().trim()
  if (!query) return list
  return list.filter(f => 
    f.name?.toLowerCase().includes(query) || 
    f.program?.toLowerCase().includes(query)
  )
})

// Verification status helpers
function getVerificationInfo(verificationStatus) {
  switch (verificationStatus) {
    case 'fully_verified':
      return {
        color: '#10b981',
        label: 'Fully Verified',
        icon: '✓'
      }
    case 'half_verified':
      return {
        color: '#f59e0b',
        label: 'Half Verified',
        icon: '!'
      }
    default:
      return {
        color: '#ef4444',
        label: 'Unverified',
        icon: '×'
      }
  }
}

async function handleJoin(groupId) {
  try {
    await joinGroup(groupId)
  } catch (error) {
    console.error(error)
  }
}

async function handlePartnerMatch(user) {
  try {
    await sendStudyInvite(user)
  } catch (err) {
    console.error('Failed to send study invite:', err)
  }
}

async function openPrivateChat(friend) {
  selectedFriend.value = friend
  isChatLoading.value = true
  privateChatMessages.value = []
  
  try {
    const msgs = await getPrivateMessages(friend.id)
    privateChatMessages.value = msgs
    await nextTick()
    scrollToBottom()
  } catch (err) {
    console.error('Failed to load private messages:', err)
  } finally {
    isChatLoading.value = false
  }
}

async function handleSendPrivate() {
  if (!selectedFriend.value || !privateChatDraft.value.trim()) return
  
  try {
    const msg = await sendPrivateMessage(selectedFriend.value.id, privateChatDraft.value)
    if (msg) {
      privateChatMessages.value.push(msg)
      privateChatDraft.value = ''
      await nextTick()
      scrollToBottom()
    }
  } catch (err) {
    console.error('Failed to send private message:', err)
  }
}

function scrollToBottom() {
  const box = document.querySelector('.private-chat-box')
  if (box) box.scrollTop = box.scrollHeight
}

function closeChat() {
  selectedFriend.value = null
}
</script>

<template>
  <section class="match-shell">
    <header class="match-hero glass-card">
      <div class="hero-left">
        <p class="eyebrow">Smart Match 3.0</p>
        <h1>Temukan Partner Belajar</h1>
        <p class="subtitle">
          Rekomendasi cerdas berdasarkan mata kuliah, program studi, dan semester.
        </p>

        <div class="search-box">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Cari partner, jurusan, atau universitas..." 
            class="search-input"
          />
        </div>
      </div>
      <div class="meta-card" v-if="smartMeta">
        <strong>{{ smartMeta.aiMode }}</strong>
        <small>{{ smartMeta.strategy }}</small>
        <div class="debug-info" v-if="smartMeta.debug">
          Sistem mendeteksi {{ smartMeta.debug.total_users }} calon partner.
        </div>
      </div>
    </header>

    <div v-if="isLoading" class="loading-bar">
      <div class="loading-bar-inner"></div>
    </div>

    <div class="section-grid single-panel">
      <!-- PARTNER MATCHES -->
      <article class="panel glass-card">
        <div class="panel-head">
          <p class="eyebrow">Partner Match</p>
          <h2>Top Candidates</h2>
        </div>

        <div v-if="partnerMatches.length" class="card-list">
          <div v-for="item in partnerMatches" :key="item.user.id" class="match-card">
            <div class="match-head">
              <div class="user-main-info">
                <div class="avatar-large" :style="{ backgroundColor: item.user.avatarColor || item.user.avatar_color || '#6366f1' }">
                  <img v-if="item.user.avatarUrl || item.user.avatar_url" :src="item.user.avatarUrl || item.user.avatar_url" alt="Avatar" />
                  <span v-else>{{ item.user.name?.charAt(0).toUpperCase() }}</span>
                </div>
                <div>
                  <strong class="user-name">{{ item.user.name }}</strong>
                  <p class="user-meta">{{ item.user.program_name || 'Mahasiswa' }} • Semester {{ item.user.semester }}</p>
                  <p class="user-univ">{{ item.user.university || 'Universitas' }}</p>
                  <span 
                    class="verification-badge"
                    :style="{ 
                      backgroundColor: getVerificationInfo(item.user.verificationStatus).color + '20',
                      borderColor: getVerificationInfo(item.user.verificationStatus).color,
                      color: getVerificationInfo(item.user.verificationStatus).color
                    }"
                  >
                    {{ getVerificationInfo(item.user.verificationStatus).icon }} {{ getVerificationInfo(item.user.verificationStatus).label }}
                  </span>
                </div>
              </div>
              <div class="score-badge" :class="item.confidence.toLowerCase()">
                <span class="score">{{ item.score }}%</span>
                <small>{{ item.confidence }}</small>
              </div>
            </div>
            
            <div class="match-content">
              <p class="narrative">
                <span class="quote-icon">"</span>
                {{ item.matchNarrative }}
                <span class="quote-icon">"</span>
              </p>

              <div class="academic-details">
                <p class="detail-label">Mata Kuliah Relevan:</p>
                <div class="chip-row">
                  <span v-for="course in item.sharedCourses" :key="course.id" class="chip">{{ course.name }}</span>
                  <span v-if="!item.sharedCourses.length" class="muted-text">Profil Akademik</span>
                </div>
              </div>
            </div>

            <button class="match-btn primary-btn" @click="handlePartnerMatch(item.user)">
              Kirim Undangan Belajar
            </button>
          </div>
        </div>
        <div v-else class="empty-state">
          <p>Belum ada partner yang cocok ditemukan.</p>
        </div>
      </article>
    </div>

    <!-- Private Chat Modal (Existing) -->
    <div v-if="selectedFriend" class="private-chat-overlay" @click="closeChat">
      <div class="private-chat-window glass-card" @click.stop>
        <header class="chat-header">
          <div class="friend-info">
            <div class="friend-avatar" :style="{ background: selectedFriend.avatarColor || '#6366f1' }">
              {{ selectedFriend.name?.charAt(0) }}
            </div>
            <div>
              <h3>{{ selectedFriend.name }}</h3>
              <p>{{ selectedFriend.program }}</p>
            </div>
          </div>
          <button class="close-chat" @click="closeChat">×</button>
        </header>

        <div class="private-chat-box">
          <div v-for="msg in privateChatMessages" :key="msg.id" class="msg-item" :class="{ 'own-msg': msg.sender_id === state.user.id }">
            <div class="msg-bubble">
              <p>{{ msg.message }}</p>
              <span class="msg-time">{{ new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
          </div>
        </div>

        <footer class="chat-footer">
          <input v-model="privateChatDraft" placeholder="Ketik pesan..." @keyup.enter="handleSendPrivate" class="chat-input-field" />
          <button class="send-private-btn" @click="handleSendPrivate" :disabled="!privateChatDraft.trim()">Kirim</button>
        </footer>
      </div>
    </div>
  </section>
</template>

<style scoped>
.match-shell { display: grid; gap: 20px; padding: 20px; }
.match-hero { display: flex; justify-content: space-between; gap: 24px; padding: 32px; align-items: flex-start; }
.hero-left { flex: 1; display: grid; gap: 12px; }
h1 { font-size: 2rem; color: white; margin: 0; }
.subtitle { color: #94a3b8; font-size: 1.1rem; }
.search-box { margin-top: 16px; max-width: 500px; }
.search-input { width: 100%; padding: 14px 20px; border: 1px solid var(--line); border-radius: 14px; background: rgba(15, 23, 42, 0.8); color: white; outline: none; transition: border-color 0.3s; }
.search-input:focus { border-color: var(--primary); }

.meta-card { max-width: 300px; padding: 20px; border-radius: 16px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); }
.meta-card strong { color: #818cf8; display: block; margin-bottom: 4px; }
.meta-card small { color: #94a3b8; font-size: 0.85rem; line-height: 1.4; }
.debug-info { margin-top: 12px; font-family: monospace; font-size: 10px; color: #64748b; }

.section-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.single-panel { grid-template-columns: 1fr; max-width: 800px; margin: 0 auto; }
.panel { padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.panel-head h2 { margin: 0; font-size: 1.8rem; color: white; }
.eyebrow { font-size: 12px; text-transform: uppercase; color: #818cf8; font-weight: 800; letter-spacing: 2px; margin-bottom: 6px; }

.card-list { display: grid; gap: 24px; }
.match-card { background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 24px; padding: 32px; display: grid; gap: 24px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
.match-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, transparent 100%); opacity: 0; transition: opacity 0.3s; }
.match-card:hover { transform: translateY(-8px); border-color: rgba(99, 102, 241, 0.3); box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5); }
.match-card:hover::before { opacity: 1; }

.match-head { display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1; }
.user-main-info { display: flex; gap: 20px; align-items: center; }
.avatar-large { width: 72px; height: 72px; border-radius: 22px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: white; border: 2px solid rgba(255, 255, 255, 0.1); overflow: hidden; flex-shrink: 0; }
.avatar-large img { width: 100%; height: 100%; object-fit: cover; }

.user-name { font-size: 1.4rem; color: white; display: block; margin-bottom: 4px; }
.user-meta { font-size: 0.95rem; color: #cbd5e1; margin: 0; font-weight: 500; }
.user-univ { font-size: 0.85rem; color: #64748b; margin: 2px 0 0; text-transform: uppercase; letter-spacing: 0.5px; }
.verification-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid;
  width: fit-content;
}

.score-badge { text-align: center; padding: 12px 16px; border-radius: 20px; background: rgba(255,255,255,0.03); min-width: 85px; border: 1px solid rgba(255, 255, 255, 0.05); }
.score-badge.tinggi { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); }
.score-badge.sedang { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2); }
.score { display: block; font-size: 1.6rem; font-weight: 900; line-height: 1; margin-bottom: 4px; }
.score-badge small { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }

.match-content { position: relative; z-index: 1; display: grid; gap: 20px; }
.narrative { color: #e2e8f0; font-size: 1.1rem; line-height: 1.6; margin: 0; font-style: italic; background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 16px; border-left: 4px solid var(--primary); }
.quote-icon { font-size: 1.5rem; color: var(--primary); opacity: 0.4; font-family: serif; }

.academic-details { display: grid; gap: 10px; }
.detail-label { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
.chip-row { display: flex; flex-wrap: wrap; gap: 10px; }
.chip { padding: 8px 16px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); color: #a5b4fc; font-size: 13px; font-weight: 600; border: 1px solid rgba(99, 102, 241, 0.2); }
.muted-text { font-size: 0.9rem; color: #475569; font-style: italic; }

.match-btn { width: 100%; padding: 16px; border-radius: 16px; font-size: 1.05rem; font-weight: 800; cursor: pointer; transition: all 0.3s; position: relative; z-index: 1; }
.primary-btn { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }
.primary-btn:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4); }

.empty-state { text-align: center; padding: 40px; color: #475569; }
.loading-bar { width: 100%; height: 3px; background: rgba(99, 102, 241, 0.1); margin-bottom: 20px; border-radius: 2px; overflow: hidden; }
.loading-bar-inner { width: 30%; height: 100%; background: var(--primary); animation: slide 1.5s infinite linear; }
@keyframes slide { from { transform: translateX(-100%); } to { transform: translateX(400%); } }

@media (max-width: 1024px) { .section-grid { grid-template-columns: 1fr; } .match-hero { flex-direction: column; } }

/* Private Chat UI (Existing Styles) */
.private-chat-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.private-chat-window { width: 100%; max-width: 500px; height: 600px; display: flex; flex-direction: column; }
.chat-header { padding: 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; }
.friend-info { display: flex; gap: 12px; align-items: center; }
.friend-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.2rem; }
.private-chat-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; background: rgba(0,0,0,0.2); }
.msg-item { max-width: 80%; }
.msg-bubble { padding: 12px 16px; border-radius: 18px; background: rgba(255,255,255,0.05); position: relative; }
.msg-bubble p { margin: 0; color: #e2e8f0; line-height: 1.5; }
.msg-time { font-size: 10px; color: #64748b; margin-top: 4px; display: block; text-align: right; }
.own-msg { align-self: flex-end; }
.own-msg .msg-bubble { background: var(--primary); border-bottom-right-radius: 4px; }
.chat-footer { padding: 20px; border-top: 1px solid var(--line); display: flex; gap: 12px; }
.chat-input-field { flex: 1; background: rgba(255,255,255,0.05); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; color: white; outline: none; }
.send-private-btn { background: var(--primary); color: white; border: none; padding: 0 24px; border-radius: 12px; font-weight: 700; cursor: pointer; }
.close-chat { background: none; border: none; color: #64748b; font-size: 2rem; cursor: pointer; line-height: 1; }
</style>
