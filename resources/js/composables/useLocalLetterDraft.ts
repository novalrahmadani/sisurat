import { usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    unref,
    watch,
} from 'vue';
import type { MaybeRef } from 'vue';

type DraftScope = { key: string; owner: string; formKey: string };
type DraftEnvelope<T extends object> = {
    version: 1;
    owner: string;
    formKey: string;
    timestamp: string;
    data: T;
};
type PendingDraft = { scope: DraftScope; json: string; timestamp: string };

export type LocalLetterDraftOptions<T extends object> = {
    key: MaybeRef<string>;
    getData: () => T;
    applyData: (data: T) => void | boolean;
    isMeaningful?: (data: T) => boolean;
};

const prefix = 'sisurat:letter-draft:v1:';
const maximumBytes = 3 * 1024 * 1024;
const debounceMilliseconds = 700;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function withinSizeLimit(value: string): boolean {
    return (
        value.length <= maximumBytes &&
        new TextEncoder().encode(value).byteLength <= maximumBytes
    );
}

export function useLocalLetterDraft<T extends object>(
    options: LocalLetterDraftOptions<T>,
) {
    const page = usePage();
    const hasSavedDraft = ref(false);
    const needsDecision = ref(false);
    const timestamp = ref<string | null>(null);
    const error = ref('');
    const available = ref(false);
    const scope = computed<DraftScope | null>(() => {
        const id = page.props.auth?.user?.id;
        const formKey = unref(options.key).trim();
        if (id === undefined || id === null || !formKey) return null;
        const owner = String(id);
        return {
            owner,
            formKey,
            key: `${prefix}${encodeURIComponent(owner)}:${encodeURIComponent(formKey)}`,
        };
    });
    let initialized = false;
    let paused = false;
    let transitioning = false;
    let dirty = false;
    let activeKey: string | null = null;
    let transitionRevision = 0;
    let pending: PendingDraft | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;

    function cancelTimer(): void {
        if (timer !== null) clearTimeout(timer);
        timer = null;
    }

    function decodeDraft(
        raw: string,
        current: DraftScope,
    ): DraftEnvelope<T> | null {
        if (!withinSizeLimit(raw)) return null;
        const parsed: unknown = JSON.parse(raw);
        if (
            !isRecord(parsed) ||
            parsed.version !== 1 ||
            parsed.owner !== current.owner ||
            parsed.formKey !== current.formKey ||
            typeof parsed.timestamp !== 'string' ||
            !Number.isFinite(Date.parse(parsed.timestamp)) ||
            !isRecord(parsed.data)
        )
            return null;
        return parsed as DraftEnvelope<T>;
    }

    function loadDraft(): void {
        cancelTimer();
        pending = null;
        dirty = false;
        hasSavedDraft.value = false;
        needsDecision.value = false;
        timestamp.value = null;
        error.value = '';
        activeKey = scope.value?.key ?? null;
        if (typeof window === 'undefined' || !scope.value) {
            available.value = false;
            return;
        }
        try {
            const raw = window.localStorage.getItem(scope.value.key);
            available.value = true;
            if (raw === null) return;
            hasSavedDraft.value = true;
            needsDecision.value = true;
            let saved: DraftEnvelope<T> | null = null;
            try {
                saved = decodeDraft(raw, scope.value);
            } catch {
                saved = null;
            }
            if (saved) timestamp.value = saved.timestamp;
            else
                error.value =
                    'Draf tidak dapat dibaca. Data tersimpan tetap ada; Anda dapat menghapus draf ini secara manual.';
        } catch {
            available.value = false;
            error.value =
                'Penyimpanan draf di browser tidak tersedia. Isi surat tetap dapat digunakan, tetapi belum tersimpan sebagai draf.';
        }
    }

    function captureDraft(): PendingDraft | null {
        if (
            !scope.value ||
            scope.value.key !== activeKey ||
            paused ||
            needsDecision.value ||
            transitioning ||
            typeof window === 'undefined'
        )
            return null;
        try {
            const data = options.getData();
            if (options.isMeaningful && !options.isMeaningful(data))
                return null;
            const savedAt = new Date().toISOString();
            const envelope: DraftEnvelope<T> = {
                version: 1,
                owner: scope.value.owner,
                formKey: scope.value.formKey,
                timestamp: savedAt,
                data,
            };
            const json = JSON.stringify(envelope);
            if (!withinSizeLimit(json)) {
                error.value =
                    'Draf melebihi batas 3 MB dan belum tersimpan. Kurangi jumlah atau panjang isi surat, lalu simpan kembali.';
                return null;
            }
            return { scope: { ...scope.value }, json, timestamp: savedAt };
        } catch {
            error.value =
                'Draf belum dapat disimpan. Periksa isi formulir, lalu coba simpan kembali.';
            return null;
        }
    }

    function persistDraft(candidate: PendingDraft): boolean {
        if (
            typeof window === 'undefined' ||
            !candidate.scope.key.startsWith(prefix) ||
            candidate.scope.owner !== scope.value?.owner
        )
            return false;
        try {
            window.localStorage.setItem(candidate.scope.key, candidate.json);
            if (candidate.scope.key === scope.value?.key) {
                available.value = true;
                hasSavedDraft.value = true;
                needsDecision.value = false;
                timestamp.value = candidate.timestamp;
                error.value = '';
                dirty = false;
            }
            return true;
        } catch {
            error.value =
                'Draf belum tersimpan. Penyimpanan browser mungkin penuh atau diblokir; isi formulir tetap tersedia.';
            return false;
        }
    }

    function flushPending(): void {
        cancelTimer();
        if (
            pending &&
            pending.scope.key === activeKey &&
            dirty &&
            !paused &&
            !needsDecision.value
        )
            persistDraft(pending);
        pending = null;
    }

    function scheduleDraft(): void {
        if (
            !initialized ||
            scope.value?.key !== activeKey ||
            paused ||
            needsDecision.value ||
            transitioning
        )
            return;
        cancelTimer();
        dirty = true;
        pending = captureDraft();
        if (pending) timer = setTimeout(flushPending, debounceMilliseconds);
    }

    function saveDraft(): boolean {
        cancelTimer();
        if (needsDecision.value) {
            error.value =
                'Pulihkan atau hapus draf tersimpan sebelum menyimpan draf baru.';
            return false;
        }
        if (paused) {
            error.value = 'Penyimpanan draf sedang dijeda.';
            return false;
        }
        const candidate = captureDraft();
        if (!candidate) return false;
        pending = null;
        return persistDraft(candidate);
    }

    function restoreDraft(): boolean {
        if (typeof window === 'undefined' || !scope.value) return false;
        cancelTimer();
        try {
            const raw = window.localStorage.getItem(scope.value.key);
            const saved = raw === null ? null : decodeDraft(raw, scope.value);
            if (!saved) {
                error.value =
                    'Draf tidak dapat dipulihkan. Data tersimpan tetap ada; periksa draf atau hapus secara manual.';
                return false;
            }
            const wasPaused = paused;
            paused = true;
            try {
                const applied: unknown = options.applyData(saved.data);
                if (applied === false) {
                    error.value =
                        'Draf belum dipulihkan karena format isinya tidak sesuai. Data tersimpan tetap ada.';
                    return false;
                }
            } finally {
                paused = wasPaused;
            }
            pending = null;
            dirty = false;
            hasSavedDraft.value = true;
            needsDecision.value = false;
            timestamp.value = saved.timestamp;
            error.value = '';
            available.value = true;
            paused = false;
            return true;
        } catch {
            error.value =
                'Draf belum dapat dipulihkan. Data tersimpan tetap ada; periksa format isi atau penyimpanan browser.';
            return false;
        }
    }

    function removeDraft(): boolean {
        cancelTimer();
        pending = null;
        dirty = false;
        if (typeof window === 'undefined' || !scope.value) return false;
        try {
            window.localStorage.removeItem(scope.value.key);
            hasSavedDraft.value = false;
            needsDecision.value = false;
            timestamp.value = null;
            error.value = '';
            return true;
        } catch {
            error.value =
                'Draf belum dapat dihapus dari browser. Data tersimpan tetap ada.';
            return false;
        }
    }

    function pause(): void {
        paused = true;
        cancelTimer();
    }

    function resume(): void {
        paused = false;
        scheduleDraft();
    }

    function clearDraft(): boolean {
        pause();
        return removeDraft();
    }

    function discardDraft(): boolean {
        const removed = removeDraft();
        if (removed) scheduleDraft();
        return removed;
    }

    function handleStorage(event: StorageEvent): void {
        if (
            !scope.value ||
            (event.key !== null && event.key !== scope.value.key)
        )
            return;
        try {
            if (event.storageArea !== window.localStorage) return;
        } catch {
            return;
        }
        loadDraft();
        if (event.newValue === null && !needsDecision.value) scheduleDraft();
    }

    watch(options.getData, scheduleDraft, { deep: true, flush: 'sync' });
    watch(
        () => scope.value?.key,
        () => {
            flushPending();
            transitioning = true;
            const revision = ++transitionRevision;
            if (initialized) loadDraft();
            void nextTick(() => {
                if (revision === transitionRevision) {
                    transitioning = false;
                    scheduleDraft();
                }
            });
        },
        { flush: 'sync' },
    );
    onMounted(() => {
        initialized = true;
        loadDraft();
        void nextTick(scheduleDraft);
        window.addEventListener('beforeunload', flushPending);
        window.addEventListener('storage', handleStorage);
    });
    onBeforeUnmount(() => {
        flushPending();
        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', flushPending);
            window.removeEventListener('storage', handleStorage);
        }
    });

    return {
        hasSavedDraft,
        needsDecision,
        timestamp,
        error,
        available,
        restoreDraft,
        discardDraft,
        clearDraft,
        pause,
        resume,
        saveDraft,
    };
}
