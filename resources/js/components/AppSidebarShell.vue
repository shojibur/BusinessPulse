<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, PanelLeftClose, PanelLeftOpen } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Component } from 'vue';
import ShellNav from '@/components/ShellNav.vue';
import SidebarUserMenu from '@/components/SidebarUserMenu.vue';
import ToastHost from '@/components/ToastHost.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useSidebar } from '@/composables/useSidebar';
import type { NavItem } from '@/types';

/**
 * The application shell shared by the company app and the platform panel: a dark sidebar (collapsible to an icon
 * rail on desktop, a slide-out menu on phones), the signed-in user at the bottom left, and a header with the toggle,
 * a breadcrumb and a slot for header actions.
 */
defineProps<{
    brandHref: string;
    brandName: string;
    brandSubtitle?: string;
    brandIcon: Component;
    navigation: NavItem[];
    breadcrumbRoot: string;
    pageTitle: string;
    userName?: string | null;
    userSubtitle?: string | null;
}>();

const emit = defineEmits<{ signOut: [] }>();

const page = usePage();
const { collapsed, toggle, onKeydown } = useSidebar();

const mobileNavOpen = ref(false);

// Close the off-canvas navigation after every visit.
watch(
    () => page.url,
    () => {
        mobileNavOpen.value = false;
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-surface px-4 py-2 text-sm font-medium text-primary-text focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:ring-2 focus:ring-ring focus:outline-none"
        >
            Skip to main content
        </a>

        <div class="min-h-svh bg-muted/30">
            <!-- Desktop sidebar (769px and up): 248px, or a 64px icon rail when collapsed. Hidden on phones. -->
            <aside
                class="hidden bg-sidebar text-sidebar-foreground transition-[width] duration-200 ease-out min-[769px]:fixed min-[769px]:inset-y-0 min-[769px]:left-0 min-[769px]:z-40 min-[769px]:flex min-[769px]:flex-col"
                :class="
                    collapsed ? 'min-[769px]:w-16' : 'min-[769px]:w-[248px]'
                "
            >
                <Link
                    :href="brandHref"
                    class="flex h-14 shrink-0 items-center gap-3 text-white outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                    :class="collapsed ? 'justify-center px-0' : 'px-4'"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <component
                            :is="brandIcon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="min-w-0 leading-tight"
                        :class="collapsed && 'sr-only'"
                    >
                        <span class="block truncate text-base font-semibold">
                            {{ brandName }}
                        </span>
                        <span
                            v-if="brandSubtitle"
                            class="block truncate text-xs text-sidebar-foreground"
                        >
                            {{ brandSubtitle }}
                        </span>
                    </span>
                </Link>
                <nav aria-label="Primary" class="flex-1 overflow-y-auto p-2">
                    <ShellNav :items="navigation" :collapsed="collapsed" />
                </nav>
                <div
                    v-if="userName"
                    class="shrink-0 border-t border-sidebar-border p-2"
                >
                    <SidebarUserMenu
                        :name="userName"
                        :subtitle="userSubtitle"
                        :collapsed="collapsed"
                        @sign-out="emit('signOut')"
                    />
                </div>
            </aside>

            <div
                class="transition-[padding] duration-200 ease-out"
                :class="
                    collapsed ? 'min-[769px]:pl-16' : 'min-[769px]:pl-[248px]'
                "
            >
                <header
                    class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-surface px-4 min-[769px]:px-6"
                >
                    <!-- Phones: slide-out navigation. -->
                    <Sheet v-model:open="mobileNavOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="min-[769px]:hidden"
                                aria-label="Open navigation menu"
                            >
                                <Menu aria-hidden="true" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="flex w-72 flex-col border-sidebar-border bg-sidebar p-0 text-sidebar-foreground"
                        >
                            <SheetHeader class="h-14 justify-center px-4">
                                <SheetTitle class="text-white">
                                    {{ brandName }}
                                </SheetTitle>
                                <SheetDescription class="sr-only">
                                    Primary navigation
                                </SheetDescription>
                            </SheetHeader>
                            <nav aria-label="Primary" class="flex-1 p-2">
                                <ShellNav :items="navigation" />
                            </nav>
                            <div
                                v-if="userName"
                                class="border-t border-sidebar-border p-2"
                            >
                                <SidebarUserMenu
                                    :name="userName"
                                    :subtitle="userSubtitle"
                                    @sign-out="emit('signOut')"
                                />
                            </div>
                        </SheetContent>
                    </Sheet>

                    <!-- Desktop: collapse or expand the sidebar (Ctrl or Cmd + B). -->
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="hidden min-[769px]:inline-flex"
                                :aria-label="
                                    collapsed
                                        ? 'Expand sidebar'
                                        : 'Collapse sidebar'
                                "
                                :aria-expanded="!collapsed"
                                @click="toggle"
                            >
                                <PanelLeftOpen
                                    v-if="collapsed"
                                    aria-hidden="true"
                                />
                                <PanelLeftClose v-else aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            {{ collapsed ? 'Expand' : 'Collapse' }} sidebar
                            (Ctrl/⌘ B)
                        </TooltipContent>
                    </Tooltip>

                    <nav aria-label="Breadcrumb" class="min-w-0 flex-1">
                        <ol class="flex items-center gap-2 text-sm">
                            <li class="hidden text-muted-foreground sm:block">
                                <Link
                                    :href="brandHref"
                                    class="touch-target hover:underline"
                                >
                                    {{ breadcrumbRoot }}
                                </Link>
                            </li>
                            <li
                                class="hidden text-muted-foreground sm:block"
                                aria-hidden="true"
                            >
                                /
                            </li>
                            <li
                                class="truncate font-semibold text-foreground"
                                aria-current="page"
                            >
                                {{ pageTitle }}
                            </li>
                        </ol>
                    </nav>

                    <slot name="header-actions" />
                </header>

                <main
                    id="main-content"
                    tabindex="-1"
                    class="mx-auto w-full max-w-[1280px] p-4 outline-none min-[769px]:p-6"
                >
                    <slot />
                </main>
            </div>
        </div>

        <ToastHost />
    </TooltipProvider>
</template>
