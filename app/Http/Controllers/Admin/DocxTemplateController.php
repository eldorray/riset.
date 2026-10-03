<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocxTemplateRequest;
use App\Models\DocxTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class DocxTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Templates', [
            'templates' => DocxTemplate::query()->withCount('projects')->orderBy('name')->get(),
            'defaults' => DocxTemplate::DEFAULTS,
            'fonts' => DocxTemplateRequest::FONTS,
        ]);
    }

    public function store(DocxTemplateRequest $request): RedirectResponse
    {
        DocxTemplate::query()->create($request->validated());
        Inertia::flash('success', 'Template dibuat.');

        return back();
    }

    public function update(DocxTemplateRequest $request, DocxTemplate $template): RedirectResponse
    {
        $template->update($request->validated());
        Inertia::flash('success', 'Template diperbarui.');

        return back();
    }

    /** Proyek yang memakai template ini kembali ke format bawaan (nullOnDelete). */
    public function destroy(DocxTemplate $template): RedirectResponse
    {
        $template->delete();
        Inertia::flash('success', 'Template dihapus.');

        return back();
    }
}
