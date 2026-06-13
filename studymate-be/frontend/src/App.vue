<script setup>
import { computed, onMounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppShell from './components/AppShell.vue'
import ToastStack from './components/ToastStack.vue'
import AiCoach from './components/AiCoach.vue'
import { bootstrapApp, state } from './store/appStore'

const route = useRoute()
const isAuthPage = computed(() => {
  // Selalu tampilkan shell jika bukan di halaman landing atau login
  return route.path === '/' || route.path === '/login'
})

const showShell = computed(() => {
  // Jika ada user dan bukan di halaman auth, tampilkan shell
  return !!state.user?.id && !isAuthPage.value
})

onMounted(() => {
  bootstrapApp().catch(() => {})
})
</script>

<template>
  <AppShell v-if="showShell">
    <RouterView />
    <AiCoach />
  </AppShell>
  <RouterView v-else />
  <ToastStack />
</template>
