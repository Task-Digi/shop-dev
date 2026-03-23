@php
$ordered = $scan->ordered_total;
$scannedSoFar = $scan->scanned_total;
$diff = $scannedSoFar - $ordered;
$remaining = max(0, $ordered - $scannedSoFar);

$statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
$statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
$errorStatus = abs($diff) > 0 ? 'error' : 'ok';
@endphp
<div class="history-item-minimal"
    id="mobile-scan-{{ $scan->id }}"
    data-scan-status="{{ isset($scan->is_virtual) ? 'missing' : 'scanned' }}"
    data-error-status="{{ $errorStatus }}"
    data-other-scanned="{{ $scan->scanned_total - $scan->units }}"
    data-ean="{{ $scan->ean_code }}"
    data-sku="{{ $scan->sku }}">

    <div class="item-data" style="width: 100%;">
        <div class="d-flex justify-content-end align-items-start w-100 mb-1">
            <span class="status-badge-mobile" style="color: white; background: {{ $statusColor }}; font-size: 0.7rem;">{{ $statusText }}</span>
        </div>

        <div class="text-center mb-2">
            <div style="font-size: 1.1rem; justify-content: center;" class="fw-800 item-name-minimal d-flex align-items-center flex-wrap gap-1">
                {{ $scan->product_name }}
                @php
                    $isGenericUnitMob = in_array(strtoupper($scan->packaging_unit), ['STK', 'PCS', 'SPA', 'BOX', 'SET', 'PAK', 'FL', 'RUL', 'POS', 'ESK', 'TUB', 'ST', 'BX']);
                    $hasVolumeInfoMob = preg_match('/[0-9,.]\s*(L|ML|KG|GR|G)\b/i', $scan->packaging_unit);
                @endphp
                @if($scan->packaging_quantity && ($scan->packaging_quantity > 1 || ($scan->packaging_quantity == 1 && ($hasVolumeInfoMob || !$isGenericUnitMob))))
                <span class="badge bg-light text-dark border ms-1" style="font-weight: 800; font-size: 0.75rem;">
                    @if($scan->packaging_quantity > 1) {{ str_replace('.', ',', (float)$scan->packaging_quantity) }} @endif
                    {{ $scan->packaging_unit }}
                </span>
                @endif
                <!-- Ref: removed -->
            </div>
            <div class="item-meta-minimal mt-1">
                EAN: <span class="selectable-ean">{{ $scan->ean_code }}</span> @if($scan->sku) / VareNr.: {{ $scan->sku }} @endif
            </div>
            @php
            $mobileEanMissing = $scan->sku && $scan->ean_code && trim((string)$scan->sku) === trim((string)$scan->ean_code);
            @endphp
            @if($mobileEanMissing)
            <div class="mb-2 ean-warning-container" data-product-sku="{{ $scan->sku }}" style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:6px 10px;font-size:0.75rem;color:#856404;font-weight:700;line-height:1.4;">
                &#9888; EAN Code MISSING &ndash; Found VareNummer<br>
            </div>
            @endif
        </div>

        <div class="metrics-grid mb-3">
            <div class="metric-box">
                <span class="metric-label">Ordered</span>
                <span class="metric-val row-ordered-val">{{ $ordered }}</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">Scanned</span>
                <span class="metric-val row-scanned-val">{{ $scannedSoFar }}</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">Rest</span>
                <span class="metric-val remaining row-remaining-val">{{ $remaining }}</span>
            </div>
            <div class="metric-box">
                <span class="metric-label">#</span>
                <span class="metric-val qty-val-minimal" id="mobile-qty-display-{{ $scan->id }}">{{ $scan->units }}</span>
            </div>
        </div>

        <div class="d-flex justify-content-center align-items-center gap-4">
            <button type="button" class="qty-btn-minimal fw-800" style="color: #2563eb; background: #eff6ff; border-radius: 8px; font-size: 1rem; padding: 0 10px;" onclick="matchOrderScans({{ $scan->id }})">#OK</button>
            <div class="qty-pill-minimal">
                <button type="button" class="qty-btn-minimal" onclick="updateUnits({{ $scan->id }}, -1)">−</button>
                <span class="qty-val-minimal" id="mobile-qty-val-{{ $scan->id }}" style="display: none;">{{ $scan->units }}</span>
                <input type="number" inputmode="numeric" class="qty-input-minimal" id="mobile-qty-input-{{ $scan->id }}" value="{{ $scan->units }}" onchange="updateUnitsExact({{ $scan->id }}, this.value)" style="width: 50px; text-align: center; font-weight: 800; font-size: 1.2rem; border: none; background: transparent; padding: 0; margin: 0 5px; color: #1e293b; outline: none; -moz-appearance: textfield;">
                <button type="button" class="qty-btn-minimal text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
            </div>
            {{-- 
            <button class="btn-delete-action shadow-sm" onclick="deleteScan({{ $scan->id }})">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
                </svg>
            </button>
            --}}
        </div>
    </div>
</div>