<script setup lang="ts">
import { CheckCircle2, Eraser, PenLine, Undo2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type Point = { x: number; y: number };

const props = withDefaults(
    defineProps<{ id?: string; disabled?: boolean; error?: string }>(),
    { id: 'signature', disabled: false, error: '' },
);
const signature = defineModel<string>({ default: '' });
const canvas = ref<HTMLCanvasElement | null>(null);
const drawing = ref(false);
const strokeCount = ref(0);
const available = ref(true);
let strokes: Point[][] = [];
let pointerId: number | null = null;
let resizeObserver: ResizeObserver | null = null;

const instructionsId = computed(() => `${props.id}-instructions`);
const errorId = computed(() => `${props.id}-error`);

function isEmpty(): boolean {
    let distance = 0;
    const aspectRatio = canvas.value
        ? canvas.value.height / canvas.value.width
        : 0.4;
    for (const stroke of strokes) {
        for (let index = 1; index < stroke.length; index++) {
            distance += Math.hypot(
                stroke[index].x - stroke[index - 1].x,
                (stroke[index].y - stroke[index - 1].y) * aspectRatio,
            );
        }
    }
    return distance < 0.025;
}

function getSignature(): string {
    return canvas.value && !isEmpty()
        ? canvas.value.toDataURL('image/png')
        : '';
}

function updateSignature(): void {
    signature.value = getSignature();
    strokeCount.value = strokes.length;
}

function drawSegment(from: Point, to: Point): void {
    const surface = canvas.value;
    const context = surface?.getContext('2d');
    if (!surface || !context) return;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.lineWidth = Math.max(2, surface.width / 280);
    context.strokeStyle = '#172c24';
    context.beginPath();
    context.moveTo(from.x * surface.width, from.y * surface.height);
    context.lineTo(to.x * surface.width, to.y * surface.height);
    context.stroke();
}

function redraw(): void {
    const surface = canvas.value;
    const context = surface?.getContext('2d');
    if (!surface || !context) return;
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, surface.width, surface.height);
    for (const stroke of strokes) {
        for (let index = 1; index < stroke.length; index++) {
            drawSegment(stroke[index - 1], stroke[index]);
        }
    }
}

function resizeCanvas(): void {
    const surface = canvas.value;
    if (!surface) return;
    const { width, height } = surface.getBoundingClientRect();
    if (width < 1 || height < 1) return;
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const pixelWidth = Math.min(2048, Math.round(width * ratio));
    const pixelHeight = Math.min(1024, Math.round(height * ratio));
    if (surface.width === pixelWidth && surface.height === pixelHeight) return;
    surface.width = pixelWidth;
    surface.height = pixelHeight;
    redraw();
    if (!drawing.value) updateSignature();
}

function pointFrom(event: PointerEvent): Point {
    const bounds = canvas.value!.getBoundingClientRect();
    return {
        x: Math.min(
            1,
            Math.max(0, (event.clientX - bounds.left) / bounds.width),
        ),
        y: Math.min(
            1,
            Math.max(0, (event.clientY - bounds.top) / bounds.height),
        ),
    };
}

function startStroke(event: PointerEvent): void {
    if (
        props.disabled ||
        !available.value ||
        pointerId !== null ||
        !event.isPrimary ||
        event.button !== 0
    ) {
        return;
    }
    pointerId = event.pointerId;
    drawing.value = true;
    strokes.push([pointFrom(event)]);
    canvas.value?.setPointerCapture(event.pointerId);
}

function continueStroke(event: PointerEvent): void {
    if (event.pointerId !== pointerId || !drawing.value) return;
    const stroke = strokes[strokes.length - 1];
    const events = event.getCoalescedEvents?.() ?? [event];
    for (const sample of events.length ? events : [event]) {
        const point = pointFrom(sample);
        const previous = stroke[stroke.length - 1];
        if (Math.hypot(point.x - previous.x, point.y - previous.y) < 0.001) {
            continue;
        }
        stroke.push(point);
        drawSegment(previous, point);
    }
}

function endStroke(event: PointerEvent): void {
    if (event.pointerId !== pointerId) return;
    if (event.type === 'pointerup') continueStroke(event);
    pointerId = null;
    drawing.value = false;
    if (canvas.value?.hasPointerCapture(event.pointerId)) {
        canvas.value.releasePointerCapture(event.pointerId);
    }
    if (strokes[strokes.length - 1]?.length === 1) strokes.pop();
    updateSignature();
}

