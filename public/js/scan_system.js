/**
 * Scan System Logic
 * Handles scanning, deleting, and real-time updates for Order Delivery.
 * 
 * Requires window.ScanConfig to be set in the Blade view:
 * window.ScanConfig = {
 *    orderId: "...",
 *    csrfToken: "...",
 *    routes: {
 *        scan: "...",
 *        delete: "...",
 *        update: "...",
 *        sync: "..."
 *    },
 *    audio: {
 *        beep: "..."
 *    }
 * };
 */

document.addEventListener('DOMContentLoaded', () => {
    initializeScanSystem();
    initPusher();
    applyFilter('scanned');
});

function initializeScanSystem() {
    const input = document.getElementById('eanInput');
    if (!input) return;

    // Focus management
    // Focus management - Aggressive
    document.addEventListener('click', (e) => {
        const target = e.target;

        // If a qty button was tapped, blur it immediately so mobile keyboards don't appear
        const qtyBtn = target.closest('.qty-btn-minimal');
        if (qtyBtn) {
            qtyBtn.blur();
            return; // let the onclick handler do its job without forcing focus back to the input just yet
        }

        // Allowed elements to steal focus:
        // - Inputs (except the scanner input itself, which is fine)
        // - Buttons (for manual actions)
        // - Links
        const isInteractive = target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.closest('button') ||
            target.closest('a') ||
            target.closest('.swal2-container'); // SweetAlert interactions

        if (!isInteractive) {
            const input = document.getElementById('eanInput');
            if (input) {
                // RELAX FOCUS: If user is selecting text, do NOT steal focus back to input
                const selection = window.getSelection().toString();
                if (selection && selection.length > 0) {
                    console.log('Selection active, skipping auto-focus');
                    return;
                }
                input.focus();
            }
        }
    });

    // Enter key handler
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') processScan();
    });

    // Session Closure Setup
    const closeOrderBtn = document.getElementById('closeOrderBtn');
    if (closeOrderBtn) {
        closeOrderBtn.addEventListener('click', () => {
            const plannedDelivery = document.getElementById('plannedDelivery')?.value;
            const staffName = document.getElementById('staffName')?.value?.trim();
            const orderNote = document.getElementById('orderNote')?.value?.trim();

            if (!staffName) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Required Field',
                    text: 'Please enter a Staff Name before closing the session.',
                    customClass: { popup: 'premium-swal-popup' }
                });
                return;
            }

            // ── PRE-CLOSE ERROR AUDIT ──
            const audit = checkSessionErrors();
            if (audit.hasErrors) {
                showSessionBlockedModal(audit);
                return; // HARD BLOCK — do not proceed
            }

            Swal.fire({
                title: 'FINISH SESSION?',
                text: "Archive and close this order delivery session?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#f1f5f9',
                confirmButtonText: 'FINISH',
                cancelButtonText: 'CANCEL',
                reverseButtons: true,
                customClass: {
                    popup: 'small-swal-popup',
                    title: 'fs-5 fw-bold',
                    htmlContainer: 'small text-muted mb-2',
                    confirmButton: 'btn btn-primary btn-sm me-2 fw-bold px-3 py-1',
                    cancelButton: 'btn btn-light btn-sm fw-bold text-secondary px-3 py-1',
                    actions: 'mt-0'
                },
                buttonsStyling: false,
                width: '280px',
                padding: '1em'
            }).then((result) => {
                if (result.isConfirmed) {
                    const headers = {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                    };

                    fetch(window.ScanConfig.routes.close, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({
                            order_id: window.ScanConfig.orderId,
                            planned_delivery: plannedDelivery,
                            staff: staffName,
                            note: orderNote
                        })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (data.status === 'Done with ERR' || (data.deviations && data.deviations.length > 0)) {
                                    // This branch might not be hit anymore due to backend hard-block, 
                                    // but kept as legacy support for any existing "Done with ERR" entries.
                                    let htmlContent = `<div style="max-height:200px;overflow-y:auto;padding-right:3px;margin-top:6px;">`;

                                    if (data.deviations && data.deviations.length > 0) {
                                        data.deviations.forEach(d => {
                                            const isOver = d.diff > 0;
                                            const diffSign = isOver ? '+' : '';
                                            const diffColor = isOver ? '#dc2626' : '#1d4ed8';
                                            const diffBg = isOver ? '#fee2e2' : '#eff6ff';

                                            htmlContent += `
                                            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 8px;margin-bottom:4px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.75rem;">
                                                <div style="flex:1;min-width:0;margin-right:8px;">
                                                    <div style="font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.name}</div>
                                                    <div style="font-size:0.65rem;color:#94a3b8;">${d.ean || d.sku}</div>
                                                </div>
                                                <div style="white-space:nowrap;font-size:0.7rem;color:#64748b;">
                                                    Exp:<strong>${d.expected}</strong> · Got:<strong>${d.scanned}</strong>
                                                    <span style="margin-left:5px;padding:1px 6px;border-radius:4px;background:${diffBg};color:${diffColor};font-weight:800;">${diffSign}${d.diff}</span>
                                                </div>
                                            </div>`;
                                        });
                                    } else {
                                        htmlContent += `<p style="font-size:0.78rem;color:#64748b;margin:0;">There were quantity mismatches during this session.</p>`;
                                    }
                                    htmlContent += `</div>`;

                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'SESSION CLOSED WITH DEVIATIONS',
                                        html: htmlContent,
                                        confirmButtonText: 'RETURN TO OVERVIEW',
                                        confirmButtonColor: '#f59e0b',
                                        allowOutsideClick: false,
                                        customClass: {
                                            popup: 'classic-premium-popup',
                                            title: 'classic-premium-title mt-2',
                                            confirmButton: 'classic-premium-confirm mt-3',
                                            icon: 'classic-premium-icon'
                                        }
                                    }).then(() => {
                                        window.location.href = '/order-delivery';
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'SESSION COMPLETED',
                                        html: `
                                            <div style="text-align:center;padding:4px 0 8px;">
                                                <div style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:#dcfce7;margin-bottom:8px;">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                                </div>
                                                <p style="margin:0;font-size:0.82rem;color:#475569;">All quantities matched perfectly.</p>
                                            </div>`,
                                        showConfirmButton: true,
                                        confirmButtonText: 'CONTINUE',
                                        timer: 4000,
                                        timerProgressBar: true,
                                        customClass: {
                                            popup: 'classic-premium-popup',
                                            title: 'classic-premium-title',
                                            confirmButton: 'classic-premium-confirm'
                                        },
                                        buttonsStyling: false
                                    }).then(() => {
                                        window.location.href = '/order-delivery';
                                    });
                                }
                            } else {
                                // BACKEND BLOCK: If server returned deviations (errors), show the block modal
                                if (data.deviations) {
                                    // Map server deviations to audit format
                                    const serverAudit = {
                                        hasErrors: true,
                                        over: data.deviations.filter(d => d.type === 'OVER'),
                                        under: data.deviations.filter(d => d.type === 'UNDER').map(d => ({ ...d, label: d.scanned === 0 ? 'NOT SCANNED' : 'UNDER' })),
                                        missingEan: data.deviations.filter(d => d.type === 'MISSING_EAN').map(d => ({ ...d, ean: d.sku })),
                                        unknown: data.deviations.filter(d => d.type === 'UNKNOWN')
                                    };
                                    showSessionBlockedModal(serverAudit);
                                } else {
                                    Swal.fire('Error', data.message || 'Could not close session.', 'error');
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Close Session Failed:', err);
                            Swal.fire('Error', 'Connection Error', 'error');
                        });
                }
            });
        });
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PRE-CLOSE SESSION AUDIT
// Reads the live DOM to detect all blocking errors before allowing closure.
// Returns: { hasErrors: bool, over: [], under: [], missingEan: [], unknown: [] }
// ─────────────────────────────────────────────────────────────────────────────
function checkSessionErrors() {
    const result = {
        hasErrors: false,
        over: [],
        under: [],
        missingEan: [],
        unknown: []   // scanned but not in order (ordered=0, scanned>0)
    };

    // Helper: get the product name from a row/card element
    const getName = el => {
        const nameEl = el.querySelector('.fw-800.text-dark, .item-name-minimal');
        return nameEl ? nameEl.innerText.trim() : '—';
    };

    const getEan = el => el.dataset.ean || el.dataset.sku || '—';

    // ── 1. Scan rows in the main history table (desktop) ──
    // Use a Set of EAN codes we've already evaluated to avoid duplicates
    // (the latestScanRow and scanHistory can both contain the same EAN)
    const checkedEans = new Set();

    const allRows = document.querySelectorAll(
        '#latestScanRow .history-row, #scanHistory .history-row'
    );

    allRows.forEach(row => {
        const ean = getEan(row);
        if (checkedEans.has(ean)) return;
        checkedEans.add(ean);

        const scanStatus = row.dataset.scanStatus; // 'scanned' | 'missing'
        const name = getName(row);

        // ── NOT SCANNED (virtual/missing row — ordered but never scanned) ──
        if (scanStatus === 'missing') {
            const orderedEl = row.querySelector('.row-ordered-val');
            const ordered = orderedEl ? parseInt(orderedEl.innerText) || 0 : 0;
            if (ordered > 0) {
                result.under.push({
                    name,
                    ean,
                    ordered,
                    scanned: 0,
                    label: 'NOT SCANNED'
                });
            }
            return;
        }

        const statusBadge = row.querySelector('.status-badge-modern');
        const statusText = statusBadge ? statusBadge.innerText.trim().toUpperCase() : '';

        const orderedEl = row.querySelector('.row-ordered-val');
        const scannedEl = row.querySelector('.row-scanned-val');
        const ordered = orderedEl ? parseInt(orderedEl.innerText) || 0 : 0;
        const scanned = scannedEl ? parseInt(scannedEl.innerText) || 0 : 0;

        // ── OVER ──
        if (statusText === 'OVER') {
            // If ordered=0, this was never in the order → UNKNOWN scan
            if (ordered === 0) {
                result.unknown.push({ name, ean, scanned });
            } else {
                result.over.push({ name, ean, ordered, scanned });
            }
        }

        // ── UNDER ──
        if (statusText === 'UNDER') {
            result.under.push({ name, ean, ordered, scanned, label: 'UNDER' });
        }

        // ── MISSING EAN (blocks closing even if quantities match) ──
        const eanWarning = row.querySelector('.ean-warning-container');
        if (eanWarning) {
            result.missingEan.push({ name, ean });
        }
    });

    // ── 2. Also check mobile cards (in case desktop rows aren't rendered) ──
    // Only add EANs we haven't already seen from table rows
    document.querySelectorAll('#mobileHistoryList .history-item-minimal').forEach(card => {
        const ean = getEan(card);
        if (checkedEans.has(ean)) return;
        checkedEans.add(ean);

        const scanStatus = card.dataset.scanStatus;
        const name = getName(card);

        if (scanStatus === 'missing') {
            const orderedEl = card.querySelector('.row-ordered-val');
            const ordered = orderedEl ? parseInt(orderedEl.innerText) || 0 : 0;
            if (ordered > 0) {
                result.under.push({ name, ean, ordered, scanned: 0, label: 'NOT SCANNED' });
            }
            return;
        }

        const statusBadge = card.querySelector('.status-badge-mobile');
        const statusText = statusBadge ? statusBadge.innerText.trim().toUpperCase() : '';

        const orderedEl = card.querySelector('.row-ordered-val');
        const scannedEl = card.querySelector('.row-scanned-val');
        const ordered = orderedEl ? parseInt(orderedEl.innerText) || 0 : 0;
        const scanned = scannedEl ? parseInt(scannedEl.innerText) || 0 : 0;

        if (statusText === 'OVER') {
            if (ordered === 0) {
                result.unknown.push({ name, ean, scanned });
            } else {
                result.over.push({ name, ean, ordered, scanned });
            }
        }
        if (statusText === 'UNDER') {
            result.under.push({ name, ean, ordered, scanned, label: 'UNDER' });
        }

        const eanWarning = card.querySelector('.ean-warning-container');
        if (eanWarning) {
            result.missingEan.push({ name, ean });
        }
    });

    result.hasErrors = (
        result.over.length > 0 ||
        result.under.length > 0 ||
        result.missingEan.length > 0 ||
        result.unknown.length > 0
    );

    return result;
}

