<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { reactive, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{ items: NavItem[]; label?: string }>(),
    { label: 'Menu utama' },
);
const { currentUrl, isCurrentUrl } = useCurrentUrl();
const { state, isMobile, setOpenMobile } = useSidebar();
const openGroups = reactive<Record<string, boolean>>({});
const page = usePage();

function isItemActive(item: NavItem): boolean {
    return (
        item.isActive ??
        item.children?.some(isChildActive) ??
        isCurrentUrl(item.href)
    );
}

function isChildActive(item: NavItem): boolean {
    return item.isActive ?? isCurrentUrl(item.href);
}

function shouldPreserveForm(href: NavItem['href']): boolean {
    const targetPath = toUrl(href).split('?')[0];

    return (
        page.component === 'letters/Create' &&
        /^\/letters\/(?:create|pkkmb|(?:permission|assignment)\/(?:school|pesantren))\/?$/.test(
            targetPath,
        )
    );
}

function closeMobileNavigation(): void {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}

watch(
    currentUrl,
    () => {
        for (const item of props.items) {
            if (item.children && isItemActive(item)) {
                openGroups[item.title] = true;
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-3 py-0">
        <SidebarGroupLabel
            class="mb-2 px-2 text-[10px] font-semibold tracking-widest text-white/65 uppercase"
            >{{ label }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <template v-if="item.children?.length">
                    <DropdownMenu v-if="state === 'collapsed' && !isMobile">
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isItemActive(item)"
                                :tooltip="item.title"
                                :aria-label="item.title"
                                class="min-h-10 data-[active=true]:bg-white data-[active=true]:text-[#005a36] data-[active=true]:hover:bg-white/95 data-[active=true]:hover:text-[#005a36]"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="right"
                            align="start"
                            :side-offset="10"
                            class="min-w-48 rounded-xl p-2"
                        >
                            <DropdownMenuLabel
                                class="text-xs text-muted-foreground"
                                >{{ item.title }}</DropdownMenuLabel
                            >
                            <DropdownMenuItem
                                v-for="child in item.children"
                                :key="child.title"
                                as-child
                                class="min-h-10 rounded-lg"
                            >
                                <Link
                                    :href="child.href"
                                    :preserve-state="
                                        shouldPreserveForm(child.href)
                                    "
                                    :aria-current="
                                        isChildActive(child)
                                            ? 'page'
                                            : undefined
                                    "
                                    :class="{
                                        'bg-accent text-accent-foreground':
                                            isChildActive(child),
                                    }"
                                >
                                    <component
                                        :is="child.icon"
                                        v-if="child.icon"
                                    />
                                    {{ child.title }}
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Collapsible
                        v-else
                        v-model:open="openGroups[item.title]"
                        class="group/nav-group"
                    >
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isItemActive(item)"
                                class="min-h-10 data-[active=true]:bg-white data-[active=true]:text-[#005a36] data-[active=true]:hover:bg-white/95 data-[active=true]:hover:text-[#005a36]"
                                :aria-label="item.title"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/nav-group:rotate-180"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub class="mt-1 gap-1 border-white/20">
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isChildActive(child)"
                                        class="min-h-9 text-xs data-[active=true]:bg-white/15 data-[active=true]:text-white"
                                    >
                                        <Link
                                            :href="child.href"
                                            :preserve-state="
                                                shouldPreserveForm(child.href)
                                            "
                                            :aria-current="
                                                isChildActive(child)
                                                    ? 'page'
                                                    : undefined
                                            "
                                            @click="closeMobileNavigation"
                                        >
                                            <component
                                                :is="child.icon"
                                                v-if="child.icon"
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                </template>
                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="isItemActive(item)"
                    :tooltip="item.title"
                    class="min-h-10 data-[active=true]:bg-white data-[active=true]:text-[#005a36] data-[active=true]:hover:bg-white/95 data-[active=true]:hover:text-[#005a36]"
                >
                    <Link
                        :href="item.href"
                        :preserve-state="shouldPreserveForm(item.href)"
                        :aria-current="isItemActive(item) ? 'page' : undefined"
                        @click="closeMobileNavigation"
                    >
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
