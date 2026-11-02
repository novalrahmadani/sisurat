<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BookOpen,
    Building2,
    FilePlus2,
    FileText,
    Files,
    GraduationCap,
    LayoutGrid,
    MapPin,
    School,
    Settings,
    ShieldCheck,
    ClipboardList,
    UsersRound,
    UserRound,
    Palette,
    MessageSquareText,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { attendanceIndexRoute, type AttendanceType } from '@/lib/attendance';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, guide } from '@/routes';
import { create as createLetter, index as letterIndex } from '@/routes/letters';
import { index as masterData, schools, pesantren } from '@/routes/master-data';
import { index as districts } from '@/routes/master-data/districts';
import { index as staffMembers } from '@/routes/master-data/staff-members';
import { index as studyPrograms } from '@/routes/master-data/study-programs';
import { index as faculties } from '@/routes/master-data/faculties';
import { index as letterTemplates } from '@/routes/master-data/letter-templates';
import { edit as pmbSignatory } from '@/routes/master-data/pmb-signatory';
import { edit as profile } from '@/routes/profile';
import { edit as appearance } from '@/routes/appearance';
import { edit as security } from '@/routes/security';
import { index as interviews } from '@/routes/interviews';
import { index as quranTests } from '@/routes/quran-tests';
import { index as accounts } from '@/routes/accounts';
import { edit as letterNumbering } from '@/routes/letter-numbering';
import { index as internships } from '@/routes/internships';
import { index as outgoingAgenda } from '@/routes/outgoing-letters';
import { home } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const currentAttendanceType = computed(
    () =>
        (page.props.attendanceType as AttendanceType | undefined) ??
        (page.props.event as { type?: AttendanceType } | undefined)?.type,
);
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Buat Surat',
        href: createLetter(),
        icon: FilePlus2,
        isActive: [
            'letters/Start',
            'letters/Create',
            'letters/GeneralCreate',
        ].includes(page.component),
    },
    {
        title: 'Arsip Surat',
        href: letterIndex(),
        icon: Files,
        isActive: ['letters/Index', 'letters/Batch'].includes(page.component),
    },
    {
        title: 'Agenda Surat Keluar',
        href: outgoingAgenda(),
        icon: BookOpen,
        isActive: page.component.startsWith('outgoing-letters/'),
    },
    {
        title: 'Daftar Hadir',
        href: attendanceIndexRoute('meeting'),
        icon: ClipboardList,
        isActive: page.component.startsWith('attendance/'),
        children: [
            {
                title: 'Daftar Hadir Rapat',
                href: attendanceIndexRoute('meeting'),
                icon: UsersRound,
                isActive:
                    page.component.startsWith('attendance/') &&
                    currentAttendanceType.value === 'meeting',
            },
            {
                title: 'Daftar Hadir Tes PMB',
                href: attendanceIndexRoute('pmb_test'),
                icon: GraduationCap,
                isActive:
                    page.component.startsWith('attendance/') &&
                    currentAttendanceType.value === 'pmb_test',
            },
            {
                title: 'Daftar Hadir Kegiatan Lainnya',
                href: attendanceIndexRoute('other'),
                icon: ClipboardList,
                isActive:
                    page.component.startsWith('attendance/') &&
                    currentAttendanceType.value === 'other',
            },
        ],
    },
    {
        title: 'Tes Wawancara PMB',
        href: interviews(),
        icon: MessageSquareText,
        isActive: page.component.startsWith('interviews/'),
    },
    ...(page.props.can?.manage_internships
        ? [
              {
                  title: 'Absensi Magang',
                  href: internships(),
                  icon: ClipboardList,
                  isActive: page.component.startsWith('internship/'),
              },
          ]
        : []),
    {
        title: 'Tes Baca Al-Qur’an',
        href: quranTests(),
        icon: BookOpen,
        isActive:
            page.component.startsWith('quran-tests/') ||
            page.component.startsWith('quran-exams/'),
    },
    {
        title: 'Master Data',
        href: masterData(),
        icon: Building2,
        isActive:
            page.component.startsWith('master-data/') ||
            ['letters/Directory', 'letters/InstitutionForm'].includes(
                page.component,
            ),
        children: [
            { title: 'Ringkasan', href: masterData(), icon: LayoutGrid },
            { title: 'Sekolah', href: schools(), icon: School },
            { title: 'Pondok / Ponpes', href: pesantren(), icon: Building2 },
            { title: 'Kabupaten / Kota', href: districts(), icon: MapPin },
            { title: 'Dosen / Petugas', href: staffMembers(), icon: UserRound },
            { title: 'Program Studi', href: studyPrograms(), icon: BookOpen },
            { title: 'Fakultas', href: faculties(), icon: GraduationCap },
            { title: 'Isi Surat', href: letterTemplates(), icon: FileText },
            ...(page.props.can?.manage_pmb_signatory
                ? [
                      {
                          title: 'Penandatangan PMB',
                          href: pmbSignatory(),
                          icon: UserRound,
                      },
                  ]
                : []),
        ],
    },
]);

const utilityNavItems: NavItem[] = [
    { title: 'Halaman Utama', href: home(), icon: Building2 },
    { title: 'Beranda', href: dashboard(), icon: LayoutGrid },
    { title: 'Panduan', href: guide(), icon: BookOpen },
];

const settingsNavItems = computed<NavItem[]>(() => [
    {
        title: 'Pengaturan',
        href: profile(),
        icon: Settings,
        children: [
            { title: 'Profil', href: profile(), icon: UserRound },
            { title: 'Tampilan', href: appearance(), icon: Palette },
            { title: 'Keamanan', href: security(), icon: ShieldCheck },
            ...(page.props.can?.manage_letter_numbering
                ? [
                      {
                          title: 'Penomoran Surat',
                          href: letterNumbering(),
                          icon: FileText,
                      },
                  ]
                : []),
            ...(page.props.can?.manage_accounts
                ? [{ title: 'Kelola Akun', href: accounts(), icon: UsersRound }]
                : []),
        ],
    },
]);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="sisurat-sidebar border-0"
    >
        <SidebarHeader class="px-3 pt-5 pb-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-white/10"
                    >
                        <Link
                            :href="dashboard()"
                            aria-label="SISURAT, buka dashboard"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent class="gap-5 pb-4">
            <NavMain :items="mainNavItems" label="Menu utama" />
            <NavMain :items="utilityNavItems" label="Akses cepat" />
            <NavMain :items="settingsNavItems" label="Akun" />
        </SidebarContent>
        <SidebarFooter class="border-t border-white/15 p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
