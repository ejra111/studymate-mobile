<script setup>
defineProps({
  title: String,
  subtitle: String
})
</script>

<template>
  <section class="section-card glass-card">
    <div class="section-head">
      <div>
        <h2>{{ title }}</h2>
        <p>{{ subtitle }}</p>
      </div>
      <slot name="action" />
    </div>
    <slot />
  </section>
</template>
