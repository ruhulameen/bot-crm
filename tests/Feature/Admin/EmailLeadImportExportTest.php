<?php

use App\Enums\LeadType;
use App\Enums\UserRole;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

function importAdmin(): User
{
    return User::factory()->role(UserRole::Admin)->create();
}

function csvFile(string $content, string $name = 'leads.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * @param  list<list<string|int|float>>  $rows
 */
function xlsxFile(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'leads').'.xlsx';
    $writer = new XlsxWriter;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(new Row(array_map(fn ($v) => is_string($v) ? new StringCell($v, null) : new NumericCell($v, null), $row)));
    }

    $writer->close();

    return new UploadedFile($path, 'leads.xlsx', null, null, true);
}

/**
 * @return list<list<string>>
 */
function xlsxRows(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $content);
    $reader = new XlsxReader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map('strval', $row->toArray());
        }
    }

    $reader->close();

    return $rows;
}

/**
 * @return list<string> the numbers in an exported CSV, in file order
 */
function exportedNumbers(string $csv): array
{
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));

    return array_column(array_slice($rows, 1), 0);
}

test('imports a CSV file and tags every lead with the account, its customer, the source and the time', function () {
    $this->travelTo('2026-03-01 10:30:00');
    $customer = User::factory()->create();
    $license = License::factory()->for($customer)->create(['username' => 'bot-one']);

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,password,type,range\n+917001,pw-1,bell,R1\n+917002,pw-2,Tap Code,R2\n"),
        'license_id' => $license->id,
    ])->assertRedirect(route('admin.leads.email.index'))->assertSessionHas('status', '2 lead(s) imported.');

    $this->assertDatabaseHas('email_leads', [
        'number' => '+917001', 'type' => 'bell', 'number_range' => 'R1',
        'license_id' => $license->id, 'user_id' => $customer->id, 'username' => 'bot-one', 'source' => 'import', 'created_at' => '2026-03-01 10:30:00',
    ]);
    $this->assertDatabaseHas('email_leads', ['number' => '+917002', 'type' => 'tap_code', 'number_range' => 'R2', 'username' => 'bot-one']);
    expect(EmailLead::firstWhere('number', '+917001')->password)->toBe('pw-1');
});

test('imports an Excel file, reading numeric cells as plain numbers', function () {
    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => xlsxFile([['Number', 'Password', 'Type', 'Range'], [919980966156, 'pw-1', 'OTP code', 'R1']]),
    ])->assertSessionHas('status', '1 lead(s) imported.');

    $this->assertDatabaseHas('email_leads', ['number' => '919980966156', 'type' => 'otp', 'number_range' => 'R1', 'source' => 'import']);
});

test('reads semicolon separated CSV files and alternative column names', function () {
    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("Phone;Pass;Category\n+917001;pw-1;sel\n"),
    ])->assertSessionHas('status', '1 lead(s) imported.');

    $this->assertDatabaseHas('email_leads', ['number' => '+917001', 'type' => 'selection']);
});

test('reads a comma separated file correctly even when passwords contain semicolons', function () {
    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,password,type,range\n+917001,p;a;s;s;w;o;rd,bell,R1\n+917002,x;y;z;1;2;3;4,otp,R1\n"),
    ])->assertSessionHas('status', '2 lead(s) imported.');

    expect(EmailLead::firstWhere('number', '+917001')->password)->toBe('p;a;s;s;w;o;rd');
});

test('takes the type and range only from the sheet, ignoring any posted from the form', function () {
    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,password,range\n+917001,pw-1,\n"),
        'default_type' => 'bell', 'default_range' => 'FROM-FORM',
    ])->assertSessionHas('import_report', fn ($report) => $report['imported'] === 0 && $report['invalid'] === 1
        && $report['errors'][0]['message'] === 'The type is missing (use bell, insert, otp, selection or tap_code).');

    expect(EmailLead::count())->toBe(0);
});

