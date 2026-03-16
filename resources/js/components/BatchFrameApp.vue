<template>
    <div class="batch-app">
        <nav class="app-nav">
            <a href="/" class="nav-link">Single Image</a>
            <a href="/batch" class="nav-link nav-link--active">Batch Processing</a>
        </nav>

        <h1 class="title">Batch Frame Processing</h1>

        <div v-if="activeFrame" class="frame-info">
            <p>Active Frame: <strong>{{ activeFrame.name }}</strong></p>
        </div>
        <div v-else-if="frameLoaded && !activeFrame" class="frame-warning">
            <p>No active photo frame configured. Please set one up in the admin panel.</p>
        </div>

        <!-- Global mode selector -->
        <div class="mode-selector">
            <span class="mode-label">Fit mode:</span>
            <label class="mode-option" :class="{ 'mode-option--active': mode === 'cover' }">
                <input type="radio" v-model="mode" value="cover" />
                Cover (Fill)
            </label>
            <label class="mode-option" :class="{ 'mode-option--active': mode === 'contain' }">
                <input type="radio" v-model="mode" value="contain" />
                Contain (Fit)
            </label>
        </div>

        <!-- Drop zone for multiple files -->
        <div
            class="drop-zone"
            :class="{ 'drop-zone--active': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="$refs.fileInput.click()"
        >
            <div class="drop-zone__placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" class="drop-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.338-2.32 5.75 5.75 0 0 1 1.822 8.095" />
                </svg>
                <span>Drag &amp; drop multiple images here, or click to browse</span>
                <span class="drop-zone__hint">JPEG, PNG, GIF, WebP supported</span>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            multiple
            class="hidden-input"
            @change="onFileSelect"
        />

        <div v-if="error" class="alert alert--error">{{ error }}</div>

        <!-- File queue -->
        <div v-if="items.length" class="queue-toolbar">
            <span class="queue-count">{{ items.length }} image{{ items.length !== 1 ? 's' : '' }} selected</span>
            <div class="queue-toolbar__actions">
                <button
                    class="btn btn--primary"
                    :disabled="isProcessing || !frameImg"
                    @click="processAll"
                >
                    {{ isProcessing ? 'Processing…' : 'Process All' }}
                </button>
                <button
                    v-if="allDone"
                    class="btn btn--secondary"
                    @click="downloadAll"
                >
                    Download All
                </button>
                <button class="btn btn--secondary" @click="clearAll">Clear</button>
            </div>
        </div>

        <div v-if="items.length" class="results-grid">
            <div
                v-for="item in items"
                :key="item.id"
                class="result-card"
                :class="{
                    'result-card--processing': item.status === 'processing',
                    'result-card--done': item.status === 'done',
                    'result-card--error': item.status === 'error',
                }"
            >
                <!-- Source preview -->
                <div class="result-card__preview-wrap">
                    <img
                        v-if="item.resultUrl"
                        :src="item.resultUrl"
                        :alt="item.name"
                        class="result-card__img"
                    />
                    <img
                        v-else
                        :src="item.previewUrl"
                        :alt="item.name"
                        class="result-card__img result-card__img--original"
                    />
                    <div v-if="item.status === 'processing'" class="result-card__overlay">
                        <span class="spinner"></span>
                    </div>
                </div>

                <div class="result-card__info">
                    <p class="result-card__name" :title="item.name">{{ item.name }}</p>
                    <p v-if="item.status === 'error'" class="result-card__error">{{ item.errorMsg }}</p>
                </div>

                <button
                    v-if="item.status === 'done'"
                    class="btn btn--secondary result-card__dl"
                    @click="downloadItem(item)"
                >
                    Download
                </button>
            </div>
        </div>

        <p v-if="!items.length" class="empty-hint">
            Select images above to get started. All processing happens entirely in your browser.
        </p>
    </div>
</template>

<script>
import axios from 'axios';
import { applyCover, applyContain, downloadBlob } from '../frameProcessor.js';

let _itemId = 0;

