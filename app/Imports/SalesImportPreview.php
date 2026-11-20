<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Renders the uploaded Sales workbook verbatim for the import preview screen.
 *
 * The source workbook has a two-row header: row 1 carries the group labels
 * ("Other Income", "Mode of Payment") that Excel draws as merged cells, and
 * row 2 carries the individual column names under those groups. Reading only
 * row 1 leaves every grouped column blank, which is why the preview used to
 * show "Col 15", "Col 16" and so on.
 *
 * So both rows are read and combined per column:
 *   "Date"                          -> only row 1 has a label
 *   "Laundry Bag & Laundry Services" -> only row 2 has a label (group is merged)
 *   "Mode of Payment / Cash"        -> both rows have a label
 *
 * Nothing is mapped, validated or persisted here — this is a faithful copy
 * for the user to eyeball before any import runs.
 */
class SalesImportPreview implements ToCollection
{
    public array $headers = [];
    public array $dataRows = [];
    public int $totalRows = 0;
    public bool $truncated = false;

    private const MAX_PREVIEW_ROWS = 200;
    private const MAX_PREVIEW_COLS = 50;

    /** Header keywords that identify a column as holding a date. */
    private const DATE_HEADER_HINTS = [
        'date', 'arrival', 'departure', 'check-in', 'checkin', 'check-out', 'checkout',
    ];

    /**
     * Excel's day-zero is 1899-12-30. Anything between roughly 1954 and 2064
     * is a plausible serial; outside that, the number is a real amount.
     */
    private const SERIAL_MIN = 20000;
    private const SERIAL_MAX = 60000;

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $allRows = $rows->values()->all();
        $rowCount = count($allRows);

        if ($rowCount === 0) {
            return;
        }

        $headerRowCount = $this->countHeaderRows($allRows);
        $this->headers = $this->buildHeaders($allRows, $headerRowCount);

        // Column indexes whose header names a date, used to decide which bare
        // numbers are Excel date serials rather than peso amounts.
        $dateColumns = [];
        foreach ($this->headers as $index => $header) {
            if ($header !== null && $this->headerIsDateLike($header)) {
                $dateColumns[$index] = true;
            }
        }

        $limit = min($rowCount, $headerRowCount + self::MAX_PREVIEW_ROWS);

        for ($i = $headerRowCount; $i < $limit; $i++) {
            $row = $this->padRow($allRows[$i]);
            $cells = [];

            for ($c = 0; $c < self::MAX_PREVIEW_COLS; $c++) {
                $cells[] = $this->cellToString($row[$c] ?? null, isset($dateColumns[$c]));
            }

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $this->dataRows[] = $cells;
        }

        $this->totalRows = count($this->dataRows);
        $this->truncated = $rowCount > $headerRowCount + self::MAX_PREVIEW_ROWS;
    }

    /**
     * How many leading rows are header rows. The workbook ships one, but a
     * title or note block above it should not be mistaken for data.
     *
     * @param  array<int, Collection<int, mixed>>  $rows
     */
    private function countHeaderRows(array $rows): int
    {
        $keywords = [
            'date', 'reference', 'guest', 'business source', 'discount', 'vatable',
            'withholding', 'other income', 'mode of payment', 'arrival', 'departure',
            'room rate', 'no show', 'room upgrade', 'extra bed',
        ];

        // All hit-counts first so the second pass can see the look-ahead.
        $hits = [];

        foreach (array_slice($rows, 0, 5) as $index => $row) {
            $count = 0;

            foreach ($row as $cell) {
                $value = strtolower(trim((string) $cell));

                if ($value === '') {
                    continue;
                }

                foreach ($keywords as $keyword) {
                    if (str_contains($value, $keyword)) {
                        $count++;
                        break;
                    }
                }
            }

            $hits[$index] = $count;
        }

        // Walk forward: once a header row is seen, a next row that is
        // non-empty but has fewer than 3 hits is the other half of a
        // two-row merged header (row 1 carries the groups, row 2 carries
        // the members). Absorb it instead of calling it data.
        $span = 0;
        $found = false;

        foreach ($hits as $index => $hitCount) {
            if ($hitCount >= 3) {
                $span = $index + 1;
                $found = true;
                continue;
            }

            if (! $found) {
                continue;
            }

            // A header row immediately followed by a non-empty, non-header
            // row is the second half of a merged header. Absorb it.
            if ($index === $span && ! $this->isBlankRow($this->padRow($rows[$index]))) {
                $span = $index + 1;
            }

            // Once a gap or a true data row appears, stop.
            break;
        }

        if ($span > 0) {
            return $span;
        }

        // No recognisable header: treat a first row that is entirely text as one.
        $first = $rows[0];
        $hasText = false;

        foreach ($first as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                $hasText = true;
                break;
            }
        }

        return $hasText ? 1 : 0;
    }

    /**
     * Combine the header rows into one label per column.
     *
     * @param  array<int, Collection<int, mixed>>  $rows
     * @return list<string|null>
     */
    private function buildHeaders(array $rows, int $headerRowCount): array
    {
        $labels = [];

        for ($c = 0; $c < self::MAX_PREVIEW_COLS; $c++) {
            $parts = [];

            for ($r = 0; $r < $headerRowCount; $r++) {
                $value = $this->rawToString($rows[$r][$c] ?? null);

                if ($value !== null && $value !== '' && ! in_array($value, $parts, true)) {
                    $parts[] = $value;
                }
            }

            $labels[$c] = $parts === [] ? null : implode(' / ', $parts);
        }

        return $labels;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }

    private function padRow(Collection $row): array
    {
        $cells = $row->values()->all();

        // Merge-aware readers can hand back a row shorter than the widest
        // sheet; stretch it so every row lines up with the header.
        while (count($cells) < self::MAX_PREVIEW_COLS) {
            $cells[] = null;
        }

        return $cells;
    }

    private function headerIsDateLike(string $header): bool
    {
        $value = strtolower($header);

        foreach (self::DATE_HEADER_HINTS as $hint) {
            if (str_contains($value, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function cellToString(mixed $value, bool $isDateColumn): ?string
    {
        $text = $this->rawToString($value);

        if ($text === null || $text === '') {
            return null;
        }

        // Only columns whose header names a date get the serial treatment, so
        // a large room rate is never mistaken for a calendar date.
        if ($isDateColumn && $value !== null && ! $value instanceof \DateTimeInterface) {
            $numeric = is_numeric($value) ? (float) $value : null;

            if ($numeric !== null && $numeric >= self::SERIAL_MIN && $numeric <= self::SERIAL_MAX) {
                try {
                    return ExcelDate::excelToDateTimeObject($numeric)
                        ->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    // Not a serial after all; fall through to the raw text.
                }
            }
        }

        return $text;
    }

    private function rawToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // A whole-day date should not render a pointless midnight time.
            return $value->format('H:i:s') === '00:00:00'
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    /**
     * @return array{
     *     fileName: string,
     *     headers: list<string|null>,
     *     rows: list<list<string|null>>,
     *     totalRows: int,
     *     truncated: bool
     * }
     */
    public function toArray(string $fileName): array
    {
        return [
            'fileName' => $fileName,
            'headers' => $this->headers,
            'rows' => $this->dataRows,
            'totalRows' => $this->totalRows,
            'truncated' => $this->truncated,
        ];
    }
}
