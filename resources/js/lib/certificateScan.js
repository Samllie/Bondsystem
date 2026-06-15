const CONFIRMATION_PATTERN = /^SICI-(BOND|CAR)-\d{4}-[A-F0-9]{8}-V\d+$/i;
const TOKEN_PATH_PATTERN = /\/verify-certificate\/([a-f0-9]{64})\/?$/i;
const MAX_DECODE_DIMENSION = 2200;

/**
 * Normalize a scanned QR value into a certifications table search term.
 */
export function parseCertificateScanValue(raw) {
    const value = raw?.trim();

    if (! value) {
        return null;
    }

    const pathMatch = value.match(TOKEN_PATH_PATTERN);

    if (pathMatch) {
        return pathMatch[1];
    }

    try {
        const url = new URL(value);
        const urlPathMatch = url.pathname.match(TOKEN_PATH_PATTERN);

        if (urlPathMatch) {
            return urlPathMatch[1];
        }
    } catch {
        // Not a URL — fall through to plain-text handling.
    }

    if (CONFIRMATION_PATTERN.test(value)) {
        return value.toUpperCase();
    }

    return value;
}

function sourceDimensions(source) {
    return {
        width: source.width ?? source.naturalWidth ?? source.videoWidth ?? 0,
        height: source.height ?? source.naturalHeight ?? source.videoHeight ?? 0,
    };
}

function drawSourceToCanvas(source, { scale = 1, cropRatio = 1 } = {}) {
    const { width: sourceWidth, height: sourceHeight } = sourceDimensions(source);

    if (sourceWidth <= 0 || sourceHeight <= 0) {
        return null;
    }

    const cropWidth = sourceWidth * cropRatio;
    const cropHeight = sourceHeight * cropRatio;
    const sourceX = (sourceWidth - cropWidth) / 2;
    const sourceY = (sourceHeight - cropHeight) / 2;

    let targetWidth = Math.round(cropWidth * scale);
    let targetHeight = Math.round(cropHeight * scale);
    const largestSide = Math.max(targetWidth, targetHeight);

    if (largestSide > MAX_DECODE_DIMENSION) {
        const resizeRatio = MAX_DECODE_DIMENSION / largestSide;
        targetWidth = Math.round(targetWidth * resizeRatio);
        targetHeight = Math.round(targetHeight * resizeRatio);
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(targetWidth, 1);
    canvas.height = Math.max(targetHeight, 1);

    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.imageSmoothingEnabled = scale !== 1;
    context.drawImage(
        source,
        sourceX,
        sourceY,
        cropWidth,
        cropHeight,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    return canvas;
}

function cloneImageData(imageData) {
    return new ImageData(new Uint8ClampedArray(imageData.data), imageData.width, imageData.height);
}

function grayscaleImageData(imageData) {
    const output = cloneImageData(imageData);

    for (let index = 0; index < output.data.length; index += 4) {
        const gray = Math.round(
            (output.data[index] * 0.299)
            + (output.data[index + 1] * 0.587)
            + (output.data[index + 2] * 0.114),
        );

        output.data[index] = gray;
        output.data[index + 1] = gray;
        output.data[index + 2] = gray;
        output.data[index + 3] = 255;
    }

    return output;
}

function contrastStretchImageData(imageData) {
    const output = grayscaleImageData(imageData);
    let min = 255;
    let max = 0;

    for (let index = 0; index < output.data.length; index += 4) {
        const value = output.data[index];
        min = Math.min(min, value);
        max = Math.max(max, value);
    }

    const range = Math.max(max - min, 1);

    for (let index = 0; index < output.data.length; index += 4) {
        const value = Math.round(((output.data[index] - min) / range) * 255);
        output.data[index] = value;
        output.data[index + 1] = value;
        output.data[index + 2] = value;
    }

    return output;
}

function thresholdImageData(imageData, threshold) {
    const output = grayscaleImageData(imageData);

    for (let index = 0; index < output.data.length; index += 4) {
        const value = output.data[index] >= threshold ? 255 : 0;
        output.data[index] = value;
        output.data[index + 1] = value;
        output.data[index + 2] = value;
    }

    return output;
}

async function decodeWithBarcodeDetector(source) {
    if (typeof window.BarcodeDetector === 'undefined') {
        return null;
    }

    try {
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        const codes = await detector.detect(source);

        return codes[0]?.rawValue ?? null;
    } catch {
        return null;
    }
}

async function decodeWithJsQrImageData(imageData) {
    const { default: jsQR } = await import('jsqr');

    const result = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'attemptBoth',
    });

    return result?.data ?? null;
}

