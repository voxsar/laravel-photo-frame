/**
 * Load an HTMLImageElement from a URL or object-URL string.
 * The element is returned only after the image has fully decoded.
 *
 * @param {string} src
 * @returns {Promise<HTMLImageElement>}
 */
export function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Failed to load image: ' + src));
        img.src = src;
    });
}

/**
 * Apply "cover" (fill) mode: scale and crop the photo so it exactly fills the
 * frame canvas, then overlay the frame PNG on top.
 *
 * @param {Blob}             photoBlob   - The user's photo blob.
 * @param {HTMLImageElement} frameImg    - Pre-loaded frame image element.
 * @param {string}           anchorPoint - e.g. 'center', 'top-left', 'bottom'.
 * @returns {Promise<Blob>}  JPEG blob of the processed image.
 */
export async function applyCover(photoBlob, frameImg, anchorPoint = 'center') {
    const photoUrl = URL.createObjectURL(photoBlob);
    try {
        const photoImg = await loadImage(photoUrl);

        const fw = frameImg.naturalWidth;
        const fh = frameImg.naturalHeight;
        const pw = photoImg.naturalWidth;
        const ph = photoImg.naturalHeight;

        const canvas = document.createElement('canvas');
        canvas.width  = fw;
        canvas.height = fh;
        const ctx = canvas.getContext('2d');

        // Cover: take the larger scale factor so the photo fills the canvas.
        const scale   = Math.max(fw / pw, fh / ph);
        const scaledW = pw * scale;
        const scaledH = ph * scale;

        // Default: center.
        let offsetX = (fw - scaledW) / 2;
        let offsetY = (fh - scaledH) / 2;

        // Adjust offset based on anchor point keywords.
        const parts = anchorPoint.toLowerCase().split('-');
        if (parts.includes('left'))   offsetX = 0;
        if (parts.includes('right'))  offsetX = fw - scaledW;
        if (parts.includes('top'))    offsetY = 0;
        if (parts.includes('bottom')) offsetY = fh - scaledH;

        ctx.drawImage(photoImg, offsetX, offsetY, scaledW, scaledH);
        ctx.drawImage(frameImg, 0, 0, fw, fh);

        return canvasToBlob(canvas, 'image/jpeg', 0.92);
    } finally {
        URL.revokeObjectURL(photoUrl);
    }
}

/**
 * Apply "contain" mode: scale the photo down to fit within the frame (never
 * upscale), center it, then overlay the frame PNG on top.
 *
 * @param {Blob}             photoBlob - The user's photo blob.
 * @param {HTMLImageElement} frameImg  - Pre-loaded frame image element.
 * @returns {Promise<Blob>}  PNG blob of the processed image (preserves transparency).
 */
export async function applyContain(photoBlob, frameImg) {
    const photoUrl = URL.createObjectURL(photoBlob);
    try {
        const photoImg = await loadImage(photoUrl);

        const fw = frameImg.naturalWidth;
        const fh = frameImg.naturalHeight;
        const pw = photoImg.naturalWidth;
        const ph = photoImg.naturalHeight;

        const canvas = document.createElement('canvas');
        canvas.width  = fw;
        canvas.height = fh;
        const ctx = canvas.getContext('2d');

        // Contain: scale down only (never upscale).
        const scale   = Math.min(pw > fw ? fw / pw : 1, ph > fh ? fh / ph : 1);
        const scaledW = pw * scale;
        const scaledH = ph * scale;

        // Center the photo.
        const offsetX = (fw - scaledW) / 2;
        const offsetY = (fh - scaledH) / 2;

        ctx.drawImage(photoImg, offsetX, offsetY, scaledW, scaledH);
        ctx.drawImage(frameImg, 0, 0, fw, fh);

        return canvasToBlob(canvas, 'image/png');
    } finally {
        URL.revokeObjectURL(photoUrl);
    }
}

/**
 * Convert a canvas element to a Blob.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {string}            mimeType
 * @param {number}            quality   - Only used for lossy formats (e.g. JPEG).
 * @returns {Promise<Blob>}
 */
export function canvasToBlob(canvas, mimeType = 'image/jpeg', quality = 0.92) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) resolve(blob);
            else reject(new Error('canvas.toBlob() returned null'));
        }, mimeType, quality);
    });
}

/**
 * Trigger a browser download for a Blob.
 *
 * @param {Blob}   blob
 * @param {string} filename
 */
export function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 100);
}
