<script setup>
import { computed, reactive, ref, nextTick } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { login, register, state, pushToast } from '../store/appStore'

const router = useRouter()
const activeTab = ref('login')
const loading = ref(false)
const showLoginPassword = ref(false)
const showRegisterPassword = ref(false)
const registerSuccess = ref('')
const authCardRef = ref(null)

const loginForm = reactive({
  email: '',
  password: ''
})

const registerForm = reactive({
  name: '',
  email: '',
  university: '',
  programName: '',
  semester: '',
  password: '',
  password_confirmation: '',
  studentId: ''
})

const loginReady = computed(() => loginForm.email.trim() && loginForm.password.trim())
const registerReady = computed(() => {
  return registerForm.name.trim() && 
         registerForm.email.trim() && 
         registerForm.university.trim() && 
         registerForm.programName.trim() && 
         registerForm.semester && 
         registerForm.studentId.trim() &&
         registerForm.password.trim() && 
         registerForm.password === registerForm.password_confirmation
})

function handleAlphanumericCaps(field) {
  registerForm[field] = registerForm[field].toUpperCase().replace(/[^A-Z0-9 ]/g, '')
}

const passwordStrength = computed(() => {
  const value = registerForm.password || ''
  let score = 0
  if (value.length >= 6) score += 35
  if (/[A-Z]/.test(value) || /[a-z]/.test(value)) score += 20
  if (/\d/.test(value)) score += 20
  if (/[^A-Za-z0-9]/.test(value)) score += 25
  return Math.min(score, 100)
})

const passwordStrengthLabel = computed(() => {
  if (passwordStrength.value >= 80) return 'Kuat'
  if (passwordStrength.value >= 55) return 'Sedang'
  if (passwordStrength.value > 0) return 'Lemah'
  return 'Belum diisi'
})

const selectedProgramName = computed(() => {
  return state.boot.programs.find((program) => program.id === registerForm.programId)?.name || 'Belum dipilih'
})

async function submitLogin() {
  try {
    loading.value = true
    registerSuccess.value = ''
    await login(loginForm)
    router.push('/dashboard')
  } catch (error) {
    pushToast(error.message, 'error')
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  try {
    loading.value = true
    const payload = await register(registerForm)
    registerSuccess.value = payload.message || 'Registrasi berhasil. Silakan login.'
    loginForm.email = registerForm.email
    loginForm.password = registerForm.password
    registerForm.name = ''
    registerForm.email = ''
    registerForm.password = ''
    registerForm.studentId = ''
    registerForm.programId = ''
    activeTab.value = 'login'
    router.replace('/login')
  } catch (error) {
    pushToast(error.message, 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-layout auth-layout-modern centered-auth">
    <RouterLink to="/" class="back-to-landing">
      <span class="icon-back">←</span>
      <span>Kembali ke Beranda</span>
    </RouterLink>
    <section ref="authCardRef" class="auth-card glass-card auth-card-modern">
      <div class="auth-card-head">
        <div>
          <p class="eyebrow">Akses akun</p>
          <h2>Masuk ke StudyMate</h2>
        </div>
        <div class="auth-tabs">
          <button :class="{ active: activeTab === 'login' }" @click="activeTab = 'login'">Login</button>
          <button :class="{ active: activeTab === 'register' }" @click="activeTab = 'register'">Register</button>
        </div>
      </div>

      <div v-if="registerSuccess" class="success-banner">
        <strong>Registrasi berhasil</strong>
        <p>{{ registerSuccess }}</p>
      </div>

      <form v-if="activeTab === 'login'" class="form-grid" @submit.prevent="submitLogin">
        <div class="full-span form-panel">
          <label>Email</label>
          <input v-model="loginForm.email" type="email" placeholder="nama@email.com" autocomplete="email" />
        </div>

        <div class="full-span form-panel password-panel">
          <label>Password</label>
          <div class="input-with-action">
            <input
              v-model="loginForm.password"
              :type="showLoginPassword ? 'text' : 'password'"
              placeholder="Masukkan password"
              autocomplete="current-password"
            />
            <button class="field-action" type="button" @click="showLoginPassword = !showLoginPassword">
              {{ showLoginPassword ? 'Sembunyikan' : 'Lihat' }}
            </button>
          </div>
        </div>

        <div class="login-helper-row full-span">
        </div>

        <button class="primary-btn full-width" :disabled="loading || !loginReady">
          {{ loading ? 'Memproses...' : 'Login ke dashboard' }}
        </button>

        
      </form>

      <form v-else class="form-grid" @submit.prevent="submitRegister">
        <div class="full-span form-panel">
          <label>Nama lengkap</label>
          <input v-model="registerForm.name" type="text" placeholder="Nama lengkap" autocomplete="name" />
        </div>

        <div class="full-span form-panel">
          <label>Email</label>
          <input v-model="registerForm.email" type="email" placeholder="nama@email.com" autocomplete="email" />
        </div>

        <div class="full-span form-panel">
          <label>Universitas</label>
          <input 
            v-model="registerForm.university" 
            type="text" 
            placeholder="CONTOH: UNIVERSITAS INDONESIA" 
            @input="handleAlphanumericCaps('university')"
          />
        </div>

        <div class="full-span form-panel">
          <label>Program studi</label>
          <input 
            v-model="registerForm.programName" 
            type="text" 
            placeholder="CONTOH: INFORMATIKA" 
            @input="handleAlphanumericCaps('programName')"
          />
        </div>

        <div class="full-span form-panel">
          <label>Semester</label>
          <select v-model="registerForm.semester">
            <option disabled value="">Pilih semester</option>
            <option v-for="s in 14" :key="s" :value="s">Semester {{ s }}</option>
          </select>
        </div>

        <div class="full-span form-panel">
          <label>NIM (Nomor Induk Mahasiswa)</label>
          <input 
            v-model="registerForm.studentId" 
            type="text" 
            placeholder="Masukkan NIM" 
            autocomplete="off"
          />
        </div>

        <div class="full-span form-panel">
          <label>Password</label>
          <input
            v-model="registerForm.password"
            :type="showRegisterPassword ? 'text' : 'password'"
            placeholder="Minimal 6 karakter"
            autocomplete="new-password"
          />
        </div>

        <div class="full-span form-panel">
          <label>Konfirmasi Password</label>
          <input
            v-model="registerForm.password_confirmation"
            type="password"
            placeholder="Ulangi password"
          />
        </div>

        <div class="full-span">
          <div class="strength-row">
            <div class="progress-bar compact-progress">
              <div class="progress-fill" :style="{ width: `${passwordStrength}%` }"></div>
            </div>
            <span>Keamanan: {{ passwordStrengthLabel }}</span>
          </div>
          <p v-if="registerForm.password && registerForm.password_confirmation && registerForm.password !== registerForm.password_confirmation" class="error-text">
            Password tidak cocok.
          </p>
        </div>

        <button class="primary-btn full-width" :disabled="loading || !registerReady">
          {{ loading ? 'Memproses...' : 'Daftar Sekarang' }}
        </button>
      </form>
    </section>
  </div>
</template>