test('reports invalid rows by row number and skips duplicates, importing the valid rows', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917001']);

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,password,type,range\n+917001,a,bell,\n+917002,b,bell,\n+917002,c,bell,\n,d,bell,\n+917003,e,nonsense,\n+917004,f,,\n"),
    ])->assertSessionHas('import_report', [
        'total' => 6, 'imported' => 1, 'duplicates' => 2, 'invalid' => 3,
        'errors' => [
            ['row' => 5, 'message' => 'The number is missing.'],
            ['row' => 6, 'message' => 'The type "nonsense" is not valid (use bell, insert, otp, selection or tap_code).'],
            ['row' => 7, 'message' => 'The type is missing (use bell, insert, otp, selection or tap_code).'],
        ],
    ]);

    expect(EmailLead::pluck('number')->sort()->values()->all())->toBe(['+917001', '+917002']);
});

test('shows an import report with row errors on the list', function () {
    $report = ['total' => 2, 'imported' => 1, 'duplicates' => 0, 'invalid' => 1, 'errors' => [['row' => 3, 'message' => 'The number is missing.']]];

    $this->actingAs(importAdmin())->withSession(['import_report' => $report])->get(route('admin.leads.email.index'))
        ->assertOk()->assertSee('Import finished with notes')->assertSee('Row 3: The number is missing.');
});

test('does not let columns in the file set the tags', function () {
    $license = License::factory()->create(['username' => 'honest-bot']);
    $victim = License::factory()->create(['username' => 'victim']);

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,type,username,license_id,user_id,source\n+917001,bell,victim,{$victim->id},{$victim->user_id},api\n"),
        'license_id' => $license->id,
    ]);

    $this->assertDatabaseHas('email_leads', ['number' => '+917001', 'license_id' => $license->id, 'username' => 'honest-bot', 'source' => 'import']);
});

test('rejects files it cannot use with a clear message and imports nothing', function (?UploadedFile $file, string $message) {
    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), array_filter(['file' => $file]))
        ->assertSessionHasErrors(['file' => $message]);

    expect(EmailLead::count())->toBe(0);
})->with([
    'no file' => [null, 'The file field is required.'],
    'wrong extension' => [fn () => UploadedFile::fake()->create('leads.pdf', 10), 'Upload an .xlsx or .csv file.'],
    'no number column' => [fn () => csvFile("password,type\npw,bell\n"), 'The first row must contain column headers, at least "number". Download the example file for the exact format.'],
    'empty file' => [fn () => csvFile(''), 'The file is empty.'],
    'unreadable xlsx' => [fn () => UploadedFile::fake()->createWithContent('leads.xlsx', 'not a zip'), 'The file could not be read. Upload a valid .xlsx or .csv file (see the example file).'],
]);

test('rejects a file with more rows than the limit', function () {
    config(['leads.import_max_rows' => 2]);

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), [
        'file' => csvFile("number,type\n+917001,bell\n+917002,bell\n+917003,bell\n"),
    ])->assertSessionHasErrors(['file' => 'The file has more than 2 rows. Split it into smaller files.']);

    expect(EmailLead::count())->toBe(0);
});

test('exports every lead as CSV with the tags and the decrypted password, ignoring list filters', function () {
    $license = License::factory()->for(User::factory()->state(['name' => 'Jane Customer']))->create(['username' => 'bot-one']);
    EmailLead::factory()->ofType(LeadType::TapCode)->inRange('R1')->suppliedBy($license, 'import')->create(['number' => '+917001', 'password' => 'pw-1', 'created_at' => '2026-01-05 12:00:00']);
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917002', 'password' => null, 'created_at' => '2026-01-06 12:00:00']);

    $response = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'csv', 'type' => 'tap_code', 'q' => 'nothing-matches']))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('email-leads-')->toContain('.csv');
    expect(array_map('str_getcsv', explode("\n", trim(ltrim($response->streamedContent(), "\xEF\xBB\xBF")))))->toBe([
        ['number', 'password', 'type', 'range', 'username', 'customer', 'source', 'date'],
        ['+917001', 'pw-1', 'tap_code', 'R1', 'bot-one', 'Jane Customer', 'import', '2026-01-05 12:00:00'],
        ['+917002', '', 'bell', '', '', '', 'manual', '2026-01-06 12:00:00'],
    ]);
});

