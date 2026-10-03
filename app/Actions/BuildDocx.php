<?php

declare(strict_types=1);

namespace App\Actions;

use App\Citation\Markers;
use App\Citation\Style;
use App\Models\DocxTemplate;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * F-07: .docx berisi judul, bab/subbab sesuai urutan kerangka tersimpan, draf, sitasi,
 * dan daftar pustaka (hanya referensi yang dirujuk dan metadatanya lengkap).
 *
 * Format mengikuti template institusi yang dipilih proyek (diatur admin), atau format bawaan.
 * Aplikasi tidak menyatakan file sesuai panduan resmi; kesesuaian template tanggung jawab admin.
 */
final class BuildDocx
{
    private const CM = 567; // twip per cm

    /**
     * @param  Collection<int, Reference>  $references  diindeks per id
     * @return string path file sementara
     */
    public function __invoke(Project $project, Collection $references): string
    {
        Settings::setOutputEscapingEnabled(true);

        $template = $project->docxTemplate ?? DocxTemplate::fallback();
        $uppercase = $project->docxTemplate ? $template->chapter_uppercase : $project->document_type->usesBabNumbering();
        $style = $project->style();
        $numbers = $style->numbers($project, $references);

        $word = new PhpWord;
        $word->setDefaultFontName($template->font_family);
        $word->setDefaultFontSize($template->font_size);
        $word->addTitleStyle(1, ['bold' => true, 'size' => $template->font_size + 2], ['alignment' => 'center', 'spaceBefore' => 240, 'spaceAfter' => 240]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => $template->font_size], ['spaceBefore' => 240, 'spaceAfter' => 120]);

        $page = [
            'marginTop' => $this->cm($template->margin_top),
            'marginBottom' => $this->cm($template->margin_bottom),
            'marginLeft' => $this->cm($template->margin_left),
            'marginRight' => $this->cm($template->margin_right),
        ];
        $body = [
            'alignment' => 'both',
            'lineHeight' => $template->line_spacing,
            'indentation' => ['firstLine' => $this->cm($template->first_line_indent)],
            'spaceAfter' => 120,
        ];

        if ($template->title_page) {
            $this->titlePage($word->addSection($page), $project, $template);
        }

        $section = $word->addSection($page);

        if ($template->page_numbers) {
            $section->addFooter()->addPreserveText('{PAGE}', null, ['alignment' => 'center']);
        }

        if (! $template->title_page) {
            $section->addText($project->title, ['bold' => true, 'size' => $template->font_size + 4], ['alignment' => 'center', 'spaceAfter' => 480]);
        }

        $this->frontMatter($section, $project, $template, $body);

        if ($template->table_of_contents) {
            $section->addText('DAFTAR ISI', ['bold' => true, 'size' => $template->font_size + 2], ['alignment' => 'center', 'spaceAfter' => 240]);
            $section->addTOC(['size' => $template->font_size]);
            $section->addPageBreak();
        }

        foreach ($project->outline ?? [] as $i => $chapter) {
            if ($template->chapter_page_break && $i > 0) {
                $section->addPageBreak();
            }

            $title = $uppercase ? mb_strtoupper($chapter['title']) : $chapter['title'];
            $section->addTitle($project->chapterLabel($i).' '.$title, 1);

            if ($chapter['sections'] === []) {
                $this->paragraphs($section, $project->draft[$chapter['id']] ?? '', $references, $style, $numbers, $body);

                continue;
            }

            foreach ($chapter['sections'] as $j => $sub) {
                $section->addTitle(($i + 1).'.'.($j + 1).' '.$sub['title'], 2);
                $this->paragraphs($section, $project->draft[$sub['id']] ?? '', $references, $style, $numbers, $body);
            }
        }

        $bibliography = $style->bibliography($project, $references);

        if ($bibliography !== []) {
            if ($template->chapter_page_break) {
                $section->addPageBreak();
            }

            $section->addTitle('DAFTAR PUSTAKA', 1);

            foreach ($bibliography as $row) {
                $run = $section->addTextRun(['indentation' => ['left' => 720, 'hanging' => 720], 'spaceAfter' => 120]);

                foreach ($style->entry($row['reference'], $row['number']) as $segment) {
                    $run->addText($segment['text'], ['italic' => $segment['italic']]);
                }
            }
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'riset');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    /**
     * Bagian awal sesuai jenis tulisan. Skripsi/tesis: tiap bagian di halaman sendiri dan masuk
     * daftar isi; artikel/karya ilmiah: abstrak langsung di bawah judul.
     *
     * @param  array<string, mixed>  $body
     */
    private function frontMatter(Section $section, Project $project, DocxTemplate $template, array $body): void
    {
        $book = $project->document_type->isBook();

        foreach ($project->document_type->frontMatter() as $part) {
            $text = $project->frontText($part['key']);

            if ($text === '') {
                continue;
            }

            if ($book) {
                $section->addTitle($part['heading'], 1);
            } else {
                $section->addText($part['heading'], ['bold' => true], ['alignment' => 'center', 'spaceBefore' => 120, 'spaceAfter' => 120]);
            }

            $paragraph = $book ? $body : [...$body, 'lineHeight' => 1.0, 'indentation' => ['firstLine' => 0]];

            foreach (preg_split('/\n\s*\n/', $text) ?: [] as $chunk) {
                if (trim($chunk) !== '') {
                    $section->addText(preg_replace('/\s*\n\s*/', ' ', trim($chunk)) ?? $chunk, null, $paragraph);
                }
            }

            $keywords = trim((string) ($project->front_matter[$part['key']]['keywords'] ?? ''));

            if ($part['keywords'] !== null && $keywords !== '') {
                $run = $section->addTextRun(['spaceBefore' => 120, 'spaceAfter' => 240]);
                $run->addText($part['keywords'].': ', ['bold' => true]);
                $run->addText($keywords, ['italic' => $part['key'] === 'abstract']);
            }

            if ($book) {
                $section->addPageBreak();
            }
        }
    }

    private function titlePage(Section $cover, Project $project, DocxTemplate $template): void
    {
        $center = ['alignment' => 'center', 'spaceAfter' => 240];

        foreach (preg_split('/\R/', trim((string) $template->title_page_text)) ?: [] as $line) {
            if (trim($line) !== '') {
                $cover->addText(trim($line), ['bold' => true], $center);
            }
        }

        $cover->addTextBreak(2);
        $cover->addText(mb_strtoupper($project->title), ['bold' => true, 'size' => $template->font_size + 2], $center);
        $cover->addTextBreak(3);
        $cover->addText('Disusun oleh:', null, $center);
        $cover->addText($project->user->name, ['bold' => true], $center);
        $cover->addTextBreak(3);

        if (filled($template->institution)) {
            $cover->addText(mb_strtoupper((string) $template->institution), ['bold' => true], $center);
        }

        $cover->addText(now()->format('Y'), ['bold' => true], $center);
    }

    /**
     * @param  Collection<int, Reference>  $references
     * @param  array<int, int>  $numbers
     * @param  array<string, mixed>  $body
     */
    private function paragraphs(Section $section, string $text, Collection $references, Style $style, array $numbers, array $body): void
    {
        $text = Markers::render($text, $references, $style, $numbers);

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $section->addText(preg_replace('/\s*\n\s*/', ' ', trim($paragraph)) ?? $paragraph, null, $body);
            }
        }
    }

    private function cm(float $value): int
    {
        return (int) round($value * self::CM);
    }
}