function clear(): void {
    const capturedPointer = pointerId;
    pointerId = null;
    drawing.value = false;
    if (
        capturedPointer !== null &&
        canvas.value?.hasPointerCapture(capturedPointer)
    ) {
        canvas.value.releasePointerCapture(capturedPointer);
    }
    strokes = [];
    redraw();
    updateSignature();
}

function undo(): void {
    if (props.disabled || drawing.value) return;
    strokes.pop();
    redraw();
    updateSignature();
}

watch(signature, (value) => {
    if (!value && !isEmpty()) clear();
});

onMounted(() => {
    available.value = Boolean(canvas.value?.getContext('2d'));
    redraw();
    resizeCanvas();
    if (typeof ResizeObserver !== 'undefined' && canvas.value) {
        resizeObserver = new ResizeObserver(resizeCanvas);
        resizeObserver.observe(canvas.value);
    }
    window.addEventListener('resize', resizeCanvas);
});

onUnmounted(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', resizeCanvas);
});

defineExpose({ clear, getSignature, isEmpty });
</script>

<template>
    <div class="grid min-w-0 gap-3">
        <p :id="instructionsId" class="text-sm leading-6 text-muted-foreground">
            Tulis tanda tangan menggunakan jari, stylus, atau mouse pada bidang
            putih. Urungkan goresan terakhir atau hapus untuk mengulang.
        </p>
        <div
            class="relative overflow-hidden rounded-xl border-2 bg-white transition-colors motion-reduce:transition-none"
            :class="error ? 'border-destructive' : 'border-border'"
        >
            <canvas
                :id="id"
                ref="canvas"
                width="600"
                height="240"
                role="img"
                aria-label="Bidang untuk menggambar tanda tangan"
                :aria-describedby="`${instructionsId}${error ? ` ${errorId}` : ''}`"
                :aria-invalid="Boolean(error)"
                :aria-disabled="disabled"
                class="signature-canvas block w-full touch-none select-none"
                :class="
                    disabled ? 'cursor-wait opacity-60' : 'cursor-crosshair'
                "
                @pointerdown.prevent="startStroke"
                @pointermove.prevent="continueStroke"
                @pointerup.prevent="endStroke"
                @pointercancel="endStroke"
                @lostpointercapture="endStroke"
                @contextmenu.prevent
            >
                Perangkat ini tidak mendukung bidang tanda tangan. Gunakan
                browser terbaru atau perangkat lain.
            </canvas>
            <span
                v-if="!strokeCount && !drawing"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 px-3 text-center text-sm text-[#61746a]"
            >
                <PenLine class="size-6 text-[#005a36]/60" aria-hidden="true" />
                <span>Tanda tangan di sini</span>
            </span>
            <span
                class="pointer-events-none absolute right-5 bottom-7 left-5 border-t border-dashed border-[#dce7e0]"
                aria-hidden="true"
            ></span>
        </div>
        <p v-if="!available" role="alert" class="text-sm text-destructive">
            Bidang tanda tangan belum tersedia. Gunakan browser terbaru atau
            perangkat lain.
        </p>
        <p v-if="error" :id="errorId" class="text-sm text-destructive">
            {{ error }}
        </p>
        <div
            class="grid min-w-0 gap-3 sm:flex sm:flex-wrap sm:items-center sm:justify-between"
        >
            <span
                aria-live="polite"
                class="flex items-center gap-1.5 text-xs"
                :class="signature ? 'text-primary' : 'text-muted-foreground'"
            >
                <CheckCircle2
                    v-if="signature"
                    class="size-3.5 shrink-0"
                    aria-hidden="true"
                />
                {{
                    signature
                        ? 'Tanda tangan terisi'
                        : 'Tanda tangan wajib diisi'
                }}
            </span>
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <button
                    type="button"
                    class="sisurat-secondary-action min-h-11 px-3 text-xs"
                    :disabled="disabled || drawing || !strokeCount"
                    @click="undo"
                >
                    <Undo2 class="size-4" aria-hidden="true" />
                    Urungkan
                </button>
                <button
                    type="button"
                    class="sisurat-secondary-action min-h-11 px-3 text-xs"
                    :disabled="disabled || drawing || !strokeCount"
                    @click="clear"
                >
                    <Eraser class="size-4" aria-hidden="true" />
                    Hapus semua
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.signature-canvas {
    height: clamp(11.5rem, 25vw, 14rem);
}
</style>
