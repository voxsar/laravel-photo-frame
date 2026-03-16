<template>
    <div class="photo-frame-app">
        <nav class="app-nav">
            <a href="/" class="nav-link nav-link--active">Single Image</a>
            <a href="/batch" class="nav-link">Batch Processing</a>
        </nav>

        <h1 class="title">Photo Frame</h1>

        <div v-if="activeFrame" class="frame-info">
            <p>Active Frame: <strong>{{ activeFrame.name }}</strong></p>
        </div>
        <div v-else-if="frameLoaded && !activeFrame" class="frame-warning">
            <p>No active photo frame configured. Please set one up in the admin panel.</p>
        </div>

        <!-- Drop Zone -->
        <div
            class="drop-zone"
            :class="{ 'drop-zone--active': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="$refs.fileInput.click()"
        >
            <div v-if="!preview" class="drop-zone__placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" class="drop-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.338-2.32 5.75 5.75 0 0 1 1.822 8.095" />
                </svg>
                <span>Drag &amp; drop an image here, or click to browse</span>
            </div>
            <div v-else class="drop-zone__preview">
                <img :src="preview" alt="Preview" />
                <button class="btn btn--secondary" @click.stop="reset">Change Image</button>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            class="hidden-input"
            @change="onFileSelect"
        />

        <p class="auto-trigger-note">
            Frame is applied instantly in your browser once an image is selected.
        </p>

        <div v-if="isProcessing" class="processing-status">Applying frame…</div>

        <div v-if="error" class="alert alert--error">{{ error }}</div>

        <!-- Results -->
        <div v-if="results" class="results">
            <h2>Results</h2>
            <div class="results__grid">
                <div class="result-card">
                    <h3>Fill (Cover)</h3>
                    <img :src="results.coverUrl" alt="Fill output" />
                    <button class="btn btn--secondary" @click="downloadResult('cover')">Download</button>
                </div>
                <div class="result-card">
                    <h3>Contain</h3>
                    <img :src="results.containUrl" alt="Contain output" />
                    <button class="btn btn--secondary" @click="downloadResult('contain')">Download</button>
                </div>
            </div>
        </div>

        <div class="history">
            <div class="history__header">
                <h2>Past Entries</h2>
                <button class="btn btn--secondary" :disabled="historyLoading" @click="fetchOutputs(currentPage)">
                    Refresh
                </button>
            </div>

            <div v-if="historyLoading" class="history__loading">Loading entries…</div>

            <div v-else-if="historyItems.length" class="history__list">
                <article v-for="item in historyItems" :key="item.id" class="history-item">
                    <img :src="item.fill_url" :alt="`Output ${item.id}`" class="history-item__thumb" />
                    <div class="history-item__meta">
                        <p><strong>{{ item.original_filename }}</strong></p>
                        <p v-if="item.photo_frame_name">Frame: {{ item.photo_frame_name }}</p>
                        <p>{{ item.created_at_human || item.created_at }}</p>
                    </div>
                    <div class="history-item__actions">
                        <a :href="item.fill_download_url" target="_blank" rel="noopener" class="btn btn--secondary">Fill</a>
                        <a :href="item.contain_download_url" target="_blank" rel="noopener" class="btn btn--secondary">Contain</a>
                    </div>
                </article>
            </div>

            <p v-else class="history__empty">No entries yet.</p>

            <div v-if="lastPage > 1" class="history__pagination">
                <button class="btn btn--secondary" :disabled="currentPage <= 1 || historyLoading" @click="fetchOutputs(currentPage - 1)">
                    Previous
                </button>
                <span>Page {{ currentPage }} of {{ lastPage }}</span>
                <button class="btn btn--secondary" :disabled="currentPage >= lastPage || historyLoading" @click="fetchOutputs(currentPage + 1)">
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { applyCover, applyContain, downloadBlob } from '../frameProcessor.js';