function blurGrayscale(imageData) {
    const gray = grayscaleImageData(imageData);
    const { width, height, data } = gray;
    const output = cloneImageData(gray);

    for (let y = 1; y < height - 1; y += 1) {
        for (let x = 1; x < width - 1; x += 1) {
            let sum = 0;

            for (let offsetY = -1; offsetY <= 1; offsetY += 1) {
                for (let offsetX = -1; offsetX <= 1; offsetX += 1) {
                    const sampleIndex = ((y + offsetY) * width + (x + offsetX)) * 4;
                    sum += data[sampleIndex];
                }
            }

            const outputIndex = (y * width + x) * 4;
            const value = Math.round(sum / 9);
            output.data[outputIndex] = value;
            output.data[outputIndex + 1] = value;
            output.data[outputIndex + 2] = value;
        }
    }

    return output;
}

async function decodeCanvasVariants(canvas) {
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

    const nativeResult = await decodeWithBarcodeDetector(canvas);

    if (nativeResult) {
        return nativeResult;
    }

    const blurred = blurGrayscale(imageData);
    const variants = [
        imageData,
        contrastStretchImageData(imageData),
        thresholdImageData(imageData, 128),
        thresholdImageData(imageData, 100),
        thresholdImageData(imageData, 160),
        thresholdImageData(blurred, 128),
        thresholdImageData(blurred, 110),
    ];

    for (const variant of variants) {
        const decoded = await decodeWithJsQrImageData(variant);

        if (decoded) {
            return decoded;
        }
    }

    return null;
}

const FILE_DECODE_ATTEMPTS = [
    { scale: 1, cropRatio: 1 },
    { scale: 2, cropRatio: 1 },
    { scale: 1.5, cropRatio: 0.85 },
    { scale: 2, cropRatio: 0.75 },
    { scale: 2.5, cropRatio: 0.65 },
    { scale: 3, cropRatio: 0.55 },
    { scale: 1, cropRatio: 0.5 },
];

async function decodeQrFromImageSource(source) {
    for (const attempt of FILE_DECODE_ATTEMPTS) {
        const canvas = drawSourceToCanvas(source, attempt);

        if (! canvas) {
            continue;
        }

        const decoded = await decodeCanvasVariants(canvas);

        if (decoded) {
            return decoded;
        }
    }

    return null;
}

export async function decodeQrFromCanvas(canvas) {
    return decodeCanvasVariants(canvas);
}

function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Unable to load the selected image.'));
        };

        image.src = url;
    });
}

export async function decodeQrFromFile(file) {
    if (! file?.type?.startsWith('image/')) {
        throw new Error('Please choose a PNG or JPG image containing a QR code.');
    }

    const image = await loadImageFromFile(file);

    return decodeQrFromImageSource(image);
}

const CAMERA_CONSTRAINT_ATTEMPTS = [
    { video: { facingMode: { ideal: 'environment' } }, audio: false },
    { video: { facingMode: 'user' }, audio: false },
    { video: true, audio: false },
];

export function isCameraScanSupported() {
    return typeof navigator !== 'undefined'
        && typeof window !== 'undefined'
        && window.isSecureContext
        && !!navigator.mediaDevices?.getUserMedia;
}

export function getCameraUnsupportedMessage() {
    if (typeof window !== 'undefined' && ! window.isSecureContext) {
        return 'Camera scanning requires HTTPS. Open this site with https:// (for example https://sici-bonds.local) instead of http://.';
    }

    return 'Camera scanning is not supported in this browser.';
}

export function getCameraErrorMessage(error) {
    if (typeof window !== 'undefined' && ! window.isSecureContext) {
        return getCameraUnsupportedMessage();
    }

    switch (error?.name) {
        case 'NotAllowedError':
            return 'Camera access was blocked. Allow camera permission for this site in your browser settings, then try again.';
        case 'NotFoundError':
            return 'No camera was found on this device.';
        case 'NotReadableError':
            return 'The camera is in use by another application. Close other apps using the camera and try again.';
        case 'OverconstrainedError':
            return 'Could not start the camera with the requested settings.';
        case 'SecurityError':
            return getCameraUnsupportedMessage();
        default:
            return error?.message ?? 'Camera access was denied or is unavailable.';
    }
}

export async function requestCameraStream() {
    if (! isCameraScanSupported()) {
        throw new Error(getCameraUnsupportedMessage());
    }

    let lastError = null;

    for (const constraints of CAMERA_CONSTRAINT_ATTEMPTS) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (error) {
            lastError = error;

            if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
                throw error;
            }
        }
    }

    throw lastError ?? new Error('Camera access was denied or is unavailable.');
}
