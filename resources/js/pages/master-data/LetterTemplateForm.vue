<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Plus, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { edit, index, update } from '@/routes/master-data/letter-templates';

type TemplateRecord = {
    id: number;
    key: string;
    name: string;
    type: string;
    destination: string | null;
    revision: number;
    updated_at: string | null;
    content: Record<string, string>;
};
type TemplateDefinition = {
    key: string;
    name: string;
    type: string;
    destination: string | null;
    fields: { id: string; label: string; limit: number; required: boolean }[];
    tokens: { key: string; label: string; sample: string }[];
};
const props = defineProps<{
    record: TemplateRecord;
    definition: TemplateDefinition;
}>();
const form = useForm({
    content: Object.fromEntries(
        props.definition.fields.map((field) => [
            field.id,
            props.record.content[field.id] ?? '',
        ]),
    ) as Record<string, string>,
    revision: props.record.revision,
});
const activeField = ref(props.definition.fields[0]?.id ?? '');
const activeFieldLabel = computed(
    () =>
        props.definition.fields.find((field) => field.id === activeField.value)
            ?.label ?? '',
);
const requestError = ref('');
const tokenSamples = computed(() =>
    Object.fromEntries(
        props.definition.tokens.map((token) => [token.key, token.sample]),
    ),
);
const requiredDescriptionTokens = computed(() =>
    props.definition.type === 'assignment'
        ? ['periode_kegiatan']
        : props.definition.type === 'pkkmb'
          ? ['periode_kegiatan', 'tema_pkkmb', 'hasil_pkkmb']
          : [],
);
const requiredDescriptionLabels = computed(() =>
    requiredDescriptionTokens.value
        .map(
            (key) =>
                props.definition.tokens.find((token) => token.key === key)
                    ?.label ?? key,
        )
        .join(', '),
);
const visibleErrors = computed(() =>
    Object.entries(form.errors).map(([key, message]) => [
        key,
        Array.isArray(message) ? message.join(' ') : String(message),
    ]),
);
function fieldError(id: string): string | undefined {
    return (form.errors as Record<string, string>)[`content.${id}`];
}
function tokensIn(value: string): string[] {
    return Array.from(
        value.matchAll(/(?<!\{)\{\{\s*([a-z][a-z0-9_]*)\s*\}\}(?!\})/gu),
        (match) => match[1],
    );
}
function hasMalformedTokens(id: string): boolean {
    const remainder = (form.content[id] ?? '').replace(
        /(?<!\{)\{\{\s*([a-z][a-z0-9_]*)\s*\}\}(?!\})/gu,
        '',
    );
    return remainder.includes('{{') || remainder.includes('}}');
}
function unsupportedTokens(id: string): string[] {
    const available = new Set(
        props.definition.tokens.map((token) => token.key),
    );
    return Array.from(
        new Set(
            tokensIn(form.content[id] ?? '').filter(
                (key) => !available.has(key),
            ),
        ),
    );
}
function sampleText(id: string): string {
    return (form.content[id] ?? '').replace(
        /(?<!\{)\{\{\s*([a-z][a-z0-9_]*)\s*\}\}(?!\})/gu,
        (placeholder: string, key: string) =>
            Object.hasOwn(tokenSamples.value, key.trim())
                ? tokenSamples.value[key.trim()]
                : placeholder,
    );
}
function onFieldInput(id: string): void {
    activeField.value = id;
    requestError.value = '';
    form.clearErrors(`content.${id}`);
}
function insertToken(key: string): void {
    if (form.processing) return;
    const field = props.definition.fields.find(
        (item) => item.id === activeField.value,
    );
    if (!field) return;
    const element = document.getElementById(`template-${field.id}`);
    const textarea = element instanceof HTMLTextAreaElement ? element : null;
    const text = form.content[field.id] ?? '';
    const start = Math.min(
        textarea?.selectionStart ?? text.length,
        text.length,
    );
    const end = Math.min(textarea?.selectionEnd ?? start, text.length);
    const placeholder = `{{${key}}}`;
    const nextValue = text.slice(0, start) + placeholder + text.slice(end);
    if (nextValue.length > field.limit) {
        requestError.value = `${field.label} maksimal ${field.limit.toLocaleString('id-ID')} karakter. Kurangi isi sebelum menyisipkan data otomatis.`;
        return;
    }
    form.content[field.id] = nextValue;
    onFieldInput(field.id);
    void nextTick(() => {
        textarea?.focus();
        textarea?.setSelectionRange(
            start + placeholder.length,
            start + placeholder.length,
        );
    });
}
function focusError(key: string): void {
    const target = document.getElementById(
        key.startsWith('content.')
            ? `template-${key.slice('content.'.length)}`
            : 'template-errors',
    );
    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target?.focus();
}
function validate(): boolean {
    const errors: Record<string, string> = {};
    for (const field of props.definition.fields) {
        const value = form.content[field.id] ?? '';
        if (field.required && !value.trim())
            errors[`content.${field.id}`] = `${field.label} wajib diisi.`;
        else if (value.length > field.limit)
            errors[`content.${field.id}`] =
                `${field.label} maksimal ${field.limit.toLocaleString('id-ID')} karakter.`;
        else if (hasMalformedTokens(field.id))
            errors[`content.${field.id}`] =
                `${field.label} memuat penanda yang tidak lengkap. Gunakan tombol Data otomatis.`;
        else if (unsupportedTokens(field.id).length)
            errors[`content.${field.id}`] =
                `${field.label} memuat data otomatis yang belum tersedia. Gunakan tombol Data otomatis.`;
        else if (field.id === 'description') {
            const names = new Set(tokensIn(value));
            if (requiredDescriptionTokens.value.some((key) => !names.has(key)))
                errors[`content.${field.id}`] =
                    `Sertakan data otomatis: ${requiredDescriptionLabels.value}.`;
        }
    }
    form.clearErrors();
    if (!Object.keys(errors).length) return true;
    form.setError(errors as typeof form.errors);
    void nextTick(() => focusError(Object.keys(errors)[0]));
    return false;
}
function submit(): void {
    if (form.processing || !validate()) return;
    requestError.value = '';
    form.put(update.url(props.record.id), {
        preserveScroll: true,
        onError: () => {
            const key = Object.keys(form.errors)[0];
            if (key) void nextTick(() => focusError(key));
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Perubahan masih tersedia; periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Isi surat belum dapat disimpan. Perubahan masih tersedia; coba kembali setelah beberapa saat.';
            return false;
        },
    });
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="`Ubah isi ${record.name}`" />
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
            <Link
                :href="index()"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke Isi
                Surat</Link
            >
            <header>
                <p class="sisurat-kicker">Master Data · Isi Surat</p>
                <h1 class="sisurat-title mt-2">{{ record.name }}</h1>
                <p class="sisurat-subtitle mt-2 max-w-3xl">
                    Sesuaikan kalimat surat. Gunakan Data otomatis untuk nama,
                    tanggal, wilayah, tema, dan isian dari formulir surat.
                    Perubahan berlaku untuk surat yang dibuat berikutnya.
                </p>
            </header>
            <form novalidate @submit.prevent="submit">
                <fieldset :disabled="form.processing" class="min-w-0">
                    <div class="grid items-start gap-6 xl:grid-cols-2">
                        <section
                            class="sisurat-panel space-y-6 p-5 sm:p-7"
                            aria-labelledby="template-content-heading"
                        >
                            <h2
                                id="template-content-heading"
                                class="text-base font-semibold"
                            >
                                Ubah isi surat
                            </h2>
                            <div
                                v-for="field in definition.fields"
                                :key="field.id"
                            >
                                <label
                                    :for="`template-${field.id}`"
                                    class="mb-2 block text-sm font-medium"
                                    >{{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                >
                                <textarea
                                    :id="`template-${field.id}`"
                                    v-model="form.content[field.id]"
                                    class="sisurat-input resize-y leading-7"
                                    :rows="field.limit <= 255 ? 2 : 6"
                                    :maxlength="field.limit"
                                    :required="field.required"
                                    :aria-invalid="
                                        Boolean(
                                            fieldError(field.id) ||
                                            hasMalformedTokens(field.id) ||
                                            unsupportedTokens(field.id).length,
                                        )
                                    "
                                    :aria-describedby="`template-${field.id}-hint template-${field.id}-error template-${field.id}-tokens`"
                                    @focus="activeField = field.id"
                                    @input="onFieldInput(field.id)"
                                />
                                <div
                                    :id="`template-${field.id}-hint`"
                                    class="mt-2 flex flex-wrap justify-between gap-2 text-xs leading-6 text-muted-foreground"
                                >
                                    <span
                                        v-if="
                                            field.id === 'description' &&
                                            requiredDescriptionTokens.length
                                        "
                                    >
                                        Wajib memuat data otomatis:
                                        {{ requiredDescriptionLabels }}.
                                    </span>
                                    <span v-else
                                        >Gunakan tombol Data otomatis untuk
                                        menyisipkan isian surat.</span
                                    >
                                    <span class="shrink-0 tabular-nums">
                                        {{
                                            (
                                                form.content[field.id] ?? ''
                                            ).length.toLocaleString('id-ID')
                                        }}
                                        /
                                        {{
                                            field.limit.toLocaleString('id-ID')
                                        }}
                                        karakter
                                    </span>
                                </div>
                                <p
                                    v-if="
                                        hasMalformedTokens(field.id) ||
                                        unsupportedTokens(field.id).length
                                    "
                                    :id="`template-${field.id}-tokens`"
                                    class="mt-2 text-xs leading-6 text-destructive"
                                >
                                    <template
                                        v-if="hasMalformedTokens(field.id)"
                                    >
                                        Tanda data otomatis belum lengkap. Hapus
                                        bagian tersebut, lalu sisipkan kembali
                                        dengan tombol Data otomatis.
                                    </template>
                                    <template v-else>
                                        Data otomatis belum tersedia:
                                        {{
                                            unsupportedTokens(field.id).join(
                                                ', ',
                                            )
                                        }}. Hapus bagian tersebut dan gunakan
                                        tombol Data otomatis.
                                    </template>
                                </p>
                                <InputError
                                    :id="`template-${field.id}-error`"
                                    class="mt-2"
                                    :message="fieldError(field.id)"
                                />
                            </div>
                        </section>
                        <div class="space-y-6 xl:sticky xl:top-6">
                            <section
                                class="sisurat-panel p-5 sm:p-7"
                                aria-labelledby="template-tokens-heading"
                            >
                                <h2
                                    id="template-tokens-heading"
                                    class="text-base font-semibold"
                                >
                                    Data otomatis
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-7 text-muted-foreground"
                                >
                                    Pilih kolom isi, lalu tekan data yang ingin
                                    disisipkan. Bagian bertanda dua kurung
                                    kurawal akan diisi sesuai formulir.
                                    Pertahankan teks di dalam tanda tersebut.
                                </p>
                                <p
                                    class="mt-3 text-xs font-medium text-primary dark:text-foreground"
                                >
                                    Sisipkan ke: {{ activeFieldLabel }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="token in definition.tokens"
                                        :key="token.key"
                                        type="button"
                                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                                        :title="`Contoh: ${token.sample}`"
                                        @mousedown.prevent
                                        @click="insertToken(token.key)"
                                    >
                                        <Plus
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />{{ token.label }}
                                    </button>
                                </div>
                            </section>
                            <section
                                class="sisurat-panel p-5 sm:p-7"
                                aria-labelledby="template-preview-heading"
                            >
                                <h2
                                    id="template-preview-heading"
                                    class="flex items-center gap-2 text-base font-semibold"
                                >
                                    <Eye
                                        class="size-4"
                                        aria-hidden="true"
                                    />Contoh isi surat
                                </h2>
                                <p
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    Contoh memakai data ilustrasi. Surat yang
                                    dibuat memakai data dari formulir.
                                </p>
                                <dl class="mt-5 space-y-5">
                                    <div
                                        v-for="field in definition.fields"
                                        :key="field.id"
                                    >
                                        <dt
                                            class="text-xs font-medium text-muted-foreground"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm leading-7 break-words whitespace-pre-wrap"
                                        >
                                            {{
                                                sampleText(field.id) ||
                                                'Belum diisi'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>
                    <div
                        v-if="form.hasErrors || requestError"
                        id="template-errors"
                        tabindex="-1"
                        class="mt-6 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        <p class="font-medium">
                            {{
                                requestError ||
                                'Isi surat belum tersimpan. Periksa isian berikut.'
                            }}
                        </p>
                        <ul
                            v-if="form.hasErrors"
                            class="mt-2 list-inside list-disc space-y-1"
                        >
                            <li
                                v-for="[key, message] in visibleErrors"
                                :key="key"
                            >
                                {{ message }}
                            </li>
                        </ul>
                        <div v-if="form.errors.revision" class="mt-3 space-y-2">
                            <p>
                                Salin perubahan Anda sebelum membuka isi
                                terbaru, lalu terapkan kembali perubahan yang
                                diperlukan.
                            </p>
                            <Link
                                :href="edit(record.id)"
                                :preserve-state="false"
                                class="sisurat-secondary-action"
                                >Buka isi terbaru</Link
                            >
                        </div>
                    </div>
                    <div
                        class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5"
                    >
                        <p
                            class="max-w-xl text-xs leading-6 text-muted-foreground"
                        >
                            Surat di Arsip Surat tetap menyimpan isi saat
                            dibuat. Periksa contoh isi sebelum menyimpan
                            perubahan.
                        </p>
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" />{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan isi surat'
                            }}
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
