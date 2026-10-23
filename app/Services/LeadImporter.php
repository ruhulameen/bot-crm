<?php

namespace App\Services;

use App\Enums\LeadType;
use App\Exceptions\InvalidLeadFile;
use App\Models\EmailLead;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Validates lead rows and stores them. Used for spreadsheet imports and for bots pushing leads through the API.
 * Rows that repeat an existing number + type + range are skipped, never overwritten.
 *
 * @phpstan-type Report array{total: int, imported: int, duplicates: int, invalid: int, errors: list<array{row: int, message: string}>}
 */
class LeadImporter
{
    private const BATCH = 500;

    private const MAX_REPORTED_ERRORS = 20;

    /** @var array<string, list<string>> Column => accepted header names (lowercase, letters and digits only). */
    private const HEADERS = [
        'number' => ['number', 'phone', 'phonenumber', 'mobile', 'msisdn'],
        'password' => ['password', 'pass', 'pwd'],
        'type' => ['type', 'category'],
        'range' => ['range', 'numberrange'],
    ];

    /**
     * @param  array{license_id: ?int, user_id: ?int, username: ?string}  $tags
     * @return Report
     *
     * @throws InvalidLeadFile
     */
    public function importFile(UploadedFile $file, array $tags): array
    {
        return $this->importRows($this->rowsFromFile($file), $tags, 'import');
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows  keyed by row number; each row has number, password, type, range
     * @param  array{license_id: ?int, user_id: ?int, username: ?string}  $tags
     * @return Report
     */
    public function importRows(iterable $rows, array $tags, string $source): array
    {
        $report = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'invalid' => 0, 'errors' => []];
        $batch = [];
        $now = now();

        foreach ($rows as $rowNumber => $row) {
            $report['total']++;
            $lead = $this->normalise($row);

            if (is_string($lead)) {
                $report['invalid']++;
                if (count($report['errors']) < self::MAX_REPORTED_ERRORS) {
                    $report['errors'][] = ['row' => (int) $rowNumber, 'message' => $lead];
                }

                continue;
            }

            $batch[] = [
                'number' => $lead['number'],
                'password' => $lead['password'] === null ? null : Crypt::encryptString($lead['password']),
                'type' => $lead['type']->value,
                'number_range' => $lead['range'],
                ...$tags,
                'source' => $source,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === self::BATCH) {
                $report['imported'] += EmailLead::insertOrIgnore($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $report['imported'] += EmailLead::insertOrIgnore($batch);
        }

        $report['duplicates'] = $report['total'] - $report['invalid'] - $report['imported'];

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{number: string, password: ?string, type: LeadType, range: string}|string a validated lead, or the reason it is invalid
     */
    private function normalise(array $row): array|string
    {
        $number = preg_replace('/\s+/', '', $this->text($row['number'] ?? null));
        $password = $this->text($row['password'] ?? null);
        $typeInput = $this->text($row['type'] ?? null);
        $range = $this->text($row['range'] ?? null);

        if ($number === '') {
            return 'The number is missing.';
        }

        if (mb_strlen($number) > 32) {
            return 'The number is longer than 32 characters.';
        }

        if (mb_strlen($password) > 255) {
            return 'The password is longer than 255 characters.';
        }

        if (mb_strlen($range) > 100) {
            return 'The range is longer than 100 characters.';
        }

        if ($typeInput === '') {
            return 'The type is missing (use bell, insert, otp, selection or tap_code).';
        }

        if (! ($type = LeadType::fromInput($typeInput))) {
            return "The type \"{$typeInput}\" is not valid (use bell, insert, otp, selection or tap_code).";
        }

        return [
            'number' => $number,
            'password' => $password === '' ? null : $password,
            'type' => $type,
            'range' => $range,
        ];
    }

    /**
     * Cell value as trimmed text. Undoes the apostrophe the exporter adds in front of CSV values that
     * could be read as a formula, and prints Excel's numeric cells without scientific notation.
     */
    private function text(mixed $value): string
    {
        if (is_float($value)) {
            $value = floor($value) === $value && abs($value) < 1e15 ? sprintf('%.0f', $value) : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        if (! is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);

        return preg_match("/^'[=+\-@\t\r]/", $text) ? substr($text, 1) : $text;
    }

    /**
     * @return Generator<int, array<string, mixed>> data rows keyed by their spreadsheet row number
     *
     * @throws InvalidLeadFile
     */
    private function rowsFromFile(UploadedFile $file): Generator
    {
        $reader = $this->readerFor($file);
        $columns = null;
        $count = 0;

        try {
            $reader->open($file->getRealPath());

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $cells = $row->toArray();

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    if ($columns === null) {
                        $columns = $this->mapColumns($cells);

                        continue;
                    }

                    if (++$count > config('leads.import_max_rows')) {
                        throw new InvalidLeadFile('The file has more than '.number_format(config('leads.import_max_rows')).' rows. Split it into smaller files.');
                    }

                    yield $rowNumber => array_map(fn (int $index) => $cells[$index] ?? null, $columns);
                }

                break;
            }
        } catch (InvalidLeadFile $e) {
            throw $e;
        } catch (Throwable) {
            throw new InvalidLeadFile('The file could not be read. Upload a valid .xlsx or .csv file (see the example file).');
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw new InvalidLeadFile('The file is empty.');
        }
    }

    private function readerFor(UploadedFile $file): ReaderInterface
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            return new XlsxReader;
        }

        return new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectDelimiter($file->getRealPath())));
    }

    /**
     * Looks at the header row only: it holds plain column names, while data rows may contain any
     * separator inside a password.
     */
    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $header = strtok(ltrim($sample, "\xEF\xBB\xBF\r\n"), "\r\n") ?: '';
        $counts = [',' => substr_count($header, ','), ';' => substr_count($header, ';'), "\t" => substr_count($header, "\t")];
        arsort($counts);

        return reset($counts) > 0 ? (string) array_key_first($counts) : ',';
    }

    /**
     * @param  array<int, mixed>  $headerCells
     * @return array<string, int> column name => cell index
     *
     * @throws InvalidLeadFile
     */
    private function mapColumns(array $headerCells): array
    {
        $columns = [];

        foreach ($headerCells as $index => $header) {
            $name = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));

            foreach (self::HEADERS as $column => $aliases) {
                if (in_array($name, $aliases, true) && ! isset($columns[$column])) {
                    $columns[$column] = $index;
                }
            }
        }

        if (! isset($columns['number'])) {
            throw new InvalidLeadFile('The first row must contain column headers, at least "number". Download the example file for the exact format.');
        }

        return $columns;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        return collect($cells)->every(fn ($cell) => $cell === null || (is_string($cell) && trim($cell) === ''));
    }
}