/**
 * Shows a premium blocking modal listing all errors that prevent session closure.
 */
function showSessionBlockedModal(audit) {
    const totalErrors = audit.over.length + audit.under.length + audit.missingEan.length + audit.unknown.length;

    let html = `
        <div style="text-align:left; font-family:'Inter',sans-serif;">
            <p style="margin:0 0 12px; font-size:0.85rem; color:#64748b;">
                <strong style="color:#dc2626;">${totalErrors} unresolved issue${totalErrors > 1 ? 's' : ''}</strong> must be fixed before closing this session.
            </p>
            <div style="max-height:320px; overflow-y:auto; padding-right:4px;">
    `;

    const renderGroup = (items, icon, label, color, bg, detail) => {
        if (!items.length) return '';
        let s = `
            <div style="margin-bottom:10px;">
                <div style="font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:${color}; margin-bottom:5px; padding:3px 8px; background:${bg}; border-radius:4px; display:inline-block;">
                    ${icon} ${label} (${items.length})
                </div>
        `;
        items.forEach(item => {
            s += `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:5px 8px; margin-bottom:3px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; font-size:0.8rem;">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name}</div>
                        <div style="font-size:0.68rem; color:#94a3b8;">${item.ean}</div>
                    </div>
                    <div style="font-size:0.72rem; color:${color}; font-weight:800; margin-left:8px; white-space:nowrap;">${detail(item)}</div>
                </div>
            `;
        });
        s += `</div>`;
        return s;
    };

    html += renderGroup(
        audit.over, '⬆', 'OVER-SCANNED', '#dc2626', '#fee2e2',
        item => `Ordered ${item.ordered} · Scanned ${item.scanned}`
    );
    html += renderGroup(
        audit.under, '⬇', 'UNDER-SCANNED / MISSING', '#1d4ed8', '#eff6ff',
        item => item.label === 'NOT SCANNED'
            ? `Not scanned (Ordered ${item.ordered})`
            : `Ordered ${item.ordered} · Scanned ${item.scanned}`
    );
    html += renderGroup(
        audit.missingEan, '⚠', 'MISSING EAN CODE', '#92400e', '#fffbeb',
        item => `Add real EAN`
    );
    html += renderGroup(
        audit.unknown, '❓', 'UNKNOWN SCAN', '#7c3aed', '#f5f3ff',
        item => `${item.scanned} scanned · Not in order`
    );

    html += `</div></div>`;

    Swal.fire({
        icon: 'error',
        title: '🔒 CANNOT CLOSE SESSION',
        html,
        confirmButtonText: 'GO BACK & FIX ERRORS',
        confirmButtonColor: '#dc2626',
        allowOutsideClick: false,
        customClass: {
            popup: 'premium-swal-popup',
            title: 'premium-swal-title',
            confirmButton: 'btn btn-danger fw-bold px-4 py-2 mt-2'
        },
        buttonsStyling: false,
        width: '480px'
    });
}



