<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Info, Save, ShieldCheck } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { index, store, update } from '@/routes/accounts';
import { edit as editSecurity } from '@/routes/security';

type AccountRole = 'admin' | 'staff';
type Account = {
    id: number;
    name: string;
    email: string;
    role: AccountRole;
    is_active: boolean;
    is_primary_admin: boolean;
};

const props = defineProps<{ account: Account | null; passwordRules: string }>();
const page = usePage();
const isSelf = computed(() =>
    Boolean(
        props.account &&
        Number(props.account.id) === Number(page.props.auth.user.id),
    ),
);
const protectedAccess = computed(() =>
    Boolean(props.account?.is_primary_admin || isSelf.value),
);
const canSetPassword = computed(() => !props.account || !isSelf.value);
const passwordMinimum = computed(() => {
    const minimum = Number(
        props.passwordRules.match(/minlength:\s*(\d+)/)?.[1],
    );
    return Number.isInteger(minimum) && minimum > 0 ? minimum : 8;
});
const passwordHint = computed(() => {
    const requirements: string[] = [];
    if (props.passwordRules.includes('required: upper'))
        requirements.push('huruf besar');
    if (props.passwordRules.includes('required: lower'))
        requirements.push('huruf kecil');
    if (props.passwordRules.includes('required: digit'))
        requirements.push('angka');
    if (props.passwordRules.includes('required: special'))
        requirements.push('simbol');
    return `Gunakan minimal ${passwordMinimum.value} karakter${requirements.length ? `, dengan ${requirements.join(', ')}` : ''}.`;
});
const pageTitle = computed(() => (props.account ? 'Ubah akun' : 'Tambah akun'));
const requestError = ref('');
const form = useForm({
    name: props.account?.name ?? '',
    email: props.account?.email ?? '',
    role: props.account?.role ?? ('staff' as AccountRole),
    is_active: props.account?.is_active ?? true,
    password: '',
    password_confirmation: '',
});

function resetPasswords(): void {
    form.reset('password', 'password_confirmation');
}
onBeforeUnmount(resetPasswords);

function focusErrors(): void {
    const key = Object.keys(form.errors)[0];
    if (key)
        void nextTick(() => document.getElementById(`account-${key}`)?.focus());
}

