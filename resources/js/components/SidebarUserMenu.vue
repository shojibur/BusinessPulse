<script setup lang="ts">
import { ChevronsUpDown, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = withDefaults(
    defineProps<{
        name: string;
        subtitle?: string | null;
        /** Icon-only rail: show just the avatar. */
        collapsed?: boolean;
    }>(),
    { subtitle: null, collapsed: false },
);

const emit = defineEmits<{ signOut: [] }>();

const initials = computed(
    () =>
        props.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '?',
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-md p-2 text-left outline-none hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                :class="collapsed && 'justify-center'"
                :aria-label="`Account menu for ${name}`"
            >
                <Avatar class="size-9">
                    <AvatarFallback
                        class="bg-primary text-xs font-semibold text-primary-foreground"
                    >
                        {{ initials }}
                    </AvatarFallback>
                </Avatar>
                <span v-if="!collapsed" class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-white">
                        {{ name }}
                    </span>
                    <span
                        v-if="subtitle"
                        class="block truncate text-xs text-sidebar-foreground"
                    >
                        {{ subtitle }}
                    </span>
                </span>
                <ChevronsUpDown
                    v-if="!collapsed"
                    class="size-4 shrink-0 text-sidebar-foreground"
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="start" class="w-60">
            <DropdownMenuLabel class="font-normal">
                <p class="truncate text-sm font-semibold">{{ name }}</p>
                <p
                    v-if="subtitle"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ subtitle }}
                </p>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @select="emit('signOut')">
                <LogOut aria-hidden="true" />
                Sign out
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