function applyFilter(filterType) {
    // Update button states
    document.querySelectorAll('.filter-link').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.remove('text-primary');
        btn.classList.add('text-secondary');
    });
    const activeBtn = document.querySelector(`[data-filter="${filterType}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.classList.add('text-primary');
        activeBtn.classList.remove('text-secondary');
    }

    // Apply filter to Mobile List
    const allItems = document.querySelectorAll('.history-item-minimal');
    allItems.forEach(item => {
        if (filterType === 'order') {
            item.style.display = '';
        } else if (filterType === 'scanned') {
            item.style.display = item.dataset.scanStatus === 'scanned' ? '' : 'none';
        } else if (filterType === 'error') {
            item.style.display = item.dataset.errorStatus === 'error' ? '' : 'none';
        }
    });

    // Apply filter to Desktop Table (optional, but good for consistency)
    const tableRows = document.querySelectorAll('#scanHistory tr');
    tableRows.forEach(row => {
        if (filterType === 'order') {
            row.style.display = '';
        } else if (filterType === 'scanned') {
            // In table, we might distinguish by class or data attr
            row.style.display = row.dataset.scanStatus === 'scanned' ? '' : 'none';
        } else if (filterType === 'error') {
            row.style.display = row.dataset.errorStatus === 'error' ? '' : 'none';
        }
    });

    // Hide/Show Latest Scan Section appropriately
    const latestScanRow = document.querySelector('#latestScanRow tr');
    const latestSection = document.getElementById('latestScanSection');
    if (latestScanRow && latestSection) {
        let showLatest = false;
        if (filterType === 'order') {
            showLatest = true;
        } else if (filterType === 'scanned') {
            showLatest = latestScanRow.dataset.scanStatus === 'scanned';
        } else if (filterType === 'error') {
            showLatest = latestScanRow.dataset.errorStatus === 'error';
        }
        latestSection.style.display = showLatest ? '' : 'none';
    }
}


// --- Core Functions ---

function processScan() {
    if (window.ScanConfig.completed) return;
    const input = document.getElementById('eanInput');
    const ean = input.value.trim();
    if (!ean) return;

    // 1. Optimistic UI: Immediately show result
    const historyList = document.getElementById('scanHistory');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.closest('tr').style.display = 'none';

    // Generate specific temp ID
    const tempId = 'temp-' + Date.now();
    const nowTime = new Date().toLocaleTimeString('en-US', { hour12: false, hour: "2-digit", minute: "2-digit", second: "2-digit" });

    // Pending Row HTML
    const pendingHtml = `
        <tr id="${tempId}" style="background-color: #fffbeb; animation: slideIn 0.3s ease-out">
            <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">${nowTime}</td>
            <td colspan="7">
                <div class="d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="fw-800">Processing: ${ean}...</span>
                </div>
            </td>
        </tr>
    `;

    // Insert immediately at top of list
    if (historyList) {
        historyList.insertAdjacentHTML('afterbegin', pendingHtml);
    }

    // Clear input and refocus immediately
    const originalValue = input.value;
    input.value = '';
    input.focus();

    // 2. Perform Network Request
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
    };
    if (window.pusher && window.pusher.connection.socket_id) {
        headers['X-Socket-ID'] = window.pusher.connection.socket_id;
    }

    fetch(window.ScanConfig.routes.scan, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({
            order_id: window.ScanConfig.orderId,
            ean_code: ean
        })
    })
        .then(res => res.json())
        .then(data => {
            // Remove temp row
            const tempRow = document.getElementById(tempId);
            if (tempRow) tempRow.remove();

            if (data.success) {
                // Remove placeholder even if not global
                const placeholder = document.getElementById('noScansPlaceholder');
                if (placeholder) placeholder.remove();

                updateHistory(data);
                playSound('success');

                // ── EAN MISSING WARNING ──
                if (data.ean_missing) {
                    // Show a prominent SweetAlert warning
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠ EAN Code MISSING',
                        html: `<div style="text-align:left;">
                            <p style="margin:0 0 8px;font-size:0.95rem;">Using <strong>VareNummer</strong> as a temporary EAN for:<br>
                            <strong>${data.product_name}</strong></p>
                            <p style="margin:0;font-size:0.8rem;color:#92400e;">Please add the correct EAN code using the <strong>ADD EAN</strong> button on the row.</p>
                        </div>`,
                        confirmButtonText: 'Got it',
                        confirmButtonColor: '#f59e0b',
                        customClass: { popup: 'premium-swal-popup', title: 'premium-swal-title' },
                        timer: 8000,
                        timerProgressBar: true,
                        didClose: () => {
                            const input = document.getElementById('eanInput');
                            if (input) input.focus();
                        }
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Failed',
                    text: data.message || 'Unknown error',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    didClose: () => {
                        const input = document.getElementById('eanInput');
                        if (input) input.focus();
                    }
                });
                playSound('error');
            }
        })
        .catch(err => {
            console.error(err);
            const tempRow = document.getElementById(tempId);
            if (tempRow) tempRow.remove();
            playSound('error');
        })
        .finally(() => {
            // CRITICAL: Always refocus input
            const input = document.getElementById('eanInput');
            if (input) {
                input.focus();
                // Double tap for safety against UI repaints
                setTimeout(() => input.focus(), 50);
            }
        });
}

function updateHistory(data) {
    // 1. Check if we need to remove empty state
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.closest('tr').style.display = 'none';

    // 2. Calculate values
    const diff = data.scanned - data.ordered;
    const remaining = Math.max(0, data.ordered - data.scanned);
    const statusText = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');
    const statusColor = diff === 0 ? '#22c55e' : (diff > 0 ? '#ef4444' : '#2563eb'); // Green, Red, Blue
    const errorStatus = Math.abs(diff) > 0 ? 'error' : 'ok';

    const qty = data.units !== undefined ? data.units : 1;
    const skuHtml = data.sku ? ` / VareNr.: ${data.sku}` :
        (data.product_id ? ` / VareNr.: ${data.product_id}` : '');

    // EAN Missing indicator HTML
    const eanMissingBadgeHtml = data.ean_missing ? `
        <div class="d-flex align-items-center gap-2 mt-1 ean-warning-container" data-product-sku="${data.product_id || data.ean_code}">
            <span class="ean-missing-badge" style="background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;font-size:0.65rem;font-weight:700;padding:3px 6px;">
                &#9888; EAN MISSING &ndash; Using VareNR
            </span>
            <button type="button"
                class="btn btn-sm btn-outline-warning fw-bold d-none d-sm-inline-flex align-items-center gap-1"
                style="font-size:0.65rem;padding:2px 8px;border-radius:4px;"
                onclick="openAddEanModal('${data.product_id || data.ean_code}', '${(data.product_name || '').replace(/'/g, '&apos;')}', ${data.scan_id})"
                title="Add correct EAN code">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                ADD EAN
            </button>
        </div>
    ` : '';

    const eanMissingMobileBannerHtml = data.ean_missing ? `
        <div class="mb-1 ean-warning-container" data-product-sku="${data.product_id || data.ean_code}" style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:4px 8px;font-size:0.7rem;color:#856404;font-weight:600;">
            &#9888; EAN Code MISSING &ndash; Found VareNummer<br>
            <span style="font-weight:400;font-size:0.65rem;">Please add the correct EAN code.</span>
        </div>
    ` : '';

    // 3. Construct Row HTML
    const rowId = `scan-${data.scan_id}`;
    const ean = data.ean_code;

    // IMPORTANT: Grouping logic REMOVED. User requested each entry separately.
    // We no longer find and remove existing rows with the same EAN.
    // However, we still check for the exact scan ID to avoid duplicating the row itself on updates.
    const existingById = document.getElementById(rowId);
    if (existingById) existingById.remove();

    // Remove virtual placeholder rows (data-scan-status="missing") that match the same EAN or SKU.
    // These are server-rendered ghost rows for products not yet scanned. Once a scan comes in, they must go.
    const sku = data.sku || data.product_id || '';
    document.querySelectorAll('#scanHistory tr[data-scan-status="missing"]').forEach(virtualRow => {
        const rowEan = virtualRow.dataset.ean || '';
        const rowSku = virtualRow.dataset.sku || '';
        if (rowEan === ean || (sku && rowSku === sku) || rowEan === sku || rowSku === ean) {
            virtualRow.remove();
        }
    });
    // Also remove matching virtual rows from the mobile list
    document.querySelectorAll('#mobileHistoryList .history-item-minimal[data-scan-status="missing"]').forEach(virtualCard => {
        const cardEan = virtualCard.dataset.ean || '';
        const cardSku = virtualCard.dataset.sku || '';
        if (cardEan === ean || (sku && cardSku === sku) || cardEan === sku || cardSku === ean) {
            virtualCard.remove();
        }
    });

    const rowHtml = `
        <tr id="${rowId}" 
            class="history-row" 
            data-scan-status="scanned" 
            data-error-status="${errorStatus}" 
            data-ean="${data.ean_code}" 
            data-sku="${data.sku || data.product_id}"
            style="animation: slideIn 0.3s ease-out">
            
            <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">${data.timestamp}</td>
            <td class="col-product">
                <div class="fw-800 text-dark">
                    ${data.product_name}
                    ${(() => {
                        const unit = (data.packaging_unit || '').toUpperCase();
                        const isGeneric = ['STK', 'PCS', 'SPA', 'BOX', 'SET', 'PAK', 'FL', 'RUL', 'POS', 'ESK', 'TUB', 'ST', 'BX'].includes(unit);
                        const hasVol = /[0-9,.]\s*(L|ML|KG|GR|G)\b/i.test(unit);
                        if (data.packaging_quantity > 1 || (data.packaging_quantity == 1 && (hasVol || !isGeneric))) {
                            const qtyStr = data.packaging_quantity > 1 ? data.packaging_quantity.toString().replace('.', ',') + ' ' : '';
                            return `<span class="badge bg-light text-dark border ms-1" style="font-weight: 800; font-size: 0.75rem;">${qtyStr}${data.packaging_unit}</span>`;
                        }
                        return '';
                    })()}
                </div>
                <div class="text-muted" style="font-size: 0.75rem;">
                    EAN: <span class="selectable-ean">${data.ean_code}</span>
                    ${skuHtml}
                </div>
                ${eanMissingBadgeHtml}
                <!-- Ref: removed -->
            </td>
            <td class="fw-600 text-secondary row-ordered-val col-ordered" data-ean="${data.ean_code}">${data.ordered}</td>
            <td class="fw-700 text-dark row-scanned-val col-scanned" data-ean="${data.ean_code}">${data.scanned}</td>
            <td class="fw-700 text-success row-remaining-val col-rest" data-ean="${data.ean_code}">${remaining}</td>
            <td class="col-qty">
                <span class="badge bg-light text-secondary border-0 fw-700 p-1" style="font-size: 0.75rem;">${qty}</span>
            </td>
            <td class="col-status">
                <span class="status-badge-modern" style="background: ${statusColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">
                    ${statusText}
                </span>
            </td>
            <td class="col-action">
                <div class="d-flex justify-content-center">
                    <div class="qty-pill">
                        <button type="button" class="qty-btn" onclick="updateUnits(${data.scan_id}, -1)">−</button>
                        <input type="number" inputmode="numeric" class="qty-input" id="qty-input-${data.scan_id}" value="${qty}" onchange="updateUnitsExact(${data.scan_id}, this.value)" style="width: 30px; height: 20px; text-align: center; font-weight: 700; font-size: 0.8rem; border: none; background: transparent; padding: 0; color: #1e293b; outline: none; -moz-appearance: textfield;">
                        <button type="button" class="qty-btn text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                    </div>
                </div>
            </td>
        </tr>
    `;

    // 4. Update "Last Scanned" Section
    updateLastScannedDisplay(rowHtml, data.scan_id);

    // 5. Update Mobile List View
    const mobileList = document.getElementById('mobileHistoryList');
    if (mobileList) {
        // Remove existing if present
        const existingMobile = document.getElementById(`mobile-scan-${data.scan_id}`);
        if (existingMobile) existingMobile.remove();

        const mobileHtml = `
            <div class="history-item-minimal" id="mobile-scan-${data.scan_id}" data-scan-status="scanned" data-error-status="${errorStatus}" data-other-scanned="${data.scanned - qty}" data-ean="${data.ean_code}" data-sku="${data.sku || data.product_id}" style="animation: slideIn 0.3s ease-out">
                <div class="item-data" style="width: 100%;">
                    <div class="d-flex justify-content-end align-items-start w-100 mb-1">
                        <span class="status-badge-mobile" style="color: white; background: ${statusColor}; font-size: 0.7rem;">${statusText}</span>
                    </div>

                    <div class="text-center mb-2">
                        <div style="font-size: 1.1rem; justify-content: center;" class="fw-800 item-name-minimal d-flex align-items-center flex-wrap gap-1">
                            ${data.product_name}
                            ${(() => {
                                const unit = (data.packaging_unit || '').toUpperCase();
                                const isGeneric = ['STK', 'PCS', 'SPA', 'BOX', 'SET', 'PAK', 'FL', 'RUL', 'POS', 'ESK', 'TUB', 'ST', 'BX'].includes(unit);
                                const hasVol = /[0-9,.]\s*(L|ML|KG|GR|G)\b/i.test(unit);
                                if (data.packaging_quantity > 1 || (data.packaging_quantity == 1 && (hasVol || !isGeneric))) {
                                    const qtyStr = data.packaging_quantity > 1 ? data.packaging_quantity.toString().replace('.', ',') + ' ' : '';
                                    return `<span class="badge bg-light text-dark border ms-1" style="font-weight: 800; font-size: 0.75rem;">${qtyStr}${data.packaging_unit}</span>`;
                                }
                                return '';
                            })()}
                            <!-- Ref: removed -->
                        </div>
                        <div class="item-meta-minimal mt-1">
                            EAN: <span class="selectable-ean">${data.ean_code}</span>
                            ${(data.sku || data.product_id) ? ` / VareNr.: ${data.sku || data.product_id}` : ''}
                        </div>
                        ${eanMissingMobileBannerHtml}
                    </div>

                    <div class="metrics-grid mb-3">
                        <div class="metric-box">
                            <span class="metric-label">Ordered</span>
                            <span class="metric-val row-ordered-val">${data.ordered}</span>
                        </div>
                        <div class="metric-box">
                            <span class="metric-label">Scanned</span>
                            <span class="metric-val row-scanned-val">${data.scanned}</span>
                        </div>
                        <div class="metric-box">
                            <span class="metric-label">Rest</span>
                            <span class="metric-val remaining row-remaining-val" style="color: ${remaining > 0 ? '#2563eb' : '#64748b'}">${remaining}</span>
                        </div>
                         <div class="metric-box">
                            <span class="metric-label">#</span>
                            <span class="metric-val qty-val-minimal" id="mobile-qty-display-${data.scan_id}">${qty}</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center align-items-center gap-4">
                        <div class="qty-pill-minimal">
                            <button type="button" class="qty-btn-minimal" onclick="updateUnits(${data.scan_id}, -1)">−</button>
                            <span class="qty-val-minimal" id="mobile-qty-val-${data.scan_id}" style="display: none;">${qty}</span>
                            <input type="number" inputmode="numeric" class="qty-input-minimal" id="mobile-qty-input-${data.scan_id}" value="${qty}" onchange="updateUnitsExact(${data.scan_id}, this.value)" style="width: 50px; text-align: center; font-weight: 800; font-size: 1.2rem; border: none; background: transparent; padding: 0; margin: 0 5px; color: #1e293b; outline: none; -moz-appearance: textfield;">
                            <button type="button" class="qty-btn-minimal text-success" onclick="updateUnits(${data.scan_id}, 1)">+</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        mobileList.insertAdjacentHTML('afterbegin', mobileHtml);
    }

    // 7. Update All other rows for this EAN (sync desktop and mobile)
    updateAllRowsForEan(data);
}

