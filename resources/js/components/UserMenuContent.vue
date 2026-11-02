<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LogOut,
    Palette,
    ShieldCheck,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit as profile } from '@/routes/profile';
import { edit as appearance } from '@/routes/appearance';
import { edit as security } from '@/routes/security';
import { index as accounts } from '@/routes/accounts';
import type { User } from '@/types';

defineProps<{ user: User }>();
const page = usePage();
const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem as-child class="min-h-10 rounded-lg">
            <Link class="w-full cursor-pointer" :href="profile()" prefetch
                ><UserRound class="size-4" />Profil</Link
            >
        </DropdownMenuItem>
        <DropdownMenuItem as-child class="min-h-10 rounded-lg">
            <Link class="w-full cursor-pointer" :href="appearance()" prefetch
                ><Palette class="size-4" />Tampilan</Link
            >
        </DropdownMenuItem>
        <DropdownMenuItem as-child class="min-h-10 rounded-lg">
            <Link class="w-full cursor-pointer" :href="security()" prefetch
                ><ShieldCheck class="size-4" />Keamanan</Link
            >
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="page.props.can?.manage_accounts"
            as-child
            class="min-h-10 rounded-lg"
        >
            <Link class="w-full cursor-pointer" :href="accounts()" prefetch
                ><UsersRound class="size-4" />Kelola Akun</Link
            >
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child class="min-h-10 rounded-lg">
        <Link
            class="w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
            ><LogOut class="size-4" />Keluar</Link
        >
    </DropdownMenuItem>
</template>
