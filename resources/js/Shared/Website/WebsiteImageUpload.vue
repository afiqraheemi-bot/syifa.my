<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { browserHttpRequest } from '../Authentication/session.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
    assetUrlTemplate: { type: String, required: true },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    aspectRatio: { type: Number, default: 4 / 3 },
    aspectRatioOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'uploaded']);
const uploading = ref(false);
const error = ref('');
const details = ref('');
const cropDialog = ref(null);
const cropCanvas = ref(null);
const cropImage = ref(null);
const cropFile = ref(null);
const cropZoom = ref(1);
const cropX = ref(50);
const cropY = ref(50);
const cropAspectRatio = ref(props.aspectRatio);
const removePlainBackground = ref(false);
const preparingImage = ref(false);
const previewUrl = computed(() =>
    props.modelValue
        ? props.assetUrlTemplate.replace('__ASSET_ID__', encodeURIComponent(props.modelValue))
        : '',
);

function cropRectangle() {
    const image = cropImage.value;
    if (!image) return null;
    const sourceRatio = image.naturalWidth / image.naturalHeight;
    let width = image.naturalWidth;
    let height = image.naturalHeight;
    if (sourceRatio > cropAspectRatio.value) width = height * cropAspectRatio.value;
    else height = width / cropAspectRatio.value;
    width /= cropZoom.value;
    height /= cropZoom.value;

    return {
        x: (image.naturalWidth - width) * (cropX.value / 100),
        y: (image.naturalHeight - height) * (cropY.value / 100),
        width,
        height,
    };
}

function drawCropPreview() {
    const canvas = cropCanvas.value;
    const image = cropImage.value;
    const crop = cropRectangle();
    if (!canvas || !image || !crop) return;
    canvas.width = 900;
    canvas.height = Math.round(canvas.width / cropAspectRatio.value);
    const context = canvas.getContext('2d');
    context?.drawImage(
        image,
        crop.x,
        crop.y,
        crop.width,
        crop.height,
        0,
        0,
        canvas.width,
        canvas.height,
    );
    if (removePlainBackground.value) applyPlainBackgroundRemoval(canvas);
}

watch([cropZoom, cropX, cropY, cropAspectRatio, removePlainBackground], drawCropPreview);

function colourDistance(data, offset, red, green, blue) {
    return Math.hypot(data[offset] - red, data[offset + 1] - green, data[offset + 2] - blue);
}

function backgroundReference(data, width, height) {
    const corners = [0, (width - 1) * 4, (height - 1) * width * 4, (height * width - 1) * 4];
    let closest = [corners[0], corners[1]];
    let closestDistance = Number.POSITIVE_INFINITY;

    for (let first = 0; first < corners.length; first += 1) {
        for (let second = first + 1; second < corners.length; second += 1) {
            const distance = colourDistance(
                data,
                corners[first],
                data[corners[second]],
                data[corners[second] + 1],
                data[corners[second] + 2],
            );
            if (distance < closestDistance) {
                closestDistance = distance;
                closest = [corners[first], corners[second]];
            }
        }
    }

    return {
        red: Math.round((data[closest[0]] + data[closest[1]]) / 2),
        green: Math.round((data[closest[0] + 1] + data[closest[1] + 1]) / 2),
        blue: Math.round((data[closest[0] + 2] + data[closest[1] + 2]) / 2),
    };
}

function applyPlainBackgroundRemoval(canvas) {
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return;

    const width = canvas.width;
    const height = canvas.height;
    const pixels = context.getImageData(0, 0, width, height);
    const { data } = pixels;
    const reference = backgroundReference(data, width, height);
    const visited = new Uint8Array(width * height);
    const queue = new Int32Array(width * height);
    let head = 0;
    let tail = 0;

    const enqueue = (index) => {
        if (visited[index]) return;
        const offset = index * 4;
        if (
            data[offset + 3] === 0 ||
            colourDistance(data, offset, reference.red, reference.green, reference.blue) > 58
        )
            return;
        visited[index] = 1;
        queue[tail] = index;
        tail += 1;
    };

    for (let x = 0; x < width; x += 1) {
        enqueue(x);
        enqueue((height - 1) * width + x);
    }
    for (let y = 1; y < height - 1; y += 1) {
        enqueue(y * width);
        enqueue(y * width + width - 1);
    }

    while (head < tail) {
        const index = queue[head];
        head += 1;
        data[index * 4 + 3] = 0;
        const x = index % width;
        const y = Math.floor(index / width);
        if (x > 0) enqueue(index - 1);
        if (x + 1 < width) enqueue(index + 1);
        if (y > 0) enqueue(index - width);
        if (y + 1 < height) enqueue(index + width);
    }

    context.putImageData(pixels, 0, 0);
}