test('exports only the chosen types', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917001']);
    EmailLead::factory()->ofType(LeadType::Otp)->create(['number' => '+917002']);
    EmailLead::factory()->ofType(LeadType::Insert)->create(['number' => '+917003']);

    $csv = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'csv', 'types' => ['bell', 'otp']]))->assertOk()->streamedContent();

    expect(exportedNumbers($csv))->toBe(['+917001', '+917002']);
});

test('exports a single day, a date range or an open-ended range, inclusive of both ends', function (array $dates, array $expected) {
    foreach (['2026-03-01 00:00:00' => '+917001', '2026-03-02 23:59:59' => '+917002', '2026-03-03 12:00:00' => '+917003', '2026-03-05 08:00:00' => '+917004'] as $at => $number) {
        EmailLead::factory()->create(['number' => $number, 'created_at' => $at]);
    }

    $csv = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'csv', ...$dates]))->assertOk()->streamedContent();

    expect(exportedNumbers($csv))->toBe($expected);
})->with([
    'a single day' => [['date_from' => '2026-03-02', 'date_to' => '2026-03-02'], ['+917002']],
    'a date range' => [['date_from' => '2026-03-02', 'date_to' => '2026-03-03'], ['+917002', '+917003']],
    'from a date onwards' => [['date_from' => '2026-03-03'], ['+917003', '+917004']],
    'up to a date' => [['date_to' => '2026-03-02'], ['+917001', '+917002']],
    'no dates means all' => [[], ['+917001', '+917002', '+917003', '+917004']],
]);

test('combines the date range with the chosen types', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917001', 'created_at' => '2026-03-02 10:00:00']);
    EmailLead::factory()->ofType(LeadType::Otp)->create(['number' => '+917002', 'created_at' => '2026-03-02 11:00:00']);
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917003', 'created_at' => '2026-03-09 10:00:00']);

    $csv = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', [
        'format' => 'csv', 'types' => ['bell'], 'date_from' => '2026-03-01', 'date_to' => '2026-03-05',
    ]))->streamedContent();

    expect(exportedNumbers($csv))->toBe(['+917001']);
});

test('names the file after what it contains', function (array $options, string $filename) {
    $this->travelTo('2026-03-10 09:00:00');

    $this->actingAs(importAdmin())->get(route('admin.leads.email.export', $options))->assertDownload($filename);
})->with([
    'everything' => [['format' => 'csv'], 'email-leads-20260310-090000.csv'],
    'types and a range' => [['format' => 'xlsx', 'types' => ['bell', 'otp'], 'date_from' => '2026-03-01', 'date_to' => '2026-03-05'], 'email-leads-bell-otp-2026-03-01-to-2026-03-05-20260310-090000.xlsx'],
    'a single day' => [['format' => 'csv', 'date_from' => '2026-03-02', 'date_to' => '2026-03-02'], 'email-leads-on-2026-03-02-20260310-090000.csv'],
    'from a date' => [['format' => 'csv', 'date_from' => '2026-03-02'], 'email-leads-from-2026-03-02-20260310-090000.csv'],
    'until a date' => [['format' => 'csv', 'date_to' => '2026-03-02'], 'email-leads-until-2026-03-02-20260310-090000.csv'],
]);

test('rejects invalid export options with a message and exports nothing', function (array $options, string $key, string $message) {
    $this->actingAs(importAdmin())->get(route('admin.leads.email.export', $options))->assertSessionHasErrors([$key => $message]);
})->with([
    'end before start' => [['date_from' => '2026-03-05', 'date_to' => '2026-03-01'], 'date_to', 'The end date must be on or after the start date.'],
    'unreadable date' => [['date_from' => '05/03/2026'], 'date_from', 'The date from field must match the format Y-m-d.'],
    'unknown type' => [['types' => ['bell', 'nonsense']], 'types.1', 'Pick a valid lead type.'],
]);

