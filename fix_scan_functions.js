                }
            }

function updateUnits(id, change) {
    fetch("{{ route('order-delivery.update-units') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify({
            scan_id: id,
            change: change
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const el = document.getElementById(`qty-val-${id}`);
                if (el) el.innerText = Math.max(0, parseInt(el.innerText) + change);

                // Update this specific row's scan status
                const row = document.getElementById(`scan-${id}`);
                if (row) {
                    const diff = data.scanned - data.ordered;
                    const bgStyle = diff === 0 ? 'background-color: #10b981;' : (diff > 0 ? 'background-color: #ef4444;' : 'background-color: #3b82f6;');
                    const statusText = diff === 0 ? 'COMPLETE' : (diff > 0 ? 'OVER' : 'UNDER');

                    const badge = row.querySelector('.status-badge');
                    if (badge) {
                        badge.style.cssText = `${bgStyle} font-size: 0.75rem; min-width: 80px;`;
                        badge.innerText = statusText;
                    }

                    row.dataset.errorStatus = diff === 0 ? 'ok' : 'error';

                    // Update scanned val on row
                    const sVal = row.querySelector('.row-scanned-val');
                    if (sVal) sVal.innerText = data.scanned;
                }

                // Sync ALL rows for this EAN
                updateAllRowsForEan(data);

                if (typeof progressEl !== 'undefined') {
                    progressEl.innerText = `${data.progress_percent}%`;
                }

                // Critical: Return focus to scanner after manual update
                setTimeout(ensureFocus, 100);
            }
        });
}

function deleteScan(id) {
    Swal.fire({
        title: 'DELETE RECORD?',
        text: "This action will permanently remove this scan from history.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: 'Confirm Delete',
        cancelButtonText: 'Keep it',
        reverseButtons: true,
        customClass: {
            popup: 'premium-swal-popup',
            title: 'premium-swal-title',
            htmlContainer: 'premium-swal-content',
            confirmButton: 'premium-swal-confirm',
            cancelButton: 'premium-swal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Optimistic UI: Remove immediately
            const row = document.getElementById(`scan-${id}`);
            if (row) {
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }

            fetch("{{ route('order-delivery.delete-scan') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    scan_id: id
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Sync all row metrics for this EAN after deletion
                        updateAllRowsForEan(data);

                        if (typeof progressEl !== 'undefined') {
                            progressEl.innerText = `${data.progress_percent}%`;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'DELETED',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'premium-swal-popup',
                                title: 'premium-swal-title'
                            }
                        });
                    }
                });
        }
    });
}
