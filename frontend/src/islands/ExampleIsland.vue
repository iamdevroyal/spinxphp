<script setup>
import { ref } from 'vue'

const props = defineProps({
  message: {
    type: String,
    default: 'Reactive Client State Hydrated',
  },
  runtime: {
    type: String,
    default: 'RoadRunner',
  },
  timestamp: {
    type: String,
    default: '',
  },
})

const count = ref(0)
const increment = () => count.value++
const decrement = () => count.value--
</script>

<template>
  <div class="island-card">
    <div class="island-info">
      <div class="island-badge">
        <span class="status-dot"></span>
        <span>Vue 3 Island</span>
      </div>
      <p class="island-message">{{ message }}</p>
      <span class="island-meta">Passed Props: runtime={{ runtime }} &bull; rendered={{ timestamp || 'just now' }}</span>
    </div>

    <div class="counter-widget">
      <button @click="decrement" class="btn-counter" aria-label="Decrement counter">-</button>
      <span class="counter-val">{{ count }}</span>
      <button @click="increment" class="btn-counter" aria-label="Increment counter">+</button>
    </div>
  </div>
</template>

<style scoped>
.island-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: rgba(18, 18, 22, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
  font-family: system-ui, -apple-system, sans-serif;
  text-align: left;
}

.island-info {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.island-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.725rem;
  font-family: monospace;
  color: #4ADE80;
  font-weight: 600;
  letter-spacing: 0.03em;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #4ADE80;
  box-shadow: 0 0 8px rgba(74, 222, 128, 0.6);
}

.island-message {
  font-size: 0.95rem;
  font-weight: 600;
  color: #FFFFFF;
}

.island-meta {
  font-size: 0.75rem;
  color: #8E8E98;
  font-family: monospace;
}

.counter-widget {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.35rem 0.65rem;
  border-radius: 8px;
}

.btn-counter {
  background: rgba(225, 29, 99, 0.15);
  border: 1px solid rgba(225, 29, 99, 0.3);
  color: #FFB2BF;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 1.1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.btn-counter:hover {
  background: rgba(225, 29, 99, 0.3);
  border-color: rgba(225, 29, 99, 0.6);
  color: #FFFFFF;
}

.counter-val {
  font-family: monospace;
  font-size: 1.1rem;
  font-weight: 700;
  color: #FFFFFF;
  min-width: 28px;
  text-align: center;
}
</style>
