<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailLeadExportRequest;
use App\Models\EmailLead;
use App\Services\LeadExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLeadExportController extends Controller
{
    /**
     * Exports the leads picked in the export panel: a date or date range and/or one or more types.
     * With nothing picked it exports every lead; the list's own search and filters never apply.
     */
    public function __invoke(EmailLeadExportRequest $request, LeadExporter $exporter): StreamedResponse
    {
        $format = $request->validated('format') ?? 'xlsx';

        $leads = EmailLead::filter([
            'from' => $request->validated('date_from'),
            'to' => $request->validated('date_to'),
            'types' => $request->validated('types'),
        ]);

        return $exporter->download($format, $leads, $request->filename($format));
    }
}