function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({
        name: data.name.trim(),
        email: data.email.trim(),
        role:
            protectedAccess.value && props.account
                ? props.account.role
                : data.role,
        is_active:
            protectedAccess.value && props.account
                ? props.account.is_active
                : data.is_active,
        ...(canSetPassword.value
            ? {
                  password: data.password,
                  password_confirmation: data.password_confirmation,
              }
            : {}),
    }));
    const options = {
        preserveScroll: true,
        onSuccess: resetPasswords,
        onError: focusErrors,
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Akun belum dapat disimpan. Muat ulang halaman atau coba kembali.';
            return false;
        },
    };
    if (props.account) form.put(update.url(props.account.id), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link
                :href="index()"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                daftar akun</Link
            >
            <header>
                <p class="sisurat-kicker">Pengaturan · Akses SISURAT</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    {{
                        account
                            ? 'Perbarui identitas dan hak akses akun.'
                            : 'Buat akses login untuk petugas yang Anda izinkan.'
                    }}
                </p>
            </header>

            <form class="space-y-6" autocomplete="off" @submit.prevent="submit">
                <fieldset :disabled="form.processing" class="min-w-0 space-y-6">
                    <section
                        class="sisurat-panel space-y-6 p-5 sm:p-7"
                        aria-labelledby="account-info-heading"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <h2
                                id="account-info-heading"
                                class="text-base font-semibold"
                            >
                                Identitas akun
                            </h2>
                            <span
                                v-if="account?.is_primary_admin"
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary dark:text-emerald-200"
                                ><ShieldCheck
                                    class="size-3.5"
                                    aria-hidden="true"
                                />Admin utama</span
                            >
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Kolom bertanda
                            <span class="text-destructive">*</span> wajib diisi.
                        </p>
                        <div>
                            <label
                                for="account-name"
                                class="mb-2 block text-sm font-medium"
                                >Nama lengkap
                                <span class="text-destructive">*</span></label
                            ><input
                                id="account-name"
                                v-model="form.name"
                                type="text"
                                maxlength="255"
                                required
                                autocomplete="off"
                                class="sisurat-input"
                                placeholder="Nama pengguna SISURAT"
                                :aria-invalid="Boolean(form.errors.name)"
                                aria-describedby="account-name-error"
                            /><InputError
                                id="account-name-error"
                                class="mt-2"
                                :message="form.errors.name"
                            />
                        </div>
                        <div>
                            <label
                                for="account-email"
                                class="mb-2 block text-sm font-medium"
                                >Alamat email
                                <span class="text-destructive">*</span></label
                            ><input
                                id="account-email"
                                v-model="form.email"
                                type="email"
                                maxlength="255"
                                required
                                autocomplete="off"
                                class="sisurat-input"
                                placeholder="nama@example.com"
                                :aria-invalid="Boolean(form.errors.email)"
                                aria-describedby="account-email-hint account-email-error"
                            />
                            <p
                                id="account-email-hint"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Alamat ini digunakan untuk masuk ke SISURAT.
                            </p>
                            <InputError
                                id="account-email-error"
                                class="mt-2"
                                :message="form.errors.email"
                            />
                        </div>
                    </section>

                    <section
                        class="sisurat-panel space-y-6 p-5 sm:p-7"
                        aria-labelledby="account-access-heading"
                    >
                        <h2
                            id="account-access-heading"
                            class="text-base font-semibold"
                        >
                            Peran dan status akses
                        </h2>
                        <div>
                            <label
                                for="account-role"
                                class="mb-2 block text-sm font-medium"
                                >Peran
                                <span class="text-destructive">*</span></label
                            ><select
                                id="account-role"
                                v-model="form.role"
                                :disabled="protectedAccess"
                                class="sisurat-input"
                                aria-describedby="account-role-hint account-role-error"
                                :aria-invalid="Boolean(form.errors.role)"
                            >
                                <option value="staff">Petugas</option>
                                <option value="admin">Admin</option>
                            </select>
                            <p
                                id="account-role-hint"
                                class="mt-2 text-xs leading-6 text-muted-foreground"
                            >
                                {{
                                    form.role === 'admin'
                                        ? 'Admin dapat menggunakan fitur SISURAT serta membuat dan mengatur akun pengguna.'
                                        : 'Petugas dapat menggunakan fitur administrasi SISURAT. Pengelolaan akun hanya tersedia bagi Admin.'
                                }}
                            </p>
                            <InputError
                                id="account-role-error"
                                class="mt-2"
                                :message="form.errors.role"
                            />
                        </div>
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-4"
                        >
                            <label
                                for="account-is_active"
                                class="flex items-start gap-3"
                                :class="
                                    protectedAccess
                                        ? 'cursor-not-allowed'
                                        : 'cursor-pointer'
                                "
                                ><input
                                    id="account-is_active"
                                    v-model="form.is_active"
                                    type="checkbox"
                                    :disabled="protectedAccess"
                                    class="mt-1 size-4 shrink-0 accent-[#005a36]"
                                    :aria-invalid="
                                        Boolean(form.errors.is_active)
                                    "
                                    aria-describedby="account-is_active-hint account-is_active-error"
                                /><span
                                    ><span class="block text-sm font-medium"
                                        >Akun aktif</span
                                    ><span
                                        id="account-is_active-hint"
                                        class="mt-1 block text-xs leading-6 text-muted-foreground"
                                        >{{
                                            form.is_active
                                                ? 'Pengguna dapat masuk dengan akun ini.'
                                                : 'Pengguna tidak dapat masuk. Akses yang sedang berjalan juga akan dihentikan.'
                                        }}</span
                                    ></span
                                ></label
                            ><InputError
                                id="account-is_active-error"
                                class="mt-2"
                                :message="form.errors.is_active"
                            />
                        </div>
                        <p
                            v-if="protectedAccess"
                            class="flex gap-2 rounded-xl bg-primary/5 p-4 text-xs leading-6 text-muted-foreground"
                        >
                            <Info
                                class="mt-1 size-4 shrink-0"
                                aria-hidden="true"
                            /><span>{{
                                account?.is_primary_admin
                                    ? 'Peran dan status Admin utama dilindungi agar akses pengelolaan SISURAT tetap tersedia.'
                                    : 'Peran dan status akun Anda tidak dapat diubah dari halaman ini.'
                            }}</span>
                        </p>
                    </section>

                    <section
                        class="sisurat-panel space-y-5 p-5 sm:p-7"
                        aria-labelledby="account-password-heading"
                    >
                        <h2
                            id="account-password-heading"
                            class="text-base font-semibold"
                        >
                            {{ account ? 'Kata sandi' : 'Kata sandi awal' }}
                        </h2>
                        <template v-if="canSetPassword">
                            <p class="text-xs leading-6 text-muted-foreground">
                                {{
                                    account
                                        ? 'Kosongkan kedua kolom jika kata sandi tidak perlu diubah.'
                                        : 'Tentukan kata sandi untuk login pertama, lalu sampaikan langsung kepada pemilik akun.'
                                }}
                                Pengguna dapat mengganti kata sandi melalui
                                Pengaturan keamanan.
                            </p>
                            <div>
                                <label
                                    for="account-password"
                                    class="mb-2 block text-sm font-medium"
                                    >{{
                                        account
                                            ? 'Kata sandi baru'
                                            : 'Kata sandi'
                                    }}
                                    <span
                                        v-if="!account"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                ><PasswordInput
                                    id="account-password"
                                    v-model="form.password"
                                    :required="!account"
                                    :minlength="passwordMinimum"
                                    maxlength="255"
                                    autocomplete="new-password"
                                    class="sisurat-input"
                                    :passwordrules="passwordRules"
                                    :aria-invalid="
                                        Boolean(form.errors.password)
                                    "
                                    aria-describedby="account-password-hint account-password-error"
                                />
                                <p
                                    id="account-password-hint"
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    {{ passwordHint }}
                                </p>
                                <InputError
                                    id="account-password-error"
                                    class="mt-2"
                                    :message="form.errors.password"
                                />
                            </div>
                            <div>
                                <label
                                    for="account-password_confirmation"
                                    class="mb-2 block text-sm font-medium"
                                    >Konfirmasi kata sandi
                                    <span
                                        v-if="!account || form.password"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                ><PasswordInput
                                    id="account-password_confirmation"
                                    v-model="form.password_confirmation"
                                    :required="
                                        !account || Boolean(form.password)
                                    "
                                    :minlength="passwordMinimum"
                                    maxlength="255"
                                    autocomplete="new-password"
                                    class="sisurat-input"
                                    :passwordrules="passwordRules"
                                    :aria-invalid="
                                        Boolean(
                                            form.errors.password_confirmation,
                                        )
                                    "
                                    aria-describedby="account-password_confirmation-error"
                                /><InputError
                                    id="account-password_confirmation-error"
                                    class="mt-2"
                                    :message="form.errors.password_confirmation"
                                />
                            </div>
                        </template>
                        <div v-else class="space-y-3">
                            <p class="text-sm leading-7 text-muted-foreground">
                                Untuk mengubah kata sandi akun Anda, buka
                                Pengaturan keamanan dan masukkan kata sandi saat
                                ini.
                            </p>
                            <Link
                                :href="editSecurity()"
                                class="sisurat-secondary-action"
                                :class="{
                                    'pointer-events-none opacity-50':
                                        form.processing,
                                }"
                                :aria-disabled="form.processing"
                                @click="
                                    form.processing && $event.preventDefault()
                                "
                                >Buka Pengaturan keamanan</Link
                            >
                        </div>
                    </section>

                    <p
                        v-if="requestError"
                        class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        {{ requestError }}
                    </p>
                    <footer
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-xs leading-6 text-muted-foreground">
                            {{
                                account
                                    ? 'Perubahan akses berlaku setelah disimpan.'
                                    : 'Akun hanya dapat dibuat melalui halaman ini oleh Admin.'
                            }}
                        </p>
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" />{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : account
                                      ? 'Simpan perubahan'
                                      : 'Buat akun'
                            }}
                        </button>
                    </footer>
                </fieldset>
            </form>
        </div>
    </main>
</template>
