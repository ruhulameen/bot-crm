<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLeadExampleController extends Controller
{
    /**
     * The example file for the import format.
     */
    public function __invoke(Request $request, LeadExporter $exporter): StreamedResponse
    {
        return $exporter->example($request->validate(['format' => ['nullable', 'in:csv,xlsx']])['format'] ?? 'xlsx');
    }
}
