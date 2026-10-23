<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmailLeadsRequest;
use App\Models\EmailLead;
use App\Services\LeadImporter;
use App\Services\LicenseAuthenticator;
use Illuminate\Http\JsonResponse;

class EmailLeadController extends Controller
{
    /**
     * A bot pushes leads it collected. They are tagged with its account, customer and the current time.
     */
    public function store(StoreEmailLeadsRequest $request, LicenseAuthenticator $authenticator, LeadImporter $importer): JsonResponse
    {
        $license = $authenticator->attempt(
            $request->validated('product'),
            $request->validated('username'),
            $request->validated('password'),
            $request->ip(),
        );

        $rows = collect($request->validated('leads'))->values()->mapWithKeys(fn (array $lead, int $index) => [$index + 1 => $lead])->all();

        $report = $importer->importRows($rows, EmailLead::tagsFor($license), 'api');

        return response()->json([
            'valid' => true,
            'received' => $report['total'],
            'created' => $report['imported'],
            'duplicates' => $report['duplicates'],
            'invalid' => $report['invalid'],
            'errors' => $report['errors'],
        ]);
    }
}
