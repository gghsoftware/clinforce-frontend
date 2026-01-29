<!-- resources/js/Components/UiCard.vue -->
<script setup>
  defineProps({
    title: { type: String, default: "" },
    subtitle: { type: String, default: "" },
  });
  </script>
  
  <template>
    <section class="card">
      <div v-if="title || subtitle" class="head">
        <div v-if="title" class="title">{{ title }}</div>
        <div v-if="subtitle" class="sub">{{ subtitle }}</div>
      </div>
      <slot />
    </section>
  </template>
  
  <style scoped>
  .card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 16px;
  }
  
  @media (min-width: 640px) {
    .card {
      padding: 18px;
    }
  }
  
  .head {
    margin-bottom: 12px;
  }
  
  .title {
    font-size: 14px;
    font-weight: 900;
    color: #111827;
  }
  
  .sub {
    margin-top: 4px;
    font-size: 12px;
    color: #6b7280;
  }
  </style>
  