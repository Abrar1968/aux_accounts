<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ImportCommitRequest;
use App\Http\Requests\Crm\ImportPreviewRequest;
use App\Services\Crm\LeadImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(private readonly LeadImportService $import) {}

    public function create(): Response
    {
        return Inertia::render('Crm/Import', ['columns' => LeadImportService::COLUMNS, 'preview' => null]);
    }

    public function preview(ImportPreviewRequest $request): Response
    {
        return Inertia::render('Crm/Import', ['columns' => LeadImportService::COLUMNS, 'preview' => $this->import->preview($request->file('file'), $request->user()->id)]);
    }

    public function commit(ImportCommitRequest $request)
    {
        $codes = $this->import->commit($request->validated('token'), $request->user()->id, $request->validated('lines'));

        return redirect()->route('leads.index')->with('toast', ['type' => 'success', 'title' => 'Import complete', 'message' => count($codes).' lead(s) imported.']);
    }

    public function template(Request $request)
    {
        return response($this->import->template(), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="lead-import-template.csv"']);
    }
}
