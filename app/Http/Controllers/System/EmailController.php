<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ComposeEmailRequest;
use App\Models\User;
use App\Services\System\EmailComposerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Admin email composer: auto-generated professional emails from live records. */
class EmailController extends Controller
{
    public function __construct(private readonly EmailComposerService $emails) {}

    public function index(Request $request): Response
    {
        $template = (string) $request->query('template', '');

        return Inertia::render('System/Email', [
            'templates' => $this->emails->templates(),
            'history' => $this->emails->history($request->only('q')),
            'suggestions' => User::query()->where('is_active', true)->orderBy('name')->get(['name', 'email']),
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
            // partial reload (only: ['draft']) when the user picks a template / record
            'draft' => fn () => $template !== '' ? $this->emails->draft($template, $request->query('record') ?: null) : null,
            'filters' => $request->only('q'),
        ]);
    }

    public function records(Request $request): JsonResponse
    {
        return response()->json($this->emails->records((string) $request->query('template'), $request->query('q')));
    }

    public function preview(ComposeEmailRequest $request): HttpResponse
    {
        return response($this->emails->render($request->validated(), $request->user()));
    }

    public function send(ComposeEmailRequest $request)
    {
        $log = $this->emails->send($request->validated(), $request->user());

        return $this->toast("Email sent to {$log->to}.", 'success', 'Email sent');
    }
}
