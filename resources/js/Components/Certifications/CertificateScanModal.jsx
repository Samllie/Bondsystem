import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import {
    decodeQrFromCanvas,
    decodeQrFromFile,
    getCameraErrorMessage,
    getCameraUnsupportedMessage,
    isCameraScanSupported,
    parseCertificateScanValue,
    requestCameraStream,
} from '@/lib/certificateScan';
import { useCallback, useEffect, useRef, useState } from 'react';

const MODES = {
    camera: 'camera',
    upload: 'upload',
};

export default function CertificateScanModal({
    show,
    onClose,
    onScanSuccess,
    initialStream = null,
}) {
    const [mode, setMode] = useState(MODES.camera);
    const [error, setError] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [isStartingCamera, setIsStartingCamera] = useState(false);
    const [cameraActive, setCameraActive] = useState(false);
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const scanFrameRef = useRef(null);
    const lastScanAtRef = useRef(0);

    const stopCamera = useCallback(() => {
        if (scanFrameRef.current) {
            cancelAnimationFrame(scanFrameRef.current);
            scanFrameRef.current = null;
        }

        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setCameraActive(false);

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    }, []);

    const handleDecodedValue = useCallback((rawValue) => {
        const searchValue = parseCertificateScanValue(rawValue);

        if (! searchValue) {
            setError('The scanned QR code did not contain a recognizable confirmation reference.');
            return;
        }

        stopCamera();
        onScanSuccess(searchValue);
        onClose();
    }, [onClose, onScanSuccess, stopCamera]);

    const scanVideoFrame = useCallback(async () => {
        const video = videoRef.current;
        const canvas = canvasRef.current;

        if (! video || ! canvas || video.readyState < video.HAVE_ENOUGH_DATA) {
            scanFrameRef.current = requestAnimationFrame(scanVideoFrame);
            return;
        }

        const now = Date.now();

        if (now - lastScanAtRef.current >= 350) {
            lastScanAtRef.current = now;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d', { willReadFrequently: true });
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            try {
                const rawValue = await decodeQrFromCanvas(canvas);

                if (rawValue) {
                    handleDecodedValue(rawValue);
                    return;
                }
            } catch {
                // Keep scanning until a readable frame is found.
            }
        }

        scanFrameRef.current = requestAnimationFrame(scanVideoFrame);
    }, [handleDecodedValue]);

    const attachStream = useCallback(async (stream) => {
        if (streamRef.current && streamRef.current !== stream) {
            streamRef.current.getTracks().forEach((track) => track.stop());
        }

        streamRef.current = stream;

        if (! videoRef.current) {
            return;
        }

        videoRef.current.srcObject = stream;

        try {
            await videoRef.current.play();
            setCameraActive(true);
            setError('');
            scanFrameRef.current = requestAnimationFrame(scanVideoFrame);
        } catch (playError) {
            setError(getCameraErrorMessage(playError));
        }
    }, [scanVideoFrame, stopCamera]);

    const startCamera = useCallback(async () => {
        if (! isCameraScanSupported()) {
            setError(getCameraUnsupportedMessage());
            return;
        }

        setError('');
        setIsStartingCamera(true);

        try {
            const stream = await requestCameraStream();
            await attachStream(stream);
        } catch (cameraError) {
            setError(getCameraErrorMessage(cameraError));
        } finally {
            setIsStartingCamera(false);
        }
    }, [attachStream]);

    useEffect(() => {
        if (! show) {
            stopCamera();
            setError('');
            setIsProcessing(false);
            setIsStartingCamera(false);
            return;
        }

        if (mode !== MODES.camera) {
            stopCamera();
            return;
        }

        if (initialStream) {
            attachStream(initialStream);
        }
    }, [attachStream, initialStream, mode, show, stopCamera]);

    const handleFileChange = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (! file) {
            return;
        }

        setError('');
        setIsProcessing(true);

        try {
            const rawValue = await decodeQrFromFile(file);

            if (! rawValue) {
                setError('No QR code was detected in that image.');
                return;
            }

            handleDecodedValue(rawValue);
        } catch (uploadError) {
            setError(uploadError.message ?? 'Unable to read the uploaded image.');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">Scan Confirmation QR</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Scan a confirmation QR code to search this table by confirmation number or verification token.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    >
                        Close
                    </button>
                </div>

                <div className="mt-4 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                    <button
                        type="button"
                        onClick={() => setMode(MODES.camera)}
                        className={`rounded-md px-4 py-2 text-sm font-medium ${
                            mode === MODES.camera
                                ? 'bg-white text-sterling-green shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        Use Camera
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode(MODES.upload)}
                        className={`rounded-md px-4 py-2 text-sm font-medium ${
                            mode === MODES.upload
                                ? 'bg-white text-sterling-green shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        Upload Image
                    </button>
                </div>

                {error && (
                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {mode === MODES.camera ? (
                    <div className="mt-4">
                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-black">
                            <video
                                ref={videoRef}
                                className="aspect-video w-full object-cover"
                                playsInline
                                muted
                            />
                        </div>
                        <canvas ref={canvasRef} className="hidden" />
                        {! cameraActive && (
                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <PrimaryButton
                                    type="button"
                                    onClick={startCamera}
                                    disabled={isStartingCamera}
                                >
                                    {isStartingCamera ? 'Starting camera…' : 'Start Camera'}
                                </PrimaryButton>
                                {! isCameraScanSupported() && (
                                    <p className="text-xs text-slate-500">
                                        {getCameraUnsupportedMessage()}
                                    </p>
                                )}
                            </div>
                        )}
                        <p className="mt-3 text-xs text-slate-500">
                            {cameraActive
                                ? 'Point your camera at the confirmation QR code. Scanning starts automatically.'
                                : 'Click Start Camera and allow browser permission when prompted.'}
                        </p>
                    </div>
                ) : (
                    <div className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <p className="text-sm text-slate-600">
                            Upload a PNG or JPG image containing the confirmation QR code.
                        </p>
                        <p className="mt-2 text-xs text-slate-500">
                            Photos of screens work best when the QR code fills most of the image. Crop tightly around the code if scanning fails.
                        </p>
                        <label className="mt-4 inline-flex cursor-pointer items-center justify-center rounded-lg bg-sterling-gold px-4 py-2 text-sm font-semibold text-sterling-green-darker hover:bg-sterling-gold-light">
                            {isProcessing ? 'Reading image…' : 'Choose Image'}
                            <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                disabled={isProcessing}
                                onChange={handleFileChange}
                            />
                        </label>
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    {mode === MODES.camera && cameraActive && (
                        <SecondaryButton type="button" onClick={stopCamera}>
                            Stop Camera
                        </SecondaryButton>
                    )}
                    <PrimaryButton type="button" onClick={onClose}>
                        Cancel
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
