// Accept any image the browser can display. GD on the server reads JPEG/PNG/WebP/GIF/BMP/AVIF;
// anything else (SVG, HEIC on Safari, TIFF, ICO, …) is rasterised to PNG here before upload.
const SERVER_READABLE = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/avif'];
export const IMAGE_ACCEPT = 'image/*,.jpg,.jpeg,.jfif,.png,.webp,.gif,.bmp,.avif,.svg,.heic,.heif,.tif,.tiff,.ico';
const MAX_EDGE = 2000;

export async function normalizeImage(file) {
    if (!file || SERVER_READABLE.includes(file.type)) return file;
    const url = URL.createObjectURL(file);
    try {
        const img = await new Promise((resolve, reject) => {
            const i = new Image();
            i.onload = () => resolve(i);
            i.onerror = () => reject(new Error('This image format cannot be read by your browser. Please use JPG or PNG.'));
            i.src = url;
        });
        // SVGs without intrinsic size report 0 — give them a sensible canvas
        const w0 = img.naturalWidth || 1200;
        const h0 = img.naturalHeight || 600;
        const scale = Math.min(1, MAX_EDGE / Math.max(w0, h0));
        const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(w0 * scale), height: Math.round(h0 * scale) });
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((r) => canvas.toBlob(r, 'image/png'));
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.png', { type: 'image/png' });
    } finally {
        URL.revokeObjectURL(url);
    }
}
