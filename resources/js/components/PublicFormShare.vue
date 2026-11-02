<script setup lang="ts">
import {
    Check,
    Copy,
    Download,
    ExternalLink,
    Image as ImageIcon,
    Link2,
    QrCode,
    RefreshCw,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        url: string;
        qrUrl: string;
        downloadUrl: string;
        linkLabel: string;
        openLabel?: string;
        disabled?: boolean;
        flyerUrl?: string;
        flyerDownloadUrl?: string;
    }>(),
    {
        openLabel: 'Buka formulir peserta',
        disabled: false,
        flyerUrl: '',
        flyerDownloadUrl: '',
    },
);

const inputId = useId();
const linkInput = ref<HTMLInputElement | null>(null);
const qrImage = ref<HTMLImageElement | null>(null);
const copied = ref(false);
const copying = ref(false);
const copyHint = ref('');
const qrLoaded = ref(false);
const qrFailed = ref(false);
const retry = ref(0);
const flyerOpen = ref(false);
const flyerImage = ref<HTMLImageElement | null>(null);
const flyerLoaded = ref(false);
const flyerFailed = ref(false);
const flyerVersion = ref(0);
const flyerSource = computed(
    () =>
        props.flyerUrl +
        (props.flyerUrl.includes('?') ? '&' : '?') +
        'preview=' +
        flyerVersion.value,
);
let copyTimer: ReturnType<typeof setTimeout> | undefined;
const qrSource = computed(() =>
    retry.value === 0
        ? props.qrUrl
        : `${props.qrUrl}${props.qrUrl.includes('?') ? '&' : '?'}retry=${retry.value}`,
);
const isLocalLink = computed(() => {
    try {
        return ['localhost', '127.0.0.1', '[::1]'].includes(
            new URL(props.url).hostname,
        );
    } catch {
        return false;
    }
});

const isTemporaryLink = computed(() => {
    try {
        return new URL(props.url).hostname.endsWith('.trycloudflare.com');
    } catch {
        return false;
    }
});

function resetCopy(): void {
    if (copyTimer) clearTimeout(copyTimer);
    copied.value = false;
    copyHint.value = '';
}

function syncQrState(): void {
    const image = qrImage.value;
    if (!image?.complete) return;
    qrLoaded.value = image.naturalWidth > 0;
    qrFailed.value = !qrLoaded.value;
}

onMounted(syncQrState);

watch(
    () => [props.url, props.qrUrl],
    async () => {
        resetCopy();
        qrLoaded.value = false;
        qrFailed.value = false;
        retry.value = 0;
        await nextTick();
        syncQrState();
    },
);
onBeforeUnmount(() => {
    if (copyTimer) clearTimeout(copyTimer);
});

async function copyLink(): Promise<void> {
    if (copying.value || props.disabled) return;
    resetCopy();
    copying.value = true;
    const url = props.url;
    try {
        if (!navigator.clipboard?.writeText)
            throw new Error('Clipboard tidak tersedia.');
        await navigator.clipboard.writeText(url);
        if (url !== props.url) return;
        copied.value = true;
        copyHint.value = 'Tautan berhasil disalin. Bagikan kepada peserta.';
        copyTimer = setTimeout(resetCopy, 3000);
    } catch {
        copyHint.value =
            'Tautan sudah dipilih. Salin dengan Ctrl+C, atau tekan lama lalu pilih Salin pada HP.';
        await nextTick();
        linkInput.value?.focus();
        linkInput.value?.select();
        linkInput.value?.setSelectionRange(0, props.url.length);
    } finally {
        copying.value = false;
    }
}

function retryQr(): void {
    qrLoaded.value = false;
    qrFailed.value = false;
    retry.value++;
}

function syncFlyerState(): void {
    if (!flyerImage.value?.complete) return;
    flyerLoaded.value = flyerImage.value.naturalWidth > 0;
    flyerFailed.value = !flyerLoaded.value;
}

async function showFlyer(): Promise<void> {
    if (props.disabled || !props.flyerUrl) return;
    flyerLoaded.value = false;
    flyerFailed.value = false;
    flyerVersion.value++;
    flyerOpen.value = true;
    await nextTick();
    syncFlyerState();
}

watch(
    () => [props.url, props.flyerUrl],
    () => {
        flyerOpen.value = false;
        flyerLoaded.value = false;
        flyerFailed.value = false;
    },
);
</script>