test('shows the export panel with date, type and format options on the list', function () {
    $this->actingAs(importAdmin())->get(route('admin.leads.email.index'))->assertOk()
        ->assertSee('name="date_from"', false)->assertSee('name="date_to"', false)
        ->assertSee('name="types[]" value="tap_code"', false)
        ->assertSee('name="format" value="xlsx"', false)->assertSee('name="format" value="csv"', false);
});

test('exports an Excel file', function () {
    EmailLead::factory()->ofType(LeadType::Otp)->inRange('R1')->create(['number' => '+917001', 'password' => 'pw-1', 'created_at' => '2026-01-05 12:00:00']);

    $response = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'xlsx']))->assertOk();

    expect(xlsxRows($response->streamedContent()))->toBe([
        ['number', 'password', 'type', 'range', 'username', 'customer', 'source', 'date'],
        ['+917001', 'pw-1', 'otp', 'R1', '', '', 'manual', '2026-01-05 12:00:00'],
    ]);
});

test('exports to Excel by default and rejects unknown formats', function () {
    $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'pdf']))->assertSessionHasErrors('format');
    $this->actingAs(importAdmin())->get(route('admin.leads.email.export'))->assertOk()
        ->assertDownload();
});

test('neutralises spreadsheet formulas in exported leads', function () {
    EmailLead::factory()->create(['number' => '+917001', 'password' => '=HYPERLINK("http://evil","x")']);

    $csv = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'csv']))->streamedContent();
    $xlsx = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'xlsx']))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");

    $path = tempnam(sys_get_temp_dir(), 'formula').'.xlsx';
    file_put_contents($path, $xlsx);
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->not->toContain('<f>')->toContain('HYPERLINK');
});

test('an exported file imports back into the same leads', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->inRange('R1')->create(['number' => '+917001', 'password' => '@starts-with-at']);
    EmailLead::factory()->ofType(LeadType::Otp)->create(['number' => '+917002', 'password' => '=starts-with-equals']);
    EmailLead::factory()->ofType(LeadType::Insert)->create(['number' => '+917003', 'password' => null]);
    $exported = $this->actingAs(importAdmin())->get(route('admin.leads.email.export', ['format' => 'csv']))->streamedContent();
    EmailLead::query()->delete();

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), ['file' => csvFile($exported)])
        ->assertSessionHas('status', '3 lead(s) imported.');

    expect(EmailLead::orderBy('number')->get()->map(fn ($l) => [$l->number, $l->password, $l->type->value, $l->number_range])->all())->toBe([
        ['+917001', '@starts-with-at', 'bell', 'R1'],
        ['+917002', '=starts-with-equals', 'otp', ''],
        ['+917003', null, 'insert', ''],
    ]);
});

test('the example file imports cleanly', function (string $format) {
    $response = $this->actingAs(importAdmin())->get(route('admin.leads.email.example', ['format' => $format]))->assertOk();
    $content = $response->streamedContent();
    $file = $format === 'xlsx' ? (function () use ($content) {
        $path = tempnam(sys_get_temp_dir(), 'example').'.xlsx';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'example.xlsx', null, null, true);
    })() : csvFile($content, 'example.csv');

    $this->actingAs(importAdmin())->post(route('admin.leads.email.import.store'), ['file' => $file])
        ->assertSessionHas('import_report', ['total' => 5, 'imported' => 5, 'duplicates' => 0, 'invalid' => 0, 'errors' => []]);

    expect(EmailLead::pluck('type')->map->value->sort()->values()->all())->toBe(['bell', 'insert', 'otp', 'selection', 'tap_code']);
})->with(['xlsx', 'csv']);
