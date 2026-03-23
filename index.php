<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ─── CONFIGURATION ──────────────────────────────────────────────────────────
const LUNCH_THRESHOLD = 5.5;   // hours before lunch deduction applies
const LUNCH_DEDUCTION = 0.5;   // hours deducted for lunch
const OVERTIME_THRESHOLD = 7.5; // daily hours before overtime kicks in
const OVERTIME_MIN = 0.1667;    // minimum overtime (10 min) to count
const FULL_DAY_HOURS = 7.5;    // standard day for Ferie / Paid day off
const EVENING_START = 18.0;    // kveldstillegg starts at 18:00
const SAT_TIER1_START = 13.0;  // lørdagstillegg tier 1 start
const SAT_TIER1_END = 15.0;    // lørdagstillegg tier 1 end
const SAT_TIER2_START = 15.0;  // lørdagstillegg tier 2 start

// ─── HELPERS ────────────────────────────────────────────────────────────────

/**
 * Parse "HH:MM" to decimal hours (e.g. "14:30" → 14.5).
 * Returns null if empty/invalid.
 */
function parseTime(?string $t): ?float
{
    $t = trim($t ?? '');
    if ($t === '') return null;
    $parts = explode(':', $t);
    if (count($parts) !== 2) return null;
    return (int)$parts[0] + (int)$parts[1] / 60.0;
}

/**
 * Calculate hours worked between two decimal times within a given window [winStart, winEnd).
 * Clamps the shift to the window and returns overlap in hours.
 */
function hoursInWindow(float $shiftIn, float $shiftOut, float $winStart, float $winEnd): float
{
    $start = max($shiftIn, $winStart);
    $end   = min($shiftOut, $winEnd);
    return max(0, $end - $start);
}

/**
 * Parse the CSV, handling multi-line quoted fields.
 */
function parseCsv(string $filePath): array
{
    $content = file_get_contents($filePath);
    // Normalize line endings
    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\r", "\n", $content);

    $rows = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $header = fgetcsv($handle);
    if (!$header) return [];
    // Trim BOM from first header
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $header = array_map('trim', $header);

    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < count($header)) {
            $data = array_pad($data, count($header), '');
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = trim($data[$i] ?? '');
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

/**
 * Determine the day of week from a date string (YYYY-MM-DD).
 */
function getDayOfWeek(string $date): string
{
    return date('D', strtotime($date)); // Mon, Tue, ..., Sat, Sun
}

// ─── STEG 1: PER-ROW CALCULATIONS ──────────────────────────────────────────

function processSteg1(array $csvRows): array
{
    $steg1 = [];

    foreach ($csvRows as $row) {
        $empId     = $row['Employee ID'] ?? '';
        $firstName = $row['First Name'] ?? '';
        $lastName  = $row['Last Name'] ?? '';
        $date      = $row['Date'] ?? '';
        $inTime    = parseTime($row['In'] ?? '');
        $outTime   = parseTime($row['Out'] ?? '');
        $csvHours  = (float)($row['Hours'] ?? 0);
        $position  = $row['Position'] ?? '';
        $timeOff   = $row['Time Off Type'] ?? '';
        $status    = $row['Status'] ?? '';
        $day       = getDayOfWeek($date);
        $isSaturday = ($day === 'Sat');

        $isEkstraTimer = (stripos($position, 'Ekstra Timer') !== false);
        $isAbsence     = ($timeOff !== '');

        // 1. Worked Hours: Out - In, fallback to CSV Hours
        $workedHours = 0.0;
        if ($inTime !== null && $outTime !== null) {
            $workedHours = $outTime - $inTime;
            if ($workedHours < 0) $workedHours += 24; // overnight shift
        } else {
            $workedHours = $csvHours;
        }

        // 2. Ekstra Timer: separate column
        $ekstraTimer = 0.0;
        if ($isEkstraTimer) {
            $ekstraTimer = round($workedHours, 2);
        }

        // Initialize absence columns
        $paidDayOff   = 0.0;
        $egenmelding  = 0.0;
        $sykemelding  = 0.0;
        $ferie        = 0.0;
        $unpaidTimeOff = 0.0;

        // 3-4. Lunch deduction & Adjusted Hours
        $lunch = 0.0;
        $adjustedHours = 0.0;

        if ($isAbsence) {
            // Absence handling
            $timeOffLower = strtolower($timeOff);
            if (strpos($timeOffLower, 'paid day off') !== false || strpos($timeOffLower, 'public holiday') !== false) {
                $paidDayOff = FULL_DAY_HOURS;
            } elseif (strpos($timeOffLower, 'egenmelding') !== false) {
                $egenmelding = $csvHours;
            } elseif (strpos($timeOffLower, 'sykemelding') !== false) {
                $sykemelding = $csvHours;
            } elseif (strpos($timeOffLower, 'ferie') !== false || strpos($timeOffLower, 'vacation') !== false) {
                $ferie = FULL_DAY_HOURS;
            } elseif (strpos($timeOffLower, 'unpaid') !== false) {
                $unpaidTimeOff = 1; // count as 1 day
            }
            // Adjusted hours = 0 on absence days
            $adjustedHours = 0.0;
        } elseif ($isEkstraTimer) {
            // Ekstra Timer: no lunch, no overtime, adjusted = 0 (goes to ekstra column)
            $adjustedHours = 0.0;
        } else {
            // Normal work day
            if ($workedHours >= LUNCH_THRESHOLD) {
                $lunch = LUNCH_DEDUCTION;
            }
            $adjustedHours = $workedHours - $lunch;
        }

        // 5. Overtime: only for normal shifts (not ekstra, not absence)
        $overtime = 0.0;
        if (!$isAbsence && !$isEkstraTimer) {
            $ot = $adjustedHours - OVERTIME_THRESHOLD;
            if ($ot >= OVERTIME_MIN) {
                $overtime = $ot;
            }
        }

        // 6. Kveldstillegg: hours after 18:00 on weekdays (not Saturday)
        $kveldstillegg = 0.0;
        if (!$isAbsence && !$isSaturday && $inTime !== null && $outTime !== null) {
            $kveldstillegg = hoursInWindow($inTime, $outTime, EVENING_START, 24.0);
        }

        // 7. Lørdagstillegg 13-15: Saturday hours between 13:00-15:00
        $lordagTillegg1315 = 0.0;
        if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
            $lordagTillegg1315 = hoursInWindow($inTime, $outTime, SAT_TIER1_START, SAT_TIER1_END);
        }

        // 8. Lørdagstillegg etter 15: Saturday hours after 15:00
        $lordagTilleggEtter15 = 0.0;
        if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
            $lordagTilleggEtter15 = hoursInWindow($inTime, $outTime, SAT_TIER2_START, 24.0);
        }

        $steg1[] = [
            'Employee ID'              => $empId,
            'First Name'               => $firstName,
            'Last Name'                => $lastName,
            'Date'                     => $date,
            'In'                       => $row['In'] ?? '',
            'Out'                      => $row['Out'] ?? '',
            'Hours'                    => round($csvHours, 2),
            'Worked Hours'             => round($workedHours, 2),
            'Ekstra Timer'             => round($ekstraTimer, 2),
            'Adjusted Hours'           => round($adjustedHours, 2),
            'Overtime'                 => round($overtime, 2),
            'Paid Day Off'             => round($paidDayOff, 2),
            'Egenmelding'              => round($egenmelding, 2),
            'Sykemelding'              => round($sykemelding, 2),
            'Ferie / Vacation'         => round($ferie, 2),
            'Unpaid Time Off'          => round($unpaidTimeOff, 2),
            'Kveldstillegg'            => round($kveldstillegg, 2),
            'Lørdagstillegg 13–15'     => round($lordagTillegg1315, 2),
            'Lørdagstillegg etter 15'  => round($lordagTilleggEtter15, 2),
            'Status'                   => $status,
            'Time Off Type'            => $timeOff,
        ];
    }

    return $steg1;
}