<template>
    <div
        class="grid min-w-0 items-start gap-5 sm:grid-cols-[minmax(0,1fr)_13rem]"
    >
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <Link2
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />{{ title }}
            </h2>
            <p class="mt-2 text-xs leading-6 text-muted-foreground">
                {{ description }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <div class="min-w-0 flex-1 basis-52">
                    <label :for="inputId" class="sr-only">{{
                        linkLabel
                    }}</label>
                    <input
                        :id="inputId"
                        ref="linkInput"
                        :value="url"
                        type="text"
                        readonly
                        class="sisurat-input text-xs"
                        spellcheck="false"
                        @click="linkInput?.select()"
                    />
                </div>
                <button
                    type="button"
                    class="sisurat-primary-action"
                    :disabled="copying || disabled"
                    @click="copyLink"
                >
                    <Check
                        v-if="copied"
                        class="size-4"
                        aria-hidden="true"
                    /><Copy v-else class="size-4" aria-hidden="true" />{{
                        copying
                            ? 'Menyalin…'
                            : copied
                              ? 'Tersalin'
                              : 'Salin tautan'
                    }}
                </button>
            </div>
            <p
                v-if="copyHint"
                class="mt-2 text-xs leading-6 text-muted-foreground"
                role="status"
                aria-live="polite"
            >
                {{ copyHint }}
            </p>
            <a
                :href="url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-3 inline-flex min-h-10 items-center gap-2 text-xs font-semibold text-primary underline-offset-4 hover:underline"
                :class="{ 'pointer-events-none opacity-50': disabled }"
                :aria-disabled="disabled"
                @click="disabled && $event.preventDefault()"
            >
                {{ openLabel
                }}<ExternalLink class="size-3.5" aria-hidden="true" />
            </a>
            <p
                v-if="isLocalLink"
                class="mt-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-xs leading-6 text-amber-800 dark:text-amber-200"
            >
                Tautan dan QR masih memakai localhost. Gunakan alamat HTTPS yang
                dapat diakses dari HP sebelum membagikannya kepada peserta.
            </p>
            <p
                v-if="isTemporaryLink"
                class="mt-3 text-xs leading-6 text-muted-foreground"
            >
                Tautan ini sementara. Jika alamat situs berubah, unduh dan
                bagikan ulang QR atau flyer yang baru.
            </p>
        </div>
        <div
            class="w-full max-w-52 justify-self-center rounded-2xl border border-border bg-muted/30 p-3 text-center"
        >
            <p
                class="mb-2 flex items-center justify-center gap-2 text-xs font-semibold"
            >
                <QrCode class="size-4" aria-hidden="true" />QR code formulir
            </p>
            <div
                class="relative aspect-square overflow-hidden rounded-xl bg-white"
                :aria-busy="!qrLoaded && !qrFailed"
            >
                <img
                    v-if="!qrFailed"
                    ref="qrImage"
                    :key="`${url}:${qrSource}`"
                    :src="qrSource"
                    :alt="`QR code ${title}`"
                    width="768"
                    height="768"
                    class="h-full w-full object-contain"
                    :class="{ 'opacity-0': !qrLoaded }"
                    @load="syncQrState"
                    @error="qrFailed = true"
                />
                <div
                    v-if="!qrLoaded && !qrFailed"
                    class="absolute inset-0 flex items-center justify-center text-xs text-slate-600"
                    role="status"
                >
                    Menyiapkan QR…
                </div>
                <div
                    v-if="qrFailed"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-3 text-xs text-slate-700"
                    role="alert"
                >
                    <span>QR belum dapat dimuat.</span>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 px-3"
                        @click="retryQr"
                    >
                        <RefreshCw class="size-3.5" aria-hidden="true" />Coba
                        lagi
                    </button>
                </div>
            </div>
            <p class="mt-2 text-[11px] leading-5 text-muted-foreground">
                Pindai dengan kamera HP untuk membuka formulir.
            </p>
            <a
                :href="downloadUrl"
                class="sisurat-secondary-action mt-3 w-full text-xs"
                :class="{
                    'pointer-events-none opacity-50':
                        disabled || !qrLoaded || qrFailed,
                }"
                :aria-disabled="disabled || !qrLoaded || qrFailed"
                @click="
                    (disabled || !qrLoaded || qrFailed) &&
                    $event.preventDefault()
                "
                ><Download class="size-4" aria-hidden="true" />Unduh QR (PNG)</a
            >
            <button
                v-if="flyerUrl"
                type="button"
                class="sisurat-primary-action mt-2 w-full text-xs"
                :disabled="disabled"
                @click="showFlyer"
            >
                <ImageIcon class="size-4" aria-hidden="true" />Unduh Flyer
            </button>
        </div>
        <Dialog v-if="flyerUrl" v-model:open="flyerOpen">
            <DialogContent class="max-h-[92dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Flyer otomatis</DialogTitle>
                    <DialogDescription>
                        Judul kegiatan dan QR mengikuti formulir ini. Unduh
                        untuk dibagikan kepada peserta atau penguji yang dituju.
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="relative mx-auto aspect-[4/5] w-[min(100%,44.8dvh)] overflow-hidden rounded-xl border border-border bg-muted/40"
                    :aria-busy="!flyerLoaded && !flyerFailed"
                >
                    <img
                        v-if="!flyerFailed"
                        ref="flyerImage"
                        :src="flyerSource"
                        :alt="'Flyer ' + title"
                        width="1080"
                        height="1350"
                        class="h-full w-full object-contain"
                        :class="{ 'opacity-0': !flyerLoaded }"
                        @load="syncFlyerState"
                        @error="flyerFailed = true"
                    />
                    <div
                        v-if="!flyerLoaded && !flyerFailed"
                        class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
                        role="status"
                    >
                        Menyiapkan flyer…
                    </div>
                    <div
                        v-if="flyerFailed"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-5 text-center text-sm"
                        role="alert"
                    >
                        <p>
                            Flyer belum dapat dimuat. Periksa koneksi atau muat
                            ulang halaman jika sesi admin berakhir.
                        </p>
                        <button
                            type="button"
                            class="sisurat-secondary-action"
                            @click="showFlyer"
                        >
                            <RefreshCw class="size-4" aria-hidden="true" />Coba
                            lagi
                        </button>
                    </div>
                </div>
                <DialogFooter>
                    <a
                        :href="flyerDownloadUrl || flyerUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        download
                        class="sisurat-primary-action w-full sm:w-auto"
                        :class="{
                            'pointer-events-none opacity-50':
                                !flyerLoaded || flyerFailed || disabled,
                        }"
                        :aria-disabled="!flyerLoaded || flyerFailed || disabled"
                        @click="
                            (!flyerLoaded || flyerFailed || disabled) &&
                            $event.preventDefault()
                        "
                    >
                        <Download class="size-4" aria-hidden="true" />Unduh
                        Flyer (PNG)
                    </a>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
