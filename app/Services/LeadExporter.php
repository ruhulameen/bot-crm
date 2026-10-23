<?php

namespace App\Services;

use App\Models\EmailLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams leads (or the example import file) as CSV or XLSX. Every cell is written as text: spreadsheet
 * programs would otherwise run values such as "=HYPERLINK(...)" typed into a lead as formulas.
 */
class LeadExporter
{
    private const EXAMPLE_ROWS = [
        ['+15550100001', 'Passw0rd1', 'bell', '5550100000-5550100099'],
        ['+15550100002', 'S3cret-two', 'insert', '5550100000-5550100099'],
        ['+15550100003', 'Hello2026', 'otp', '5550100100-5550100199'],
        ['+15550100004', 'Blue-sky-9', 'selection', '5550100100-5550100199'],
        ['+15550100005', 'Tap-c0de-5', 'tap_code', '5550100200-5550100299'],
    ];

    /**
     * @param  Builder<EmailLead>  $leads
     */
    public function download(string $format, Builder $leads, string $filename): StreamedResponse
    {
        $rows = function () use ($leads) {
            yield ['number', 'password', 'type', 'range', 'username', 'customer', 'source', 'date'];

            $query = $leads->clone()
                ->select('email_leads.*')
                ->addSelect(['customer_name' => User::select('name')->whereColumn('users.id', 'email_leads.user_id')->limit(1)]);

            foreach ($query->lazyById(1000) as $lead) {
                yield [
                    $lead->number,
                    (string) $lead->password,
                    $lead->type->value,
                    $lead->number_range,
                    (string) $lead->username,
                    (string) $lead->getAttribute('customer_name'),
                    $lead->source,
                    $lead->created_at->format('Y-m-d H:i:s'),
                ];
            }
        };

        return $this->stream($format, $filename, $rows());
    }

    /**
     * The example file the admin downloads to see the import format.
     */
    public function example(string $format): StreamedResponse
    {
        return $this->stream($format, "email-leads-example.{$format}", (function () {
            yield ['number', 'password', 'type', 'range'];
            yield from self::EXAMPLE_ROWS;
        })());
    }

    /**
     * @param  iterable<int, list<string>>  $rows
     */
    private function stream(string $format, string $filename, iterable $rows): StreamedResponse
    {
        $isXlsx = $format === 'xlsx';

        return response()->streamDownload(function () use ($isXlsx, $rows) {
            $writer = $isXlsx ? new XlsxWriter : new CsvWriter;
            $writer->openToFile('php://output');

            foreach ($rows as $row) {
                $writer->addRow($this->row($row, $isXlsx));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => $isXlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $values
     */
    private function row(array $values, bool $isXlsx): Row
    {
        return new Row(array_map(
            fn (string $value) => new StringCell($isXlsx ? $value : $this->csvSafe($value), null),
            $values,
        ));
    }

    /**
     * CSV has no cell types, so values a spreadsheet would read as formulas get an apostrophe in front
     * (the importer removes it). Plain phone numbers such as "+919980966156" are left alone.
     */
    private function csvSafe(string $value): string
    {
        if (preg_match('/^[+-]?[0-9][0-9 ()\-]*$/', $value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