export default {
    name: 'PhotoFrameApp',

    data() {
        return {
            isDragging: false,
            selectedFile: null,
            preview: null,
            isProcessing: false,
            results: null,
            error: null,
            activeFrame: null,
            frameLoaded: false,
            frameImg: null,
            frameObjectUrl: null,
            historyItems: [],
            historyLoading: false,
            currentPage: 1,
            lastPage: 1,
            fetchToken: 0,
        };
    },

    mounted() {
        this.loadActiveFrame();
        this.fetchOutputs(1);
    },

    unmounted() {
        this.revokeResultUrls();
        if (this.frameObjectUrl) URL.revokeObjectURL(this.frameObjectUrl);
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
                // Fetch the frame image via the same-origin proxy so the Canvas
                // does not become tainted by cross-origin content.
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
            }
        },

        onDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file) this.setFile(file);
        },

        onFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.setFile(file);
        },

        setFile(file) {
            this.error = null;
            this.revokeResultUrls();
            this.results = null;
            this.selectedFile = file;
            this.preview = URL.createObjectURL(file);
            this.processImage();
        },

        reset() {
            this.revokeResultUrls();
            this.selectedFile = null;
            this.preview = null;
            this.results = null;
            this.error = null;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },

        revokeResultUrls() {
            if (this.results) {
                if (this.results.coverUrl)   URL.revokeObjectURL(this.results.coverUrl);
                if (this.results.containUrl) URL.revokeObjectURL(this.results.containUrl);
            }
        },

        async fetchOutputs(page = 1) {
            this.historyLoading = true;
            const token = ++this.fetchToken;

            try {
                const { data } = await axios.get('/api/frame-outputs', {
                    params: { page, per_page: 6 },
                });

                if (token !== this.fetchToken) return;

                this.historyItems = data.data ?? [];
                this.currentPage  = data.meta?.current_page ?? 1;
                this.lastPage     = data.meta?.last_page ?? 1;
            } catch (e) {
                if (!this.error) {
                    this.error = 'Could not load past entries.';
                }
            } finally {
                if (token === this.fetchToken) {
                    this.historyLoading = false;
                }
            }
        },

        async processImage() {
            if (!this.selectedFile) return;

            if (!this.frameImg) {
                this.error = 'Frame image is not loaded yet. Please wait and try again.';
                return;
            }

            this.isProcessing = true;
            this.error = null;

            try {
                const anchor = this.activeFrame?.anchor_point ?? 'center';

                const [coverBlob, containBlob] = await Promise.all([
                    applyCover(this.selectedFile, this.frameImg, anchor),
                    applyContain(this.selectedFile, this.frameImg),
                ]);

                this.results = {
                    coverBlob,
                    containBlob,
                    coverUrl:   URL.createObjectURL(coverBlob),
                    containUrl: URL.createObjectURL(containBlob),
                };
            } catch (e) {
                this.error = e.message || 'An unexpected error occurred. Please try again.';
            } finally {
                this.isProcessing = false;
            }
        },

        downloadResult(variant) {
            if (!this.results || !this.selectedFile) return;

            const baseName = this.selectedFile.name.replace(/\.[^.]+$/, '');

            if (variant === 'cover') {
                downloadBlob(this.results.coverBlob, `${baseName}_fill.jpg`);
            } else {
                downloadBlob(this.results.containBlob, `${baseName}_contain.png`);
            }
        },
    },
};
</script>

<style scoped>
.photo-frame-app {
    max-width: 900px;
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
    margin-bottom: 1.25rem;
    color: #4a5568;
}

.frame-warning {
    text-align: center;
    margin-bottom: 1.25rem;
    color: #c05621;
    background: #fefcbf;
    border: 1px solid #f6e05e;
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
}

.drop-zone {
    border: 2px dashed #a0aec0;
    border-radius: 0.75rem;
    padding: 3rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: #f7fafc;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.drop-zone--active {
    border-color: #4299e1;
    background: #ebf8ff;
}

.drop-zone__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    color: #718096;
}

.drop-icon {
    width: 3rem;
    height: 3rem;
    color: #a0aec0;
}

.drop-zone__preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.drop-zone__preview img {
    max-height: 200px;
    max-width: 100%;
    border-radius: 0.375rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.hidden-input {
    display: none;
}

.auto-trigger-note {
    margin-top: 1rem;
    text-align: center;
    color: #4a5568;
    font-size: 0.95rem;
}

.processing-status {
    margin-top: 1rem;
    text-align: center;
    color: #2b6cb0;
    font-weight: 600;
}

.btn {
    padding: 0.625rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.95rem;
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

.btn--secondary:hover {
    background: #cbd5e0;
}

.btn--secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.alert {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.9rem;
}

.alert--error {
    background: #fff5f5;
    border: 1px solid #fc8181;
    color: #c53030;
}

.results {
    margin-top: 2.5rem;
}

.results h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.25rem;
    text-align: center;
}

.results__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.result-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.25rem;
    text-align: center;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
}

.result-card h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #2d3748;
}

.result-card img {
    max-width: 100%;
    border-radius: 0.375rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

.history {
    margin-top: 2.5rem;
}

.history__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    gap: 0.75rem;
}

.history__header h2 {
    margin: 0;
}

.history__loading,
.history__empty {
    text-align: center;
    color: #4a5568;
    margin: 1rem 0;
}

.history__list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.history-item {
    display: grid;
    grid-template-columns: 96px 1fr auto;
    gap: 0.9rem;
    align-items: center;
    padding: 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #fff;
}

.history-item__thumb {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 0.5rem;
}

.history-item__meta p {
    margin: 0.2rem 0;
    color: #2d3748;
}

.history-item__actions {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.history__pagination {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
}

@media (max-width: 640px) {
    .history-item {
        grid-template-columns: 1fr;
    }

    .history-item__thumb {
        width: 100%;
        height: auto;
        max-height: 220px;
    }

    .history-item__actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
}
</style>