// ─── STEG 2: SUMMARY PER EMPLOYEE ──────────────────────────────────────────

function processSteg2(array $steg1): array
{
    $employees = [];

    foreach ($steg1 as $row) {
        $key = $row['Employee ID'];
        if (!isset($employees[$key])) {
            $employees[$key] = [
                'Employee ID'  => $row['Employee ID'],
                'First Name'   => $row['First Name'],
                'Last Name'    => $row['Last Name'],
                'Ordinære timer'              => 0.0,
                'Ekstra timer'                => 0.0,
                'Paid day off / Public holiday' => 0.0,
                'Betalte arbeidstimer'        => 0.0,
                'Overtid'                     => 0.0,
                'Egenmelding'                 => 0.0,
                'Sykemelding'                 => 0.0,
                'Ferie / Vacation'            => 0.0,
                'Unpaid time off'             => 0.0,
                'Totalt arbeidstid inkl. fravær' => 0.0,
                'Kveldstillegg (timer)'       => 0.0,
                'Lørdagstillegg 13–15'        => 0.0,
                'Lørdagstillegg etter 15'     => 0.0,
            ];
        }

        $emp = &$employees[$key];

        // Ordinære timer = Adjusted Hours (normal work, excluding ekstra and absence)
        $emp['Ordinære timer']              += $row['Adjusted Hours'];
        $emp['Ekstra timer']                += $row['Ekstra Timer'];
        $emp['Paid day off / Public holiday'] += $row['Paid Day Off'];
        $emp['Overtid']                     += $row['Overtime'];
        $emp['Egenmelding']                 += $row['Egenmelding'];
        $emp['Sykemelding']                 += $row['Sykemelding'];
        $emp['Ferie / Vacation']            += $row['Ferie / Vacation'];
        $emp['Unpaid time off']             += $row['Unpaid Time Off'];
        $emp['Kveldstillegg (timer)']       += $row['Kveldstillegg'];
        $emp['Lørdagstillegg 13–15']        += $row['Lørdagstillegg 13–15'];
        $emp['Lørdagstillegg etter 15']     += $row['Lørdagstillegg etter 15'];

        unset($emp);
    }

    // Calculate derived totals and round everything
    foreach ($employees as &$emp) {
        $emp['Betalte arbeidstimer'] = $emp['Ordinære timer'] + $emp['Ekstra timer'] + $emp['Paid day off / Public holiday'];
        $emp['Totalt arbeidstid inkl. fravær'] = $emp['Betalte arbeidstimer'] + $emp['Egenmelding'] + $emp['Sykemelding'] + $emp['Ferie / Vacation'];

        // Round all numeric fields to 2 decimals
        foreach ($emp as $k => &$v) {
            if (is_float($v)) {
                $v = round($v, 2);
            }
        }
        unset($v);
    }
    unset($emp);

    // Sort by Employee ID
    usort($employees, fn($a, $b) => $a['Employee ID'] <=> $b['Employee ID']);

    return $employees;
}

