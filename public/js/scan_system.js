/**
 * Scan System Hub Logic
 * Handles real-time scanning, quantity updates, and PDF report generation.
 */

(function () {
    // Local state to track syncing status
    let isSyncing = false;
    let lastActivityTimer = null;

    // --- Utility Functions ---

    function resetInactivityTimer() {
        if (lastActivityTimer) clearTimeout(lastActivityTimer);
        lastActivityTimer = setTimeout(moveLatestToHistory, 30000); // 30 seconds
    }

    function moveLatestToHistory() {
        const latestTbody = document.getElementById('latestScanRow');
        const historyTbody = document.getElementById('scanHistory');
        const latestSection = document.getElementById('latestScanSection');

        if (latestTbody && latestTbody.children.length > 0) {
            const row = latestTbody.children[0];
            row.classList.remove('latest-scan-row');
            if (historyTbody) {
                historyTbody.prepend(row);
            }
            if (latestSection) {
                latestSection.style.display = 'none';
            }
        }
    }

    function setSyncStatus(status) {
        const dot = document.getElementById('syncDot');
        const text = document.getElementById('syncText');
        if (!dot || !text) return;

        switch (status) {
            case 'active':
                dot.style.background = '#22c55e'; // green
                text.textContent = 'Live Connected';
                break;
            case 'syncing':
                dot.style.background = '#eab308'; // yellow
                text.textContent = 'Syncing...';
                break;
            case 'error':
                dot.style.background = '#ef4444'; // red
                text.textContent = 'Offline';
                break;
        }
    }

    // --- Pusher Integration ---

    if (window.Pusher && window.ScanConfig && window.ScanConfig.pusher) {
        Pusher.logToConsole = false;

        const pusher = new Pusher(window.ScanConfig.pusher.key, {
            cluster: window.ScanConfig.pusher.cluster
        });

        const channel = pusher.subscribe(window.ScanConfig.pusher.channel);

        channel.bind('scan-event', function (data) {
            console.log('Real-time update received:', data);

            // If action is reload_list, refresh page
            if (data.action === 'reload_list') {
                window.location.reload();
                return;
            }

            // Update UI elements based on EAN/Product ID
            updateUIRow(data);

            // If it's a new scan, we might need to prepend to history
            // But usually the backend handles the broadcast for all actions
            if (data.scan_id && !document.getElementById('scan-' + data.scan_id) && !data.delete_scan) {
                addNewScanToUI(data);
            }

            if (data.delete_scan) {
                removeScanFromUI(data.scan_id);
            }

            setSyncStatus('active');
        });

        pusher.connection.bind('connected', () => setSyncStatus('active'));
        pusher.connection.bind('disconnected', () => setSyncStatus('error'));
        setSyncStatus('syncing');
    }

    // --- Scanner Logic ---

    window.processScan = function () {
        const input = document.getElementById('eanInput');
        const ean = input.value.trim();
        if (!ean) return;

        // 1. INSTANT ACTION: Clear and focus immediately
        input.value = '';
        input.focus();

        resetInactivityTimer();
        setSyncStatus('syncing');

        // 2. OPTIMISTIC UI: Find existing product info to show a pending row
        const existingRow = document.querySelector(`[data-ean="${ean}"]`);
        const pendingId = 'pending-' + Date.now();

        let optimisticData = {
            scan_id: pendingId,
            ean_code: ean,
            product_name: 'Scanning...',
            product_id: '',
            ordered: 0,
            scanned: 0,
            remaining: 0,
            units: 1,
            status: 'PENDING',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            optimistic: true
        };

        if (existingRow) {
            optimisticData.product_name = existingRow.querySelector('.fw-800')?.textContent || 'Scanning...';
            optimisticData.ordered = parseInt(document.querySelector(`.row-ordered-val[data-ean="${ean}"]`)?.textContent) || 0;
            const currentScanned = parseInt(document.querySelector(`.row-scanned-val[data-ean="${ean}"]`)?.textContent) || 0;
            optimisticData.scanned = currentScanned + 1;
            optimisticData.remaining = Math.max(0, optimisticData.ordered - optimisticData.scanned);
            const diff = optimisticData.scanned - optimisticData.ordered;
            optimisticData.status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');
        }

        addNewScanToUI(optimisticData);

        // 3. BACKGROUND FETCH
        fetch(window.ScanConfig.routes.scan, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            },
            body: JSON.stringify({
                order_id: window.ScanConfig.orderId,
                ean_code: ean
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // This will either adopt the pending row or add a new one if needed
                    addNewScanToUI(data);
                    updateUIRow(data);
                } else {
                    // Remove pending row and show error
                    removeScanFromUI(pendingId);
                    Swal.fire({
                        icon: 'error',
                        title: 'Scan Error',
                        text: data.message || 'Error processing scan',
                        confirmButtonColor: '#2563eb'
                    });
                }
            })
            .catch(err => {
                console.error('Scan error:', err);
                removeScanFromUI(pendingId);
                setSyncStatus('error');
            })
            .finally(() => {
                setSyncStatus('active');
            });
    };

    window.updateUnits = function (scanId, change) {
        const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
        if (!row) return;

        // OPTIMISTIC UPDATE: Calculate values immediately from DOM
        const ean = row.getAttribute('data-ean');
        const unitsElem = row.querySelector('.qty-val-minimal') || row.querySelector('.qty-input-' + scanId);
        const scannedElem = document.querySelector('.row-scanned-val[data-ean="' + ean + '"]');
        const orderedElem = document.querySelector('.row-ordered-val[data-ean="' + ean + '"]');

        if (unitsElem && scannedElem && orderedElem) {
            const currentUnits = parseInt(unitsElem.value || unitsElem.textContent) || 0;
            const currentScanned = parseInt(scannedElem.textContent) || 0;
            const ordered = parseInt(orderedElem.textContent) || 0;

            const newUnits = Math.max(0, currentUnits + change);
            const actualChange = newUnits - currentUnits;
            const newScanned = currentScanned + actualChange;
            const newRemaining = Math.max(0, ordered - newScanned);
            const diff = newScanned - ordered;
            const status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

            // Apply optimistic UI
            updateUIRow({
                ean_code: ean,
                scan_id: scanId,
                units: newUnits,
                scanned: newScanned,
                ordered: ordered,
                remaining: newRemaining,
                status: status,
                optimistic: true
            });
        }

        setSyncStatus('syncing');
        fetch(window.ScanConfig.routes.update, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            },
            body: JSON.stringify({
                scan_id: scanId,
                change: change
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateUIRow(data); // Final sync with server truth
                } else if (data.message) {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Update units error:', err);
                setSyncStatus('error');
            })
            .finally(() => setSyncStatus('active'));
    };

    window.updateUnitsExact = function (scanId, val) {
        if (!window.ScanConfig.routes.updateExact) {
            console.error('UpdateExact route missing');
            return;
        }

        const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
        if (!row) return;

        // OPTIMISTIC UPDATE: Calculate values immediately from DOM
        const ean = row.getAttribute('data-ean');
        const unitsElem = row.querySelector('.qty-val-minimal') || row.querySelector('.qty-input-' + scanId);
        const scannedElem = document.querySelector('.row-scanned-val[data-ean="' + ean + '"]');
        const orderedElem = document.querySelector('.row-ordered-val[data-ean="' + ean + '"]');

        if (unitsElem && scannedElem && orderedElem) {
            const currentUnits = parseInt(unitsElem.value || unitsElem.textContent) || 0;
            const currentScanned = parseInt(scannedElem.textContent) || 0;
            const ordered = parseInt(orderedElem.textContent) || 0;

            const newUnits = parseInt(val) || 0;
            const actualChange = newUnits - currentUnits;
            const newScanned = currentScanned + actualChange;
            const newRemaining = Math.max(0, ordered - newScanned);
            const diff = newScanned - ordered;
            const status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

            // Apply optimistic UI
            updateUIRow({
                ean_code: ean,
                scan_id: scanId,
                units: newUnits,
                scanned: newScanned,
                ordered: ordered,
                remaining: newRemaining,
                status: status,
                optimistic: true
            });
        }

        setSyncStatus('syncing');
        fetch(window.ScanConfig.routes.updateExact, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            },
            body: JSON.stringify({
                scan_id: scanId,
                units: val
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateUIRow(data); // Final sync
                } else if (data.message) {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Update exact units error:', err);
                setSyncStatus('error');
            })
            .finally(() => setSyncStatus('active'));
    };

    window.deleteScan = function (scanId) {
        Swal.fire({
            title: 'Delete Scan Entry?',
            text: "Are you sure you want to delete this scan entry?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                setSyncStatus('syncing');
                fetch(window.ScanConfig.routes.delete, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                    },
                    body: JSON.stringify({
                        scan_id: scanId
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            removeScanFromUI(scanId);
                            updateUIRow(data);
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'The entry has been removed.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    })
                    .catch(err => console.error('Delete scan error:', err))
                    .finally(() => setSyncStatus('active'));
            }
        });
    };

    // --- UI Helpers ---

    function updateUIRow(data) {
        if (!data.ean_code) return;

        // Update Scan-specific elements if scan_id is provided
        if (data.scan_id) {
            // Find ALL instances of this scan row (latest, history, mobile)
            const scanRows = document.querySelectorAll('.scan-row-' + data.scan_id);
            const mobileQtyVals = document.querySelectorAll('.mobile-qty-val-' + data.scan_id);
            const qtyInputs = document.querySelectorAll('.qty-input-' + data.scan_id);

            scanRows.forEach(row => {
                const qtyBadge = row.querySelector('.col-qty .badge');
                if (qtyBadge) qtyBadge.textContent = data.units;

                // MOVE TO TOP: If it's not already in the latestScanRow, move it there
                const latestTbody = document.getElementById('latestScanRow');
                const historyTbody = document.getElementById('scanHistory');
                const latestSection = document.getElementById('latestScanSection');

                if (latestTbody && !latestTbody.contains(row)) {
                    if (latestSection) latestSection.style.display = 'block';

                    // Move current latest to history
                    if (latestTbody.children.length > 0) {
                        const currentLatest = latestTbody.children[0];
                        currentLatest.classList.remove('latest-scan-row');
                        if (historyTbody) historyTbody.prepend(currentLatest);
                    }

                    // Move this row to latest
                    document.querySelectorAll('.latest-scan-row').forEach(r => r.classList.remove('latest-scan-row'));
                    row.classList.add('latest-scan-row');
                    latestTbody.prepend(row);

                    row.style.animation = 'none';
                    row.offsetHeight;
                    row.style.animation = 'slideIn 0.5s ease-out';
                }

                // Mobile list: Always prepend to the very top
                const mobileList = document.getElementById('mobileHistoryList');
                if (mobileList && mobileList.firstChild !== row && mobileList.contains(row)) {
                    mobileList.prepend(row);
                }

                // CLEANUP: Remove pending/optimistic styles
                row.classList.remove('opacity-50');
                const actions = row.querySelector('.col-action').querySelectorAll('.invisible');
                actions.forEach(a => a.classList.remove('invisible'));
            });

            qtyInputs.forEach(input => {
                input.value = data.units;
            });

            mobileQtyVals.forEach(el => {
                el.textContent = data.units;

                // For mobile items that aren't the same as scanRows (different structure)
                const mobileItem = el.closest('.history-item-minimal');
                const mobileList = document.getElementById('mobileHistoryList');
                if (mobileItem && mobileList && mobileList.firstChild !== mobileItem) {
                    mobileList.prepend(mobileItem);
                    mobileItem.style.animation = 'none';
                    mobileItem.offsetHeight;
                    mobileItem.style.animation = 'slideIn 0.5s ease-out';
                }

                // CLEANUP MOBILE: Remove pending/optimistic styles
                if (mobileItem) {
                    mobileItem.classList.remove('opacity-50');
                    const mobActions = mobileItem.querySelector('.qty-pill-minimal.invisible');
                    if (mobActions) mobActions.classList.remove('invisible');
                }
            });
        }

        // Update all elements with the corresponding data-ean or class (Totals)
        const scannedElems = document.querySelectorAll('.row-scanned-val[data-ean="' + data.ean_code + '"]');
        scannedElems.forEach(el => el.textContent = data.scanned);

        const orderedElems = document.querySelectorAll('.row-ordered-val[data-ean="' + data.ean_code + '"]');
        orderedElems.forEach(el => el.textContent = data.ordered);

        const remainingElems = document.querySelectorAll('.row-remaining-val[data-ean="' + data.ean_code + '"]');
        remainingElems.forEach(el => {
            el.textContent = data.remaining;
            el.style.color = data.remaining > 0 ? '#2563eb' : '#64748b';
        });

        const rows = document.querySelectorAll('tr[data-ean="' + data.ean_code + '"], .history-item-minimal[data-ean="' + data.ean_code + '"]');
        rows.forEach(row => {
            const badge = row.querySelector('.status-badge-modern, .status-badge-mobile');
            if (badge) {
                badge.textContent = data.status;
                const color = data.status === 'COMPLETE' ? '#22c55e' : (data.status === 'OVER' ? '#ef4444' : '#2563eb');
                if (badge.classList.contains('status-badge-modern')) {
                    badge.style.background = color;
                } else {
                    badge.style.color = color;
                    badge.style.background = color + '15';
                }
            }
            row.setAttribute('data-error-status', data.status === 'COMPLETE' ? 'ok' : 'error');
        });

        const progressBar = document.querySelector('.progress-bar');
        if (progressBar && data.progress_percent !== undefined) {
            progressBar.style.width = data.progress_percent + '%';
            progressBar.setAttribute('aria-valuenow', data.progress_percent);
            progressBar.textContent = data.progress_percent + '%';
        }

        resetInactivityTimer();
    }

    function addNewScanToUI(data) {
        const historyTable = document.getElementById('scanHistory');
        const latestScanRow = document.getElementById('latestScanRow');
        const latestSection = document.getElementById('latestScanSection');
        const mobileList = document.getElementById('mobileHistoryList');

        if (!data.scan_id) return;
        if (latestSection) latestSection.style.display = 'block';

        resetInactivityTimer();

        // Check if this is a real data arrival and if we have a pending row to "adopt"
        if (!data.optimistic) {
            const pendingRow = document.querySelector(`.history-row.opacity-50[data-ean="${data.ean_code}"]`);
            if (pendingRow) {
                const pendingId = pendingRow.id.replace('scan-', '');
                pendingRow.id = 'scan-' + data.scan_id;
                pendingRow.classList.remove('scan-row-' + pendingId, 'opacity-50');
                pendingRow.classList.add('scan-row-' + data.scan_id);

                // Also handle mobile if it exists
                const pendingMobile = document.getElementById('mobile-scan-' + pendingId);
                if (pendingMobile) {
                    pendingMobile.id = 'mobile-scan-' + data.scan_id;
                    pendingMobile.classList.remove('scan-row-' + pendingId, 'opacity-50');
                    pendingMobile.classList.add('scan-row-' + data.scan_id);
                }

                updateUIRow(data);
                return;
            }
        }

        const isPending = String(data.scan_id).startsWith('pending-');

        // 1. If there's already a scan in the "Latest" row, move it to history
        if (latestScanRow && latestScanRow.children.length > 0) {
            const oldLatest = latestScanRow.children[0];
            oldLatest.classList.remove('latest-scan-row');
            if (historyTable) {
                historyTable.prepend(oldLatest);
            }
        }

        // 2. Prepare the new row HTML (Desktop)
        const statusColor = data.status === 'COMPLETE' ? '#22c55e' : (data.status === 'OVER' ? '#ef4444' : (data.status === 'PENDING' ? '#94a3b8' : '#2563eb'));
        const rowHtml = `
            <tr id="scan-${data.scan_id}" class="history-row latest-scan-row scan-row-${data.scan_id} ${isPending ? 'opacity-50' : ''}" data-ean="${data.ean_code}" data-error-status="${data.status === 'COMPLETE' ? 'ok' : 'error'}">
                <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">${data.timestamp || '--:--'}</td>
                <td class="col-product">
                    <div class="fw-800 text-dark">${data.product_name}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        EAN: <span class="selectable-ean">${data.ean_code}</span>
                        ${data.product_id ? ' / SKU: ' + data.product_id : ''}
                    </div>
                </td>
                <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="${data.ean_code}">${data.ordered}</td>
                <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="${data.ean_code}">${data.scanned}</td>
                <td class="row-remaining-val col-rest fw-700 text-success" data-ean="${data.ean_code}">${data.remaining}</td>
                <td class="col-qty">
                    <span class="badge bg-light text-secondary border-0 fw-700 p-1" style="font-size: 0.75rem;">${data.units || 1}</span>
                </td>
                <td class="col-status">
                    <span class="status-badge-modern" style="background: ${statusColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">
                        ${data.status}
                    </span>
                </td>
                <td class="col-action">
                    <div class="d-flex justify-content-center gap-1 ${isPending ? 'invisible' : ''}">
                        <div class="qty-pill">
                            <button class="qty-btn" onclick="updateUnits(${data.scan_id}, -1)">-</button>
                            <input type="number" class="qty-input qty-input-${data.scan_id}" id="qty-input-${data.scan_id}" value="${data.units || 1}" onchange="updateUnitsExact(${data.scan_id}, this.value)" style="width: 30px; height: 18px; text-align: center; border: none; font-weight: 700;">
                            <button class="qty-btn text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                        </div>
                        <button class="btn btn-link text-danger p-0" onclick="deleteScan(${data.scan_id})">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" /></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;

        if (latestScanRow) {
            latestScanRow.innerHTML = rowHtml;
        }

        // 3. Mobile list handling
        if (mobileList) {
            // Remove latest class from existing mobile items
            document.querySelectorAll('#mobileHistoryList .latest-scan-row').forEach(el => el.classList.remove('latest-scan-row'));
            const mobileHtml = `
                <div class="history-item-minimal scan-row-${data.scan_id} latest-scan-row ${isPending ? 'opacity-50' : ''}" id="mobile-scan-${data.scan_id}" data-scan-status="scanned" data-error-status="${data.status === 'COMPLETE' ? 'ok' : 'error'}" data-ean="${data.ean_code}">
                    <div class="item-data" style="flex-grow: 1;">
                        <span class="item-name-minimal">${data.product_name}</span>
                        <div class="item-meta-minimal">${data.timestamp || '--:--'} | ${data.ean_code}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="qty-pill-minimal ${isPending ? 'invisible' : ''}">
                            <button class="qty-btn-minimal" onclick="updateUnits(${data.scan_id}, -1)">−</button>
                            <span class="qty-val-minimal mobile-qty-val-${data.scan_id}">${data.units || 1}</span>
                            <button class="qty-btn-minimal text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                        </div>
                    </div>
                </div>
            `;
            mobileList.insertAdjacentHTML('afterbegin', mobileHtml);
        }
    }

    function removeScanFromUI(scanId) {
        const row = document.getElementById('scan-' + scanId);
        if (row) row.remove();

        const mobileItem = document.getElementById('mobile-scan-' + scanId);
        if (mobileItem) mobileItem.remove();

        // After removing, check if latestScanRow is empty
        const latestTbody = document.getElementById('latestScanRow');
        const historyTbody = document.getElementById('scanHistory');
        const latestSection = document.getElementById('latestScanSection');

        if (latestTbody && latestTbody.children.length === 0) {
            if (historyTbody && historyTbody.children.length > 0) {
                // Promote the first history item to latest
                const firstHistory = historyTbody.children[0];
                firstHistory.classList.add('latest-scan-row');
                latestTbody.appendChild(firstHistory);
            } else if (latestSection) {
                // No more scans at all
                latestSection.style.display = 'none';
            }
        }
        resetInactivityTimer();
    }

    // --- Filtering ---

    window.applyFilter = function (filter) {
        const links = document.querySelectorAll('.filter-link');
        links.forEach(l => {
            if (l.getAttribute('data-filter') === filter) {
                l.classList.add('active');
                l.classList.replace('text-secondary', 'text-primary');
            } else {
                l.classList.remove('active');
                l.classList.replace('text-primary', 'text-secondary');
            }
        });

        const rows = document.querySelectorAll('.history-row, .history-item-minimal');
        rows.forEach(row => {
            const status = row.getAttribute('data-error-status');
            const type = row.getAttribute('data-scan-status');

            if (filter === 'scanned' && type === 'scanned') {
                row.style.display = '';
            } else if (filter === 'order') {
                row.style.display = '';
            } else if (filter === 'error' && status === 'error') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    // --- PDF / Print Logic ---

    window.downloadPDF = function () {
        if (typeof html2pdf === 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Library Missing',
                text: 'PDF library not loaded yet. Please wait or refresh.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        const printArea = document.getElementById('print-area');
        if (!printArea) return;

        setSyncStatus('syncing');

        // Capture data from the UI to build the report
        const orderId = window.ScanConfig.orderId;
        const date = new Date().toLocaleDateString('no-NO');
        const staff = document.getElementById('staffName')?.value || 'Guest';

        // Build the HTML for the PDF
        let tableRows = '';
        const historyRows = document.querySelectorAll('#scanHistory tr:not(.opacity-75)'); // Scanned items
        const missingRows = document.querySelectorAll('#scanHistory tr.opacity-75'); // Ordered but not scanned

        const allRows = [...historyRows, ...missingRows];

        allRows.forEach(row => {
            const name = row.querySelector('.col-product .fw-800')?.textContent || 'Unknown';
            const ean = row.querySelector('.selectable-ean')?.textContent || '';
            const ordered = row.querySelector('.col-ordered')?.textContent || '0';
            const scanned = row.querySelector('.col-scanned')?.textContent || '0';
            const rest = row.querySelector('.col-rest')?.textContent || '0';
            const status = row.querySelector('.status-badge-modern')?.textContent.trim() || '';

            tableRows += `
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;">${name}<br><small>${ean}</small></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">${ordered}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">${scanned}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">${rest}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center; font-weight: bold;">${status}</td>
                </tr>
            `;
        });

        const reportHTML = `
            <div style="font-family: Arial, sans-serif; padding: 40px; color: #333;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    <div>
                        <h1 style="margin: 0; font-size: 24px; text-transform: uppercase;">Reception Report</h1>
                        <p style="margin: 5px 0; color: #666;">Order ID: <strong>#${orderId}</strong></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 0;">Date: ${date}</p>
                        <p style="margin: 5px 0;">Staff: ${staff}</p>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left;">
                            <th style="padding: 12px 8px; border-bottom: 2px solid #e2e8f0;">PRODUCT</th>
                            <th style="padding: 12px 8px; border-bottom: 2px solid #e2e8f0; text-align: center;">ORDERED</th>
                            <th style="padding: 12px 8px; border-bottom: 2px solid #e2e8f0; text-align: center;">SCANNED</th>
                            <th style="padding: 12px 8px; border-bottom: 2px solid #e2e8f0; text-align: center;">REST</th>
                            <th style="padding: 12px 8px; border-bottom: 2px solid #e2e8f0; text-align: center;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>

                <div style="margin-top: 40px; font-size: 12px; color: #94a3b8; text-align: center;">
                    Generated by Registry Reception Hub Logic
                </div>
            </div>
        `;

        printArea.innerHTML = reportHTML;
        printArea.style.display = 'block';

        const opt = {
            margin: 0.5,
            filename: `Reception_Report_Order_#${orderId}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().from(printArea).set(opt).save()
            .then(() => {
                printArea.style.display = 'none';
                setSyncStatus('active');
            })
            .catch(err => {
                console.error('PDF generation error:', err);
                setSyncStatus('error');
            });
    };

    // --- Init ---
    document.addEventListener('DOMContentLoaded', () => {
        // Auto-focus on input if it exists
        const input = document.getElementById('eanInput');
        if (input) {
            input.focus();
            // Enter key listener
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    window.processScan();
                }
            });
        }

        // Close order logic
        const closeBtn = document.getElementById('closeOrderBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                const staff = document.getElementById('staffName').value.trim();
                const note = document.getElementById('orderNote').value;
                const deliveryDate = document.getElementById('plannedDelivery').value;

                if (!staff) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Staff Name',
                        text: 'Staff name is required to archive session.',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Archive Session?',
                    text: 'Are you sure you want to finish and archive this session?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, finish it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        setSyncStatus('syncing');
                        fetch(window.ScanConfig.routes.close, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                            },
                            body: JSON.stringify({
                                order_id: window.ScanConfig.orderId,
                                staff: staff,
                                note: note,
                                planned_delivery: deliveryDate
                            })
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success!',
                                        text: 'Order session archived successfully!',
                                        showConfirmButton: false,
                                        timer: 2000
                                    }).then(() => {
                                        window.location.href = window.ScanConfig.routes.index || '/order-delivery';
                                    });
                                }
                            })
                            .catch(err => console.error('Close order error:', err))
                            .finally(() => setSyncStatus('active'));
                    }
                });
            });
        }

        // Initialize timer
        resetInactivityTimer();
    });

})();