// Global variable for tracking - check if already defined in Blade
if (typeof currentLastScanId === 'undefined') {
    window.currentLastScanId = null;
    window.lastScanTimeout = null;
}

function moveLastToHistory() {
    const latestSection = document.getElementById('latestScanSection');
    const latestRowTbody = document.getElementById('latestScanRow');
    const historyList = document.getElementById('scanHistory');
    
    if (currentLastScanId) {
        const oldRow = document.querySelector('#latestScanRow .history-row');
        if (oldRow) {
            const mainTableRow = oldRow.cloneNode(true);
            mainTableRow.classList.remove('latest-scan-row');
            // Ensure ID reflects it's now in the main table if there's any conflict, 
            // but usually we want to keep it to allow future updates to find it.
            if (historyList) historyList.prepend(mainTableRow);
        }
        if (latestSection) latestSection.style.display = 'none';
        if (latestRowTbody) latestRowTbody.innerHTML = '';
        currentLastScanId = null;
        if (lastScanTimeout) {
            clearTimeout(lastScanTimeout);
            lastScanTimeout = null;
        }
    }
}

window.startLastScanTimeout = function(scanId) {
    if (lastScanTimeout) clearTimeout(lastScanTimeout);
    currentLastScanId = scanId;
    lastScanTimeout = setTimeout(() => {
        moveLastToHistory();
    }, 30000);
};

function updateLastScannedDisplay(rowHtml, scanId) {
    const latestSection = document.getElementById('latestScanSection');
    const latestRowTbody = document.getElementById('latestScanRow');

    // If a NEW scan comes in, move the PREVIOUS one immediately
    if (currentLastScanId && currentLastScanId != scanId) {
        moveLastToHistory();
    }

    // Now set the NEW one
    currentLastScanId = scanId;

    if (latestSection && latestRowTbody) {
        latestRowTbody.innerHTML = rowHtml;
        const newRow = latestRowTbody.querySelector('.history-row');
        if (newRow) newRow.classList.add('latest-scan-row');
        latestSection.style.display = 'block';
        
        // Start or Reset 30s timeout
        startLastScanTimeout(scanId);
    }
}

function updateUnits(id, change) {
    if (window.ScanConfig.completed) return;
    // 1. OPTIMISTIC UI: Update immediately
    // Expanded selector to include the new # metric display
    const els = document.querySelectorAll(`#qty-input-${id}, #mobile-qty-input-${id}, #qty-val-${id}, #mobile-qty-val-${id}, #mobile-qty-display-${id}`);
    let ean = null;
    let newQty = null;

    // First pass: Find current value and calculate new value ONCE
    for (let el of els) {
        let txt = el.tagName === 'INPUT' ? el.value : el.innerText.trim();
        if (txt !== "" && !isNaN(txt)) {
            let currentVal = parseInt(txt);
            newQty = Math.max(0, currentVal + change); // Enforce min 0 to match backend

            // If trying to go below 0, stop here (don't send request, don't update UI)
            if (newQty === currentVal && change < 0 && currentVal === 0) return;

            break; // Found a valid source
        }
    }

    // Fallback if no valid text found (shouldn't happen)
    if (newQty === null) newQty = change > 0 ? 1 : 0;

    console.log(`[updateUnits] ID: ${id}, Change: ${change}, NewQty: ${newQty}, Elements found: ${els.length}`);

    // Second pass: Apply to all
    els.forEach(el => {
        if (el.tagName === 'INPUT') {
            el.value = newQty;
        } else {
            el.innerText = newQty;
        }

        const row = el.closest('[data-ean]');
        if (row) {
            ean = row.dataset.ean;
            const badge = row.querySelector('.badge.bg-light');
            if (badge) badge.innerText = newQty;

            // Robustly update the # metric box in mobile view by class if explicit ID fail
            const metricQty = row.querySelector('.metric-box .qty-val-minimal');
            if (metricQty && metricQty.id !== el.id) {
                metricQty.innerText = newQty;
            }
        }
    });

    // Update Running Total & Status across the UI
    if (ean) {
        let totalScanned = 0;
        const seenIds = new Set();

        // Sum all unique scan quantities for this EAN
        // Sum all unique scan quantities for this EAN
        const relevantRows = Array.from(document.querySelectorAll(`[data-ean="${ean}"]`));
        const uniqueIds = new Set();
        let domSum = 0;
        let singleRowOtherScanned = 0;
        let foundIdsCount = 0;

        relevantRows.forEach(el => {
            const sid = el.id.replace('latest-scan-row-', '')
                .replace('mobile-scan-', '')
                .replace('scan-', '');

            if (sid && !uniqueIds.has(sid)) {
                uniqueIds.add(sid);
                foundIdsCount++;

                // Get Current Units in UI
                const qtyVal = el.querySelector(`#qty-input-${sid}, #mobile-qty-input-${sid}, #qty-val-${sid}, #mobile-qty-val-${sid}, .qty-val, .qty-val-minimal, .badge.bg-light`);
                const currentUnits = qtyVal ? (qtyVal.tagName === 'INPUT' ? parseInt(qtyVal.value) : parseInt(qtyVal.innerText) || 0) : 0;

                domSum += currentUnits;

                // Capture other scanned from attribute if available
                if (el.dataset.otherScanned) {
                    singleRowOtherScanned = parseInt(el.dataset.otherScanned);
                }
            }
        });

        // Smart Total Calculation
        // If we only have 1 active scan row in the DOM (Mobile View), use the stored 'other' count + current UI units
        // If we have multiple rows (Desktop View), trust the sum of the DOM elements
        if (foundIdsCount === 1) {
            totalScanned = singleRowOtherScanned + domSum; // domSum is just the current unit in this case
        } else {
            totalScanned = domSum;
        }

        const ordered = parseInt(document.querySelector(`[data-ean="${ean}"] .row-ordered-val`)?.innerText) || 0;
        const diff = totalScanned - ordered;
        const remaining = Math.max(0, ordered - totalScanned);
        const statusText = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');
        const statusColor = diff === 0 ? '#22c55e' : (diff > 0 ? '#ef4444' : '#2563eb');

        // Update UI components specifically
        document.querySelectorAll(`[data-ean="${ean}"]`).forEach(m => {
            // Monitor update
            // Also update mobile items
            if (m.classList.contains('latest-scan-row') || m.classList.contains('history-item-minimal')) {
                const scVal = m.querySelector('.row-scanned-val');
                if (scVal) scVal.innerText = totalScanned;
                const remVal = m.querySelector('.row-remaining-val');
                if (remVal) remVal.innerText = remaining;
            }

            // Status Badge update (Modern & Mobile)
            const badge = m.querySelector('.status-badge-modern');
            if (badge) {
                badge.innerText = statusText;
                badge.style.background = statusColor;
            }

            const mobileStatus = m.querySelector('.status-badge-mobile');
            if (mobileStatus) {
                mobileStatus.innerText = statusText;
                mobileStatus.style.color = statusColor;
                mobileStatus.style.background = statusColor + '15';
            }
        });
    }

    // 2. BACKEND SYNC
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
    };
    if (window.pusher && window.pusher.connection.socket_id) {
        headers['X-Socket-ID'] = window.pusher.connection.socket_id;
    }

    fetch(window.ScanConfig.routes.update, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({
            scan_id: id,
            change: change
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateHistory(data);
                // Final Sync (optional if client logic is perfect, but good for safety)
                updateAllRowsForEan(data);
                if (typeof progressEl !== 'undefined') {
                    progressEl.innerText = `${data.progress_percent}%`;
                }
            } else {
                // REVERT on error
                Swal.fire({ icon: 'error', title: 'Update Failed', text: data.message });
                window.location.reload(); // Simplest revert
            }
        })
        .catch(err => {
            console.error('Update Failed:', err);
        });

    // Return focus immediately ONLY if we are not on a mobile device where keyboard popups are annoying
    // Very simple check: if window width is large, act as a desktop scanner and refocus
    if (window.innerWidth >= 768) {
        document.getElementById('eanInput')?.focus();
    }
}

