<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { FALLBACK_COURSES, FALLBACK_PROGRAMS, INTEREST_CATALOG } from '../data/academicCatalog'
import { getScheduleHistory, loadUser, pushToast, state, updateProfile, uploadAvatarFile, uploadKtmFile } from '../store/appStore'

const form = reactive({
  name: '',
  email: '',
  university: '',
  programId: '',
  programName: '',
  semester: '',
  bio: '',
  interestsText: '',
  interestCodes: [],
  interestSearch: '',
  courseCodesText: '',
  courseIds: [],
  courseSearch: '',
  availability: [],
  avatarColor: '#4f46e5',
})

const scheduleInputError = ref('')
const scheduleDraft = reactive({
  courseId: '',
  day: 'SENIN',
  time: '19:00',
  durationMinutes: 90,
})
const uploading = ref(false)
const uploadingKtm = ref(false)
const avatarLocalPreview = ref(null)
const boot = computed(() => state.boot)
const scheduleHistory = ref([])
const faceApiLoaded = ref(false)
const faceApiModelsLoaded = ref(false)

// Verification computed properties
const verificationStatus = computed(() => state.user?.verificationStatus || 'unverified')
const verificationProgress = computed(() => {
  if (verificationStatus.value === 'unverified') return 0
  if (verificationStatus.value === 'half_verified') return 50
  return 100
})
const verificationStatusLabel = computed(() => {
  if (verificationStatus.value === 'unverified') return 'Belum Terverifikasi'
  if (verificationStatus.value === 'half_verified') return 'Terverifikasi Sebagian'
  return 'Terverifikasi Sepenuhnya'
})
const verificationStatusColor = computed(() => {
  if (verificationStatus.value === 'unverified') return '#ef4444'
  if (verificationStatus.value === 'half_verified') return '#f59e0b'
  return '#10b981'
})

// Load face-api.js from CDN
async function loadFaceApi() {
  if (faceApiLoaded.value) return
  try {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js'
    script.onload = async () => {
      faceApiLoaded.value = true
      await loadFaceApiModels()
    }
    document.head.appendChild(script)
  } catch (err) {
    console.error('Failed to load face-api.js:', err)
  }
}

async function loadFaceApiModels() {
  if (faceApiModelsLoaded.value) return
  try {
    const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model'
    await window.faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL)
    faceApiModelsLoaded.value = true
  } catch (err) {
    console.error('Failed to load face-api models:', err)
  }
}

// Face detection function
async function detectFaces(imageFile) {
  if (!faceApiLoaded.value || !faceApiModelsLoaded.value) {
    throw new Error('Face detection models are still loading. Please try again.')
  }

  return new Promise((resolve, reject) => {
    const img = document.createElement('img')
    const url = URL.createObjectURL(imageFile)
    
    img.onload = async () => {
      try {
        const detections = await window.faceapi.detectAllFaces(img, new window.faceapi.TinyFaceDetectorOptions())
        URL.revokeObjectURL(url)
        resolve(detections.length)
      } catch (err) {
        URL.revokeObjectURL(url)
        reject(err)
      }
    }
    
    img.onerror = (err) => {
      URL.revokeObjectURL(url)
      reject(err)
    }
    
    img.src = url
  })
}

const VALID_DAYS = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU']
const dayOptions = VALID_DAYS
const timeOptions = Array.from({ length: 17 }, (_, index) => `${String(index + 6).padStart(2, '0')}:00`)
const durationOptions = [60, 90, 120, 150]
const DAY_ALIASES = {
  SEN: 'SENIN', SEL: 'SELASA', RAB: 'RABU', KAM: 'KAMIS',
  JUM: 'JUMAT', "JUM'AT": 'JUMAT', SAB: 'SABTU', MIN: 'MINGGU', MING: 'MINGGU',
  MONDAY: 'SENIN', TUESDAY: 'SELASA', WEDNESDAY: 'RABU',
  THURSDAY: 'KAMIS', FRIDAY: 'JUMAT', SATURDAY: 'SABTU', SUNDAY: 'MINGGU',
}

function normalizeText(value) {
  return String(value || '').trim().toUpperCase()
}

function uniqueById(items) {
  const seen = new Set()
  return items.filter((item) => {
    if (!item?.id || seen.has(item.id)) return false
    seen.add(item.id)
    return true
  })
}