export default {
    name: 'BatchFrameApp',

    data() {
        return {
            mode: 'cover',
            isDragging: false,
            activeFrame: null,
            frameLoaded: false,
            frameImg: null,
            frameObjectUrl: null,
            items: [],
            isProcessing: false,
            error: null,
        };
    },

    computed: {
        allDone() {
            return this.items.length > 0 && this.items.every(i => i.status === 'done' || i.status === 'error');
        },
    },

    mounted() {
        this.loadActiveFrame();
    },

    beforeUnmount() {
        if (this.frameObjectUrl) URL.revokeObjectURL(this.frameObjectUrl);
        this.items.forEach(i => {
            if (i.previewUrl) URL.revokeObjectURL(i.previewUrl);
            if (i.resultUrl)  URL.revokeObjectURL(i.resultUrl);
        });
    },

    methods: {
        async loadActiveFrame() {
            try {
                const { data } = await axios.get('/api/active-frame');
                this.activeFrame = data.frame;
                if (this.activeFrame) {
                    await this.loadFrameImage();
                }
            } catch (e) {
                console.error('Could not load active frame', e);
            } finally {
                this.frameLoaded = true;
            }
        },

        async loadFrameImage() {
            try {
                const response = await fetch('/api/frame-image');
                if (!response.ok) throw new Error('Frame image not available');
                const blob = await response.blob();

                // Revoke any previously held object URL before creating a new one.
                if (this.frameObjectUrl) URL.revokeObjectURL(this.frameObjectUrl);
                const objectUrl = URL.createObjectURL(blob);
                this.frameObjectUrl = objectUrl;

                await new Promise((resolve, reject) => {
                    const img = new Image();
                    img.onload = () => {
                        this.frameImg = img;
                        resolve();
                    };
                    img.onerror = reject;
                    img.src = objectUrl;
                });
            } catch (e) {
                console.error('Could not load frame image', e);
                this.error = 'Could not load the active frame image. Please try refreshing.';
            }
        },

        onDrop(event) {
            this.isDragging = false;
            this.addFiles(Array.from(event.dataTransfer.files));
        },

        onFileSelect(event) {
            this.addFiles(Array.from(event.target.files));
            event.target.value = '';
        },

        addFiles(files) {
            const accepted = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const filtered = files.filter(f => accepted.includes(f.type));

            if (filtered.length < files.length) {
                this.error = 'Some files were skipped (only JPEG, PNG, GIF and WebP are accepted).';
            } else {
                this.error = null;
            }

            for (const file of filtered) {
                this.items.push({
                    id:         ++_itemId,
                    file,
                    name:       file.name,
                    previewUrl: URL.createObjectURL(file),
                    resultUrl:  null,
                    resultBlob: null,
                    status:     'pending',
                    errorMsg:   null,
                });
            }
        },

        clearAll() {
            this.items.forEach(i => {
                if (i.previewUrl) URL.revokeObjectURL(i.previewUrl);
                if (i.resultUrl)  URL.revokeObjectURL(i.resultUrl);
            });
            this.items = [];
            this.error = null;
        },

        async processAll() {
            if (!this.frameImg) {
                this.error = 'Frame image is not loaded yet. Please wait and try again.';
                return;
            }

            this.isProcessing = true;
            const anchor = this.activeFrame?.anchor_point ?? 'center';

            for (const item of this.items) {
                if (item.status === 'done') continue;

                item.status = 'processing';
                item.errorMsg = null;

                try {
                    const blob = this.mode === 'cover'
                        ? await applyCover(item.file, this.frameImg, anchor)
                        : await applyContain(item.file, this.frameImg);

                    if (item.resultUrl) URL.revokeObjectURL(item.resultUrl);
                    item.resultBlob = blob;
                    item.resultUrl  = URL.createObjectURL(blob);
                    item.status     = 'done';
                } catch (e) {
                    item.status   = 'error';
                    item.errorMsg = e.message || 'Processing failed';
                }
            }

            this.isProcessing = false;
        },

        downloadItem(item) {
            if (!item.resultBlob) return;
            const baseName = item.name.replace(/\.[^.]+$/, '');
            const ext      = this.mode === 'cover' ? 'jpg' : 'png';
            const suffix   = this.mode === 'cover' ? 'fill' : 'contain';
            downloadBlob(item.resultBlob, `${baseName}_${suffix}.${ext}`);
        },

        async downloadAll() {
            const done = this.items.filter(i => i.status === 'done');
            for (const item of done) {
                this.downloadItem(item);
                // Small delay so the browser accepts multiple download triggers.
                await new Promise(r => setTimeout(r, 150));
            }
        },
    },
};
</script>