function resetCrop() {
    cropFile.value = null;
    cropImage.value = null;
    cropZoom.value = 1;
    cropX.value = 50;
    cropY.value = 50;
    cropAspectRatio.value = props.aspectRatio;
    removePlainBackground.value = false;
    preparingImage.value = false;
}

function cancelCrop() {
    cropDialog.value?.close();
    resetCrop();
}

async function chooseImage(event) {
    const input = event.currentTarget;
    const file = input.files?.[0];
    if (!file || uploading.value) return;

    error.value = '';
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 8 * 1024 * 1024
    ) {
        error.value = 'Choose a JPEG, PNG or WebP image no larger than 8 MB.';
        input.value = '';
        return;
    }

    cropFile.value = file;
    const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () =>
            typeof reader.result === 'string'
                ? resolve(reader.result)
                : reject(new Error('Image could not be read.'));
        reader.onerror = () => reject(new Error('Image could not be read.'));
        reader.readAsDataURL(file);
    }).catch(() => null);
    if (!dataUrl) {
        error.value = 'The selected image could not be opened.';
        resetCrop();
        input.value = '';
        return;
    }
    const image = new Image();
    image.onload = async () => {
        cropImage.value = image;
        await nextTick();
        drawCropPreview();
        cropDialog.value?.showModal();
    };
    image.onerror = () => {
        error.value = 'The selected image could not be opened.';
        resetCrop();
    };
    image.src = dataUrl;
    input.value = '';
}

async function confirmCrop() {
    const source = cropFile.value;
    const image = cropImage.value;
    const crop = cropRectangle();
    if (!source || !image || !crop || uploading.value || preparingImage.value) return;

    preparingImage.value = true;
    await nextTick();
    await new Promise((resolve) => window.setTimeout(resolve, 0));

    const output = document.createElement('canvas');
    output.width = Math.max(1, Math.min(2400, Math.round(crop.width)));
    output.height = Math.max(1, Math.round(output.width / cropAspectRatio.value));
    output
        .getContext('2d')
        ?.drawImage(
            image,
            crop.x,
            crop.y,
            crop.width,
            crop.height,
            0,
            0,
            output.width,
            output.height,
        );
    if (removePlainBackground.value) applyPlainBackgroundRemoval(output);
    const outputType = removePlainBackground.value ? 'image/png' : source.type;
    const blob = await new Promise((resolve) =>
        output.toBlob(resolve, outputType, outputType === 'image/png' ? undefined : 0.9),
    );
    if (!blob) {
        error.value = 'The cropped image could not be prepared.';
        preparingImage.value = false;
        return;
    }
    const extension = outputType === 'image/jpeg' ? 'jpg' : outputType.split('/')[1];
    const cropped = new File([blob], `website-image.${extension}`, { type: outputType });
    cropDialog.value?.close();
    resetCrop();
    await uploadCroppedImage(cropped);
}

async function uploadCroppedImage(file) {
    uploading.value = true;
    error.value = '';
    details.value = '';
    const body = new FormData();
    body.append('image', file);

    try {
        const response = await browserHttpRequest(props.uploadUrl, { method: 'POST', body });
        if (!response.ok) {
            const validation = response.body?.errors?.image;
            throw new Error(
                (Array.isArray(validation) ? validation[0] : null) ??
                    response.body?.message ??
                    'Image could not be uploaded.',
            );
        }
        emit('update:modelValue', response.body.data.asset_id);
        emit('uploaded', response.body.data);
        details.value = `${response.body.data.width} × ${response.body.data.height}px`;
    } catch (exception) {
        error.value =
            exception instanceof Error ? exception.message : 'Image could not be uploaded.';
    } finally {
        uploading.value = false;
    }
}

function remove() {
    if (uploading.value) return;
    emit('update:modelValue', '');
    details.value = '';
    error.value = '';
}