function updateUnitsExact(id, exactValue) {
    if (window.ScanConfig.completed) return;
    exactValue = parseInt(exactValue, 10);
    if (isNaN(exactValue) || exactValue < 0) {
        Swal.fire('Error', 'Please enter a valid positive number', 'error');
        return;
    }

    // Backend Sync
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.ScanConfig.csrfToken
    };
    if (window.pusher && window.pusher.connection.socket_id) {
        headers['X-Socket-ID'] = window.pusher.connection.socket_id;
    }

    const exactRoute = window.ScanConfig.routes.update.replace('update-units', 'update-exact');

    fetch(exactRoute, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({
            scan_id: id,
            units: exactValue
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateHistory(data);
                updateAllRowsForEan(data);
                if (typeof progressEl !== 'undefined') {
                    progressEl.innerText = `${data.progress_percent}%`;
                }

                const rowQtyDisplay = document.getElementById(`qty-val-${id}`);
                const rowQtyInput = document.getElementById(`qty-input-${id}`);
                const mobileQtyDisplay = document.getElementById(`mobile-qty-val-${id}`);
                const mobileQtyInput = document.getElementById(`mobile-qty-input-${id}`);

                if (data.units !== undefined) {
                    if (rowQtyDisplay) rowQtyDisplay.innerText = data.units;
                    if (rowQtyInput) rowQtyInput.value = data.units;
                    if (mobileQtyDisplay) mobileQtyDisplay.innerText = data.units;
                    if (mobileQtyInput) mobileQtyInput.value = data.units;
                }

                if (mobileQtyInput) mobileQtyInput.blur();
                if (rowQtyInput) rowQtyInput.blur();

            } else {
                console.error('Exact Update Failed');
            }
        })
        .catch(err => {
            console.error('Exact Update Network Error:', err);
        });
}

function matchOrderScans(id) {
    if (window.ScanConfig.completed) return;
    Swal.fire({
        title: '#OK - Match Ordered?',
        text: "This will set the scanned quantity to match the required order quantity.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: 'Confirm',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            popup: 'small-swal-popup',
            title: 'fs-5 fw-bold',
            htmlContainer: 'small text-muted mb-2',
            confirmButton: 'btn btn-primary btn-sm me-2 fw-bold px-3 py-1',
            cancelButton: 'btn btn-light btn-sm fw-bold text-secondary px-3 py-1',
            actions: 'mt-0'
        },
        buttonsStyling: false,
        width: '280px',
        padding: '1em'
    }).then((result) => {
        if (result.isConfirmed) {

            // Backend Sync
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            };
            if (window.pusher && window.pusher.connection.socket_id) {
                headers['X-Socket-ID'] = window.pusher.connection.socket_id;
            }

            fetch(window.ScanConfig.routes.matchOrder, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    scan_id: id
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'MATCHED',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            customClass: {
                                popup: 'premium-swal-toast',
                                title: 'swal2-title text-success'
                            }
                        });

                        // The websocket will broadcast the new units and our listener
                        // will update the UI, but we can do a local update too if preferred.
                        // For safety, Pusher handler will catch it and update DOM automatically.
                        // Or if no pusher: updateAllRowsForEan(data); 
                        updateHistory(data);
                        updateAllRowsForEan(data); // Immediate local update 

                        if (typeof progressEl !== 'undefined') {
                            progressEl.innerText = `${data.progress_percent}%`;
                        }

                        const rowQtyDisplay = document.querySelector(`#qty-val-${id}, #mobile-qty-val-${id}`);
                        if (rowQtyDisplay && data.units !== undefined) {
                            rowQtyDisplay.innerText = data.units;
                        }

                    } else {
                        Swal.fire('Error', data.message || 'Could not match order quantity.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Match Update Failed:', err);
                });
        }
    });
}

function deleteScan(id) {
    if (window.ScanConfig.completed) return;
    Swal.fire({
        title: 'DELETE RECORD?',
        text: "Remove this scan?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: 'DELETE',
        cancelButtonText: 'KEEP',
        reverseButtons: true,
        customClass: {
            popup: 'small-swal-popup',
            title: 'fs-5 fw-bold',
            htmlContainer: 'small text-muted mb-2',
            confirmButton: 'btn btn-danger btn-sm me-2 fw-bold px-3 py-1',
            cancelButton: 'btn btn-light btn-sm fw-bold text-secondary px-3 py-1',
            actions: 'mt-0'
        },
        buttonsStyling: false,
        width: '280px',
        padding: '1em'
    }).then((result) => {
        if (result.isConfirmed) {
            // Optimistic Removal
            const rows = document.querySelectorAll(`[id="scan-${id}"], [id="mobile-scan-${id}"]`);
            rows.forEach(row => {
                row.style.display = 'none';
                row.remove();
            });

            // Clean up Last Scanned if it was this one
            if (currentLastScanId == id) {
                const latestSection = document.getElementById('latestScanSection');
                if (latestSection) latestSection.style.display = 'none';

                currentLastScanId = null;
                promoteNextLatestScan();
            }

            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            };
            if (window.pusher && window.pusher.connection.socket_id) {
                headers['X-Socket-ID'] = window.pusher.connection.socket_id;
            }

            fetch(window.ScanConfig.routes.delete, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    scan_id: id
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        updateAllRowsForEan(data);

                        // If the backend provided the next scan to display as latest (critical for mobile)
                        if (data.next_scan_data) {
                            updateHistory(data.next_scan_data);
                        } else {
                            // If no more scans exist in the entire order, ensure mobile shows a placeholder
                            const mobileList = document.getElementById('mobileHistoryList');
                            if (mobileList && !mobileList.querySelector('.history-item-minimal')) {
                                mobileList.innerHTML = `<div class="text-center text-muted mt-5" id="noScansPlaceholder"><p>No scans left.</p><small>Scan an item to begin.</small></div>`;
                            }
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'DELETED',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'premium-swal-toast',
                                title: 'swal2-title'
                            }
                        });
                    } else {
                        //ovrcitt? (C0ex, maybe just alert error)
                        setTimeout(() => Swal, 300).fire('Error', 'Could not delete scan.', 'error');
                    }
                });
        }
    });
}

function updateAllRowsForEan(data) {
    const ean = data.ean_code;
    const diff = data.scanned - data.ordered;
    const remaining = Math.max(0, data.ordered - data.scanned);

    // Update ALL rows for status, but ONLY monitor for quantities
    const allRows = document.querySelectorAll(`.history-row[data-ean="${ean}"], .history-item-minimal[data-ean="${ean}"], .latest-scan-row[data-ean="${ean}"]`);

    const statusText = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');
    const statusColor = diff === 0 ? '#22c55e' : (diff > 0 ? '#ef4444' : '#2563eb');

    allRows.forEach(item => {
        // Update aggregate counts for ALL found rows (Table, Latest, Mobile)
        const scannedVal = item.querySelector('.row-scanned-val');
        if (scannedVal) scannedVal.innerText = data.scanned;

        const remainingVal = item.querySelector('.row-remaining-val');
        if (remainingVal) remainingVal.innerText = remaining;

        // Update data-other-scanned for Mobile items to keep them in sync for future optimistic updates
        if (item.hasAttribute('data-other-scanned')) {
            // We need the current unit count of this ITEM to calculate OTHER
            // Try to find the input/badge for this specific item
            // Extract ID from the row ID
            const sid = item.id.replace('mobile-scan-', '');
            const qtyEl = item.querySelector(`#mobile-qty-input-${sid}, #mobile-qty-val-${sid}, .qty-val-minimal`);
            const currentQty = qtyEl ? (qtyEl.tagName === 'INPUT' ? parseInt(qtyEl.value) : parseInt(qtyEl.innerText) || 0) : 0;

            // other = total - current
            item.dataset.otherScanned = data.scanned - currentQty;
        }

        // Update status for EVERYTHING (Instant feedback that product is done)
        const badge = item.querySelector('.status-badge-modern');
        if (badge) {
            badge.innerText = statusText;
            badge.style.background = statusColor;
        }

        const mobileStatusSpan = item.querySelector('.status-badge-mobile');
        if (mobileStatusSpan) {
            mobileStatusSpan.innerText = statusText;
            mobileStatusSpan.style.color = statusColor;
            mobileStatusSpan.style.background = statusColor + '15';
        }

        item.dataset.errorStatus = diff === 0 ? 'ok' : 'error';
    });

    // Re-apply current filter to ensure UI consistency when rows change status dynamically
    const activeFilter = document.querySelector('.filter-link.active')?.dataset.filter || 'scanned';
    applyFilter(activeFilter);
}


// --- Pusher Real-Time Sync ---

