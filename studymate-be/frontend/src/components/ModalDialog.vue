<script setup>
defineProps({
  open: Boolean,
  title: String
})
const emit = defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
      <div class="modal-panel glass-card">
        <div class="modal-head">
          <h3>{{ title }}</h3>
          <button class="icon-btn" @click="emit('close')">✕</button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>
