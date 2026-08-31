<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimesheetDownloadRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimesheetController extends Controller
{
    const LUNCH_THRESHOLD = 5.5;
    const LUNCH_DEDUCTION = 0.5;
    const OVERTIME_THRESHOLD = 7.5;
    const OVERTIME_MIN = 0.1667;
    const FULL_DAY_HOURS = 7.5;
    const EVENING_START = 18.0;
    const SAT_TIER1_START = 13.0;
    const SAT_TIER1_END = 15.0;
    const SAT_TIER2_START = 15.0;

    public function index()
    {
        return view('timesheet.index');
    }

    public function download(TimesheetDownloadRequest $request)
    {
        $file = $request->file('csv_file');

        try {
            $csvRows = $this->parseCsv($file->getRealPath());
            if (empty($csvRows)) {
                return back()->with('error', 'No data rows found in CSV.');
            }

            $steg1 = $this->processSteg1($csvRows);
            $steg2 = $this->processSteg2($steg1);

            $spreadsheet = $this->createXlsx($steg1, 'STEG 1');

            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('STEG 2');
            if (!empty($steg2)) {
                $headers2 = array_keys($steg2[0]);
                foreach ($steg2 as $rowIdx => $row) {
                    foreach (array_values($row) as $col => $value) {
                        $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                        $sheet2->getCell($colLetter . ($rowIdx + 1))->setValue($value);
                    }
                }
                foreach ($headers2 as $col => $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet2->getColumnDimension($colLetter)->setAutoSize(true);
                }
            }

            $spreadsheet->setActiveSheetIndex(0);

            return new StreamedResponse(function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="lonnsrapport.xlsx"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Processing error: ' . $e->getMessage());
        }
    }

    private function parseTime(?string $t): ?float
    {
        $t = trim($t ?? '');
        if ($t === '') return null;
        $parts = explode(':', $t);
        if (count($parts) !== 2) return null;
        return (int)$parts[0] + (int)$parts[1] / 60.0;
    }

    private function hoursInWindow(float $shiftIn, float $shiftOut, float $winStart, float $winEnd): float
    {
        $start = max($shiftIn, $winStart);
        $end   = min($shiftOut, $winEnd);
        return max(0, $end - $start);
    }

    private function parseCsv(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle);
        if (!$header) return [];
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

    private function getDayOfWeek(string $date): string
    {
        return date('D', strtotime($date));
    }

    private function processSteg1(array $csvRows): array
    {
        $steg1 = [];
        foreach ($csvRows as $row) {
            $empId     = $row['Employee ID'] ?? '';
            $firstName = $row['First Name'] ?? '';
            $lastName  = $row['Last Name'] ?? '';
            $date      = $row['Date'] ?? '';
            $inTime    = $this->parseTime($row['In'] ?? '');
            $outTime   = $this->parseTime($row['Out'] ?? '');
            $csvHours  = (float)($row['Hours'] ?? 0);
            $position  = $row['Position'] ?? '';
            $timeOff   = $row['Time Off Type'] ?? '';
            $status    = $row['Status'] ?? '';
            $day       = $this->getDayOfWeek($date);
            $isSaturday = ($day === 'Sat');

            $isEkstraTimer = (stripos($position, 'Ekstra Timer') !== false);
            $isAbsence     = ($timeOff !== '');

            $workedHours = 0.0;
            if ($inTime !== null && $outTime !== null) {
                $workedHours = $outTime - $inTime;
                if ($workedHours < 0) $workedHours += 24;
            } else {
                $workedHours = $csvHours;
            }

            $ekstraTimer = $isEkstraTimer ? round($workedHours, 2) : 0.0;
            $paidDayOff = $egenmelding = $sykemelding = $ferie = $unpaidTimeOff = 0.0;
            $lunch = 0.0;
            $adjustedHours = 0.0;

            if ($isAbsence) {
                $timeOffLower = strtolower($timeOff);
                if (strpos($timeOffLower, 'paid day off') !== false || strpos($timeOffLower, 'public holiday') !== false) {
                    $paidDayOff = self::FULL_DAY_HOURS;
                } elseif (strpos($timeOffLower, 'egenmelding') !== false) {
                    $egenmelding = $csvHours;
                } elseif (strpos($timeOffLower, 'sykemelding') !== false) {
                    $sykemelding = $csvHours;
                } elseif (strpos($timeOffLower, 'ferie') !== false || strpos($timeOffLower, 'vacation') !== false) {
                    $ferie = self::FULL_DAY_HOURS;
                } elseif (strpos($timeOffLower, 'unpaid') !== false) {
                    $unpaidTimeOff = 1;
                }
            } elseif ($isEkstraTimer) {
                $adjustedHours = 0.0;
            } else {
                if ($workedHours >= self::LUNCH_THRESHOLD) {
                    $lunch = self::LUNCH_DEDUCTION;
                }
                $adjustedHours = $workedHours - $lunch;
            }

            $overtime = 0.0;
            if (!$isAbsence && !$isEkstraTimer) {
                $ot = $adjustedHours - self::OVERTIME_THRESHOLD;
                if ($ot >= self::OVERTIME_MIN) $overtime = $ot;
            }

            $kveldstillegg = 0.0;
            if (!$isAbsence && !$isSaturday && $inTime !== null && $outTime !== null) {
                $kveldstillegg = $this->hoursInWindow($inTime, $outTime, self::EVENING_START, 24.0);
            }

            $lordagTillegg1315 = 0.0;
            if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
                $lordagTillegg1315 = $this->hoursInWindow($inTime, $outTime, self::SAT_TIER1_START, self::SAT_TIER1_END);
            }

            $lordagTilleggEtter15 = 0.0;
            if (!$isAbsence && $isSaturday && $inTime !== null && $outTime !== null) {
                $lordagTilleggEtter15 = $this->hoursInWindow($inTime, $outTime, self::SAT_TIER2_START, 24.0);
            }

            $steg1[] = [
                'AnsatteID'                => $empId,
                'Fornavn'                  => $firstName,
                'Etternavn'                => $lastName,
                'Dato'                     => $date,
                'Stilling'                 => $position,
                'Fridag'                   => $timeOff,
                'Inn'                      => $row['In'] ?? '',
                'Ut'                       => $row['Out'] ?? '',
                'Hours'                    => round($csvHours, 2),
                'Worked Hours'             => round($workedHours, 2),
                'Ekstra Timer'             => round($ekstraTimer, 2),
                'Total Just. Timer'        => round($adjustedHours, 2),
                'Overtid'                  => round($overtime, 2),
                'Betalt Fridag'            => round($paidDayOff, 2),
                'Egenmelding'              => round($egenmelding, 2),
                'Sykemelding'              => round($sykemelding, 2),
                'Ferie/Vacation'           => round($ferie, 2),
                'Ubet. Fridag'             => round($unpaidTimeOff, 2),
                'Kveldstillegg'            => round($kveldstillegg, 2),
                'Lørd. 13-15'              => round($lordagTillegg1315, 2),
                'Lørd. e 15'               => round($lordagTilleggEtter15, 2),
                'Status'                   => $status,
            ];
        }
        return $steg1;
    }

    private function processSteg2(array $steg1): array
    {
        $employees = [];
        foreach ($steg1 as $row) {
            $key = $row['AnsatteID'];
            if (!isset($employees[$key])) {
                $employees[$key] = [
                    'AnsatteID'                => $row['AnsatteID'],
                    'Fornavn'                  => $row['Fornavn'],
                    'Etternavn'                => $row['Etternavn'],
                    'Reg. Timer'               => 0.0,
                    'Ekstra Timer'             => 0.0,
                    'Betalt Fridag'            => 0.0,
                    'Total Just. Timer'        => 0.0,
                    'Overtid'                  => 0.0,
                    'Egenmelding'              => 0.0,
                    'Sykemelding'              => 0.0,
                    'Ferie/Vacation'           => 0.0,
                    'Ubet. Fridag'             => 0.0,
                    'Totalt arbeidstid inkl. fravær' => 0.0,
                    'Kveldstillegg'            => 0.0,
                    'Lørd. 13-15'              => 0.0,
                    'Lørd. e 15'               => 0.0,
                ];
            }
            $emp = &$employees[$key];
            $emp['Reg. Timer']                  += $row['Total Just. Timer'];
            $emp['Ekstra Timer']                += $row['Ekstra Timer'];
            $emp['Betalt Fridag']               += $row['Betalt Fridag'];
            $emp['Overtid']                     += $row['Overtid'];
            $emp['Egenmelding']                 += $row['Egenmelding'];
            $emp['Sykemelding']                 += $row['Sykemelding'];
            $emp['Ferie/Vacation']              += $row['Ferie/Vacation'];
            $emp['Ubet. Fridag']                += $row['Ubet. Fridag'];
            $emp['Kveldstillegg']               += $row['Kveldstillegg'];
            $emp['Lørd. 13-15']                 += $row['Lørd. 13-15'];
            $emp['Lørd. e 15']                  += $row['Lørd. e 15'];
            unset($emp);
        }

        foreach ($employees as &$emp) {
            $emp['Total Just. Timer'] = $emp['Reg. Timer'] + $emp['Ekstra Timer'] + $emp['Betalt Fridag'];
            $emp['Totalt arbeidstid inkl. fravær'] = $emp['Total Just. Timer'] + $emp['Egenmelding'] + $emp['Sykemelding'] + $emp['Ferie/Vacation'];
            foreach ($emp as $k => &$v) {
                if (is_float($v)) $v = round($v, 2);
            }
            unset($v);
        }
        unset($emp);
        usort($employees, fn($a, $b) => $a['AnsatteID'] <=> $b['AnsatteID']);
        return $employees;
    }

    private function createXlsx(array $data, string $sheetName): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        if (empty($data)) return $spreadsheet;

        foreach ($data as $rowIdx => $row) {
            foreach (array_values($row) as $col => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->getCell($colLetter . ($rowIdx + 1))->setValue($value);
            }
        }
        $headers = array_keys($data[0]);
        foreach ($headers as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        return $spreadsheet;
    }
}
