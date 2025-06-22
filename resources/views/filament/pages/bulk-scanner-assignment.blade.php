{{-- resources/views/filament/pages/bulk-scanner-assignment.blade.php --}}
<x-filament-panels::page>
    {{ $this->form }}

    <style>
        @keyframes pulse-success {
            0%, 100% { background-color: rgb(220 252 231); }
            50% { background-color: rgb(187 247 208); }
        }

        @keyframes pulse-error {
            0%, 100% { background-color: rgb(254 226 226); }
            50% { background-color: rgb(252 165 165); }
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateX(-100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes bounce-in {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        .animate-slide-in {
            animation: slide-in 0.4s ease-out;
        }

        .animate-bounce-in {
            animation: bounce-in 0.5s ease-out;
        }

        .pulse-success {
            animation: pulse-success 1s ease-in-out;
        }

        .pulse-error {
            animation: pulse-error 1s ease-in-out;
        }

        .scanner-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            transition: box-shadow 0.3s ease;
        }

        .scanner-glow:focus {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .success-gradient {
            background: linear-gradient(135deg, #667eea 0%, #10b981 100%);
        }

        .error-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let scannedShipments = [];
            let stats = { success: 0, failed: 0, total: 0 };
            let isProcessing = false;
            let scanCount = 0;

            const scannerInput = document.getElementById('scanner-input');
            const loadingIndicator = document.getElementById('loading-indicator');
            const scansListContainer = document.getElementById('scans-list');
            const emptyState = document.getElementById('empty-state');
            const scannerContainer = document.getElementById('scanner-container');

            if (!scannerInput) return;

            // Focus on scanner input
            scannerInput.focus();

            // Add sound effects
            function playSound(type) {
                try {
                    const context = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = context.createOscillator();
                    const gainNode = context.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(context.destination);

                    if (type === 'success') {
                        oscillator.frequency.setValueAtTime(800, context.currentTime);
                        oscillator.frequency.setValueAtTime(1200, context.currentTime + 0.1);
                    } else if (type === 'error') {
                        oscillator.frequency.setValueAtTime(400, context.currentTime);
                        oscillator.frequency.setValueAtTime(200, context.currentTime + 0.1);
                    }

                    gainNode.gain.setValueAtTime(0.1, context.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.2);

                    oscillator.start(context.currentTime);
                    oscillator.stop(context.currentTime + 0.2);
                } catch (e) {
                    // Ignore audio errors
                }
            }

            // Handle input with visual feedback
            scannerInput.addEventListener('input', function() {
                if (this.value.length >= 10 && this.value.includes('-')) {
                    processScan();
                }
            });

            // Handle Enter key
            scannerInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    processScan();
                }
            });

            async function processScan() {
                if (!scannerInput.value.trim() || isProcessing) return;

                const trackingNumber = scannerInput.value.trim();
                scannerInput.value = '';
                isProcessing = true;
                scanCount++;

                // Visual feedback
                scannerContainer.classList.add('pulse-success');
                setTimeout(() => scannerContainer.classList.remove('pulse-success'), 1000);

                showLoading(true);

                try {
                    const response = await fetch(window.location.pathname + '/check-tracking', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Livewire': true
                        },
                        body: JSON.stringify({
                            tracking_number: trackingNumber,
                            driver_id: getSelectedDriverId()
                        })
                    });

                    const result = await response.json();
                    handleScanResult(trackingNumber, result);

                } catch (error) {
                    console.error('Error processing scan:', error);
                    handleScanResult(trackingNumber, {
                        status: 'error',
                        message: 'System error - please try again',
                        color: 'red'
                    });
                    scannerContainer.classList.add('pulse-error');
                    setTimeout(() => scannerContainer.classList.remove('pulse-error'), 1000);
                } finally {
                    isProcessing = false;
                    showLoading(false);
                    scannerInput.focus();
                }
            }

            function handleScanResult(trackingNumber, result) {
                const scan = {
                    tracking_number: trackingNumber,
                    status: result.status,
                    message: result.message,
                    shipment_info: result.shipment_info,
                    timestamp: new Date().toLocaleTimeString(),
                    color: result.color || 'gray',
                    id: scanCount
                };

                scannedShipments.push(scan);
                updateStats(result.status);
                addScanToList(scan);
                updateCounters();

                // Play sound
                if (result.status === 'valid') {
                    playSound('success');
                } else {
                    playSound('error');
                }
            }

            function addScanToList(scan) {
                if (emptyState) {
                    emptyState.style.display = 'none';
                }

                const scanElement = document.createElement('div');
                scanElement.className = 'scan-item animate-slide-in';

                const statusIcon = getStatusIcon(scan.status);
                const gradientClass = getGradientClass(scan.status);

                scanElement.innerHTML = `
                    <div class="relative overflow-hidden rounded-xl border border-gray-200 ${gradientClass} shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-102">
                        <div class="absolute top-0 right-0 w-32 h-32 -mt-16 -mr-16 ${gradientClass} opacity-20 rounded-full"></div>
                        <div class="relative p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <div class="flex-shrink-0 w-8 h-8 ${gradientClass} rounded-full flex items-center justify-center shadow-lg">
                                            ${statusIcon}
                                        </div>
                                        <div>
                                            <span class="font-mono text-lg font-bold text-gray-800">${scan.tracking_number}</span>
                                            <span class="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">${scan.timestamp}</span>
                                        </div>
                                    </div>

                                    <div class="ml-11">
                                        <p class="text-sm font-medium text-gray-700 mb-1">${scan.message}</p>
                                        ${scan.shipment_info ? `
                                            <div class="text-xs text-gray-600 bg-white bg-opacity-50 rounded-lg p-2 border">
                                                <div class="flex items-center space-x-4">
                                                    <span><i class="fas fa-user mr-1"></i>${scan.shipment_info.recipient}</span>
                                                    <span><i class="fas fa-map-marker-alt mr-1"></i>${scan.shipment_info.city}</span>
                                                    <span><i class="fas fa-info-circle mr-1"></i>${scan.shipment_info.current_status}</span>
                                                </div>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>

                                <div class="flex-shrink-0 ml-4">
                                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-bold text-white ${gradientClass} shadow-lg animate-bounce-in">
                                        ${scan.status === 'valid' ? '✓ Valid' : '✗ Invalid'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                scansListContainer.insertBefore(scanElement, scansListContainer.firstChild);

                // Keep only last 30 scans for better performance
                const scanElements = scansListContainer.querySelectorAll('.scan-item');
                if (scanElements.length > 30) {
                    scanElements[scanElements.length - 1].remove();
                }
            }

            function getStatusIcon(status) {
                switch(status) {
                    case 'valid':
                        return '<i class="fas fa-check text-white"></i>';
                    case 'not_found':
                        return '<i class="fas fa-times text-white"></i>';
                    case 'already_assigned':
                        return '<i class="fas fa-info text-white"></i>';
                    case 'assigned_to_other':
                        return '<i class="fas fa-user-times text-white"></i>';
                    case 'cannot_assign':
                        return '<i class="fas fa-ban text-white"></i>';
                    default:
                        return '<i class="fas fa-question text-white"></i>';
                }
            }

            function getGradientClass(status) {
                switch(status) {
                    case 'valid':
                        return 'success-gradient';
                    case 'not_found':
                    case 'cannot_assign':
                        return 'error-gradient';
                    case 'already_assigned':
                    case 'assigned_to_other':
                        return 'info-gradient';
                    default:
                        return 'gradient-bg';
                }
            }

            function updateStats(status) {
                if (status === 'valid') {
                    stats.success++;
                } else {
                    stats.failed++;
                }
                stats.total++;
            }

            function updateCounters() {
                const successEl = document.getElementById('success-count');
                const failedEl = document.getElementById('failed-count');
                const totalEl = document.getElementById('total-count');

                if (successEl) {
                    successEl.textContent = stats.success;
                    successEl.parentElement.classList.add('animate-bounce-in');
                }
                if (failedEl) {
                    failedEl.textContent = stats.failed;
                    failedEl.parentElement.classList.add('animate-bounce-in');
                }
                if (totalEl) {
                    totalEl.textContent = stats.total;
                    totalEl.parentElement.classList.add('animate-bounce-in');
                }

                // Remove animation classes after animation completes
                setTimeout(() => {
                    document.querySelectorAll('.animate-bounce-in').forEach(el => {
                        el.classList.remove('animate-bounce-in');
                    });
                }, 500);
            }

            function showLoading(show) {
                if (loadingIndicator) {
                    loadingIndicator.style.display = show ? 'flex' : 'none';
                }
            }

            function getSelectedDriverId() {
                const driverSelect = document.querySelector('[name="data.driver_id"]');
                return driverSelect ? driverSelect.value : null;
            }

            // Listen for Livewire events
            window.addEventListener('livewire:initialized', function() {
                Livewire.on('reset-scanner', function() {
                    scannedShipments = [];
                    stats = { success: 0, failed: 0, total: 0 };
                    scanCount = 0;
                    updateCounters();
                    scansListContainer.innerHTML = `
                        <div id="empty-state" class="text-center py-12 text-gray-500">
                            <div class="glass-effect rounded-2xl p-8 mx-auto max-w-md">
                                <div class="gradient-bg w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center">
                                    <i class="fas fa-qrcode text-white text-2xl"></i>
                                </div>
                                <h class="text-lg font-semibold text-gray-700 mb-2">Ready to Scan!</h3>
                                <p class="text-sm text-gray-500">Start scanning barcodes to see results here</p>
                            </div>
                        </div>
                    `;
                    scannerInput.focus();
                });
            });

            // Auto-focus when driver is selected
            document.addEventListener('livewire:updated', function() {
                setTimeout(() => {
                    if (scannerInput) {
                        scannerInput.focus();
                    }
                }, 100);
            });

            // Add scanning animation on focus
            scannerInput.addEventListener('focus', function() {
                this.classList.add('scanner-glow');
            });

            scannerInput.addEventListener('blur', function() {
                this.classList.remove('scanner-glow');
            });
        });
    </script>

    {{-- Add Font Awesome for icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @endpush
</x-filament-panels::page>