function uniqueByCourseIdentity(items) {
  const seen = new Set()
  return items.filter((course) => {
    const key = normalizeText(course.code) + "|" + normalizeText(course.name)
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

const programOptions = computed(() => {
  const merged = uniqueById([...(boot.value?.programs || []), ...FALLBACK_PROGRAMS])
  return merged.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
})

function programNameById(programId) {
  return programOptions.value.find((program) => program.id === programId)?.name || ''
}

function programFacultyById(programId) {
  return programOptions.value.find((program) => program.id === programId)?.faculty || ''
}

const courseOptions = computed(() => {
  const bootCourses = (boot.value?.courses || []).map((course) => ({
    ...course,
    programId: course.programId || course.program_id,
  }))

  return uniqueByCourseIdentity(uniqueById([...bootCourses, ...FALLBACK_COURSES]))
    .map((course) => ({
      ...course,
      code: normalizeText(course.code),
      name: String(course.name || '').trim(),
      programId: course.programId || course.program_id || '',
      programName: course.program?.name || programNameById(course.programId || course.program_id),
      faculty: course.program?.faculty || programFacultyById(course.programId || course.program_id),
    }))
    .sort((a, b) => `${a.code} ${a.name}`.localeCompare(`${b.code} ${b.name}`))
})

const selectedCourses = computed(() => {
  const selected = new Set(form.courseIds)
  return courseOptions.value.filter((course) => selected.has(course.id))
})

const filteredCourseOptions = computed(() => {
  const query = normalizeText(form.courseSearch)
  const selected = new Set(form.courseIds)

  return courseOptions.value
    .filter((course) => !selected.has(course.id))
    .filter((course) => {
      const haystack = normalizeText([course.code, course.name, course.programName, course.faculty].join(' '))
      if (!query) return !form.programId || course.programId === form.programId
      return haystack.includes(query)
    })
    .slice(0, 120)
})

const selectedInterestOptions = computed(() => {
  const selected = new Set(form.interestCodes)
  return INTEREST_CATALOG.filter((interest) => selected.has(interest.code))
})

const manualInterestCodes = computed(() => {
  const catalogCodes = new Set(INTEREST_CATALOG.map((interest) => interest.code))
  return form.interestCodes.filter((code) => !catalogCodes.has(code))
})

const filteredInterestOptions = computed(() => {
  const query = normalizeText(form.interestSearch)
  const selected = new Set(form.interestCodes)

  return INTEREST_CATALOG
    .filter((interest) => !selected.has(interest.code))
    .filter((interest) => {
      const haystack = normalizeText(`${interest.code} ${interest.label}`)
      return !query || haystack.includes(query)
    })
})

const selectedProgramPayload = computed(() => {
  const selected = programOptions.value.find((program) => program.id === form.programId)
  return selected ? { id: selected.id, name: selected.name, faculty: selected.faculty } : null
})

const avatarPreviewSrc = computed(() => {
  if (avatarLocalPreview.value) return avatarLocalPreview.value
  return state.user?.avatarUrl || state.user?.avatar_url || null
})

function formatCourseLabel(course) {
  return course?.name || 'Mata kuliah'
}

function syncProgramNameFromId() {
  const selected = selectedProgramPayload.value
  if (selected) form.programName = normalizeText(selected.name)
}

function addCourse(course) {
  if (!course?.id || form.courseIds.includes(course.id)) return
  form.courseIds = [...form.courseIds, course.id]
  form.courseSearch = ''
  normalizeCourseCodes()
}

function removeCourse(courseId) {
  form.courseIds = form.courseIds.filter((id) => id !== courseId)
  normalizeCourseCodes()
}

function addInterest(interest) {
  if (!interest?.code || form.interestCodes.includes(interest.code)) return
  form.interestCodes = [...form.interestCodes, interest.code]
  form.interestSearch = ''
  syncInterestsText()
}

function removeInterest(code) {
  form.interestCodes = form.interestCodes.filter((item) => item !== code)
  syncInterestsText()
}

function addManualInterest() {
  const raw = normalizeText(form.interestSearch).replace(/[^A-Z0-9\- ]/g, ' ').replace(/\s+/g, ' ').trim()
  if (!raw || form.interestCodes.includes(raw)) return
  form.interestCodes = [...form.interestCodes, raw]
  form.interestSearch = ''
  syncInterestsText()
}

function syncInterestsText() {
  form.interestsText = form.interestCodes.join(', ')
}

function normalizeInterests() {
  const tokens = String(form.interestsText || '')
    .split(',')
    .map((item) => normalizeText(item))
    .filter(Boolean)
  form.interestCodes = Array.from(new Set(tokens))
  syncInterestsText()
}

function normalizeCourseCodes() {
  form.courseCodesText = selectedCourses.value.map((course) => course.code).join(', ').toUpperCase()
}

function normalizeScheduleSlot(raw) {
  let text = String(raw || '').trim().toUpperCase()
  text = text.replace(/\bJAM\b/g, '').replace(/\bPUKUL\b/g, '').trim()

  text = text.replace(/(\d{1,2})\s*(PAGI)/g, (_, h) => `${String(parseInt(h)).padStart(2, '0')}:00`)
  text = text.replace(/(\d{1,2})\s*(SIANG)/g, (_, h) => `${String(parseInt(h) < 10 ? parseInt(h) + 12 : parseInt(h)).padStart(2, '0')}:00`)
  text = text.replace(/(\d{1,2})\s*(SORE|MALAM)/g, (_, h) => `${String(Math.min(parseInt(h) < 12 ? parseInt(h) + 12 : parseInt(h), 23)).padStart(2, '0')}:00`)
  text = text.replace(/\b(\d{1,2})\b(?!:|\d)/g, (_, h) => `${String(parseInt(h)).padStart(2, '0')}:00`)
  text = text.replace(/\s+/g, ' ').trim()

  const parts = text.split(/\s+/)
  let day = null
  let time = null

  for (const part of parts) {
    if (VALID_DAYS.includes(part)) day = part
    else if (DAY_ALIASES[part]) day = DAY_ALIASES[part]
    else if (/^\d{1,2}:\d{2}$/.test(part)) time = part
  }

  if (day && time) {
    const [hh, mm] = time.split(':').map(Number)
    if (hh >= 0 && hh <= 23 && mm >= 0 && mm <= 59) {
      return `${day} ${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
    }
  }

  return null
}

function scheduleCourseById(courseId) {
  return courseOptions.value.find((course) => course.id === courseId) || null
}

function makeScheduleSlotKey(slot) {
  if (typeof slot === 'string') return slot
  return [slot?.courseId || 'COURSE', slot?.day || 'DAY', slot?.time || 'TIME', slot?.durationMinutes || 90].join('|')
}

function normalizeAvailabilitySlot(slot, index = 0) {
  if (!slot) return null

  if (typeof slot === 'string') {
    const parsed = normalizeScheduleSlot(slot)
    if (!parsed) return null
    const [day, time] = parsed.split(' ')
    const course = selectedCourses.value[index % Math.max(selectedCourses.value.length, 1)] || null
    return {
      id: `${course?.id || 'general'}-${day}-${time}-90`,
      courseId: course?.id || '',
      courseCode: course?.code || '',
      courseName: course?.name || 'Belum terkait mata kuliah',
      day,
      time,
      durationMinutes: 90,
      label: `${course?.code ? `${course.code} — ${course.name} · ` : ''}${day} ${time}`,
    }
  }

  const day = normalizeText(slot.day)
  const time = String(slot.time || '').trim()
  const durationMinutes = Number(slot.durationMinutes || slot.duration || 90)
  if (!VALID_DAYS.includes(day) || !/^\d{2}:\d{2}$/.test(time)) return null

  const course = scheduleCourseById(slot.courseId) || null
  const courseCode = normalizeText(slot.courseCode || course?.code)
  const courseName = String(slot.courseName || course?.name || 'Mata kuliah belum dipilih').trim()

  return {
    id: slot.id || `${slot.courseId || 'general'}-${day}-${time}-${durationMinutes}`,
    courseId: slot.courseId || '',
    courseCode,
    courseName,
    day,
    time,
    durationMinutes,
    label: `${courseCode ? `${courseCode} — ${courseName} · ` : ''}${day} ${time}`,
  }
}

const normalizedAvailabilitySlots = computed(() => {
  return form.availability
    .map((slot, index) => normalizeAvailabilitySlot(slot, index))
    .filter(Boolean)
})

function syncAvailabilityNormalized() {
  form.availability = normalizedAvailabilitySlots.value
}

function addScheduleSlot() {
  scheduleInputError.value = ''

  if (!selectedCourses.value.length) {
    scheduleInputError.value = 'Pilih mata kuliah aktif dulu di bagian Akademik & Minat.'
    return
  }

  if (!scheduleDraft.courseId) {
    scheduleInputError.value = 'Pilih mata kuliah untuk slot belajar ini.'
    return
  }

  const course = scheduleCourseById(scheduleDraft.courseId)
  if (!course) {
    scheduleInputError.value = 'Mata kuliah tidak valid. Pilih dari daftar mata kuliah aktif.'
    return
  }

  const slot = normalizeAvailabilitySlot({
    courseId: course.id,
    courseCode: course.code,
    courseName: course.name,
    day: scheduleDraft.day,
    time: scheduleDraft.time,
    durationMinutes: scheduleDraft.durationMinutes,
  })

  if (!slot) {
    scheduleInputError.value = 'Slot waktu tidak valid. Pilih hari, jam, dan durasi dari opsi yang tersedia.'
    return
  }

  const slotKey = makeScheduleSlotKey(slot)
  const exists = normalizedAvailabilitySlots.value.some((item) => makeScheduleSlotKey(item) === slotKey)
  if (exists) {
    scheduleInputError.value = 'Slot untuk mata kuliah dan waktu ini sudah ditambahkan.'
    return
  }

  form.availability = [...normalizedAvailabilitySlots.value, slot]
  scheduleInputError.value = ''
}

function removeSlot(slot) {
  const slotKey = makeScheduleSlotKey(slot)
  form.availability = normalizedAvailabilitySlots.value.filter((item) => makeScheduleSlotKey(item) !== slotKey)
}

function loadScheduleHistoryData() {
  scheduleHistory.value = getScheduleHistory()
}

function hydrateForm() {
  if (!state.user) return

  form.name = state.user.name || ''
  form.email = state.user.email || ''
  form.university = state.user.university || ''
  form.programId = state.user.programId || state.user.program_id || state.user.program?.id || ''
  form.programName = state.user.programName || state.user.program_name || state.user.program?.name || ''
  form.semester = state.user.semester || ''
  form.bio = state.user.bio || ''

  const courseIdsFromUser = [
    ...(state.user.courseIds || []),
    ...((state.user.courses || []).map((course) => course.id).filter(Boolean)),
  ]
  form.courseIds = Array.from(new Set(courseIdsFromUser))
  normalizeCourseCodes()

  const existingInterests = (state.user.interests || []).map((item) => normalizeText(item)).filter(Boolean)
  form.interestCodes = Array.from(new Set(existingInterests))
  syncInterestsText()

  form.availability = [...(state.user.availability || [])]
  syncAvailabilityNormalized()
  form.avatarColor = state.user.avatarColor || state.user.avatar_color || '#4f46e5'
}

watch(() => state.user, () => hydrateForm(), { deep: true })

onMounted(async () => {
  loadFaceApi()
  try {
    await loadUser()
    hydrateForm()
    loadScheduleHistoryData()
  } catch (error) {
    pushToast(error.message, 'error')
  }
})

function upperField(field) {
  form[field] = String(form[field] || '').toUpperCase().replace(/[^A-Z0-9 ]/g, ' ')
}

function formatHistoryEntry(entry) {
  const slots = entry?.slots || []
  const preview = slots.slice(0, 2).map((slot) => slot.label || slot).join(', ')
  return `${preview}${slots.length > 2 ? ` +${slots.length - 2}` : ''}`
}

function applyHistoryEntry(entry) {
  form.availability = [...(entry.slots || [])]
  syncAvailabilityNormalized()
  pushToast('Jadwal dari riwayat berhasil dimuat.', 'info')
}

async function onAvatarSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return

  const localUrl = URL.createObjectURL(file)

  try {
    uploading.value = true
    avatarLocalPreview.value = localUrl
    
    // Face detection
    const numFaces = await detectFaces(file)
    if (numFaces === 0) {
      throw new Error('Tidak ada wajah terdeteksi. Silakan gunakan foto yang jelas dengan wajah Anda.')
    }
    if (numFaces > 1) {
      throw new Error('Terlalu banyak wajah terdeteksi. Silakan gunakan foto hanya dengan wajah Anda.')
    }

    await uploadAvatarFile(file)
    hydrateForm()
  } catch (error) {
    pushToast(error.message, 'error')
  } finally {
    uploading.value = false
    avatarLocalPreview.value = null
    event.target.value = ''
    URL.revokeObjectURL(localUrl)
  }
}

async function onKtmSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return

  try {
    uploadingKtm.value = true
    await uploadKtmFile(file)
    hydrateForm()
  } catch (error) {
    pushToast(error.message, 'error')
  } finally {
    uploadingKtm.value = false
    event.target.value = ''
  }
}

async function submitProfile() {
  try {
    syncProgramNameFromId()
    normalizeCourseCodes()

    const selectedCoursePayloads = selectedCourses.value.map((course) => ({
      id: course.id,
      code: course.code,
      name: course.name,
      programId: course.programId,
      programName: course.programName || programNameById(course.programId),
      faculty: course.faculty || programFacultyById(course.programId),
    }))

    await updateProfile({
      name: form.name,
      email: form.email,
      university: form.university.toUpperCase(),
      programId: form.programId || null,
      programName: form.programName.toUpperCase(),
      programPayload: selectedProgramPayload.value,
      semester: Number(form.semester),
      bio: form.bio,
      interests: [...form.interestCodes],
      courseIds: [...form.courseIds],
      selectedCoursePayloads,
      availability: [...normalizedAvailabilitySlots.value],
      avatarColor: form.avatarColor,
    })

    await loadUser()
    hydrateForm()
    loadScheduleHistoryData()
  } catch (error) {
    pushToast(error.message, 'error')
  }
}
</script>
<template>
  <section class="profile-shell">
    <header class="profile-header">
      <div>
        <p class="eyebrow-text">PROFIL AKADEMIK</p>
        <h1 class="greeting">Kelola Profil</h1>
        <p class="subtitle-text">
          Lengkapi data dirimu untuk mendapatkan rekomendasi partner belajar yang lebih akurat.
        </p>
      </div>
    </header>

    <!-- Verification Center -->
    <article class="panel glass-card verification-center">
      <div class="section-head">
        <h3>Verification Center</h3>
        <p>Lengkapi verifikasi akun untuk meningkatkan kepercayaan dan rekomendasi yang lebih baik.</p>
      </div>

      <div class="verification-status">
        <div class="status-badge" :style="{ backgroundColor: verificationStatusColor }">
          {{ verificationStatusLabel }}
        </div>
        <div class="progress-container">
          <div class="progress-bar">
            <div 
              class="progress-fill" 
              :style="{ width: verificationProgress + '%', backgroundColor: verificationStatusColor }"
            ></div>
          </div>
          <span class="progress-text">{{ verificationProgress }}%</span>
        </div>
      </div>

      <div class="verification-actions">
        <!-- Upload Profile Photo -->
        <div class="verification-step" :class="{ completed: verificationStatus !== 'unverified' }">
          <div class="step-icon">
            {{ verificationStatus !== 'unverified' ? '✓' : '1' }}
          </div>
          <div class="step-content">
            <h4>Upload Foto Profil</h4>
            <p class="step-desc">Foto profil harus berisi tepat satu wajah yang jelas.</p>
            <label class="step-btn" :class="{ disabled: verificationStatus === 'fully_verified' }">
              <input 
                type="file" 
                accept="image/*" 
                @change="onAvatarSelected" 
                :disabled="uploading || verificationStatus === 'fully_verified'"
                hidden 
              />
              {{ uploading ? 'Mengunggah...' : 'Pilih Foto' }}
            </label>
          </div>
        </div>

        <!-- Upload KTM -->
        <div class="verification-step" :class="{ completed: verificationStatus === 'fully_verified' }">
          <div class="step-icon">
            {{ verificationStatus === 'fully_verified' ? '✓' : '2' }}
          </div>
          <div class="step-content">
            <h4>Upload KTM</h4>
            <p class="step-desc">Upload Kartu Tanda Mahasiswa untuk verifikasi penuh.</p>
            <label class="step-btn">
              <input 
                type="file" 
                accept="image/*" 
                @change="onKtmSelected" 
                :disabled="uploadingKtm"
                hidden 
              />
              {{ uploadingKtm ? 'Memverifikasi...' : 'Pilih KTM' }}
            </label>
          </div>
        </div>
      </div>
    </article>

    <div class="profile-grid">
      <article class="panel glass-card main-info">
        <div class="section-head">
          <h3>Data Utama</h3>
          <p>Informasi dasar identitas mahasiswa Anda.</p>
        </div>

        <form class="profile-form" @submit.prevent="submitProfile">
          <div class="avatar-section">
            <div class="avatar-wrapper">
              <img v-if="avatarPreviewSrc" :src="avatarPreviewSrc" alt="Avatar" class="profile-avatar-img" />
              <div v-else class="avatar-placeholder" :style="{ backgroundColor: form.avatarColor }">
                {{ form.name?.slice(0, 1) || '?' }}
              </div>
              <label class="avatar-upload-btn">
                <input type="file" accept="image/*" @change="onAvatarSelected" hidden />
                <span class="upload-icon">📷</span>
              </label>
            </div>
            <div class="avatar-info">
              <strong>{{ form.name || 'Nama Belum Diisi' }}</strong>
              <p>{{ uploading ? 'Sedang mengunggah...' : 'Klik ikon kamera untuk ganti foto' }}</p>
            </div>
          </div>

          <div class="form-grid-inner">
            <div class="field-group">
              <label>Nama Lengkap</label>
              <input v-model="form.name" type="text" placeholder="Masukkan nama" />
            </div>
            <div class="field-group">
              <label>Email</label>
              <input v-model="form.email" type="email" placeholder="nama@kampus.ac.id" />
            </div>
            <div class="field-group">
              <label>Universitas</label>
              <input v-model="form.university" type="text" placeholder="Contoh: UNIVERSITAS INDONESIA" @input="upperField('university')" />
            </div>
            <div class="field-group">
              <label>Program Studi</label>
              <select v-model="form.programId" @change="syncProgramNameFromId">
                <option value="">Pilih program studi</option>
                <option v-for="program in programOptions" :key="program.id" :value="program.id">
                  {{ program.name }} — {{ program.faculty }}
                </option>
              </select>
              <small class="hint">Program dipakai untuk memfilter opsi mata kuliah.</small>
            </div>
            <div class="field-group">
              <label>Semester</label>
              <input v-model.number="form.semester" type="number" min="1" max="14" placeholder="1-14" />
            </div>
            <div class="field-group">
              <label>Warna Tema Avatar</label>
              <input v-model="form.avatarColor" type="color" class="color-picker" />
            </div>
            <div class="field-group full-span">
              <label>Bio Singkat</label>
              <textarea v-model="form.bio" rows="3" placeholder="Ceritakan sedikit tentang dirimu..."></textarea>
            </div>
          </div>

          <button class="primary-btn save-btn" :disabled="uploading">Simpan Perubahan</button>
        </form>
      </article>

      <div class="side-sections">
        <article class="panel glass-card academic-section">
          <div class="section-head">
            <h3>Akademik & Minat</h3>
            <p>Membantu algoritma Smart Match.</p>
          </div>
          
          <form @submit.prevent="submitProfile">
            <div class="form-grid-inner">
              <div class="field-group full-span">
                <label>Minat Akademik</label>
                <div v-if="selectedInterestOptions.length || manualInterestCodes.length" class="selected-chip-list">
                  <span v-for="interest in selectedInterestOptions" :key="interest.code" class="selected-chip">
                    <strong>{{ interest.code }}</strong> — {{ interest.label }}
                    <button type="button" class="chip-remove" @click="removeInterest(interest.code)">×</button>
                  </span>
                  <span v-for="code in manualInterestCodes" :key="code" class="selected-chip">
                    <strong>{{ code }}</strong>
                    <button type="button" class="chip-remove" @click="removeInterest(code)">×</button>
                  </span>
                </div>
                <input
                  v-model="form.interestSearch"
                  placeholder="Cari kode/minat: AI, WEB, DATA, UIUX..."
                  @keyup.enter.prevent="filteredInterestOptions[0] ? addInterest(filteredInterestOptions[0]) : addManualInterest()"
                />
                <div class="option-panel">
                  <button
                    v-for="interest in filteredInterestOptions"
                    :key="interest.code"
                    type="button"
                    class="option-row"
                    @click="addInterest(interest)"
                  >
                    <span class="option-code">{{ interest.code }}</span>
                    <span class="option-title">{{ interest.label }}</span>
                  </button>
                  <button v-if="form.interestSearch && !filteredInterestOptions.length" type="button" class="option-row" @click="addManualInterest">
                    <span class="option-code">+</span>
                    <span class="option-title">Tambah minat manual: {{ form.interestSearch.toUpperCase() }}</span>
                  </button>
                </div>
                <small class="hint">Minat disimpan sebagai kode agar Smart Match lebih konsisten.</small>
              </div>

              <div class="field-group full-span">
                <label>Mata Kuliah Aktif</label>
                <div v-if="selectedCourses.length" class="selected-chip-list">
                  <span v-for="course in selectedCourses" :key="course.id" class="selected-chip course-chip">
                    <strong>{{ course.name }}</strong>
                    <em v-if="course.programName">{{ course.programName }}</em>
                    <button type="button" class="chip-remove" @click="removeCourse(course.id)">×</button>
                  </span>
                </div>
                <p v-else class="empty-select-text">Belum ada mata kuliah dipilih.</p>

                <input
                  v-model="form.courseSearch"
                  placeholder="Cari nama mata kuliah: Kecerdasan Buatan, Basis Data, Manajemen Proses Bisnis..."
                  @keyup.enter.prevent="filteredCourseOptions[0] && addCourse(filteredCourseOptions[0])"
                />
                <div class="option-panel course-panel">
                  <button
                    v-for="course in filteredCourseOptions"
                    :key="course.id"
                    type="button"
                    class="option-row course-option"
                    @click="addCourse(course)"
                  >
                    <span class="option-title course-title-only">{{ course.name }}</span>
                    <small>{{ course.programName || 'Program umum' }}</small>
                  </button>
                  <p v-if="!filteredCourseOptions.length" class="empty-option-text">Tidak ada opsi. Coba kata kunci lain atau pilih program studi berbeda.</p>
                </div>
                <small class="hint">Opsi bisa discroll. Pilih berdasarkan nama mata kuliah; kode tetap disimpan otomatis untuk sistem.</small>
              </div>
            </div>
            <button class="primary-btn save-btn">Simpan Akademik & Minat</button>
          </form>
        </article>

      </div>
    </div>
  </section>
</template>

<style scoped>
.profile-shell { display: grid; gap: 32px; }
.profile-header { margin-bottom: 8px; }
.eyebrow-text { font-size: 11px; font-weight: 600; color: #64748b; letter-spacing: 0.1em; margin-bottom: 8px; }
.greeting { font-size: 42px; font-weight: 800; margin-bottom: 12px; color: white; }
.subtitle-text { color: #94a3b8; font-size: 16px; }

/* Verification Center Styles */
.verification-center { margin-bottom: 24px; }
.verification-status { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
.status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 8px 20px; border-radius: 999px; color: white; font-weight: 700; font-size: 14px; width: fit-content; }
.progress-container { display: flex; align-items: center; gap: 16px; }
.progress-bar { flex: 1; height: 12px; background: rgba(255, 255, 255, 0.1); border-radius: 999px; overflow: hidden; }
.progress-fill { height: 100%; transition: width 0.5s ease; }
.progress-text { font-size: 18px; font-weight: 700; color: white; min-width: 40px; text-align: right; }

.verification-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 24px; }
.verification-step { display: flex; gap: 16px; padding: 20px; background: rgba(255, 255, 255, 0.02); border-radius: 16px; border: 1px solid var(--line); }
.verification-step.completed { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05); }
.step-icon { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 18px; color: white; background: rgba(255, 255, 255, 0.1); flex-shrink: 0; }
.verification-step.completed .step-icon { background: #10b981; }
.step-content { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.step-content h4 { margin: 0; font-size: 16px; font-weight: 700; color: white; }
.step-desc { margin: 0; font-size: 13px; color: #94a3b8; }
.step-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 12px; color: #a5b4fc; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; width: fit-content; }
.step-btn:hover:not(.disabled) { background: rgba(99, 102, 241, 0.35); transform: translateY(-1px); }
.step-btn.disabled { opacity: 0.5; cursor: not-allowed; }
.step-btn input { cursor: pointer; }

.profile-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; }
.panel { padding: 32px; }
.section-head { margin-bottom: 24px; }
.section-head h3 { font-size: 20px; font-weight: 700; margin-bottom: 4px; color: white; }
.section-head p { font-size: 14px; color: #64748b; }

.avatar-section { display: flex; align-items: center; gap: 20px; margin-bottom: 32px; padding: 20px; background: rgba(255, 255, 255, 0.02); border-radius: 20px; border: 1px solid var(--line); }
.avatar-wrapper { position: relative; width: 80px; height: 80px; }
.profile-avatar-img, .avatar-placeholder { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid var(--line); display: grid; place-items: center; font-size: 32px; font-weight: 800; color: white; }
.avatar-upload-btn { position: absolute; bottom: 0; right: 0; background: var(--primary); width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; cursor: pointer; border: 2px solid var(--bg); transition: transform 0.2s; }
.avatar-upload-btn:hover { transform: scale(1.1); }
.avatar-info strong { display: block; font-size: 18px; color: white; margin-bottom: 4px; }
.avatar-info p { font-size: 13px; color: #64748b; }

.form-grid-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.full-span { grid-column: span 2; }
.field-group { display: flex; flex-direction: column; gap: 8px; }
.field-group label { font-size: 13px; font-weight: 600; color: #94a3b8; }
.hint { font-size: 11px; color: #475569; margin-top: 4px; }

.color-picker { height: 46px; padding: 4px; cursor: pointer; }
.save-btn { margin-top: 32px; width: 100%; padding: 14px; font-size: 16px; }

.side-sections { display: grid; gap: 24px; }
.planner-note-section { border: 1px solid rgba(99, 102, 241, 0.18); }
.planner-note-text { color: #94a3b8; font-size: 14px; line-height: 1.6; }

/* Schedule Input Styles */
.schedule-input-area { margin-bottom: 20px; }
.schedule-input-area label { display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 8px; }
.schedule-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.structured-schedule select {
  width: 100%;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--line);
  color: white;
  padding: 12px 14px;
  border-radius: 12px;
  outline: none;
  font-size: 13px;
}
.structured-schedule option { background: #0f172a; color: white; }
.schedule-warning {
  border: 1px solid rgba(248, 113, 113, 0.35);
  background: rgba(127, 29, 29, 0.18);
  color: #fca5a5;
  border-radius: 14px;
  padding: 14px;
  font-size: 13px;
  line-height: 1.5;
}
.add-slot-btn {
  padding: 12px 16px;
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.3);
  color: #a5b4fc;
  border-radius: 12px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.add-slot-btn:hover { background: rgba(99, 102, 241, 0.25); }
.schedule-error { font-size: 11px; color: #f87171; font-weight: 600; display: block; margin-top: 8px; }

.active-slots { margin-top: 16px; }
.slots-label { font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 10px; }
.slot-list { display: grid; gap: 10px; }
.slot-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  background: rgba(99, 102, 241, 0.12);
  border: 1px solid rgba(99, 102, 241, 0.28);
  border-radius: 14px;
}
.slot-card strong { color: #e2e8f0; font-size: 13px; }
.slot-card p { color: #94a3b8; font-size: 12px; margin-top: 4px; }
.chip-remove {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 16px;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  transition: color 0.2s;
}
.chip-remove:hover { color: #f87171; }
.no-slots-text { font-size: 13px; color: #475569; margin-top: 12px; }

@media (max-width: 1200px) {
  .profile-grid { grid-template-columns: 1fr; }
}

.history-section { margin-bottom: 16px; }
.history-label { font-size: 13px; font-weight: 600; color: #818cf8; margin-bottom: 10px; }
.history-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.history-chip {
  padding: 8px 14px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(99, 102, 241, 0.1);
  border: 1px solid rgba(99, 102, 241, 0.25);
  color: #a5b4fc;
  cursor: pointer;
  transition: all 0.2s;
}
.history-chip:hover {
  background: rgba(99, 102, 241, 0.25);
  color: white;
  transform: translateY(-1px);
}
.selected-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.selected-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.16);
  border: 1px solid rgba(129, 140, 248, 0.3);
  color: #c7d2fe;
  font-size: 12px;
  line-height: 1.25;
}

.selected-chip strong {
  color: white;
  letter-spacing: 0.03em;
}

.course-chip {
  border-radius: 14px;
  align-items: flex-start;
  flex-direction: column;
  gap: 2px;
  padding-right: 34px;
  position: relative;
}

.course-chip .chip-remove {
  position: absolute;
  top: 8px;
  right: 10px;
}

.course-chip em {
  color: #64748b;
  font-style: normal;
  font-size: 11px;
}

.option-panel {
  margin-top: 10px;
  max-height: 230px;
  overflow-y: auto;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: rgba(15, 23, 42, 0.5);
  padding: 8px;
  display: grid;
  gap: 8px;
}

.course-panel {
  max-height: 320px;
}

.option-row {
  width: 100%;
  display: grid;
  grid-template-columns: 92px 1fr;
  align-items: start;
  gap: 10px;
  text-align: left;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
  color: white;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, transform 0.2s;
}

.option-row:hover {
  background: rgba(99, 102, 241, 0.14);
  border-color: rgba(129, 140, 248, 0.45);
  transform: translateY(-1px);
}

.option-code {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  min-width: 68px;
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(14, 165, 233, 0.16);
  color: #7dd3fc;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.option-title {
  font-size: 13px;
  font-weight: 700;
  color: #e2e8f0;
}

.course-option {
  grid-template-columns: 86px 1fr;
}

.course-option small {
  grid-column: 2;
  color: #64748b;
  font-size: 11px;
}

.empty-select-text,
.empty-option-text {
  font-size: 12px;
  color: #64748b;
  padding: 8px 0;
}

</style>
