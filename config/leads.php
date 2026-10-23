<?php

return [

    /*
    | Limits for importing leads from a spreadsheet and for bots pushing leads through the API.
    */
    'import_max_rows' => (int) env('LEADS_IMPORT_MAX_ROWS', 50000),
    'import_max_kilobytes' => (int) env('LEADS_IMPORT_MAX_KB', 10240),
    'api_max_per_request' => (int) env('LEADS_API_MAX_PER_REQUEST', 500),

];
