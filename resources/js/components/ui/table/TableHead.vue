<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "cn"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <th
    data-slot="table-head"
    :class="cn('h-11 bg-muted/40 px-4 text-left align-middle text-xs font-semibold tracking-wide text-muted-foreground uppercase whitespace-nowrap first:rounded-tl-xl last:rounded-tr-xl [&:has([role=checkbox])]:pr-0 *:[[role=checkbox]]:translate-y-0.5', props.class)"
  >
    <slot />
  </th>
</template>