onBeforeUnmount(resetCrop);
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm font-semibold text-slate-800">{{ label }}</p>
        <div v-if="modelValue" class="mt-3 grid gap-3 sm:grid-cols-[8rem_1fr] sm:items-center">
            <img
                :src="previewUrl"
                alt="Selected website image preview"
                class="h-28 w-full rounded-lg border border-slate-200 bg-white object-cover sm:w-32"
            />
            <div>
                <p class="text-sm font-medium text-emerald-800">Image attached</p>
                <p v-if="details" class="mt-1 text-xs text-slate-600">{{ details }}</p>
                <button
                    type="button"
                    class="mt-2 min-h-10 rounded-lg border border-red-300 bg-white px-3 text-sm font-semibold text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-60"
                    :disabled="disabled || uploading"
                    @click="remove"
                >
                    Remove image
                </button>
            </div>
        </div>
        <label class="mt-3 block text-sm font-semibold text-slate-800">
            {{ modelValue ? 'Upload a replacement' : 'Upload a new image' }}
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-2 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:min-h-11 file:border-0 file:bg-emerald-700 file:px-4 file:font-semibold file:text-white hover:file:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="disabled || uploading"
                :required="required && !modelValue"
                @change="chooseImage"
            />
        </label>
        <p class="mt-2 text-xs text-slate-600">JPEG, PNG or WebP. Maximum 8 MB.</p>
        <p v-if="uploading" role="status" class="mt-2 text-sm font-medium text-emerald-800">
            Uploading image…
        </p>
        <p v-if="error" role="alert" class="mt-2 text-sm font-medium text-red-700">{{ error }}</p>

        <dialog
            ref="cropDialog"
            class="m-auto w-[min(94vw,52rem)] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-950/70"
            aria-labelledby="crop-dialog-title"
            @cancel.prevent="cancelCrop"
            @close="resetCrop"
        >
            <div class="p-5 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="crop-dialog-title" class="text-xl font-bold text-slate-950">
                            Crop {{ label }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Adjust the image position and zoom before uploading.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border border-slate-300 px-4 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                        @click="cancelCrop"
                    >
                        Cancel
                    </button>
                </div>

                <div
                    class="mt-5 overflow-hidden rounded-xl"
                    :class="removePlainBackground ? 'transparent-preview' : 'bg-slate-950'"
                >
                    <canvas ref="cropCanvas" class="block max-h-[55vh] w-full object-contain" />
                </div>

                <fieldset v-if="aspectRatioOptions.length" class="mt-5">
                    <legend class="text-sm font-semibold text-slate-800">Logo shape</legend>
                    <p class="mt-1 text-xs text-slate-600">
                        Choose the shape that fits the logo artwork closely, without large empty
                        margins.
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in aspectRatioOptions"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-semibold text-slate-800 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50"
                        >
                            <input
                                v-model.number="cropAspectRatio"
                                type="radio"
                                name="website_image_aspect_ratio"
                                :value="option.value"
                                class="accent-emerald-700"
                            />
                            {{ option.label }}
                        </label>
                    </div>
                </fieldset>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <label class="text-sm font-semibold text-slate-800">
                        Zoom
                        <input
                            v-model.number="cropZoom"
                            class="mt-2 w-full accent-emerald-700"
                            type="range"
                            min="1"
                            max="3"
                            step="0.05"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Horizontal position
                        <input
                            v-model.number="cropX"
                            class="mt-2 w-full accent-emerald-700"
                            type="range"
                            min="0"
                            max="100"
                            step="1"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Vertical position
                        <input
                            v-model.number="cropY"
                            class="mt-2 w-full accent-emerald-700"
                            type="range"
                            min="0"
                            max="100"
                            step="1"
                        />
                    </label>
                </div>

                <label
                    class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <input
                        v-model="removePlainBackground"
                        type="checkbox"
                        class="mt-1 size-5 shrink-0 accent-emerald-700"
                    />
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">
                            Remove plain background
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-slate-600">
                            Best for logos and images with a solid background. Check the preview
                            before uploading; complex photo backgrounds should use the original.
                        </span>
                    </span>
                </label>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border border-slate-300 px-5 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                        :disabled="preparingImage"
                        @click="cancelCrop"
                    >
                        Choose another image
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-lg bg-emerald-700 px-5 font-semibold text-white focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                        :disabled="preparingImage"
                        @click="confirmCrop"
                    >
                        {{ preparingImage ? 'Preparing image…' : 'Crop and upload' }}
                    </button>
                </div>
            </div>
        </dialog>
    </div>
</template>

<style scoped>
.transparent-preview {
    background-color: #fff;
    background-image:
        linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
        linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
        linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
    background-position:
        0 0,
        0 8px,
        8px -8px,
        -8px 0;
    background-size: 16px 16px;
}
</style>
