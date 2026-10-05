<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * F-05: AI hanya mengisi subbab. Jumlah, urutan, dan judul bab diambil dari struktur
 * jenis tulisan (config/riset.php), jadi jenis tulisan tidak pernah diperlakukan seragam.
 * Hasil tidak disimpan: pengguna memeriksa dan menyimpannya sendiri.
 */
final class GenerateOutline
{
    private const MAX_SECTIONS = 10;

    public function __construct(private readonly AiClient $ai) {}

    /**
     * @return list<array{id: string, title: string, sections: list<array{id: string, title: string}>}>
     *
     * @throws AiException
     */
    public function __invoke(Project $project): array
    {
        $structure = $project->document_type->structure();
        $lines = [];

        foreach ($structure as $i => $chapter) {
            $example = $chapter['sections'] === [] ? '(tanpa subbab)' : implode('; ', $chapter['sections']);
            $lines[] = $project->chapterLabel($i)." {$chapter['title']} — contoh subbab: {$example}";
        }

        $count = count($structure);
        $prompt = implode("\n", [
            "Judul: {$project->title}",
            "Jenis tulisan: {$project->document_type->label()}",
            $project->researchGapContext(),
            $project->designContext(),
            '',
            'Struktur bab yang wajib diikuti:',
            ...$lines,
            '',
            'Susun judul subbab yang sesuai dengan judul di atas untuk setiap bab (0–'.self::MAX_SECTIONS.' subbab per bab).',
            'Bab yang contohnya tanpa subbab boleh tetap tanpa subbab. Jangan mengubah, menambah, atau menghapus bab. Jangan menulis isi.',
            'Bila ada rancangan penelitian, subbab metode dan hasil mengikuti pendekatan dan rumusan masalah di rancangan.',
            "Jawab dengan JSON: {\"chapters\": [{\"sections\": [\"judul subbab\", ...]}, ...]} berisi tepat {$count} bab sesuai urutan.",
        ]);

        $data = $this->ai->json(
            'Anda membantu mahasiswa dan peneliti menyusun kerangka tulisan akademik berbahasa Indonesia. Jawab hanya dengan JSON.',
            $prompt,
        );

        $chapters = $data['chapters'] ?? null;

        if (! is_array($chapters) || count($chapters) !== $count) {
            throw new AiException('Kerangka dari AI tidak sesuai struktur jenis tulisan. Kerangka tersimpan tidak berubah.');
        }

        $outline = [];

        foreach ($structure as $i => $chapter) {
            $sections = $chapters[$i]['sections'] ?? [];
            $titles = is_array($sections) ? array_filter($sections, fn ($s): bool => is_string($s) && trim($s) !== '') : [];

            $outline[] = [
                'id' => self::id(),
                'title' => $chapter['title'],
                'sections' => array_map(
                    fn (string $title): array => ['id' => self::id(), 'title' => Str::limit(trim($title), 250, '')],
                    array_slice(array_values($titles), 0, self::MAX_SECTIONS),
                ),
            ];
        }

        return $outline;
    }

    private static function id(): string
    {
        return Str::lower(Str::random(10));
    }
}
