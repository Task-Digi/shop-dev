<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PayrollController extends Controller
{
    const LUNCH_THRESHOLD = 5.5;   // hours before lunch deduction applies
    const LUNCH_DEDUCTION = 0.5;   // hours deducted for lunch
    const OVERTIME_THRESHOLD = 7.5; // daily hours before overtime kicks in
    const OVERTIME_MIN = 0.1666;    // minimum overtime (10 min) to count
    const FULL_DAY_HOURS = 7.5;    // standard day for Ferie / Paid day off
    const EVENING_START = 18.0;    // kveldstillegg starts at 18:00
    const SAT_TIER1_START = 13.0;  // lørdagstillegg tier 1 start
    const SAT_TIER1_END = 15.0;    // lørdagstillegg tier 1 end
    const SAT_TIER2_START = 15.0;  // lørdagstillegg tier 2 start

    public function index()
    {
        return view('payroll.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file'
        ]);

        $file = $request->file('csv_file');

        try {
            $csvRows = self::parseUploadedFile($file->getPathname());
            if (empty($csvRows)) {
                throw new \RuntimeException('No data rows found in CSV.');
            }

            $steg1 = self::processSteg1($csvRows);
            $steg2 = self::processSteg2($steg1);

            $spreadsheet = self::createXlsx($steg1, 'STEG 1');
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('STEG 2');

            if (!empty($steg2)) {
                $headers2 = array_keys($steg2[0]);
                foreach ($headers2 as $col => $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet2->setCellValue($colLetter . '1', $header);
                }
                $lastCol2 = Coordinate::stringFromColumnIndex(count($headers2));
                $sheet2->getStyle('A1:' . $lastCol2 . '1')->getFont()->setBold(true);
                foreach ($steg2 as $rowIdx => $row) {
                    foreach (array_values($row) as $col => $value) {
                        $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                        $sheet2->setCellValue($colLetter . ($rowIdx + 2), $value);
                    }
                }
                foreach ($headers2 as $col => $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet2->getColumnDimension($colLetter)->setAutoSize(true);
                }
            }

            $spreadsheet->setActiveSheetIndex(0);

            $outPath = sys_get_temp_dir() . '/' . uniqid() . '_lonnsrapport.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($outPath);

            return response()->download($outPath, 'lonnsrapport.xlsx')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Processing error: ' . $e->getMessage());
        }
    }

    private static function parseTime(?string $t): ?float
    {
        $t = trim($t ?? '');
        if ($t === '') return null;
        $parts = explode(':', $t);
        if (count($parts) < 2) {
            // Might be a decimal or just hour
            $val = (float)str_replace(',', '.', $t);
            return $val > 0 ? $val : null;
        }
        return (int)$parts[0] + (int)$parts[1] / 60.0;
    }

    private static function hoursInWindow(float $shiftIn, float $shiftOut, float $winStart, float $winEnd): float
    {
        $start = max($shiftIn, $winStart);
        $end   = min($shiftOut, $winEnd);
        return max(0, $end - $start);
    }

    private static function parseUploadedFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $rows = [];
        $header = [];
        foreach ($sheetData as $rowIndex => $rowData) {
            if ($rowIndex === 1) {
                // Remove BOM if present in columns
                foreach ($rowData as $colIdx => $colVal) {
                    if ($colVal !== null) {
                        $colVal = preg_replace('/^\xEF\xBB\xBF/', '', $colVal);
                        $header[$colIdx] = trim((string)$colVal);
                    }
                }
                continue;
            }

            $row = [];
            $isEmptyRow = true;
            foreach ($header as $colIdx => $colName) {
                if ($colName === '') continue;
                $val = $rowData[$colIdx] ?? '';
                $row[$colName] = trim((string)$val);
                if ($val !== '' && $val !== null) {
                    $isEmptyRow = false;
                }
            }
            if (!$isEmptyRow) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private static function getDayOfWeek(string $date): string
    {
        return date('D', strtotime($date));
    }

    private static function getPublicHolidayHours(string $date): float
    {
        $d = date('m-d', strtotime($date));
        if ($d === '12-24' || $d === '12-31') {
            return 7.0; // Special half-day holidays
        }
        return self::FULL_DAY_HOURS;
    }

    private static function processSteg1(array $csvRows): array
    {
        $steg1 = [];

        foreach ($csvRows as $row) {
            $empId     = $row['Employee ID'] ?? '';
            $firstName = $row['First Name'] ?? '';
            $lastName  = $row['Last Name'] ?? '';
            $date      = $row['Date'] ?? '';
            $inTime    = self::parseTime($row['In'] ?? '');
            $outTime   = self::parseTime($row['Out'] ?? '');
            $csvHoursStr = str_replace(',', '.', $row['Hours'] ?? '0');
            $csvHours  = (float)$csvHoursStr;
            $position  = $row['Position'] ?? '';
            $timeOff   = $row['Time Off Type'] ?? '';
            $status    = $row['Status'] ?? '';
            $day       = self::getDayOfWeek($date);
            $isSaturday = ($day === 'Sat');

            $isEkstraTimer = (stripos($position, 'Ekstra Timer') !== false);
            $isAbsence     = ($timeOff !== '');

            $workedHours = 0.0;
            if ($inTime !== null && $outTime !== null) {
                if ($outTime < $inTime) {
                    $outTime += 24.0;
                }
                $workedHours = $outTime - $inTime;
            } else {
                $workedHours = $csvHours;
            }

            $ekstraTimer = 0.0;
            if ($isEkstraTimer) {
                $ekstraTimer = round($workedHours, 2);
            }

            $paidDayOff   = 0.0;
            $egenmelding  = 0.0;
            $sykemelding  = 0.0;
            $ferie        = 0.0;
            $unpaidTimeOff = 0.0;

            $lunch = 0.0;
            $adjustedHours = 0.0;

            if ($isAbsence) {
                $timeOffLower = strtolower($timeOff);
                if (strpos($timeOffLower, 'paid day off') !== false || strpos($timeOffLower, 'public holiday') !== false) {
                    $paidDayOff = self::getPublicHolidayHours($date);
                } elseif (strpos($timeOffLower, 'egenmelding') !== false) {
                    $egenmelding = $csvHours;
                } elseif (strpos($timeOffLower, 'sykemelding') !== false) {
                    $sykemelding = $csvHours;
                } elseif (strpos($timeOffLower, 'ferie') !== false || strpos($timeOffLower, 'vacation') !== false) {
                    $ferie = self::FULL_DAY_HOURS;
                } elseif (strpos($timeOffLower, 'unpaid') !== false) {
                    $unpaidTimeOff = 1;
                }
                $adjustedHours = 0.0;
            } elseif ($isEkstraTimer) {
                $adjustedHours = 0.0;
            } else {
                if ($workedHours > self::LUNCH_THRESHOLD) {
                    $lunch = self::LUNCH_DEDUCTION;
                }
                $adjustedHours = $workedHours - $lunch;
            }

            $overtime = 0.0;
            if (!$isAbsence && (!$isEkstraTimer)) {
                $ot = $adjustedHours - self::OVERTIME_THRESHOLD;
                if ($ot >= self::OVERTIME_MIN) {
                    $overtime = $ot;
                }
            }

            $kveldstillegg = 0.0;
            if (!$isAbsence && !$isSaturday && $inTime !== null && $outTime !== null) {
                $kveldstillegg = self::hoursInWindow($inTime, $outTime, self::EVENING_START, 24.0);
            }

            $lordagTillegg1315 = 0.0;
            if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
                $lordagTillegg1315 = self::hoursInWindow($inTime, $outTime, self::SAT_TIER1_START, self::SAT_TIER1_END);
            }

            $lordagTilleggEtter15 = 0.0;
            if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
                $lordagTilleggEtter15 = self::hoursInWindow($inTime, $outTime, self::SAT_TIER2_START, 24.0);
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
                'Total Adjusted Hours'     => round($adjustedHours, 2),
                'Regulære timer'           => round($adjustedHours - $overtime, 2),
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

    private static function processSteg2(array $steg1): array
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
            
            $emp['Ordinære timer']              += ($row['Regulære timer'] ?? ($row['Total Adjusted Hours'] - $row['Overtime']));
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

        foreach ($employees as &$emp) {
            $emp['Betalte arbeidstimer'] = $emp['Ordinære timer'] + $emp['Ekstra timer'] + $emp['Paid day off / Public holiday'];
            $emp['Totalt arbeidstid inkl. fravær'] = $emp['Betalte arbeidstimer'] + $emp['Overtid'] + $emp['Egenmelding'] + $emp['Sykemelding'] + $emp['Ferie / Vacation'];

            foreach ($emp as $k => &$v) {
                if (is_float($v)) {
                    $v = round($v, 2);
                }
            }
            unset($v);
        }
        unset($emp);

        usort($employees, function ($a, $b) {
            return $a['Employee ID'] <=> $b['Employee ID'];
        });

        return $employees;
    }

    private static function createXlsx(array $data, string $sheetName): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        if (empty($data)) return $spreadsheet;

        $headers = array_keys($data[0]);
        $colCount = count($headers);
        foreach ($headers as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue($colLetter . '1', $header);
        }

        $lastColLetter = Coordinate::stringFromColumnIndex($colCount);
        $sheet->getStyle('A1:' . $lastColLetter . '1')->getFont()->setBold(true);

        foreach ($data as $rowIdx => $row) {
            foreach (array_values($row) as $col => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue($colLetter . ($rowIdx + 2), $value);
            }
        }

        foreach ($headers as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
