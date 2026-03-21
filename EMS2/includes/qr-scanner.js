// QR Scanner Library for Officer Attendance
// This file provides the QR scanning functionality

class QRScanner {
    constructor(elementId, onScanSuccess, onScanError) {
        this.elementId = elementId;
        this.onScanSuccess = onScanSuccess;
        this.onScanError = onScanError;
        this.scanner = null;
        this.isScanning = false;
    }

    async start() {
        try {
            // Check if browser supports camera
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Camera not supported in this browser');
            }

            // Initialize HTML5 QR Code scanner
            this.scanner = new Html5Qrcode(this.elementId);
            
            // Start scanning
            await this.scanner.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                (decodedText) => {
                    // On success
                    this.stop();
                    if (this.onScanSuccess) {
                        this.onScanSuccess(decodedText);
                    }
                },
                (errorMessage) => {
                    // On error - silent fail to avoid console spam
                    if (this.onScanError) {
                        // Only call on significant errors
                        if (errorMessage.includes('No QR code found') === false) {
                            this.onScanError(errorMessage);
                        }
                    }
                }
            );
            
            this.isScanning = true;
            return true;
        } catch (err) {
            console.error('QR Scanner error:', err);
            if (this.onScanError) {
                this.onScanError(err.message);
            }
            return false;
        }
    }

    stop() {
        if (this.scanner && this.isScanning) {
            this.scanner.stop().then(() => {
                this.isScanning = false;
            }).catch((err) => {
                console.error('Failed to stop scanner:', err);
            });
        }
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QRScanner;
}