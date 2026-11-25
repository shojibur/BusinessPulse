<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Services\Imports\CsvImportReader;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    public function __invoke(CsvImportReader $reader): StreamedResponse
    {
        Gate::authorize('create', Import::class);

        return response()->streamDownload(
            function () use ($reader): void {
                echo $reader->templateHeader()."\n";
            },
            'sales-import-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
