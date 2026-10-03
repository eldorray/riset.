<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildDocx;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ExportController extends Controller
{
    /** Gagal = pesan kegagalan, bukan file setengah jadi (AC F-07). */
    public function __invoke(Project $project, BuildDocx $build): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $project);

        if (($project->outline ?? []) === []) {
            Inertia::flash('error', 'Simpan kerangka lebih dulu sebelum mengekspor.');

            return back();
        }

        try {
            $path = $build($project, $project->references->keyBy('id'));
        } catch (Throwable $e) {
            report($e);
            Inertia::flash('error', 'File Word gagal dibuat. Tidak ada file yang diunduh; coba lagi.');

            return back();
        }

        return response()
            ->download($path, Str::slug(Str::limit($project->title, 80, '')).'.docx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }
}