<style scoped>
.batch-app {
    max-width: 1100px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: system-ui, -apple-system, sans-serif;
    color: #1a202c;
}

.app-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.75rem;
}

.nav-link {
    padding: 0.4rem 1rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    color: #4a5568;
    transition: background 0.15s, color 0.15s;
}

.nav-link:hover {
    background: #edf2f7;
    color: #2d3748;
}

.nav-link--active {
    background: #4299e1;
    color: #fff;
}

.nav-link--active:hover {
    background: #3182ce;
    color: #fff;
}

.title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-align: center;
}

.frame-info {
    text-align: center;
    margin-bottom: 1rem;
    color: #4a5568;
}

.frame-warning {
    text-align: center;
    margin-bottom: 1rem;
    color: #c05621;
    background: #fefcbf;
    border: 1px solid #f6e05e;
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
}

/* ── Mode selector ── */
.mode-selector {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.mode-label {
    font-weight: 600;
    color: #4a5568;
}

.mode-option {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 1rem;
    border-radius: 0.375rem;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    font-weight: 500;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}

.mode-option input[type="radio"] {
    accent-color: #4299e1;
}

.mode-option--active {
    border-color: #4299e1;
    background: #ebf8ff;
    color: #2b6cb0;
}

/* ── Drop zone ── */
.drop-zone {
    border: 2px dashed #a0aec0;
    border-radius: 0.75rem;
    padding: 2.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: #f7fafc;
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.drop-zone--active {
    border-color: #4299e1;
    background: #ebf8ff;
}

.drop-zone__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #718096;
}

.drop-zone__hint {
    font-size: 0.85rem;
    color: #a0aec0;
}

.drop-icon {
    width: 2.5rem;
    height: 2.5rem;
    color: #a0aec0;
}

.hidden-input {
    display: none;
}

/* ── Alerts ── */
.alert {
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.9rem;
}

.alert--error {
    background: #fff5f5;
    border: 1px solid #fc8181;
    color: #c53030;
}

/* ── Queue toolbar ── */
.queue-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.queue-count {
    font-weight: 600;
    color: #4a5568;
}

.queue-toolbar__actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ── Buttons ── */
.btn {
    padding: 0.55rem 1.25rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    transition: background 0.15s, opacity 0.15s;
    text-decoration: none;
    display: inline-block;
}

.btn--primary {
    background: #4299e1;
    color: #fff;
}

.btn--primary:hover:not(:disabled) {
    background: #3182ce;
}

.btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn--secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn--secondary:hover:not(:disabled) {
    background: #cbd5e0;
}

.btn--secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Results grid ── */
.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.result-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
    display: flex;
    flex-direction: column;
    transition: border-color 0.2s;
}

.result-card--processing {
    border-color: #90cdf4;
}

.result-card--done {
    border-color: #68d391;
}

.result-card--error {
    border-color: #fc8181;
}

.result-card__preview-wrap {
    position: relative;
    width: 100%;
    padding-top: 100%;
    background: #f7fafc;
    overflow: hidden;
}

.result-card__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.result-card__img--original {
    opacity: 0.6;
}

.result-card__overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Spinner */
.spinner {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 3px solid #bee3f8;
    border-top-color: #4299e1;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.result-card__info {
    padding: 0.6rem 0.75rem 0.4rem;
    flex: 1;
}

.result-card__name {
    font-size: 0.8rem;
    color: #4a5568;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}

.result-card__error {
    font-size: 0.78rem;
    color: #c53030;
    margin: 0.25rem 0 0;
}

.result-card__dl {
    margin: 0 0.75rem 0.75rem;
    width: calc(100% - 1.5rem);
    text-align: center;
}

/* ── Empty state ── */
.empty-hint {
    text-align: center;
    color: #718096;
    margin-top: 1rem;
}
</style>