function initPusher() {
    if (!window.Pusher || !window.ScanConfig.pusher.key) {
        alert("Pusher Sync Error: Configuration Missing!");
        return;
    }

    Pusher.logToConsole = true; // Enable logging for debugging
    window.pusher = new Pusher(window.ScanConfig.pusher.key, {
        cluster: window.ScanConfig.pusher.cluster,
        forceTLS: true
    });

    const channelName = window.ScanConfig.pusher.channel;
    const channel = window.pusher.subscribe(channelName);

    window.pusher.connection.bind('state_change', function (states) {
        console.log('%c Pusher State:', 'background: #1e293b; color: #fff; padding: 2px 5px;', states.current);
        const dot = document.getElementById('syncDot');
        const txt = document.getElementById('syncText');
        if (dot) {
            if (states.current === 'connected') {
                dot.style.background = '#22c55e';
                if (txt) txt.innerText = 'Live';
            } else if (states.current === 'connecting') {
                dot.style.background = '#f59e0b';
                if (txt) txt.innerText = 'Connecting...';
            } else {
                dot.style.background = '#ef4444';
                if (txt) txt.innerText = 'Offline';
                // Try to reconnect if disconnected
                if (states.current === 'disconnected' || states.current === 'failed') {
                    setTimeout(() => window.pusher.connect(), 5000);
                }
            }
        }
    });

    // Debug: Listen to ALL events on this channel
    channel.bind_global(function (eventName, data) {
        console.log('%c [Global Event] ' + eventName + ':', 'background: #6b21a8; color: #fff; padding: 2px 5px; border-radius: 3px;', data);

        // VISUAL FLASH: If we get ANY event, flash the dot to show activity
        const dot = document.getElementById('syncDot');
        if (dot) {
            const originalColor = dot.style.background;
            dot.style.background = '#ffffff';
            setTimeout(() => { dot.style.background = originalColor; }, 200);
        }

        // If it's our broadcast event but not bound specifically, handle it here too
        if (eventName.includes('ScanBroadcast') || eventName === 'scan.updated') {
            handleSyncEvent(data);
        }
    });

    channel.bind('pusher:subscription_succeeded', function () {
        console.log('%c Sync Connected: ' + channelName, 'background: #22c55e; color: #fff; padding: 2px 5px; border-radius: 3px;');
        const dot = document.getElementById('syncDot');
        const txt = document.getElementById('syncText');
        if (dot) dot.style.background = '#22c55e'; // Green
        if (txt) txt.innerText = "Live";
    });

    channel.bind('pusher:subscription_error', function (status) {
        const txt = document.getElementById('syncText');
        if (txt) txt.innerText = "Sub Error: " + status;
        alert("Pusher Subscription Error: " + status);
    });

    window.pusher.connection.bind('state_change', function (states) {
        const dot = document.getElementById('syncDot');
        const txt = document.getElementById('syncText');

        console.log('%c Pusher State: ' + states.current, 'color: #7c3aed; font-weight: bold;');

        if (dot && txt) {
            // Only update text here if we aren't already showing "Live: ..." (handled by subscription_succeeded)
            if (!txt.innerText.startsWith("Live:")) {
                txt.innerText = states.current.toUpperCase();
            }

            if (states.current === 'connected') {
                dot.style.background = '#22c55e';
            } else if (states.current === 'connecting' || states.current === 'initialized') {
                dot.style.background = '#f59e0b';
            } else {
                dot.style.background = '#ef4444';
                txt.innerText = "Err: " + states.current;
            }
        }
    });

    window.pusher.connection.bind('error', function (err) {
        console.error('Pusher Connection Error:', err);
        const txt = document.getElementById('syncText');
        if (txt) txt.innerText = 'Conn Err';
        // Optional: alert check
        // alert("Pusher Connection Error: " + JSON.stringify(err));
    });

    // Bind to multiple potential event names to ensure we catch it
    const eventNames = ['scan.updated', '.scan.updated', 'App\\Events\\ScanBroadcast'];

    eventNames.forEach(evtName => {
        channel.bind(evtName, function (data) {
            console.log(`%c Event Received [${evtName}]:`, 'background: #2563eb; color: #fff;', data);

            // Debug Toast to confirm receipt to user
            const pData = data.data || data;
            const type = pData.update_units ? 'Update' : (pData.delete_scan ? 'Delete' : 'Scan');

            // Visual confirmation of sync
            const dot = document.getElementById('syncDot');
            if (dot) {
                dot.style.background = '#3b82f6'; // Blue flash
                setTimeout(() => dot.style.background = '#22c55e', 500);
            }

            handleSyncEvent(data);
        });
    });

    // Extracted handler
    function handleSyncEvent(data) {
        console.log('%c Sync Event Received:', 'background: #2563eb; color: #fff; padding: 2px 5px; border-radius: 3px;', data);

        // Handle both Laravel 5.x/6.x (wrapped in data.data) and straight payload
        let pData = data.data || data.payload || data;

        // If data.data exists but is the WHOLE payload, and it has an internal 'data'
        if (data && data.data && data.data.data) {
            pData = data.data.data;
        }

        // CRITICAL FIX: If pData is a string (encoded JSON), parse it!
        if (typeof pData === 'string') {
            try { pData = JSON.parse(pData); } catch (e) { console.error("JSON Parse Error:", e); }
        }

        // Ensure we find the order_id (using properties from the event class itself)
        const eventOrderId = (data.orderId || data.order_id || pData.orderId || pData.order_id);
        if (eventOrderId) pData.order_id = eventOrderId;

        // Handle EAN UPDATED Event (Administrative Correction)
        if (pData.ean_updated) {
            console.log('%c EAN Update Sync Received:', 'background: #f59e0b; color: #fff;', pData);
            // Delegate to the shared DOM update function
            applyEanUpdateToDOM(pData.product_id, pData.new_ean, pData.new_product_name, pData.ordered_quantity);
            return; // Done handling this event
        }

        // 2. Order Switch Logic (Mainly for Global Scanner)
        if (pData.switch_order && window.ScanConfig.isGlobal) {
            console.log('Order switch requested via desktop:', pData.order_id);
            if (pData.order_id != window.ScanConfig.orderId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Switching Order...',
                        text: pData.message || `Moving to Order #${pData.order_id}`,
                        icon: 'info',
                        timer: 1000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top'
                    }).then(() => {
                        switchToOrder(pData.order_id);
                    });
                } else {
                    switchToOrder(pData.order_id);
                }
            }
            return;
        }

        if (!pData || (!pData.scan_id && !pData.update_units && !pData.delete_scan)) {
            console.warn('Received malformed sync data:', data);
            return;
        }

        // GUARD: If we are on a SPECIFIC order page, ignore events from other orders
        if (!window.ScanConfig.isGlobal && pData.order_id) {
            const currentId = String(window.ScanConfig.orderId);
            const incomingId = String(pData.order_id);
            if (currentId !== incomingId) {
                console.log('Ignoring sync event for different order:', incomingId, 'vs current:', currentId);
                return;
            }
        }

        // GLOBAL SCANNER LOGIC: Update Order ID in real-time
        if (window.ScanConfig.isGlobal && pData.order_id) {
            const orderIdDisplay = document.getElementById('displayOrderId');
            if (orderIdDisplay) {
                orderIdDisplay.innerText = `ORDER #${pData.order_id}`;
                window.ScanConfig.orderId = pData.order_id;
            }
            // Remove "No scans yet" placeholder if it exists
            const placeholder = document.getElementById('noScansPlaceholder');
            if (placeholder) placeholder.remove();
        }

        if (pData.price_update) {
            // Price update logic (optional)
        } else if (pData.delete_scan) {
            // Remove from main table
            // Remove from main table and mobile list
            const rows = document.querySelectorAll(`[id="scan-${pData.scan_id}"], [id="mobile-scan-${pData.scan_id}"]`);
            rows.forEach(row => {
                row.style.display = 'none';
                row.remove();
            });

            // Remove from Last Scanned if it matches
            if (currentLastScanId == pData.scan_id) {
                const latestSection = document.getElementById('latestScanSection');
                if (latestSection) latestSection.style.display = 'none';
                currentLastScanId = null;

                if (pData.next_scan_data) {
                    updateHistory(pData.next_scan_data);
                } else {
                    promoteNextLatestScan();

                    // Fallback for mobile view if it becomes totally empty
                    const mobileList = document.getElementById('mobileHistoryList');
                    if (mobileList && !mobileList.querySelector('.history-item-minimal')) {
                        mobileList.innerHTML = `<div class="text-center text-muted mt-5" id="noScansPlaceholder"><p>No scans left.</p><small>Scan an item to begin.</small></div>`;
                    }
                }
            }

            updateAllRowsForEan(pData);
        } else if (pData.update_units) {
            updateHistory(pData);
            // Updated Quantity Logic - Robust
            const els = document.querySelectorAll(`#qty-val-${pData.scan_id}, #mobile-qty-val-${pData.scan_id}, #mobile-qty-display-${pData.scan_id}`);
            els.forEach(el => {
                const updatedQty = pData.units !== undefined ? pData.units : pData.scanned;
                el.innerText = updatedQty;
                const row = el.closest('[data-ean]');
                if (row) {
                    const badge = row.querySelector('.badge.bg-light');
                    if (badge) badge.innerText = updatedQty;

                    // Also update input if present
                    const rowInput = row.querySelector('.qty-input');
                    const mobInput = row.querySelector('.qty-input-minimal');
                    if (rowInput) rowInput.value = updatedQty;
                    if (mobInput) mobInput.value = updatedQty;

                    // Also find class-based mobile inputs that might not match ID
                    const metricQty = row.querySelector('.metric-box .qty-val-minimal, .qty-pill-minimal .qty-val-minimal');
                    if (metricQty) metricQty.innerText = updatedQty;
                }
            });
            updateAllRowsForEan(pData);
        } else {
            // New Scan
            // Only update history if we didn't just do it ourselves (optimistic UI ref check?)
            // For now, simpler to just update. The function handles duplication.
            updateHistory(pData);
        }

        // Global Progress Bar
        const progressEl = document.getElementById('globalProgressBar');
        if (progressEl && pData.progress_percent !== undefined) {
            progressEl.style.width = `${pData.progress_percent}%`;
            const label = progressEl.querySelector('.progress-bar-label');
            if (label) label.innerText = `${pData.progress_percent}%`;
        }
    }

    function promoteNextLatestScan() {
        const historyList = document.getElementById('scanHistory');
        if (historyList) {
            const allRows = Array.from(historyList.querySelectorAll('.history-row'));
            const activeRows = allRows.filter(r => r.style.opacity !== '0' && r.style.display !== 'none');

            if (activeRows.length > 0) {
                const firstRow = activeRows[0];
                const newScanId = firstRow.id.replace('scan-', '').replace('virtual-', '');

                const latestSection = document.getElementById('latestScanSection');
                const latestRowTbody = document.getElementById('latestScanRow');
                if (latestSection && latestRowTbody) {
                    const newRowHTML = firstRow.outerHTML;
                    latestRowTbody.innerHTML = newRowHTML;
                    const newRow = latestRowTbody.querySelector('.history-row');
                    if (newRow) newRow.classList.add('latest-scan-row');
                    firstRow.remove();
                    currentLastScanId = newScanId;
                    latestSection.style.display = '';
                }
            } else {
                const latestSection = document.getElementById('latestScanSection');
                if (latestSection) latestSection.style.display = 'none';
                currentLastScanId = null;
            }
        }
    }
}

// --- Camera Functions (QuaggaJS) ---
let _scannerIsRunning = false;
let _lastScanTime = 0;

window.toggleCamera = function () {
    if (_scannerIsRunning) {
        stopCamera();
    } else {
        startCamera();
    }
};

window.stopCamera = function () { // Make globally available for the close button
    if (_scannerIsRunning) {
        Quagga.stop();
        _scannerIsRunning = false;
    }
    const container = document.getElementById('camera-container');
    if (container) container.style.display = 'none';

    // Restore Start Button
    const camBtn = document.getElementById('camBtn');
    if (camBtn) camBtn.style.display = '';
};

