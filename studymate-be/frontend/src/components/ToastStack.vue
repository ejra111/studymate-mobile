<script setup>
import { state } from '../store/appStore'
</script>

<template>
  <div class="toast-stack">
    <div
      v-for="toast in state.toasts"
      :key="toast.id"
      class="toast-item"
      :class="toast.tone"
    >
      {{ toast.message }}
    </div>
  </div>
</template>
