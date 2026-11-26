<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "cn"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('hover:bg-muted/40 data-[state=selected]:bg-muted border-b transition-colors last:border-b-0', props.class)"
  >
    <slot />
  </tr>
</template>