// ─── XLSX GENERATION ────────────────────────────────────────────────────────

function createXlsx(array $data, string $sheetName): Spreadsheet
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);

    if (empty($data)) return $spreadsheet;

    // Header row
    $headers = array_keys($data[0]);
    $colCount = count($headers);
    foreach ($headers as $col => $header) {
        $colLetter = Coordinate::stringFromColumnIndex($col + 1);
        $sheet->getCell($colLetter . '1')->setValue($header);
    }

    // Style header
    $lastColLetter = Coordinate::stringFromColumnIndex($colCount);
    $sheet->getStyle('A1:' . $lastColLetter . '1')->getFont()->setBold(true);

    // Data rows
    foreach ($data as $rowIdx => $row) {
        foreach (array_values($row) as $col => $value) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->getCell($colLetter . ($rowIdx + 2))->setValue($value);
        }
    }

    // Auto-size columns
    foreach ($headers as $col => $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    return $spreadsheet;
}

// ─── REQUEST HANDLING ───────────────────────────────────────────────────────

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed (error code: ' . $file['error'] . ')';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Please upload a CSV file.';
    } else {
        try {
            // Parse CSV
            $csvRows = parseCsv($file['tmp_name']);
            if (empty($csvRows)) {
                throw new \RuntimeException('No data rows found in CSV.');
            }

            // Process
            $steg1 = processSteg1($csvRows);
            $steg2 = processSteg2($steg1);

            // Generate single XLSX with two sheets
            $spreadsheet = createXlsx($steg1, 'STEG 1');
            // Add STEG 2 as second sheet
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('STEG 2');
            if (!empty($steg2)) {
                $headers2 = array_keys($steg2[0]);
                foreach ($headers2 as $col => $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet2->getCell($colLetter . '1')->setValue($header);
                }
                $lastCol2 = Coordinate::stringFromColumnIndex(count($headers2));
                $sheet2->getStyle('A1:' . $lastCol2 . '1')->getFont()->setBold(true);
                foreach ($steg2 as $rowIdx => $row) {
                    foreach (array_values($row) as $col => $value) {
                        $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                        $sheet2->getCell($colLetter . ($rowIdx + 2))->setValue($value);
                    }
                }
                foreach ($headers2 as $col => $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet2->getColumnDimension($colLetter)->setAutoSize(true);
                }
            }

            // Set STEG 1 as active sheet
            $spreadsheet->setActiveSheetIndex(0);

            // Write to temp and send
            $tmpDir = sys_get_temp_dir();
            $outPath = $tmpDir . '/lonnsrapport.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($outPath);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="lonnsrapport.xlsx"');
            header('Content-Length: ' . filesize($outPath));
            readfile($outPath);
            unlink($outPath);
            exit;
        } catch (\Throwable $e) {
            $error = 'Processing error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Lønnsrapport</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        p.subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1rem;
            transition: border-color 0.2s;
        }

        .upload-area:hover {
            border-color: #0066cc;
        }

        .upload-area input[type="file"] {
            margin: 0.5rem 0;
        }

        button {
            background: #0066cc;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }

        button:hover {
            background: #0052a3;
        }

        .error {
            background: #fee;
            color: #c00;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .info {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #999;
        }

        .info ul {
            padding-left: 1.2rem;
            margin-top: 0.3rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Timesheet Lønnsrapport</h1>
        <p class="subtitle">Last opp Tamigo CSV-eksport for å generere STEG 1 og STEG 2 rapporter.</p>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area">
                <p>Velg CSV-fil fra Tamigo</p>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit">Generer Lønnsrapport</button>
        </form>

        <div class="info">
            <strong>Output:</strong> lonnsrapport.xlsx med to ark:
            <ul>
                <li>STEG 1 — daglige detaljer per ansatt</li>
                <li>STEG 2 — sammendrag per ansatt</li>
            </ul>
        </div>
    </div>
</body>

</html>