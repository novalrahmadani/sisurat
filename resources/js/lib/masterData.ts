import { useForm } from '@inertiajs/vue3';
import { ref, shallowRef } from 'vue';

export type MasterDataPagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type MasterDistrictOption = { id: number; name: string };
export type MasterAcademicCategory = 'study-programs' | 'faculties';
export type MasterAcademicOption = { id: number; name: string };
export type MasterDistrict = MasterDistrictOption & {
    institutions_count: number;
    staff_members_count: number;
};
export type MasterStaffMember = {
    id: number;
    name: string;
    office: string;
    employee_number: string | null;
    district_id: number | null;
    district: string | null;
};

export function useMasterDataDeletion<T extends { id: number; name: string }>(
    destroyUrl: (id: number) => string,
    fallbackFocusId: string,
) {
    const deleteForm = useForm({});
    const deleteOpen = ref(false);
    const deleteTarget = shallowRef<T | null>(null);
    const deleteError = ref('');
    const returnFocus = shallowRef<HTMLElement | null>(null);

    function requestDeletion(record: T, event: Event): void {
        if (deleteForm.processing) return;
        deleteTarget.value = record;
        returnFocus.value = event.currentTarget as HTMLElement;
        deleteError.value = '';
        deleteForm.clearErrors();
        deleteOpen.value = true;
    }

    function updateDeleteDialog(open: boolean): void {
        if (deleteForm.processing) return;
        deleteOpen.value = open;
        if (!open) {
            deleteTarget.value = null;
            deleteError.value = '';
            deleteForm.clearErrors();
        }
    }

    function restoreDeleteFocus(event: Event): void {
        event.preventDefault();
        if (returnFocus.value?.isConnected) returnFocus.value.focus();
        else document.getElementById(fallbackFocusId)?.focus();
    }

    function confirmDeletion(): void {
        if (!deleteTarget.value || deleteForm.processing) return;
        deleteError.value = '';
        deleteForm.delete(destroyUrl(deleteTarget.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteOpen.value = false;
                deleteTarget.value = null;
            },
            onError: (errors) => {
                deleteError.value =
                    Object.values(errors).filter(Boolean).join(' ') ||
                    'Data belum dapat dihapus. Periksa keterkaitan data ini.';
            },
            onNetworkError: () => {
                deleteError.value =
                    'Koneksi terputus. Periksa koneksi lalu coba hapus kembali.';
                return false;
            },
            onHttpException: () => {
                deleteError.value =
                    'Server belum dapat menghapus data. Muat ulang halaman atau coba kembali.';
                return false;
            },
        });
    }

    return {
        deleteForm,
        deleteOpen,
        deleteTarget,
        deleteError,
        requestDeletion,
        updateDeleteDialog,
        restoreDeleteFocus,
        confirmDeletion,
    };
}