function startCamera() {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#interactive'),
                constraints: {
                    width: { min: 640 },
                    height: { min: 480 },
                    facingMode: "environment",
                    aspectRatio: { min: 1, max: 2 }
                }
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: 2,
            decoder: {
                readers: ["ean_reader"]
            },
            locate: true
        }, function (err) {
            if (err) {
                console.log(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Camera Error',
                    text: 'Could not access camera. Please ensure you have granted permission.',
                    customClass: {
                        popup: 'premium-swal-popup',
                        title: 'premium-swal-title'
                    }
                });
                return;
            }
            Quagga.start();
            _scannerIsRunning = true;

            const container = document.getElementById('camera-container');
            if (container) container.style.display = 'block';

            const camBtn = document.getElementById('camBtn');
            if (camBtn) camBtn.style.display = 'none';
        });

        Quagga.onDetected(function (result) {
            const code = result.codeResult.code;
            const now = Date.now();

            if (code && (now - _lastScanTime > 2000)) {
                _lastScanTime = now;

                const input = document.getElementById('eanInput');
                if (input) input.value = code;

                processScan();

                Swal.fire({
                    icon: 'success',
                    title: 'SCANNED: ' + code,
                    toast: true,
                    position: 'top',
                    showConfirmButton: false,
                    timer: 1000
                });
            }
        });
    } else {
        Swal.fire('Error', 'Camera not supported in this browser.', 'error');
    }
}
// --- Print & PDF Functionality ---

function getReportHTML() {
    const config = window.ScanConfig || {};
    const orderId = config.orderId || 'N/A';

    // Robust Date & Staff selection
    const infoSpans = Array.from(document.querySelectorAll('.session-info span'));
    const dateSpan = infoSpans.find(s => s.innerText.toUpperCase().includes('DATE'));
    const date = dateSpan ? (dateSpan.querySelector('strong')?.innerText || dateSpan.innerText.replace(/DATE:?/i, '').trim()) : new Date().toLocaleDateString();

    const staff = document.getElementById('staffName')?.value ||
        (infoSpans.find(s => s.innerText.toUpperCase().includes('STAFF'))?.querySelector('strong')?.innerText) ||
        'N/A';

    const note = document.getElementById('orderNote')?.value || '';

    let totalOrdered = 0;
    let totalScanned = 0;
    const processedEans = new Set();
    const tableRows = document.querySelectorAll('#scanHistory tr:not(#emptyState), #mobileHistoryList .history-item-minimal');

    let rowsHtml = '';

    tableRows.forEach(row => {
        try {
            const ean = row.dataset.ean || 'N/A';
            const nameEl = row.querySelector('.col-product .fw-800, .item-name-minimal');
            const name = nameEl ? nameEl.innerText.trim() : 'Unknown';

            const textMuted = row.querySelector('.col-product .text-muted, .item-meta-minimal')?.innerText || '';
            let sku = '';
            if (textMuted.includes('/')) {
                sku = textMuted.split('/')[1].replace(/VareNr.:?/i, '').split('|')[0].trim();
            } else if (textMuted.includes('VareNr.:')) {
                sku = textMuted.split('VareNr.:')[1].split('|')[0].trim();
            }

            const orderedVal = row.querySelector('.row-ordered-val');
            const ordered = orderedVal ? (parseInt(orderedVal.innerText) || 0) : 0;

            const scannedVal = row.querySelector('.row-scanned-val');
            const scanned = scannedVal ? (parseInt(scannedVal.innerText) || 0) : 0;

            const remainingVal = row.querySelector('.row-remaining-val');
            const rest = remainingVal ? (parseInt(remainingVal.innerText) || 0) : 0;

            const statusEl = row.querySelector('.status-badge-modern, .status-badge-mobile');
            const status = statusEl ? statusEl.innerText.trim() : (scanned >= ordered ? 'COMPLETE' : 'UNDER');

            if (!processedEans.has(ean)) {
                totalOrdered += ordered;
                totalScanned += scanned;
                processedEans.add(ean);
            }

            const isVirtual = row.id && row.id.startsWith('virtual');
            const timestampEl = row.querySelector('.col-time');
            let timestamp = '--:--';
            if (!isVirtual) {
                if (timestampEl) timestamp = timestampEl.innerText.trim();
                else if (textMuted.includes('|')) timestamp = textMuted.split('|')[0].trim();
            }

            rowsHtml += `
        < tr >
                    <td style="font-size: 10px; color: #64748b; border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">${timestamp}</td>
                    <td style="border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">
                        <div style="font-weight: 700; font-size: 11px;">${name}</div>
                        <div style="font-size: 10px; color: #64748b;">EAN: ${ean} ${sku ? ' | SKU: ' + sku : ''}</div>
                    </td>
                    <td style="text-align:center; font-weight: 600; border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">${ordered}</td>
                    <td style="text-align:center; font-weight: 800; border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">${scanned}</td>
                    <td style="text-align:center; color: ${rest > 0 ? '#2563eb' : '#000'}; border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">${rest}</td>
                    <td style="text-align:center; border-bottom: 1px solid #f1f5f9; padding: 8px 4px;">
                        <span class="status-tag status-tag-${status}" style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; white-space: nowrap; ${status === 'COMPLETE' ? 'background: #dcfce7; color: #166534;' :
                    (status === 'OVER' ? 'background: #fee2e2; color: #991b1b;' : 'background: #eff6ff; color: #1e40af;')
                }">${status}</span>
                    </td>
                </tr >
            `;
        } catch (rowErr) { console.warn(rowErr); }
    });

    const progress = totalOrdered > 0 ? Math.round((totalScanned / totalOrdered) * 100) : 0;

    return `
            < div style = "padding: 30px; font-family: 'Inter', -apple-system, sans-serif; background: #fff; color: #1e293b;" >
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px;">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800; color: #2563eb; margin: 0; text-transform: uppercase; letter-spacing: -0.02em;">Reception Report</h1>
                    <div style="font-size: 14px; font-weight: 700; color: #64748b; margin-top: 5px;">ORDER REF: #${orderId}</div>
                </div>
                <div style="text-align: right; font-size: 12px; color: #64748b; line-height: 1.5;">
                    DATE: <strong>${date}</strong><br>
                        STAFF: <strong>${staff}</strong>
                </div>
            </div>

            ${note ? `
            <div style="margin-bottom: 25px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 5px solid #2563eb; border-radius: 6px;">
                <div style="font-size: 10px; font-weight: 800; color: #2563eb; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.05em;">Session Notes</div>
                <div style="font-size: 12px; color: #334155; line-height: 1.5;">${note}</div>
            </div>` : ''}

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="background: #f8fafc;">
                <th style="width: 50px; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: left; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Time</th>
                <th style="padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: left; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Product Details</th>
                <th style="width: 50px; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Ord.</th>
                <th style="width: 50px; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Scan.</th>
                <th style="width: 50px; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Rest</th>
                <th style="width: 80px; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Status</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml || '<tr><td colspan="6" style="text-align:center; padding: 30px; color: #94a3b8;">No scan activity recorded for this session.</td></tr>'}
        </tbody>
    </table>

        </div >
        `;
}

function printReport() {
    try {
        console.log("Preparing Report for Print...");
        let printArea = document.getElementById('print-area');
        if (!printArea) {
            printArea = document.createElement('div');
            printArea.id = 'print-area';
            document.body.prepend(printArea);
        }

        printArea.innerHTML = getReportHTML();
        printArea.style.display = 'block';

        setTimeout(() => {
            window.print();
        }, 250);

    } catch (err) {
        console.error("Print Error:", err);
        alert("Could not generate print report. Error: " + err.message);
    }
}

function downloadPDF() {
    try {
        console.log("Preparing Report for PDF Download...");

        const config = window.ScanConfig || {};
        const orderId = config.orderId || '0000';
        const fileName = `Reception_Report_Order_${orderId}.pdf`;

        // Use a temporary hidden container for PDF generation
        const source = document.createElement('div');
        source.innerHTML = getReportHTML();

        // html2pdf options
        const opt = {
            margin: 0,
            filename: fileName,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        // Execution
        html2pdf().set(opt).from(source).save().then(() => {
            console.log("PDF download triggered.");
        }).catch(pdfErr => {
            console.error("html2pdf Error:", pdfErr);
            alert("PDF Generation Failed: " + pdfErr.message);
        });

    } catch (err) {
        console.error("Download Error:", err);
        alert("An error occurred while preparing the PDF: " + err.message);
    }
}

// Call initPusher on load
document.addEventListener('DOMContentLoaded', initPusher);

/**
 * Opens a SweetAlert2 dialog (PC/Desktop only) allowing staff to enter
 * and save the correct EAN code for a product where VareNummer was used.
 */
function openAddEanModal(productId, productName, scanId) {
    Swal.fire({
        title: 'Add Correct EAN Code',
        html: `
        <div style="text-align:left;">
                <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">
                    Product: <strong>${productName}</strong><br>
                    VareNummer (used as temp EAN): <strong style="color:#92400e;">${productId}</strong>
                </p>
                <label for="swal-ean-input" style="font-size:0.75rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;">New EAN Code</label>
                <input id="swal-ean-input" class="swal2-input" placeholder="Enter real EAN (e.g. 5701234567890)" inputmode="numeric" autocomplete="off" style="margin-top:4px;">
            </div>
    `,
        showCancelButton: true,
        confirmButtonText: 'Save EAN',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f59e0b',
        reverseButtons: true,
        customClass: {
            popup: 'premium-swal-popup',
            title: 'premium-swal-title',
            confirmButton: 'btn btn-warning btn-sm fw-bold me-2 px-3 py-1',
            cancelButton: 'btn btn-light btn-sm fw-bold text-secondary px-3 py-1',
        },
        buttonsStyling: false,
        width: '360px',
        didOpen: () => {
            const inp = document.getElementById('swal-ean-input');
            if (inp) inp.focus();
        },
        preConfirm: () => {
            const val = document.getElementById('swal-ean-input')?.value?.trim();
            if (!val) {
                Swal.showValidationMessage('Please enter an EAN code.');
                return false;
            }
            if (val === productId) {
                Swal.showValidationMessage('EAN cannot be the same as VareNummer.');
                return false;
            }
            return val;
        }
    }).then(result => {
        if (!result.isConfirmed || !result.value) return;

        const newEan = result.value;

        fetch(window.ScanConfig.routes.updateEan, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
            },
            body: JSON.stringify({
                product_id: productId,
                new_ean: newEan,
                scan_id: scanId || null,
                order_id: window.ScanConfig.orderId
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ── IMMEDIATE DOM UPDATE (before any Swal, no waiting) ──
                    applyEanUpdateToDOM(productId, newEan, productName, data.ordered_quantity, data.scanned_total);

                    // Refocus scanner immediately
                    const eanInput = document.getElementById('eanInput');
                    if (eanInput) eanInput.focus();

                    // Show confirmation toast (non-blocking)
                    Swal.fire({
                        icon: 'success',
                        title: 'EAN Updated!',
                        html: `<p>EAN code for <strong>${productName}</strong> has been updated to <strong>${newEan}</strong>.</p>`,
                        confirmButtonText: 'Great!',
                        confirmButtonColor: '#22c55e',
                        customClass: { popup: 'premium-swal-popup', title: 'premium-swal-title' },
                        timer: 4000,
                        timerProgressBar: true,
                        toast: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message || 'Could not update EAN code.',
                        customClass: { popup: 'premium-swal-popup' }
                    });
                }
            })
            .catch(err => {
                console.error('EAN Update Error:', err);
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not reach server.', customClass: { popup: 'premium-swal-popup' } });
            });
    });
}

