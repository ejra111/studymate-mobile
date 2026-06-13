<script setup>
defineProps({
  label: String,
  value: [String, Number],
  hint: String
})
</script>

<template>
  <article class="stat-card glass-card">
    <p class="muted-label">{{ label }}</p>
    <h3>{{ value }}</h3>
    <p class="small-muted">{{ hint }}</p>
  </article>
</template>
