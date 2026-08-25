<script setup>
defineProps({
  message: {
    type: String,
    default: 'Hello from a Spinx island!',
  },
})
</script>

<template>
  <div class="spinx-example-island">
    <p>{{ message }}</p>
    <p><small>Server-rendered markup, client-hydrated component.</small></p>
  </div>
</template>

<style scoped>
.spinx-example-island {
  padding: 1rem;
  margin-top: 1rem;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-family: system-ui, sans-serif;
}
</style>