/**
 * applyEanUpdateToDOM(productId, newEan, newName, orderedQty)
 *
 * Correct flow:
 *  1. Remove "Unknown Product" rows that have data-ean=newEan, collecting their scanned qty.
 *  2. Update the target product row (data-ean=productId or data-sku=productId):
 *     - Change its EAN to newEan
 *     - Add the collected qty to its existing scanned count
 *     - Recalculate REST and STATUS
 *  3. Strip warning banners and update EAN text everywhere.
 */
function applyEanUpdateToDOM(productId, newEan, newName, orderedQty, scannedTotal) {
    console.log('%c EAN DOM Update:', 'background:#f59e0b;color:#000;', productId, '->', newEan, '|', newName, '| ordered:', orderedQty, '| scanned:', scannedTotal);

    // ── STEP 1: Remove "Unknown Product" rows that held the real barcode ──────────────────────
    // These are rows with data-ean=newEan whose product name is "Unknown" or similar.
    // We collect their scanned qty before removing.
    let extraScanned = 0;

    document.querySelectorAll(
        'tr.history-row[data-ean="' + newEan + '"],' +
        'div.history-item-minimal[data-ean="' + newEan + '"],' +
        'tr.latest-scan-row[data-ean="' + newEan + '"]'
    ).forEach(function(el) {
        var nameEl = el.querySelector('.col-product .fw-800, .item-name-minimal');
        var nameText = nameEl ? nameEl.innerText.trim().toLowerCase() : '';
        var isUnknown = nameText === '' ||
                        nameText.includes('unknown') ||
                        nameText.startsWith('product_');

        if (isUnknown) {
            // Collect scanned qty from this Unknown row
            var qtyInput = el.querySelector('.qty-input, .qty-input-minimal');
            var qtyBadge = el.querySelector('.badge.bg-light.text-secondary');
            if (qtyInput)      extraScanned += parseInt(qtyInput.value) || 0;
            else if (qtyBadge) extraScanned += parseInt(qtyBadge.innerText) || 0;
            el.remove();
        }
    });

    // ── STEP 2: Update the TARGET product row ─────────────────────────────────────────────────
    // The target row is keyed by the old productId/varenummer (data-ean=productId or data-sku=productId).
    // We update it in-place rather than remove-and-recreate to preserve the DOM element.

    var targetSelectors = [
        'tr.history-row[data-ean="' + productId + '"]',
        'div.history-item-minimal[data-ean="' + productId + '"]',
        'tr.latest-scan-row[data-ean="' + productId + '"]',
        'tr.history-row[data-sku="' + productId + '"]',
        'div.history-item-minimal[data-sku="' + productId + '"]'
    ];

    // Deduplicate matched elements (same element could match multiple selectors)
    var seen = new Set();
    var targetEls = [];
    targetSelectors.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(function(el) {
            if (!seen.has(el)) { seen.add(el); targetEls.push(el); }
        });
    });

    targetEls.forEach(function(el) {
        // Update data attributes
        el.dataset.ean = newEan;
        el.dataset.sku = productId;
        el.dataset.scanStatus = 'scanned';

        // Update EAN text spans
        el.querySelectorAll('.selectable-ean').forEach(function(s) { s.innerText = newEan; });

        // Update product name
        if (newName) {
            var nameEl = el.querySelector('.col-product .fw-800, .item-name-minimal');
            if (nameEl) nameEl.innerText = newName;
        }

        // Use authoritative scanned total from server if available, otherwise fall back to DOM merge
        var scEl = el.querySelector('.row-scanned-val');
        var currentScanned = scEl ? (parseInt(scEl.innerText) || 0) : 0;
        // scannedTotal from server is the TRUE total — always prefer it over DOM calculation
        var newScanned = (scannedTotal !== undefined && scannedTotal !== null)
            ? parseInt(scannedTotal)
            : (currentScanned + extraScanned);
        if (scEl) scEl.innerText = newScanned;

        // Update the # column badge (shows total qty next to the action buttons)
        var qtyBadge = el.querySelector('.col-qty .badge, .badge.bg-light.text-secondary');
        if (qtyBadge) qtyBadge.innerText = newScanned;

        // Update the editable qty-input (the number field between − and + buttons)
        var qtyInput = el.querySelector('.qty-input, .qty-input-minimal');
        if (qtyInput) qtyInput.value = newScanned;

        // Update ordered count
        var orderedFromDOM = parseInt(el.querySelector('.row-ordered-val') ? el.querySelector('.row-ordered-val').innerText : '0') || 0;
        var ordered = (orderedQty !== undefined && orderedQty !== null) ? orderedQty : orderedFromDOM;
        var ordEl = el.querySelector('.row-ordered-val');
        if (ordEl) ordEl.innerText = ordered;

        // Recalculate REST = ORDERED - SCANNED
        var remaining = Math.max(0, ordered - newScanned);
        var remEl = el.querySelector('.row-remaining-val');
        if (remEl) remEl.innerText = remaining;

        // Update STATUS badge
        var diff = newScanned - ordered;
        var statusText  = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');
        var statusColor = diff === 0 ? '#22c55e'  : (diff > 0 ? '#ef4444' : '#2563eb');

        var badge = el.querySelector('.status-badge-modern');
        if (badge) { badge.innerText = statusText; badge.style.background = statusColor; }
        var mobBadge = el.querySelector('.status-badge-mobile');
        if (mobBadge) { mobBadge.innerText = statusText; mobBadge.style.background = statusColor + '20'; mobBadge.style.color = statusColor; }

        // Remove warning banners and ADD EAN buttons
        el.querySelectorAll('.ean-warning-container').forEach(function(b) { b.remove(); });
        el.querySelectorAll('button[onclick*="openAddEanModal"]').forEach(function(b) { b.remove(); });

        // Update VareNr text in meta line if present
        el.querySelectorAll('.text-muted, .item-meta-minimal').forEach(function(meta) {
            if (meta.innerText.includes('VareNr.:')) {
                meta.innerHTML = meta.innerHTML.replace(/EAN:\s*<span[^>]*>.*?<\/span>/, 'EAN: <span class="selectable-ean">' + newEan + '</span>');
            }
        });
    });

    // ── STEP 3: Clean up stray warning banners ────────────────────────────────────────────────
    document.querySelectorAll('.ean-warning-container[data-product-sku="' + productId + '"]').forEach(function(el) { el.remove(); });

    // Update any lone EAN spans still showing the old code
    document.querySelectorAll('#latestScanRow .selectable-ean, #scanHistory .selectable-ean').forEach(function(span) {
        if (span.innerText.trim() === productId) span.innerText = newEan;
    });

    console.log('%c EAN DOM Update done. Extra scanned merged: ' + extraScanned + ', Targets updated: ' + targetEls.length, 'background:#22c55e;color:#fff;');
}

function switchToOrder(orderId) {
    if (!orderId) return;

    console.log('%c Switching Context to Order:', 'background: #7c3aed; color: #fff; padding: 2px 5px;', orderId);

    // 1. Update UI Header
    const orderIdDisplay = document.getElementById('displayOrderId');
    if (orderIdDisplay) {
        orderIdDisplay.innerText = `ORDER #${orderId} `;
    }
    window.ScanConfig.orderId = orderId;

    // 2. Clear current lists
    const historyList = document.getElementById('scanHistory');
    const mobileList = document.getElementById('mobileHistoryList');
    if (historyList) historyList.innerHTML = '';
    if (mobileList) mobileList.innerHTML = '';

    // 3. Fetch History for the new order
    const url = `/ order - delivery / ${orderId}/history`;
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.scans) {
                // Controller returns desc updated_at, so we reverse it to populate correctly
                // or just let updateHistory handle the prepending
                data.scans.reverse().forEach(scanData => {
                    updateHistory(scanData);
                });

                // Update sticky bar with the latest scan (original first item)
                if (data.scans.length > 0) {
                    const latest = data.scans[data.scans.length - 1]; // because we reversed
                    const bar = document.getElementById('stickyLatestBar');
                    const name = document.getElementById('stickyItemName');
                    const qty = document.getElementById('stickyItemQty');
                    if (bar && name && qty) {
                        name.innerText = latest.product_name;
                        qty.innerText = latest.units;
                        bar.style.display = 'flex';
                    }
                } else {
                    const bar = document.getElementById('stickyLatestBar');
                    if (bar) bar.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Error switching order context:', err));
}
