<script setup>
import { ref, nextTick, watch, computed, onMounted } from 'vue'
import { state, askCoach, loadAiHealth } from '../store/appStore'

const isOpen = ref(false)
const messageDraft = ref('')
const chatContainer = ref(null)

const isSending = ref(false)
const aiHealth = ref(null)

const coachModeLabel = computed(() => {
  if (!aiHealth.value) return 'AI Study Coach'
  return aiHealth.value.groqConfigured ? 'Groq aktif' : 'Mode lokal aktif'
})

onMounted(async () => {
  try {
    aiHealth.value = await loadAiHealth()
  } catch {
    aiHealth.value = { groqConfigured: false, fallbackEnabled: true }
  }
})


async function sendMessage() {
  if (!messageDraft.value.trim() || isSending.value) return
  const msg = messageDraft.value
  messageDraft.value = ''
  isSending.value = true
  try {
    await askCoach(msg)
    scrollToBottom()
  } catch (err) {
    state.ai.coachMessages.push({
      sender: 'AI Coach',
      message: 'Gagal menghubungi backend AI Coach. Pastikan Laravel API menyala di port 4000. 🙏',
      timestamp: new Date().toISOString(),
      isError: true
    })
    console.error(err)
  } finally {
    isSending.value = false
  }
}

async function retryLastMessage() {
  // Find last user message before an error
  const messages = state.ai.coachMessages
  let lastUserMsg = null

  for (let i = messages.length - 1; i >= 0; i--) {
    if (messages[i].sender === 'Me') {
      lastUserMsg = messages[i].message
      break
    }
  }

  if (!lastUserMsg) return

  // Remove the last error message
  const lastIdx = messages.length - 1
  if (messages[lastIdx]?.isError) {
    messages.splice(lastIdx, 1)
  }

  // Resend
  isSending.value = true
  try {
    await askCoach(lastUserMsg, { pushUserMessage: false })
    scrollToBottom()
  } catch (err) {
    state.ai.coachMessages.push({
      sender: 'AI Coach',
      message: 'Masih gagal menghubungi backend AI Coach. Cek terminal Laravel. 🙏',
      timestamp: new Date().toISOString(),
      isError: true
    })
  } finally {
    isSending.value = false
  }
}

function toggleChat() {
  isOpen.value = !isOpen.value
  if (isOpen.value) scrollToBottom()
}

async function scrollToBottom() {
  await nextTick()
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight
  }
}

watch(() => state.ai.coachMessages.length, scrollToBottom)
</script>

<template>
  <div class="coach-wrapper" v-if="state.user?.id">
    <button class="coach-trigger" @click="toggleChat">
      <span class="coach-icon">🤖</span>
      <span class="coach-label">AI Coach</span>
    </button>

    <div v-if="isOpen" class="coach-panel glass-card">
      <header class="coach-head">
        <div>
          <h3>AI Study Coach</h3>
          <p>{{ coachModeLabel }} · Tanya apa saja</p>
        </div>
        <button class="close-btn" @click="toggleChat">×</button>
      </header>

      <div class="coach-messages" ref="chatContainer">
        <div 
          v-for="(msg, idx) in state.ai.coachMessages" 
          :key="idx" 
          class="coach-msg-item"
          :class="{ 'me': msg.sender !== 'AI Coach', 'error': msg.isError }"
        >
          <div class="msg-bubble">
            <p>{{ msg.message }}</p>
            <small v-if="msg.source === 'local_fallback'" class="fallback-note">Mode lokal · isi GROQ_API_KEY untuk Groq asli</small>
          </div>
          <!-- Retry button on error messages -->
          <button
            v-if="msg.isError && idx === state.ai.coachMessages.length - 1"
            class="retry-btn"
            @click="retryLastMessage"
            :disabled="isSending"
          >
            🔄 Coba Lagi
          </button>
        </div>
        <div v-if="isSending" class="coach-msg-item">
          <div class="msg-bubble loading">
            <span class="dot">.</span><span class="dot">.</span><span class="dot">.</span>
          </div>
        </div>
      </div>

      <div class="coach-input-area">
        <input 
          v-model="messageDraft" 
          placeholder="Tanya tips belajar..." 
          @keyup.enter="sendMessage"
          :disabled="isSending"
        />
        <button @click="sendMessage" :disabled="isSending" :class="{ disabled: isSending }">➤</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.coach-wrapper {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.coach-trigger {
  background: var(--primary);
  color: white;
  border: 0;
  padding: 12px 20px;
  border-radius: 99px;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
  cursor: pointer;
  font-weight: 700;
  transition: transform 0.2s;
}

.coach-trigger:hover {
  transform: translateY(-2px);
}

.coach-icon { font-size: 20px; }

.coach-panel {
  width: 380px;
  height: 520px;
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: var(--shadow);
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.coach-head {
  padding: 20px;
  background: rgba(99, 102, 241, 0.1);
  border-bottom: 1px solid var(--line);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.coach-head h3 { font-size: 16px; margin: 0; color: white; }
.coach-head p { font-size: 11px; margin: 4px 0 0; color: #94a3b8; }

.close-btn {
  background: transparent;
  border: 0;
  color: #94a3b8;
  font-size: 24px;
  cursor: pointer;
}

.coach-messages {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.coach-msg-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.coach-msg-item.me {
  align-items: flex-end;
}

.msg-bubble {
  max-width: 85%;
  padding: 10px 14px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--line);
}

.me .msg-bubble {
  background: #312e81;
  border-color: rgba(99, 102, 241, 0.3);
}

.msg-bubble p {
  font-size: 13px;
  color: #cbd5e1;
  margin: 0;
  line-height: 1.5;
  white-space: pre-wrap;
}

.me .msg-bubble p { color: white; }

.error .msg-bubble {
  border-color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}

.error p { color: #fca5a5; }

.fallback-note {
  display: block;
  margin-top: 8px;
  color: #94a3b8;
  font-size: 10px;
  line-height: 1.4;
}

.retry-btn {
  margin-top: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #fca5a5;
  cursor: pointer;
  transition: all 0.2s;
}
.retry-btn:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.2);
  color: white;
}
.retry-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.loading {
  display: flex;
  gap: 4px;
  padding: 12px 18px;
}

.dot {
  animation: wave 1.3s infinite;
  color: #818cf8;
  font-weight: 800;
  font-size: 20px;
  line-height: 0;
}

.dot:nth-child(2) { animation-delay: -1.1s; }
.dot:nth-child(3) { animation-delay: -0.9s; }

@keyframes wave {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-4px); }
}

.coach-input-area {
  padding: 16px;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  gap: 8px;
  border-top: 1px solid var(--line);
}

.coach-input-area input {
  flex: 1;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 8px 12px;
  color: white;
  font-size: 13px;
  outline: none;
}

.coach-input-area button {
  background: var(--primary);
  color: white;
  border: 0;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  cursor: pointer;
}

.coach-input-area button.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>