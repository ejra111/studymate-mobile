<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppShell from '../components/AppShell.vue'
import SectionCard from '../components/SectionCard.vue'
import {
  addCourse,
  addLocation,
  addProgram,
  deleteCourse,
  deleteLocation,
  deleteProgram,
  loadAdminSummary,
  pushToast,
  state,
  updateManagedUser
} from '../store/appStore'

const tab = ref('programs')
const summary = computed(() => state.adminSummary)

const programForm = reactive({ name: '', faculty: '' })
const courseForm = reactive({ code: '', name: '', programId: '' })
const locationForm = reactive({ name: '', address: '', mapHint: '' })

onMounted(async () => {
  try {
    await loadAdminSummary()
  } catch (error) {
    pushToast(error.message, 'error')
  }
})

async function submitProgram() {
  try {
    await addProgram(programForm)
    programForm.name = ''
    programForm.faculty = ''
  } catch (error) {
    pushToast(error.message, 'error')
  }
}

async function submitCourse() {
  try {
    await addCourse(courseForm)
    courseForm.code = ''
    courseForm.name = ''
    courseForm.programId = ''
  } catch (error) {
    pushToast(error.message, 'error')
  }
}

async function submitLocation() {
  try {
    await addLocation(locationForm)
    locationForm.name = ''
    locationForm.address = ''
    locationForm.mapHint = ''
  } catch (error) {
    pushToast(error.message, 'error')
  }
}

async function toggleRole(user) {
  try {
    await updateManagedUser(user.id, {
      role: user.role === 'admin' ? 'student' : 'admin'
    })
  } catch (error) {
    pushToast(error.message, 'error')
  }
}
</script>

<template>
  <AppShell>
    <header class="page-header">
      <div>
        <p class="eyebrow">Admin console</p>
        <h1>Kelola data utama dan aktivitas platform</h1>
        <p>Panel ini mencakup master data, user management, dan monitoring aktivitas grup belajar.</p>
      </div>
    </header>

    <div class="tabs-row">
      <button :class="{ active: tab === 'programs' }" @click="tab = 'programs'">Master data</button>
      <button :class="{ active: tab === 'users' }" @click="tab = 'users'">User management</button>
      <button :class="{ active: tab === 'activities' }" @click="tab = 'activities'">Aktivitas</button>
    </div>

    <div v-if="tab === 'programs'" class="stack-lg">
      <div class="grid-three">
        <SectionCard title="Tambah program studi" subtitle="Kelola data dasar prodi">
          <form class="form-grid" @submit.prevent="submitProgram">
            <div>
              <label>Nama program</label>
              <input v-model="programForm.name" type="text" />
            </div>
            <div>
              <label>Fakultas</label>
              <input v-model="programForm.faculty" type="text" />
            </div>
            <button class="primary-btn">Tambah program</button>
          </form>

          <div class="list-stack">
            <article v-for="program in summary.programs" :key="program.id" class="compact-card">
              <strong>{{ program.name }}</strong>
              <p>{{ program.faculty }}</p>
              <button class="ghost-btn" @click="deleteProgram(program.id)">Hapus</button>
            </article>
          </div>
        </SectionCard>

        <SectionCard title="Tambah mata kuliah" subtitle="Data ini dipakai untuk profil dan grup">
          <form class="form-grid" @submit.prevent="submitCourse">
            <div>
              <label>Kode</label>
              <input v-model="courseForm.code" type="text" />
            </div>
            <div>
              <label>Nama mata kuliah</label>
              <input v-model="courseForm.name" type="text" />
            </div>
            <div>
              <label>Program studi</label>
              <select v-model="courseForm.programId">
                <option disabled value="">Pilih program</option>
                <option v-for="program in summary.programs" :key="program.id" :value="program.id">
                  {{ program.name }}
                </option>
              </select>
            </div>
            <button class="primary-btn">Tambah mata kuliah</button>
          </form>

          <div class="list-stack">
            <article v-for="course in summary.courses" :key="course.id" class="compact-card">
              <strong>{{ course.code }} · {{ course.name }}</strong>
              <button class="ghost-btn" @click="deleteCourse(course.id)">Hapus</button>
            </article>
          </div>
        </SectionCard>

        <SectionCard title="Tambah lokasi belajar" subtitle="Mendukung fitur nearby study spots">
          <form class="form-grid" @submit.prevent="submitLocation">
            <div>
              <label>Nama lokasi</label>
              <input v-model="locationForm.name" type="text" />
            </div>
            <div>
              <label>Alamat</label>
              <input v-model="locationForm.address" type="text" />
            </div>
            <div>
              <label>Catatan lokasi</label>
              <input v-model="locationForm.mapHint" type="text" />
            </div>
            <button class="primary-btn">Tambah lokasi</button>
          </form>

          <div class="list-stack">
            <article v-for="location in summary.locations" :key="location.id" class="compact-card">
              <strong>{{ location.name }}</strong>
              <p>{{ location.address }}</p>
              <button class="ghost-btn" @click="deleteLocation(location.id)">Hapus</button>
            </article>
          </div>
        </SectionCard>
      </div>
    </div>

    <SectionCard v-else-if="tab === 'users'" title="Data pengguna" subtitle="Ubah peran atau pantau profil mahasiswa">
      <div class="cards-grid">
        <article v-for="user in summary.users" :key="user.id" class="glass-card compact-card">
          <div class="compact-top">
            <div class="avatar" :style="{ background: user.avatarColor }">{{ user.name.charAt(0) }}</div>
            <div>
              <strong>{{ user.name }}</strong>
              <p>{{ user.email }}</p>
            </div>
          </div>
          <div class="meta-chips">
            <span>{{ user.role }}</span>
            <span>{{ user.program?.name }}</span>
            <span>{{ user.studentId }}</span>
          </div>
          <button class="secondary-btn" @click="toggleRole(user)">
            Ubah ke {{ user.role === 'admin' ? 'student' : 'admin' }}
          </button>
        </article>
      </div>
    </SectionCard>

    <SectionCard v-else title="Monitoring aktivitas" subtitle="Pantau perubahan penting pada grup dan user">
      <div class="timeline">
        <article v-for="activity in summary.activities" :key="activity.id" class="timeline-item">
          <div class="timeline-dot"></div>
          <div>
            <strong>{{ activity.message }}</strong>
            <p class="small-muted">{{ new Date(activity.createdAt).toLocaleString('id-ID') }}</p>
          </div>
        </article>
      </div>
    </SectionCard>
  </AppShell>
</template>
