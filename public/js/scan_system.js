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

    function flushLatestStripIntoHistory() {
        const latestTbody = document.getElementById('latestScanRow');
        const historyTbody = document.getElementById('scanHistory');
        if (!latestTbody || !historyTbody) return;
        while (latestTbody.firstElementChild) {
            const row = latestTbody.firstElementChild;
            row.classList.remove('latest-scan-row');
            historyTbody.prepend(row);
        }
    }

    function syncLastScannedStripVisibility() {
        const input = document.getElementById('productLookupInput');
        const lookupTerm = (input && input.value ? String(input.value) : '').trim().toUpperCase();
        const section = document.getElementById('latestScanSection');
        const latestTbody = document.getElementById('latestScanRow');
        if (!section || !latestTbody) return;

        const latestRow = latestTbody.querySelector('tr.history-row');
        if (!latestRow) {
            section.style.display = 'none';
            return;
        }

        const activeLink = document.querySelector('.filter-link.active');
        const activeFilter = activeLink ? String(activeLink.getAttribute('data-filter') || '').toLowerCase() : 'scanned';

        // Keep "LAST SCANNED" deterministic:
        // - Never show while product lookup/search is active
        // - Never show outside SCANNED tab
        // - Only show if latest row is currently visible after filters
        const hideForSearch = !!lookupTerm;
        const hideForNonScannedTab = activeFilter !== 'scanned';
        const hideForRowFilteredOut = latestRow.style.display === 'none';

        section.style.display = (hideForSearch || hideForNonScannedTab || hideForRowFilteredOut) ? 'none' : '';
    }

    function promoteRowToLatestStrip(desktopRow) {
        const latestTbody = document.getElementById('latestScanRow');
        const historyTbody = document.getElementById('scanHistory');
        if (!latestTbody || !historyTbody || !desktopRow || desktopRow.tagName !== 'TR') return;

        Array.from(latestTbody.querySelectorAll('tr')).forEach((r) => {
            if (r !== desktopRow) {
                r.classList.remove('latest-scan-row');
                historyTbody.prepend(r);
            }
        });
        if (!latestTbody.contains(desktopRow)) {
            latestTbody.appendChild(desktopRow);
        }
        latestTbody.querySelectorAll('tr').forEach((r) => r.classList.remove('latest-scan-row'));
        desktopRow.classList.add('latest-scan-row');
        historyTbody.querySelectorAll('tr.latest-scan-row').forEach((r) => {
            if (!latestTbody.contains(r)) r.classList.remove('latest-scan-row');
        });
        syncLastScannedStripVisibility();
    }

    function moveLatestToHistory() {
        flushLatestStripIntoHistory();
        syncLastScannedStripVisibility();
    }

    function setSyncStatus(status) {
        if (isReportLocked()) {
            const dot = document.getElementById('syncDot');
            const text = document.getElementById('syncText');
            const liveHub = document.getElementById('liveHubIndicator');
            const liveLabel = document.getElementById('liveHubLabel');
            if (dot) dot.style.background = '#adb5bd';
            if (text) text.textContent = 'Locked';
            if (liveHub) {
                liveHub.dataset.liveState = 'offline';
                liveHub.classList.add('hub-live--offline');
                liveHub.classList.remove('hub-live--sync');
            }
            if (liveLabel) liveLabel.textContent = 'OFFLINE';
            return;
        }

        const dot = document.getElementById('syncDot');
        const text = document.getElementById('syncText');
        const liveHub = document.getElementById('liveHubIndicator');
        const liveLabel = document.getElementById('liveHubLabel');

        switch (status) {
            case 'active':
                if (dot) dot.style.background = '#22c55e';
                if (text) text.textContent = 'Live';
                if (liveHub) {
                    liveHub.dataset.liveState = 'live';
                    liveHub.classList.remove('hub-live--offline', 'hub-live--sync');
                }
                if (liveLabel) liveLabel.textContent = 'LIVE';
                break;
            case 'syncing':
                if (dot) dot.style.background = '#eab308';
                if (text) text.textContent = 'Syncing...';
                if (liveHub) {
                    liveHub.dataset.liveState = 'sync';
                    liveHub.classList.add('hub-live--sync');
                    liveHub.classList.remove('hub-live--offline');
                }
                if (liveLabel) liveLabel.textContent = 'LIVE';
                break;
            case 'error':
                if (dot) dot.style.background = '#ef4444';
                if (text) text.textContent = 'Offline';
                if (liveHub) {
                    liveHub.dataset.liveState = 'offline';
                    liveHub.classList.add('hub-live--offline');
                    liveHub.classList.remove('hub-live--sync');
                }
                if (liveLabel) liveLabel.textContent = 'OFFLINE';
                break;
        }
    }

    function hideSoftKeyboard() {
        const active = document.activeElement;
        if (!active) return;

        const isEditable = active.tagName === 'INPUT' ||
            active.tagName === 'TEXTAREA' ||
            active.isContentEditable;

        if (isEditable && typeof active.blur === 'function') {
            active.blur();
        }
    }

    function isMobileTouchDevice() {
        return window.matchMedia('(pointer: coarse)').matches || /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    }

    function isReportLocked() {
        return window.ScanConfig && window.ScanConfig.orderStatus === 'Completed';
    }

    function showLockedMessage() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Report Locked',
                text: 'This completed report is locked. Reopen it to make changes.',
                confirmButtonColor: '#2563eb'
            });
        }
    }

    // --- Pusher Integration ---

    // Track which scan IDs were recently self-initiated (to reduce duplicate self-echoes)
    const _selfInitiatedScanIds = new Set();
    // Keep last applied server event timestamp per scan to ignore stale out-of-order updates.
    const _lastAppliedEventTsByScan = new Map();
    let _lastAppliedGlobalEventTs = 0;

    function markSelfInitiated(scanId) {
        if (scanId) {
            _selfInitiatedScanIds.add(String(scanId));
            // Clear this scan from the set after 4s (enough time for Pusher round-trip)
            setTimeout(() => _selfInitiatedScanIds.delete(String(scanId)), 4000);
        }
    }

    function isSelfInitiated(data) {
        // For scan events without scan_id (new scan), check via a generic flag briefly
        if (!data || !data.scan_id) return false;
        return _selfInitiatedScanIds.has(String(data.scan_id));
    }

    function getEventTimestamp(data) {
        if (!data || data.updated_at === undefined || data.updated_at === null) return 0;
        const ts = Number(data.updated_at);
        return Number.isFinite(ts) ? ts : 0;
    }

    function shouldApplyServerEvent(data) {
        if (!data || !data.scan_id) return true;
        const incomingTs = getEventTimestamp(data);
        if (window.ScanConfig && window.ScanConfig.isGlobal && incomingTs) {
            if (incomingTs < _lastAppliedGlobalEventTs) return false;
            _lastAppliedGlobalEventTs = incomingTs;
        }
        const scanKey = String(data.scan_id);
        if (!incomingTs) return true; // Backward compatibility for older payloads.
        const lastTs = _lastAppliedEventTsByScan.get(scanKey) || 0;
        if (incomingTs < lastTs) return false;
        _lastAppliedEventTsByScan.set(scanKey, incomingTs);
        return true;
    }

    function hasMeaningfulDiffAgainstUI(data) {
        if (!data || !data.scan_id) return false;
        const row = document.getElementById('scan-' + data.scan_id) || document.getElementById('mobile-scan-' + data.scan_id);
        if (!row) return true; // If no row exists locally, we definitely need to apply.

        const qtyInput = row.querySelector('.qty-input-' + data.scan_id + ', #mobile-qty-input-' + data.scan_id);
        const qtyBadge = row.querySelector('.units-val, #mobile-qty-display-' + data.scan_id + ', #mobile-qty-val-' + data.scan_id);
        const uiUnits = parseInt((qtyInput && qtyInput.value) || (qtyBadge && qtyBadge.textContent) || '0', 10);
        const uiStatus = (row.querySelector('.status-badge-modern, .status-badge-mobile')?.textContent || '').trim();

        const incomingUnits = data.units !== undefined ? Number(data.units) : uiUnits;
        const incomingStatus = data.status !== undefined ? String(data.status).trim() : uiStatus;

        return uiUnits !== incomingUnits || uiStatus !== incomingStatus;
    }

    function reconcileFromServerLatest() {
        if (!window.ScanConfig || !window.ScanConfig.routes || !window.ScanConfig.routes.sync) return;

        fetch('/order-delivery/' + window.ScanConfig.orderId + '/latest-scan', { cache: 'no-store' })
            .then(r => r.json())
            .then(fresh => {
                if (!fresh || !fresh.success) return;
                if (!fresh.has_scan) {
                    setSyncStatus('active');
                    return;
                }

                if (!shouldApplyServerEvent(fresh)) {
                    setSyncStatus('active');
                    return;
                }

                if (window.ScanConfig.isMobileView) {
                    const list = document.getElementById('mobileHistoryList');
                    const existing = document.getElementById('mobile-scan-' + fresh.scan_id);
                    if (existing) {
                        updateUIRow(fresh);
                    } else if (list) {
                        list.innerHTML = '';
                        addNewScanToUI(fresh);
                    }
                } else {
                    if (!document.getElementById('scan-' + fresh.scan_id)) {
                        addNewScanToUI(fresh);
                    }
                    updateUIRow(fresh);
                }
                setSyncStatus('active');
            })
            .catch(() => setSyncStatus('error'));
    }

    // Mobile-specific: sync the displayed card with what the other device just did
    function mobileRefreshFromServer(data) {
        const orderId = (data && data.order_id) ? data.order_id : window.ScanConfig.orderId;
        if (window.ScanConfig && window.ScanConfig.isGlobal && data && data.order_id) {
            window.ScanConfig.orderId = data.order_id;
            const header = document.getElementById('displayOrderId');
            if (header) header.textContent = 'ORDER #' + data.order_id;
        }

        // ── DELETE EVENT ──────────────────────────────────────────────────────
        // The deleted scan is gone — fetch the new latest from server
        if (data && data.delete_scan) {
            const deletedCard = document.getElementById('mobile-scan-' + data.scan_id);
            if (deletedCard) deletedCard.remove();

            fetch('/order-delivery/' + orderId + '/latest-scan', { cache: 'no-store' })
                .then(r => r.json())
                .then(fresh => {
                    const list = document.getElementById('mobileHistoryList');
                    if (!list) return;
                    if (!fresh.success || !fresh.has_scan) {
                        list.innerHTML = '<div class="text-center text-muted mt-5" id="noScansPlaceholder"><p>No scans yet.</p><small>Scan an item to begin.</small></div>';
                        setSyncStatus('active');
                        return;
                    }
                    list.innerHTML = '';
                    addNewScanToUI(fresh);
                    setSyncStatus('active');
                })
                .catch(() => setSyncStatus('error'));
            return;
        }

        // ── SCAN / UPDATE EVENT ───────────────────────────────────────────────
        // We have full data from Pusher — use it directly (ZERO server round-trip)
        if (data && data.scan_id && data.ean_code) {
            if (!shouldApplyServerEvent(data)) {
                setSyncStatus('active');
                return;
            }
            const placeholder = document.getElementById('noScansPlaceholder');
            if (placeholder) placeholder.remove();

            const existingCard = document.getElementById('mobile-scan-' + data.scan_id);
            if (window.ScanConfig && window.ScanConfig.isGlobal) {
                // Global mobile view must always show the latest updated scan as single visible card.
                const list = document.getElementById('mobileHistoryList');
                if (list) list.innerHTML = '';
                addNewScanToUI(data);
            } else if (existingCard) {
                // Same scan already on screen — update values in-place
                updateUIRow(data);
            } else {
                // Different scan was on screen — replace with the just-touched scan
                const list = document.getElementById('mobileHistoryList');
                if (list) list.innerHTML = '';
                addNewScanToUI(data);
            }
            setSyncStatus('active');
            return;
        }

        // ── FALLBACK: fetch from server if data is incomplete ─────────────────
        fetch('/order-delivery/' + orderId + '/latest-scan', { cache: 'no-store' })
            .then(r => r.json())
            .then(fresh => {
                if (!fresh.success) return;
                const list = document.getElementById('mobileHistoryList');
                if (!fresh.has_scan) {
                    if (list) list.innerHTML = '<div class="text-center text-muted mt-5"><p>No scans yet.</p><small>Scan an item to begin.</small></div>';
                    setSyncStatus('active');
                    return;
                }
                const placeholder = document.getElementById('noScansPlaceholder');
                if (placeholder) placeholder.remove();
                const existing = document.getElementById('mobile-scan-' + fresh.scan_id);
                if (existing) { updateUIRow(fresh); } else { if (list) list.innerHTML = ''; addNewScanToUI(fresh); }
                setSyncStatus('active');
            })
            .catch(() => setSyncStatus('error'));
    }

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

            // MOBILE VIEW: sync card with what just happened
            if (window.ScanConfig.isMobileView) {
                if (isSelfInitiated(data) && !hasMeaningfulDiffAgainstUI(data)) {
                    // Self-echo with same values: skip DOM churn.
                    setSyncStatus('active');
                    return;
                }
                // Remote event (from desktop or another device) → update mobile display
                mobileRefreshFromServer(data);
                return;
            }

            // DESKTOP VIEW: fine-grained DOM update
            if (!shouldApplyServerEvent(data)) {
                setSyncStatus('active');
                return;
            }
            updateUIRow(data);

            // Prepend new scan row if not already in DOM
            if (data.scan_id && !document.getElementById('scan-' + data.scan_id) && !data.delete_scan) {
                addNewScanToUI(data);
            }

            if (data.delete_scan) {
                removeScanFromUI(data.scan_id);
            }

            setSyncStatus('active');
        });

        pusher.connection.bind('connected', () => {
            setSyncStatus('active');
            // Ensure eventual consistency after temporary disconnects/missed events.
            reconcileFromServerLatest();
        });
        pusher.connection.bind('disconnected', () => setSyncStatus('error'));
        setSyncStatus('syncing');
    }


    // --- Scanner Logic ---

    window.processScan = function () {
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        const input = document.getElementById('eanInput');
        const ean = input.value.trim();
        if (!ean) return;

        // 1. INSTANT ACTION: Clear and focus immediately
        input.value = '';
        input.focus();

        resetInactivityTimer();
        setSyncStatus('syncing');

        // 2. OPTIMISTIC UI: Find existing product info to show a pending row
        const rowsWithEan = document.querySelectorAll(`[data-ean="${ean}"]`);
        let foundName = null;
        let foundOrdered = 0;
        let foundScanned = 0;

        rowsWithEan.forEach(row => {
            const name = row.querySelector('.fw-800, .item-name-minimal')?.textContent;
            if (name && name !== 'Scanning...' && !name.startsWith('Unknown')) {
                foundName = name;
            }
            const ord = parseInt(row.querySelector('.row-ordered-val')?.textContent);
            if (!isNaN(ord)) foundOrdered = ord;
            const sca = parseInt(row.querySelector('.row-scanned-val')?.textContent);
            if (!isNaN(sca)) foundScanned = sca;
        });

        let foundTarget = 0;
        const sampleRow = document.querySelector('tr[data-ean="' + ean + '"], .history-item-minimal[data-ean="' + ean + '"]');
        if (sampleRow) {
            const t = parseInt(sampleRow.getAttribute('data-delivery-target') || '', 10);
            if (!Number.isNaN(t)) foundTarget = t;
        }
        if (!foundTarget) foundTarget = foundOrdered;

        const pendingId = 'pending-' + Date.now();
        const nextScanned = foundScanned + 1;
        const diff0 = nextScanned - foundTarget;
        let optimisticData = {
            scan_id: pendingId,
            ean_code: ean,
            product_name: foundName || 'Scanning...',
            product_id: '',
            ordered: foundOrdered || 0,
            delivery_target: foundTarget,
            scanned: nextScanned,
            remaining: Math.max(0, foundTarget - nextScanned),
            units: 1,
            status: foundName ? (diff0 === 0 ? 'COMPLETE' : (diff0 > 0 ? 'OVER' : 'UNDER')) : 'PENDING',
            timestamp: new Date().toLocaleDateString('no-NO', { day: '2-digit', month: '2-digit', year: 'numeric' }),
            optimistic: true
        };

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
                    // Mark this scan as self-initiated so mobile ignores the echo
                    markSelfInitiated(data.scan_id);
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

    let pendingChanges = {};
    let debounceTimers = {};

    window.updateUnits = function (scanId, change) {
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        // Mobile only: increment/decrement should not keep keyboard open.
        if (isMobileTouchDevice()) {
            hideSoftKeyboard();
        }

        const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
        if (!row) return;

        // Mark as self-initiated so mobile Pusher echo for this scan is ignored
        markSelfInitiated(scanId);

        // OPTIMISTIC UPDATE: Calculate values immediately from DOM
        const ean = row.getAttribute('data-ean');
        const unitsElem = row.querySelector('.qty-val-minimal') || 
                         row.querySelector('.qty-input-' + scanId) ||
                         row.querySelector('.qty-input-minimal') ||
                         row.querySelector('#mobile-qty-input-' + scanId) ||
                         row.querySelector('.qty-val');
        
        const scannedElems = document.querySelectorAll('.row-scanned-val[data-ean="' + ean + '"]');
        const orderedElems = document.querySelectorAll('.row-ordered-val[data-ean="' + ean + '"]');
        const scannedElem = scannedElems[0];
        const orderedElem = orderedElems[0];

        if (unitsElem) {
            const currentUnits = parseInt(unitsElem.value || unitsElem.textContent) || 0;
            const newUnits = Math.max(0, currentUnits + change);
            const actualChange = newUnits - currentUnits;

            // Update units display immediately (optimistic)
            if (unitsElem.tagName === 'INPUT') {
                unitsElem.value = newUnits;
            } else {
                unitsElem.textContent = newUnits;
            }

            // Update totals if elements exist
            if (scannedElem && orderedElem) {
                const currentScanned = parseInt(scannedElem.textContent) || 0;
                const ordered = parseInt(orderedElem.textContent) || 0;
                const hostRow = row.closest('[data-ean]') || document.querySelector('tr[data-ean="' + ean + '"], .history-item-minimal[data-ean="' + ean + '"]');
                let targetQty = hostRow ? parseInt(hostRow.getAttribute('data-delivery-target') || '', 10) : NaN;
                if (Number.isNaN(targetQty)) targetQty = ordered;
                const newScanned = currentScanned + actualChange;
                const newRemaining = Math.max(0, targetQty - newScanned);
                const diff = newScanned - targetQty;
                const status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

                updateUIRow({
                    ean_code: ean,
                    scan_id: scanId,
                    units: newUnits,
                    scanned: newScanned,
                    ordered: ordered,
                    delivery_target: targetQty,
                    remaining: newRemaining,
                    status: status,
                    optimistic: true
                });
            }
        }

        // Aggregate changes
        if (!pendingChanges[scanId]) {
            pendingChanges[scanId] = 0;
        }
        pendingChanges[scanId] += change;

        setSyncStatus('syncing');

        const aggregatedChange = pendingChanges[scanId];
        pendingChanges[scanId] = 0; // reset

        if (aggregatedChange === 0) {
            setSyncStatus('active');
            return;
        }

        fetch(window.ScanConfig.routes.update, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            },
            body: JSON.stringify({
                scan_id: scanId,
                change: aggregatedChange
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
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        if (!window.ScanConfig.routes.updateExact) {
            console.error('UpdateExact route missing');
            return;
        }

        const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
        if (!row) return;

        // Mark as self-initiated so mobile Pusher echo for this scan is ignored
        markSelfInitiated(scanId);

        // OPTIMISTIC UPDATE
        const ean = row.getAttribute('data-ean');
        const unitsElem = row.querySelector('.qty-val-minimal') || 
                         row.querySelector('.qty-input-' + scanId) ||
                         row.querySelector('.qty-input-minimal') ||
                         row.querySelector('#mobile-qty-input-' + scanId) ||
                         row.querySelector('.qty-val');
        
        const scannedElems = document.querySelectorAll('.row-scanned-val[data-ean="' + ean + '"]');
        const orderedElems = document.querySelectorAll('.row-ordered-val[data-ean="' + ean + '"]');
        const scannedElem = scannedElems[0];
        const orderedElem = orderedElems[0];

        if (unitsElem) {
            const newUnits = parseInt(val) || 0;
            const currentUnits = parseInt(unitsElem.value || unitsElem.textContent) || 0;
            const actualChange = newUnits - currentUnits;

            // Update display
            if (unitsElem.tagName === 'INPUT') {
                unitsElem.value = newUnits;
            } else {
                unitsElem.textContent = newUnits;
            }

            if (scannedElem && orderedElem) {
                const currentScanned = parseInt(scannedElem.textContent) || 0;
                const ordered = parseInt(orderedElem.textContent) || 0;
                const hostRow = row.closest('[data-ean]') || document.querySelector('tr[data-ean="' + ean + '"], .history-item-minimal[data-ean="' + ean + '"]');
                let targetQty = hostRow ? parseInt(hostRow.getAttribute('data-delivery-target') || '', 10) : NaN;
                if (Number.isNaN(targetQty)) targetQty = ordered;
                const newScanned = currentScanned + actualChange;
                const newRemaining = Math.max(0, targetQty - newScanned);
                const diff = newScanned - targetQty;
                const status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

                updateUIRow({
                    ean_code: ean,
                    scan_id: scanId,
                    units: newUnits,
                    scanned: newScanned,
                    ordered: ordered,
                    delivery_target: targetQty,
                    remaining: newRemaining,
                    status: status,
                    optimistic: true
                });
            }
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
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        // Mark as self-initiated so mobile Pusher echo is ignored
        markSelfInitiated(scanId);

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
                // OPTIMISTIC DELETE UI: removing the row immediately
                const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
                let ean = '';
                let units = 0;
                if (row) {
                    ean = row.getAttribute('data-ean');
                    const unitsElem = row.querySelector('.qty-val-minimal') || 
                                     row.querySelector('.qty-input-' + scanId) ||
                                     row.querySelector('.qty-input-minimal') ||
                                     row.querySelector('#mobile-qty-input-' + scanId);
                    if (unitsElem) units = parseInt(unitsElem.value || unitsElem.textContent) || 0;

                    // Optimistic update of totals before server responds
                    const scannedElems = document.querySelectorAll('.row-scanned-val[data-ean="' + ean + '"]');
                    const orderedElems = document.querySelectorAll('.row-ordered-val[data-ean="' + ean + '"]');
                    const scannedElem = scannedElems[0];
                    const orderedElem = orderedElems[0];
                    
                    if (scannedElem && orderedElem) {
                        const currentScanned = parseInt(scannedElem.textContent) || 0;
                        const ordered = parseInt(orderedElem.textContent) || 0;
                        const hostRow = row ? row.closest('[data-ean]') : null;
                        let targetQty = hostRow ? parseInt(hostRow.getAttribute('data-delivery-target') || '', 10) : NaN;
                        if (Number.isNaN(targetQty)) targetQty = ordered;
                        const newScanned = Math.max(0, currentScanned - units);
                        const newRemaining = Math.max(0, targetQty - newScanned);
                        const diff = newScanned - targetQty;
                        const status = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

                        // Fake a data object for updateUIRow to update totals
                        updateUIRow({
                            ean_code: ean,
                            scanned: newScanned,
                            ordered: ordered,
                            delivery_target: targetQty,
                            remaining: newRemaining,
                            status: status,
                            optimistic: true
                        });
                    }
                }

                removeScanFromUI(scanId);

                // Centered success message — appears instantly, no modal conflict
                Swal.fire({
                    position: 'center',
                    icon: 'success',
                    title: 'Deleted!',
                    html: '<span style="font-size:1rem;color:#64748b;">The scan entry has been removed.</span>',
                    showConfirmButton: false,
                    timer: 1800,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal-delete-success-popup',
                        title: 'swal-delete-success-title',
                        icon: 'swal-delete-success-icon'
                    },
                    didOpen: (popup) => {
                        popup.style.borderRadius = '20px';
                        popup.style.padding = '30px 40px';
                        popup.style.boxShadow = '0 20px 60px rgba(0,0,0,0.15)';
                        const title = popup.querySelector('.swal2-title');
                        if (title) { title.style.fontSize = '1.6rem'; title.style.fontWeight = '800'; title.style.color = '#1e293b'; }
                        const icon = popup.querySelector('.swal2-icon');
                        if (icon) { icon.style.borderColor = '#22c55e'; icon.style.color = '#22c55e'; }
                    }
                });

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
                            updateUIRow(data); // Final sync with server truth
                        }
                    })
                    .catch(err => console.error('Delete scan error:', err))
                    .finally(() => setSyncStatus('active'));
            }
        });
    };

    window.matchOrderScans = function (scanId) {
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        if (!window.ScanConfig.routes.matchOrder) {
            console.error('MatchOrder route missing');
            return;
        }

        const row = document.getElementById('scan-' + scanId) || document.getElementById('mobile-scan-' + scanId);
        if (!row) return;

        // Mark as self-initiated so mobile Pusher echo is ignored
        markSelfInitiated(scanId);

        setSyncStatus('syncing');
        fetch(window.ScanConfig.routes.matchOrder, {
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
                    updateUIRow(data); // Final sync
                } else if (data.message) {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Match error:', err);
                setSyncStatus('error');
            })
            .finally(() => setSyncStatus('active'));
    };

    // --- UI Helpers ---

    function updateUIRow(data) {
        if (!data.ean_code) return;

        // Update Scan-specific elements if scan_id is provided
        if (data.scan_id) {
            // Find instances of this scan row
            const desktopRows = document.querySelectorAll('tr.scan-row-' + data.scan_id);
            const mobileRows = document.querySelectorAll('div.scan-row-' + data.scan_id);
            const mobileQtyVals = document.querySelectorAll(
                '.mobile-qty-val-' + data.scan_id + ', ' +
                '#mobile-qty-display-' + data.scan_id + ', ' +
                '#mobile-qty-val-' + data.scan_id + ', ' +
                '#mq-' + data.scan_id
            );
            const qtyInputs = document.querySelectorAll(
                '.qty-input-' + data.scan_id + ', ' +
                '#mobile-qty-input-' + data.scan_id
            );

            desktopRows.forEach(row => {
                const qtyBadge = row.querySelector('.col-qty .units-val, .col-qty .badge');
                if (qtyBadge && data.units !== undefined) {
                    qtyBadge.textContent = data.units;
                }

                // CLEANUP: Remove pending/optimistic styles
                row.classList.remove('opacity-50');
                const actions = row.querySelector('.col-action').querySelectorAll('.invisible');
                actions.forEach(a => a.classList.remove('invisible'));
            });

            if (desktopRows.length) {
                const primaryRow = desktopRows[0];
                promoteRowToLatestStrip(primaryRow);
                primaryRow.style.animation = 'none';
                primaryRow.offsetHeight;
                primaryRow.style.animation = 'slideIn 0.5s ease-out';
            }

            // Handle Mobile list rows separately
            mobileRows.forEach(row => {
                const mobileList = document.getElementById('mobileHistoryList');
                if (mobileList && mobileList.firstChild !== row && mobileList.contains(row)) {
                    mobileList.prepend(row);
                }
            });

            qtyInputs.forEach(input => {
                if (data.units !== undefined) input.value = data.units;
            });

            mobileQtyVals.forEach(el => {
                if (data.units !== undefined) el.textContent = data.units;

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

        if (data.delivery_target !== undefined) {
            document.querySelectorAll('tr[data-ean="' + data.ean_code + '"], .history-item-minimal[data-ean="' + data.ean_code + '"]').forEach(r => {
                r.setAttribute('data-delivery-target', String(data.delivery_target));
            });
        }

        const remainingElems = document.querySelectorAll('.row-remaining-val[data-ean="' + data.ean_code + '"]');
        remainingElems.forEach(el => {
            el.textContent = data.remaining;
            el.style.color = '';
            el.classList.remove('text-primary', 'text-success');
            if (Number(data.remaining) > 0) {
                el.classList.add('text-primary');
            } else {
                el.classList.add('text-success');
            }
        });

        const rows = document.querySelectorAll('tr[data-ean="' + data.ean_code + '"], .history-item-minimal[data-ean="' + data.ean_code + '"]');
        rows.forEach(row => {
            // Update name if it was generic
            const nameEl = row.querySelector('.fw-800, .item-name-minimal');
            if (nameEl && (nameEl.textContent === 'Scanning...' || nameEl.textContent.startsWith('Unknown')) && data.product_name && !data.optimistic) {
                nameEl.textContent = data.product_name;
            }

            const badge = row.querySelector('.status-badge-modern, .status-badge-mobile');
            if (badge) {
                badge.textContent = data.status;
                const color = data.status === 'COMPLETE' ? '#22c55e' : (data.status === 'OVER' ? '#ef4444' : '#007bff');
                if (badge.classList.contains('status-badge-modern')) {
                    badge.style.background = color;
                } else {
                    badge.style.color = color;
                    badge.style.background = color + '15';
                }
            }
        });

        const progressBar = document.querySelector('.progress-bar');
        if (progressBar && data.progress_percent !== undefined) {
            progressBar.style.width = data.progress_percent + '%';
            progressBar.setAttribute('aria-valuenow', data.progress_percent);
            progressBar.textContent = data.progress_percent + '%';
        }

        if (isMobileTouchDevice()) {
            document.querySelectorAll('.qty-input, .qty-input-minimal').forEach((el) => {
                if (document.activeElement !== el) {
                    el.setAttribute('readonly', 'readonly');
                }
            });
        }

        reapplyActiveHubFilter();
        resetInactivityTimer();
    }

    function addNewScanToUI(data) {
        if (!data.scan_id) return;

        const historyTable = document.getElementById('scanHistory');
        const mobileList = document.getElementById('mobileHistoryList');

        // Prevent Duplicate Rows for the same Scan ID (e.g. from both Pusher and Fetch)
        if (document.getElementById('scan-' + data.scan_id) || document.getElementById('mobile-scan-' + data.scan_id)) {
            updateUIRow(data); // Just update if already exists
            return;
        }

        resetInactivityTimer();

        // 1. Remove matching "Missing/Not Scanned" placeholder immediately
        document.querySelectorAll(`.history-row[data-scan-status="missing"][data-ean="${data.ean_code}"]`).forEach(el => el.remove());
        document.querySelectorAll(`.history-item-minimal[data-scan-status="missing"][data-ean="${data.ean_code}"]`).forEach(el => el.remove());

        // 2. Remove Pending/Optimistic Rows for this EAN when real data arrives
        if (!data.optimistic) {
            document.querySelectorAll(`.history-row.opacity-50[data-ean="${data.ean_code}"]`).forEach(el => el.remove());
            document.querySelectorAll(`.history-item-minimal.opacity-50[data-ean="${data.ean_code}"]`).forEach(el => el.remove());
        }

        const isPending = String(data.scan_id).startsWith('pending-');

        const latestScanRow = document.getElementById('latestScanRow');
        if (historyTable && latestScanRow) {
            while (latestScanRow.firstElementChild) {
                const old = latestScanRow.firstElementChild;
                old.classList.remove('latest-scan-row');
                historyTable.prepend(old);
            }
        } else if (historyTable) {
            historyTable.querySelectorAll('tr.latest-scan-row').forEach((r) => r.classList.remove('latest-scan-row'));
        }

        // 2. Prepare the new row HTML (Desktop)
        const statusColor = data.status === 'COMPLETE' ? '#22c55e' : (data.status === 'OVER' ? '#ef4444' : (data.status === 'PENDING' ? '#94a3b8' : '#007bff'));
        const restClass = Number(data.remaining) > 0 ? 'text-primary' : 'text-success';
        const eanMissingAttr = (data.ean_missing || (data.product_id && data.ean_code && String(data.ean_code) == String(data.product_id))) ? '1' : '0';
        const productIdDisp = data.product_id ? String(data.product_id) : '—';
        const eanDisp = data.ean_code ? String(data.ean_code) : '—';
        const eanDisplayHtml = (data.product_id && data.ean_code)
            ? `<span class="selectable-ean clickable-ean" onclick="addEanCode('${String(data.product_id).replace(/'/g, "\\'")}', '${String(data.product_name || '').replace(/'/g, "\\'")}', '${String(data.ean_code || '').replace(/'/g, "\\'")}')">${eanDisp}</span>`
            : `<span class="selectable-ean">${eanDisp}</span>`;
        const rowHtml = `
            <tr id="scan-${data.scan_id}" class="history-row latest-scan-row scan-row-${data.scan_id} ${isPending ? 'opacity-50' : ''}" data-ean="${data.ean_code}" data-delivery-target="${data.delivery_target !== undefined ? data.delivery_target : data.ordered}" data-scan-status="scanned" data-ean-missing="${eanMissingAttr}" data-product-id="${data.product_id || ''}" data-product-name="${String(data.product_name || '').replace(/"/g, '&quot;')}" data-sort-key="${Number(data.scan_id) || Date.now()}">
                <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">${data.timestamp || '--:--'}</td>
                <td class="col-product">
                    <div class="fw-800 text-dark">${data.product_name}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        Varenr : ${productIdDisp}&nbsp;&nbsp;EAN : ${eanDisplayHtml}
                    </div>
                    ${(data.ean_missing || (data.product_id && data.ean_code && String(data.ean_code) == String(data.product_id))) ? `
                    <div class="ean-missing-wrapper">
                        <div class="ean-missing-tag">
                            &#9888; EAN MISSING &ndash; Using VareNr
                        </div>
                        <button class="btn-add-ean" onclick="addEanCode('${data.product_id}', '${data.product_name.replace(/'/g, "\\'")}')">
                            + ADD EAN
                        </button>
                    </div>
                    ` : ''}
                </td>
                <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="${data.ean_code}">${data.ordered}</td>
                <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="${data.ean_code}">${data.scanned}</td>
                <td class="row-remaining-val col-rest fw-700 ${restClass}" data-ean="${data.ean_code}">${data.remaining}</td>
                <td class="col-qty">
                    <span class="badge bg-light text-secondary border-0 fw-700 p-1 units-val" style="font-size: 0.75rem;">${data.units || 1}</span>
                </td>
                <td class="col-status">
                    <span class="status-badge-modern" style="background: ${statusColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">
                        ${data.status}
                    </span>
                </td>
                <td class="col-action">
                    <div class="d-flex justify-content-center gap-1 ${isPending ? 'invisible' : ''}">
                        <div class="qty-pill">
                            <button type="button" class="qty-btn" onclick="updateUnits(${data.scan_id}, -1)">-</button>
                            <input type="number" class="qty-input qty-input-${data.scan_id}" id="qty-input-${data.scan_id}" value="${data.units || 1}" onchange="updateUnitsExact(${data.scan_id}, this.value)" style="width: 30px; height: 18px; text-align: center; border: none; font-weight: 700;">
                            <button type="button" class="qty-btn text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                        </div>
                        <button class="btn btn-link text-danger p-0" onclick="deleteScan(${data.scan_id})">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" /></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;

        if (latestScanRow) {
            latestScanRow.insertAdjacentHTML('beforeend', rowHtml);
        } else if (historyTable) {
            historyTable.insertAdjacentHTML('afterbegin', rowHtml);
        }
        syncLastScannedStripVisibility();

        // 3. Mobile list handling
        if (mobileList) {
            // Remove latest class from existing mobile items
            document.querySelectorAll('#mobileHistoryList .latest-scan-row').forEach(el => el.classList.remove('latest-scan-row'));
            const mobileHtml = `
                <div class="history-item-minimal scan-row-${data.scan_id} latest-scan-row ${isPending ? 'opacity-50' : ''}" id="mobile-scan-${data.scan_id}" data-scan-status="scanned" data-ean-missing="${eanMissingAttr}" data-ean="${data.ean_code}" data-delivery-target="${data.delivery_target !== undefined ? data.delivery_target : data.ordered}" data-product-id="${data.product_id || ''}" data-product-name="${String(data.product_name || '').replace(/"/g, '&quot;')}" data-sort-key="${Number(data.scan_id) || Date.now()}">
                    <div class="item-data" style="width: 100%;">
                        <div class="d-flex justify-content-end align-items-start w-100 mb-1">
                            <span class="status-badge-mobile" style="color: white; background: ${statusColor}; font-size: 0.7rem;">${data.status}</span>
                        </div>

                        <div class="text-center mb-2">
                            <div style="font-size: 1.1rem; justify-content: center;" class="fw-800 item-name-minimal d-flex align-items-center flex-wrap gap-1">
                                ${data.product_name}
                                ${data.order_id2 ? `<span class="badge bg-light text-secondary border" style="font-size: 0.65rem;" title="Original Order List">${data.order_id2}</span>` : ''}
                            </div>
                            <div class="item-meta-minimal mt-1">
                                Varenr : ${productIdDisp}&nbsp;&nbsp;EAN : ${eanDisp}
                            </div>
                            ${(data.ean_missing || (data.product_id && data.ean_code && String(data.ean_code) == String(data.product_id))) ? `
                            <div style="display:flex; align-items:center; justify-content:center; gap:6px; background:#fff7ed; border:2px solid #fed7aa; border-radius:8px; padding:5px 10px; margin-top:6px; font-size:0.75rem; font-weight:700; color:#c2410c;">
                                &#9888; EAN Code MISSING &ndash; Found Varenummer
                            </div>
                            ` : ''}
                        </div>

                        <div class="metrics-grid mb-3">
                            <div class="metric-box">
                                <span class="metric-label">Ordered</span>
                                <span class="metric-val row-ordered-val" data-ean="${data.ean_code}">${data.ordered}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Scanned</span>
                                <span class="metric-val row-scanned-val" data-ean="${data.ean_code}">${data.scanned}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Rest</span>
                                <span class="metric-val remaining row-remaining-val" data-ean="${data.ean_code}">${data.remaining}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">#</span>
                                <span class="metric-val qty-val-minimal" id="mobile-qty-display-${data.scan_id}">${data.units || 1}</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center align-items-center gap-4 ${isPending ? 'invisible' : ''}">
                            <button type="button" class="qty-btn-minimal fw-800" style="color: #2563eb; background: #eff6ff; border-radius: 8px; font-size: 1rem; padding: 0 10px;" onclick="matchOrderScans(${data.scan_id})">#OK</button>
                            <div class="qty-pill-minimal">
                                <button type="button" class="qty-btn-minimal" onclick="updateUnits(${data.scan_id}, -1)">−</button>
                                <span class="qty-val-minimal" id="mobile-qty-val-${data.scan_id}" style="display: none;">${data.units || 1}</span>
                                <input type="number" inputmode="numeric" class="qty-input-minimal" id="mobile-qty-input-${data.scan_id}" value="${data.units || 1}" onchange="updateUnitsExact(${data.scan_id}, this.value)" style="width: 50px; text-align: center; font-weight: 800; font-size: 1.2rem; border: none; background: transparent; padding: 0; margin: 0 5px; color: #1e293b; outline: none; -moz-appearance: textfield;">
                                <button type="button" class="qty-btn-minimal text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                            </div>
                            <button class="btn-delete-action shadow-sm" onclick="deleteScan(${data.scan_id})">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            mobileList.insertAdjacentHTML('afterbegin', mobileHtml);
        }

        if (isMobileTouchDevice()) {
            document.querySelectorAll('#mobile-qty-input-' + data.scan_id + ', .qty-input-' + data.scan_id).forEach((el) => {
                if (document.activeElement !== el) {
                    el.setAttribute('readonly', 'readonly');
                }
            });
        }

        reapplyActiveHubFilter();
    }

    function removeScanFromUI(scanId) {
        const latestTbody = document.getElementById('latestScanRow');
        const historyTbody = document.getElementById('scanHistory');
        const row = document.getElementById('scan-' + scanId);
        const wasInLatest = row && latestTbody && latestTbody.contains(row);

        if (row) row.remove();

        const mobileItem = document.getElementById('mobile-scan-' + scanId);
        if (mobileItem) mobileItem.remove();

        if (wasInLatest && historyTbody && latestTbody) {
            const firstReal = historyTbody.querySelector('tr.history-row[id^="scan-"]');
            if (firstReal) {
                promoteRowToLatestStrip(firstReal);
            } else {
                syncLastScannedStripVisibility();
            }
        } else if (historyTbody) {
            historyTbody.querySelectorAll('tr.latest-scan-row').forEach((r) => {
                if (latestTbody && !latestTbody.contains(r)) r.classList.remove('latest-scan-row');
            });
            syncLastScannedStripVisibility();
        }
        reapplyActiveHubFilter();
        resetInactivityTimer();
    }

    // --- Hub filter / error classification (PRINT + tabs + close validation) ---

    function parseQuantitiesFromRow(row) {
        const orderedEl = row.querySelector('.row-ordered-val');
        const scannedEl = row.querySelector('.row-scanned-val');
        const parseNum = (el) => {
            if (!el) return NaN;
            const t = (el.textContent || '').trim().replace(/\s+/g, '');
            const n = parseInt(t, 10);
            return Number.isNaN(n) ? NaN : n;
        };
        let ordered = parseNum(orderedEl);
        let scanned = parseNum(scannedEl);
        if (Number.isNaN(ordered)) ordered = 0;
        if (Number.isNaN(scanned)) scanned = 0;
        let target = parseInt(row.getAttribute('data-delivery-target') || '', 10);
        if (Number.isNaN(target)) {
            target = ordered;
        }
        return { ordered, scanned, target };
    }

    function hubRowEanMissing(row) {
        if (row.getAttribute('data-ean-missing') === '1') return true;
        return !!(row.querySelector('.ean-missing-wrapper, .ean-missing-tag'));
    }

    /**
     * ERROR = scan problems only — not “not scanned yet”.
     * Includes: over/under when some units were scanned, unknown EAN (not on order), missing EAN info.
     */
    function hubRowIsError(row) {
        if (hubRowEanMissing(row)) return true;
        const { scanned, target } = parseQuantitiesFromRow(row);
        if (target === 0 && scanned > 0) return true;
        if (scanned > target) return true;
        if (target > 0 && scanned > 0 && scanned < target) return true;
        return false;
    }

    function hubRowIsRest(row) {
        const { scanned, target } = parseQuantitiesFromRow(row);
        return target > 0 && scanned === 0;
    }

    function hubRowMatchesFilter(filter, row) {
        const blank = row.querySelector('td[colspan]');
        if (blank) {
            return filter === 'ordered';
        }

        const f = filter === 'order' ? 'ordered' : filter;
        const { ordered, scanned } = parseQuantitiesFromRow(row);

        if (f === 'ordered') return true;
        if (f === 'scanned') return scanned > 0;
        if (f === 'rest') return hubRowIsRest(row);
        if (f === 'error') return hubRowIsError(row);
        return true;
    }

    function reapplyActiveHubFilter() {
        const link = document.querySelector('.filter-link.active');
        const f = link ? link.getAttribute('data-filter') : 'scanned';
        window.applyFilter(f || 'scanned');
    }

    function getProductLookupTerm() {
        const input = document.getElementById('productLookupInput');
        return (input && input.value ? input.value : '').trim().toUpperCase();
    }

    function rowMatchesProductLookup(row, lookupTerm) {
        if (!lookupTerm) return true;
        const tokens = lookupTerm.split(/\s+/).filter(Boolean);
        if (!tokens.length) return true;
        const productName = (row.getAttribute('data-product-name') || '').toUpperCase();
        const ean = (row.getAttribute('data-ean') || '').toUpperCase().replace(/\s+/g, '');
        const vareNr = (row.getAttribute('data-product-id') || '').toUpperCase().replace(/\s+/g, '');
        const haystack = productName + ' ' + ean + ' ' + vareNr;
        return tokens.every((t) => {
            const raw = (t || '').toUpperCase();
            if (!raw) return true;
            if (haystack.includes(raw)) return true;
            const compact = raw.replace(/\s+/g, '');
            if (compact && (ean.includes(compact) || vareNr.includes(compact))) return true;
            return false;
        });
    }

    function sortVisibleHistoryByNewest() {
        const lookupTerm = getProductLookupTerm();
        const historyTbody = document.getElementById('scanHistory');
        if (historyTbody) {
            const rows = Array.from(historyTbody.querySelectorAll(':scope > tr.history-row'));
            if (lookupTerm) {
                rows.sort((a, b) => {
                    const ma = rowMatchesProductLookup(a, lookupTerm) ? 1 : 0;
                    const mb = rowMatchesProductLookup(b, lookupTerm) ? 1 : 0;
                    if (ma !== mb) return mb - ma;
                    return Number(b.getAttribute('data-sort-key') || 0) - Number(a.getAttribute('data-sort-key') || 0);
                });
            } else {
                rows.sort((a, b) => Number(b.getAttribute('data-sort-key') || 0) - Number(a.getAttribute('data-sort-key') || 0));
            }
            rows.forEach((row) => historyTbody.appendChild(row));
        }

        const mobileList = document.getElementById('mobileHistoryList');
        if (mobileList) {
            const cards = Array.from(mobileList.querySelectorAll(':scope > .history-item-minimal'));
            if (lookupTerm) {
                cards.sort((a, b) => {
                    const ma = rowMatchesProductLookup(a, lookupTerm) ? 1 : 0;
                    const mb = rowMatchesProductLookup(b, lookupTerm) ? 1 : 0;
                    if (ma !== mb) return mb - ma;
                    return Number(b.getAttribute('data-sort-key') || 0) - Number(a.getAttribute('data-sort-key') || 0);
                });
            } else {
                cards.sort((a, b) => Number(b.getAttribute('data-sort-key') || 0) - Number(a.getAttribute('data-sort-key') || 0));
            }
            cards.forEach((card) => mobileList.appendChild(card));
        }
    }

    /**
     * When the log filter is active, several rows can share the same EAN (separate scan events).
     * For search/focus UX, keep a single visible row per EAN (highest data-sort-key = newest).
     */
    function dedupeHubRowsByEanWhenFiltered(lookupTerm) {
        if (!lookupTerm) return;
        const activeTab = document.querySelector('.filter-link.active');
        if (activeTab && activeTab.getAttribute('data-filter') === 'error') return;

        const dedupeGroup = (rowElements) => {
            const rows = Array.from(rowElements);
            const visible = rows.filter((r) => r.style.display !== 'none');
            const best = new Map();
            visible.forEach((row) => {
                const ean = (row.getAttribute('data-ean') || '').trim();
                if (!ean) return;
                const sk = Number(row.getAttribute('data-sort-key') || 0);
                if (!best.has(ean) || sk > best.get(ean).sk) {
                    best.set(ean, { el: row, sk });
                }
            });
            visible.forEach((row) => {
                const ean = (row.getAttribute('data-ean') || '').trim();
                if (!ean) return;
                const winner = best.get(ean);
                if (!winner) return;
                row.style.display = row === winner.el ? '' : 'none';
            });
        };

        dedupeGroup(document.querySelectorAll('#latestScanRow tr.history-row, #scanHistory tr.history-row'));
        dedupeGroup(document.querySelectorAll('#mobileHistoryList .history-item-minimal'));
    }

    window.reapplyActiveHubFilter = reapplyActiveHubFilter;

    // --- Filtering ---

    function getActiveReportFilter() {
        const filterLink = document.querySelector('.filter-link.active');
        let reportFilter = filterLink ? filterLink.getAttribute('data-filter') : 'ordered';
        if (reportFilter === 'order') reportFilter = 'ordered';
        return reportFilter || 'ordered';
    }

    function getCurrentPrintCandidateRows() {
        const mobileListEl = document.getElementById('mobileHistoryList');
        const useMobileRows = mobileListEl && window.getComputedStyle(mobileListEl).display !== 'none';
        if (useMobileRows) {
            return Array.from(mobileListEl.querySelectorAll('.history-item-minimal'));
        }
        const latestRows = Array.from(document.querySelectorAll('#latestScanRow tr.history-row'));
        const historyRows = Array.from(document.querySelectorAll('#scanHistory tr.history-row'));
        const seen = new Set();
        const merged = [];
        [...latestRows, ...historyRows].forEach((r) => {
            const id = r.getAttribute('id');
            if (id && seen.has(id)) return;
            if (id) seen.add(id);
            merged.push(r);
        });
        return merged;
    }

    function buildPrintableSelection(reportFilter) {
        const rowNodes = getCurrentPrintCandidateRows();
        const seenKeys = new Set();
        const rows = [];

        rowNodes.forEach((row) => {
            if (row.style.display === 'none') return;

            const colspanCell = row.querySelector('td[colspan]');
            if (colspanCell) {
                // Empty placeholder rows are UI-only and should never generate a PDF page.
                return;
            }

            if (!hubRowMatchesFilter(reportFilter, row)) return;

            const rowId = (row.getAttribute('id') || '').trim();
            const ean = (row.getAttribute('data-ean') || '').trim();
            const name = (row.getAttribute('data-product-name') || '').trim();
            // Stable key to prevent duplicated/fragment rows in PDF output.
            const key = rowId || (ean + '|' + name);
            if (key && seenKeys.has(key)) return;
            if (key) seenKeys.add(key);

            const nameNode = row.querySelector('.col-product .fw-800, .item-name-minimal');
            const eanNode = row.querySelector('.selectable-ean, .item-meta-minimal, .col-product .text-muted');
            const visibleName = nameNode ? (nameNode.textContent || '').trim() : '';
            const visibleEan = eanNode ? (eanNode.textContent || '').trim() : '';
            // Guard against malformed partial rows (e.g. broken split line with just a number).
            if (!visibleName && !visibleEan && !name && !ean) return;

            rows.push(row);
        });

        return { rows };
    }

    function countPrintableRowsForCurrentSelection() {
        const reportFilter = getActiveReportFilter();
        const selection = buildPrintableSelection(reportFilter);
        return selection.rows.length;
    }

    function updatePrintButtonState() {
        const btn = document.getElementById('printReportBtn');
        if (!btn) return;
        const count = countPrintableRowsForCurrentSelection();
        const disabled = count === 0;
        btn.disabled = disabled;
        btn.title = disabled
            ? 'No rows match current tab/search selection to print.'
            : 'Print rows matching the current log filter and search selection.';
    }

    window.applyFilter = function (filter) {
        const links = document.querySelectorAll('.filter-link');
        links.forEach(l => {
            if (l.getAttribute('data-filter') === filter) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });

        const lookupTerm = getProductLookupTerm();
        const rows = document.querySelectorAll('.history-row, .history-item-minimal');
        rows.forEach(row => {
            const blank = row.querySelector('td[colspan]');
            if (blank && lookupTerm) {
                row.style.display = 'none';
                return;
            }
            const passesTabFilter = hubRowMatchesFilter(filter, row);
            const matchesLookup = rowMatchesProductLookup(row, lookupTerm);
            // ERROR tab must list every blocking row; search was hiding unknown / mismatched labels.
            const passesLookup = filter === 'error' ? true : (!lookupTerm || matchesLookup);
            row.style.display = (passesTabFilter && passesLookup) ? '' : 'none';
        });

        sortVisibleHistoryByNewest();
        dedupeHubRowsByEanWhenFiltered(lookupTerm);
        syncLastScannedStripVisibility();
        updatePrintButtonState();
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

        const selectedCount = countPrintableRowsForCurrentSelection();
        if (selectedCount === 0) {
            updatePrintButtonState();
            Swal.fire({
                icon: 'info',
                title: 'Nothing to print',
                text: 'No rows match the selected tab/search.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        setSyncStatus('syncing');

        // Capture data from the UI to build the report
        const orderId = window.ScanConfig.orderId;
        const isCompleted = window.ScanConfig.orderStatus === 'Completed';
        const reportDate = (isCompleted && window.ScanConfig.lockTimestamp)
            ? window.ScanConfig.lockTimestamp
            : new Date().toLocaleDateString('no-NO');
        const staff = document.getElementById('staffName')?.value || 'Guest';

        const reportFilter = getActiveReportFilter();

        const filterTitles = {
            scanned: 'SCANNED',
            ordered: 'ORDERED',
            rest: 'REST',
            error: 'ERROR'
        };
        const filterTitle = filterTitles[reportFilter] || 'ORDERED';

        const selection = buildPrintableSelection(reportFilter);
        const rowNodes = selection.rows;

        function extractPrintFields(row) {
            const nameDesk = row.querySelector('.col-product .fw-800');
            const nameMob = row.querySelector('.item-name-minimal');
            const name = (nameDesk || nameMob)?.textContent.trim() || 'Unknown';

            const metaText = (row.querySelector('.item-meta-minimal, .col-product .text-muted')?.textContent || '')
                .replace(/\s+/g, ' ')
                .trim();
            const metaVareNr = (metaText.match(/Vare(?:Nr|nr)\.?\s*:\s*([^\s/|]+)/i) || [])[1] || '';
            const metaEan = (metaText.match(/EAN\s*:\s*([^\s/|]+)/i) || [])[1] || '';

            const vareNr = ((row.getAttribute('data-product-id') || '').trim() || metaVareNr).trim();
            const eanVal = ((row.getAttribute('data-ean') || '').trim() || metaEan).trim();
            const eanLine = `Varenr : ${vareNr || '—'}  EAN : ${eanVal || '—'}`;

            const ordered = row.querySelector('.row-ordered-val')?.textContent.trim() || '0';
            const scanned = row.querySelector('.row-scanned-val')?.textContent.trim() || '0';
            const restEl = row.querySelector('.row-remaining-val');
            const rest = restEl ? restEl.textContent.trim() : '0';

            const statusNode = row.querySelector('.status-badge-modern, .status-badge-mobile');
            const status = statusNode ? statusNode.textContent.trim() : '';

            return { name, eanLine, ordered, scanned, rest, status };
        }

        // Build the HTML for the PDF — strictly rows currently visible under active filters/search
        let tableRows = '';
        rowNodes.forEach(row => {
            const { name, eanLine, ordered, scanned, rest, status } = extractPrintFields(row);
            tableRows += `
                <tr>
                    <td class="pdf-col-product">
                        <span class="pdf-product-name">${name}</span>
                        <span class="pdf-product-meta">${eanLine}</span>
                    </td>
                    <td class="pdf-col-num">${ordered}</td>
                    <td class="pdf-col-num">${scanned}</td>
                    <td class="pdf-col-num">${rest}</td>
                    <td class="pdf-col-status">${status}</td>
                </tr>
            `;
        });

        if (!tableRows.trim()) {
            printArea.style.display = 'none';
            setSyncStatus('active');
            Swal.fire({
                icon: 'info',
                title: 'Nothing to print',
                text: 'No rows match the selected filter for this report.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        const reportHTML = `
            <div style="font-family: Arial, sans-serif; padding: 40px; color: #333;">
                <style>
                    .pdf-report-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                        table-layout: fixed;
                    }
                    .pdf-report-table thead th,
                    .pdf-report-table tbody td {
                        border-bottom: 1px solid #e5e7eb;
                        vertical-align: top;
                        page-break-inside: avoid;
                        break-inside: avoid;
                    }
                    .pdf-report-table thead th {
                        padding: 10px 8px;
                        font-size: 12px;
                        font-weight: 700;
                        background: #f8fafc;
                    }
                    .pdf-report-table tbody td {
                        padding: 8px;
                        font-size: 12px;
                    }
                    .pdf-report-table tbody tr {
                        page-break-inside: avoid !important;
                        break-inside: avoid !important;
                    }
                    .pdf-col-product {
                        width: 56%;
                        word-break: break-word;
                        overflow-wrap: anywhere;
                    }
                    .pdf-col-num {
                        width: 11%;
                        text-align: center;
                        white-space: nowrap;
                    }
                    .pdf-col-status {
                        width: 11%;
                        text-align: center;
                        font-weight: 700;
                        white-space: nowrap;
                    }
                    .pdf-product-name {
                        display: block;
                        font-weight: 700;
                        line-height: 1.3;
                    }
                    .pdf-product-meta {
                        display: block;
                        margin-top: 2px;
                        color: #64748b;
                        font-size: 11px;
                        line-height: 1.25;
                        word-break: break-word;
                        overflow-wrap: anywhere;
                    }
                </style>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    <div>
                        <h1 style="margin: 0; font-size: 24px; text-transform: uppercase;">Reception Report</h1>
                        <p style="margin: 5px 0; color: #666;">Order ID: <strong>#${orderId}</strong></p>
                        <p style="margin: 0; color: #2563eb; font-weight: 700;">View: ${filterTitle}</p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 0;">${isCompleted ? 'Locked At' : 'Date'}: ${reportDate}</p>
                        <p style="margin: 5px 0;">Staff: ${staff}</p>
                    </div>
                </div>

                <table class="pdf-report-table">
                    <thead>
                        <tr style="text-align: left;">
                            <th class="pdf-col-product">PRODUCT</th>
                            <th class="pdf-col-num">ORDERED</th>
                            <th class="pdf-col-num">SCANNED</th>
                            <th class="pdf-col-num">REST</th>
                            <th class="pdf-col-status">STATUS</th>
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
            filename: `Reception_Report_Order_${orderId}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'] }
        };

        html2pdf().from(printArea).set(opt).save()
            .then(() => {
                printArea.style.display = 'none';
                setSyncStatus('active');
            })
            .catch(err => {
                console.error('PDF generation error:', err);
                setSyncStatus('error');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Print / PDF failed',
                        text: (err && err.message) ? String(err.message) : 'Could not generate the PDF. Check the browser console or try another browser.',
                        confirmButtonColor: '#2563eb'
                    });
                }
            });
    };

    // --- EAN Update Logic ---
    window.addEanCode = function (productId, productName, currentEan = '') {
        if (isReportLocked()) {
            showLockedMessage();
            return;
        }

        Swal.fire({
            title: 'Update EAN Code',
            html: `
                <div style="text-align: left; margin-bottom: 10px;">
                    <small class="text-muted">Product:</small><br>
                    <strong>${productName}</strong><br>
                    <small class="text-muted">VareNummer: <strong>${productId}</strong></small>
                    ${currentEan ? `<br><small class="text-muted">Current EAN: <strong>${currentEan}</strong></small>` : ''}
                </div>
                <input type="text" id="swal-new-ean" class="swal2-input" placeholder="Scan or type new EAN" autocomplete="off">
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Save',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            didOpen: () => {
                const input = document.getElementById('swal-new-ean');
                if (input) {
                    if (currentEan) {
                        input.value = currentEan;
                        input.select();
                    }
                    input.focus();
                }
            },
            preConfirm: () => {
                const newEan = document.getElementById('swal-new-ean').value.trim();
                if (!newEan) {
                    Swal.showValidationMessage('Please enter an EAN code');
                    return false;
                }
                return newEan;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const newEan = result.value;
                setSyncStatus('syncing');

                fetch(window.ScanConfig.routes.updateEan, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        new_ean: newEan
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'EAN Updated!',
                            text: 'EAN code has been saved for this product.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload to reflect new state
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to update EAN', 'error');
                    }
                })
                .catch(err => {
                    console.error('Update EAN error:', err);
                    Swal.fire('Error', 'Network error while updating EAN', 'error');
                })
                .finally(() => {
                    setSyncStatus('active');
                });
            }
        });
    };

    // --- Init ---
    document.addEventListener('DOMContentLoaded', () => {
        // Keep keyboard closed for +/- buttons; capture phase so the input does not receive focus on tablets.
        ['mousedown', 'touchstart'].forEach(evtName => {
            document.addEventListener(evtName, (event) => {
                if (!isMobileTouchDevice()) return;
                const adjustButton = event.target.closest('.qty-btn, .qty-btn-minimal');
                if (!adjustButton) return;
                hideSoftKeyboard();
                event.preventDefault();
            }, { passive: false, capture: true });
        });

        // Quantity fields: on touch devices keep readonly until the user focuses the field explicitly (then keyboard is OK).
        function applyTouchReadonlyQty() {
            if (!isMobileTouchDevice()) return;
            document.querySelectorAll('.qty-input, .qty-input-minimal').forEach((el) => {
                el.setAttribute('readonly', 'readonly');
            });
        }
        document.body.addEventListener('focusin', (e) => {
            if (!isMobileTouchDevice()) return;
            if (e.target.matches('.qty-input, .qty-input-minimal')) {
                e.target.removeAttribute('readonly');
            }
        }, true);
        document.body.addEventListener('focusout', (e) => {
            if (!isMobileTouchDevice()) return;
            if (e.target.matches('.qty-input, .qty-input-minimal')) {
                e.target.setAttribute('readonly', 'readonly');
            }
        }, true);
        applyTouchReadonlyQty();

        // Auto-focus on input if it exists
        const input = document.getElementById('eanInput');
        if (input) {
            input.focus();
            // Enter key listener
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    if (isReportLocked()) {
                        showLockedMessage();
                        return;
                    }
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

                // Blocking issues: wrong counts once scanning started, unknown EAN, missing EAN on file — not "not scanned yet" (REST)
                let hasErrors = false;
                document.querySelectorAll('#latestScanRow tr.history-row, #scanHistory tr.history-row').forEach(row => {
                    if (hubRowIsError(row)) hasErrors = true;
                });
                const mobileHistory = document.getElementById('mobileHistoryList');
                if (mobileHistory && window.getComputedStyle(mobileHistory).display !== 'none') {
                    mobileHistory.querySelectorAll('.history-item-minimal').forEach(row => {
                        if (hubRowIsError(row)) hasErrors = true;
                    });
                }

                if (hasErrors) {
                    Swal.fire({
                        position: 'center',
                        html: `
                            <div style="text-align:center; padding: 10px 0;">
                                <div style="width:72px;height:72px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                </div>
                                <div style="font-size:1.5rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Cannot Close Session</div>
                                <div style="font-size:0.95rem;color:#64748b;line-height:1.6;">Please fix all scanning errors<br><strong style="color:#ef4444;">Over / Under / Unknown Scan / Missing EAN</strong><br>before finishing the session.</div>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'Got it',
                        confirmButtonColor: '#ef4444',
                        customClass: { popup: 'swal-custom-popup', confirmButton: 'swal-custom-btn' },
                        didOpen: (popup) => {
                            popup.style.borderRadius = '24px';
                            popup.style.padding = '30px 36px';
                            popup.style.boxShadow = '0 25px 60px rgba(239,68,68,0.15)';
                        }
                    });
                    return;
                }

                Swal.fire({
                    position: 'center',
                    html: `
                        <div style="text-align:center; padding: 10px 0;">
                            <div style="width:72px;height:72px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div style="font-size:1.5rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Archive Session?</div>
                            <div style="font-size:0.95rem;color:#64748b;line-height:1.6;">Are you sure you want to<br><strong style="color:#2563eb;">finish and archive</strong> this session?<br>This action cannot be undone.</div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, finish it!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#94a3b8',
                    reverseButtons: true,
                    customClass: { popup: 'swal-custom-popup' },
                    didOpen: (popup) => {
                        popup.style.borderRadius = '24px';
                        popup.style.padding = '30px 36px';
                        popup.style.boxShadow = '0 25px 60px rgba(37,99,235,0.15)';
                    }
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
                                        position: 'center',
                                        html: `
                                            <div style="text-align:center; padding: 10px 0;">
                                                <div style="width:72px;height:72px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                </div>
                                                <div style="font-size:1.5rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Session Archived!</div>
                                                <div style="font-size:0.95rem;color:#64748b;">Order session archived successfully.</div>
                                            </div>
                                        `,
                                        showConfirmButton: false,
                                        timer: 2200,
                                        timerProgressBar: true,
                                        customClass: { popup: 'swal-custom-popup' },
                                        didOpen: (popup) => {
                                            popup.style.borderRadius = '24px';
                                            popup.style.padding = '30px 36px';
                                            popup.style.boxShadow = '0 25px 60px rgba(34,197,94,0.15)';
                                        }
                                    }).then(() => {
                                        const idx = window.ScanConfig.routes && window.ScanConfig.routes.index;
                                        window.location.href = idx || '/order-delivery';
                                    });
                                } else {
                                    const rawDetails = (data.details || '').trim();
                                    const esc = (s) => String(s || '')
                                        .replace(/&/g, '&amp;')
                                        .replace(/</g, '&lt;')
                                        .replace(/>/g, '&gt;')
                                        .replace(/"/g, '&quot;');
                                    const issues = Array.isArray(data.blocking_issues) ? data.blocking_issues : [];
                                    let issuesHtml = '';
                                    if (issues.length) {
                                        const rows = issues.map((it) => {
                                            const ids = (it.order_scan_ids && it.order_scan_ids.length)
                                                ? `<div style="margin-top:4px;font-size:0.8rem;color:#475569;"><strong>order_scans.id:</strong> ${esc((it.order_scan_ids || []).join(', '))}</div>`
                                                : '';
                                            const br = (it.scan_breakdown && it.scan_breakdown.length)
                                                ? `<div style="margin-top:6px;font-size:0.78rem;color:#64748b;line-height:1.45;">${(it.scan_breakdown || []).map((b) => `#${esc(String(b.order_scan_id))}: raw "${esc(b.raw_input)}" x${esc(String(b.units))}`).join('<br>')}</div>`
                                                : '';
                                            const meta = [
                                                it.line_key ? `<span><strong>line_key:</strong> ${esc(it.line_key)}</span>` : '',
                                                it.vare_nr ? `<span style="margin-left:8px;"><strong>VareNr:</strong> ${esc(it.vare_nr)}</span>` : '',
                                                it.ean ? `<span style="margin-left:8px;"><strong>EAN:</strong> ${esc(it.ean)}</span>` : ''
                                            ].filter(Boolean).join('');
                                            const counts = (it.expected != null && it.scanned != null)
                                                ? `<div style="margin-top:4px;font-size:0.82rem;">ordered/delivered <strong>${esc(String(it.expected))}</strong> · scanned <strong>${esc(String(it.scanned))}</strong></div>`
                                                : '';
                                            return `<div style="border-bottom:1px solid #e2e8f0;padding:10px 0;text-align:left;"><div style="font-size:0.72rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">${esc(it.issue_type || 'issue')}</div><div style="font-weight:700;color:#0f172a;margin-top:4px;">${esc(it.product_name || '—')}</div>${meta ? `<div style="margin-top:4px;font-size:0.8rem;color:#475569;">${meta}</div>` : ''}${counts}${ids}${br}</div>`;
                                        }).join('');
                                        issuesHtml = `<div style="font-size:0.88rem;color:#334155;text-align:left;margin:14px 0 0;padding:12px 14px;background:#f8fafc;border-radius:12px;line-height:1.5;max-height:220px;overflow-y:auto;">${rows}</div>`;
                                    }
                                    const detailsHtml = rawDetails && !issues.length
                                        ? `<div style="font-size:0.88rem;color:#334155;text-align:left;margin:14px 0 0;padding:12px 14px;background:#f8fafc;border-radius:12px;line-height:1.55;white-space:pre-wrap;">${esc(rawDetails).replace(/\n/g, '<br>')}</div>`
                                        : '';
                                    const searchHint = `<div style="font-size:0.82rem;color:#94a3b8;margin-top:12px;line-height:1.5;">On the <strong>ERROR</strong> tab the search box is ignored so every problem row stays visible.</div>`;
                                    Swal.fire({
                                        position: 'center',
                                        html: `
                                            <div style="text-align:center; padding: 10px 0;">
                                                <div style="width:72px;height:72px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                </div>
                                                <div style="font-size:1.5rem;font-weight:800;color:#1e293b;margin-bottom:10px;">Cannot Close Session</div>
                                                <div style="font-size:0.95rem;color:#64748b;">${esc(data.message || 'An error occurred while closing.')}</div>
                                                ${issuesHtml}
                                                ${detailsHtml}
                                                ${searchHint}
                                            </div>
                                        `,
                                        confirmButtonText: 'OK',
                                        confirmButtonColor: '#ef4444',
                                        customClass: { popup: 'swal-custom-popup' },
                                        didOpen: (popup) => {
                                            popup.style.borderRadius = '24px';
                                            popup.style.padding = '30px 36px';
                                            popup.style.boxShadow = '0 25px 60px rgba(239,68,68,0.15)';
                                        }
                                    });
                                }
                            })
                            .catch(err => console.error('Close order error:', err))
                            .finally(() => setSyncStatus('active'));
                    }
                });
            });
        }

        // Apply toolbar filter once rows exist so SCANNED / ERROR / REST / ORDERED behave immediately
        reapplyActiveHubFilter();
        updatePrintButtonState();

        const productLookupInput = document.getElementById('productLookupInput');
        if (productLookupInput) {
            productLookupInput.addEventListener('input', () => {
                reapplyActiveHubFilter();
            });
        }

        // Deep-link from order list: ?highlight= — ORDERED tab, same filter text, one row per EAN, scroll to hit
        try {
            const params = new URLSearchParams(window.location.search);
            const hl = (params.get('highlight') || '').trim();
            if (hl && productLookupInput) {
                if (typeof window.applyFilter === 'function') {
                    window.applyFilter('ordered');
                }
                productLookupInput.value = hl;
                reapplyActiveHubFilter();
                const hlUpper = hl.toUpperCase();
                setTimeout(() => {
                    const rows = document.querySelectorAll('.history-row[data-ean], .history-item-minimal[data-ean]');
                    for (let i = 0; i < rows.length; i++) {
                        const hit = rows[i];
                        if (hit.style.display === 'none') continue;
                        if (!rowMatchesProductLookup(hit, hlUpper)) continue;
                        hit.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        hit.classList.add('table-warning');
                        setTimeout(() => hit.classList.remove('table-warning'), 2500);
                        break;
                    }
                }, 200);
            }
        } catch (e) { /* ignore */ }

        // Initialize timer
        resetInactivityTimer();
        updatePrintButtonState();
    });

})();
