<script setup>
    defineProps({
      label: { type: String, required: true },
      value: { type: [String, Number], required: true },
      hint: { type: String, default: "" },
    });
    </script>
    
    <template>
      <div class="bg-white border border-neutral-200 rounded-2xl p-4">
        <div class="text-[11px] text-neutral-500">{{ label }}</div>
        <div class="mt-1 text-xl font-semibold">{{ value }}</div>
        <div v-if="hint" class="mt-1 text-[11px] text-neutral-500">{{ hint }}</div>
      </div>
    </template>
    