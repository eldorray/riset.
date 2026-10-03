<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Citation\Markers;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Langkah 5: draf bagian awal naskah dari isi draf yang sudah ditulis. Abstrak hanya meringkas
 * draf (tidak menambah temuan), abstract menerjemahkan abstrak, kata pengantar memakai
 * placeholder untuk nama yang tidak diketahui. Hasil tidak disimpan sebelum ditinjau pengguna.
 */
final class GenerateFrontMatter
{
    // ponytail: tiap bagian dikirim sebagai cuplikan 1.500 karakter agar prompt tetap kecil untuk
    // draf panjang; naikkan atau ringkas per bab bila abstrak terasa melewatkan isi.
    private const EXCERPT = 1500;

    public function __construct(private readonly AiClient $ai) {}

    /**
     * @return array{text: string, keywords: string, limitations: string}
     *
     * @throws AiException
     */
    public function __invoke(Project $project, string $part): array
    {
        $type = $project->document_type->label();
        $header = "Judul: {$project->title}\nJenis tulisan: {$type}";

        $prompt = match ($part) {
            'abstrak' => implode("\n", [
                $header,
                '',
                'Isi draf per bagian (cuplikan):',
                $this->body($project),
                '',
                "Susun abstrak {$type} berbahasa Indonesia, 150–250 kata, satu paragraf: tujuan, metode, hasil, dan simpulan.",
                'Hanya ringkas isi draf di atas. Jangan menambah temuan, angka, atau klaim yang tidak ada di draf, dan jangan menulis sitasi.',
                'Jika draf belum memuat hasil atau simpulan, sebutkan di "limitations".',
                'Jawab dengan JSON: {"text": "…", "keywords": ["3–5 kata kunci"], "limitations": "…"}',
            ]),
            'abstract' => implode("\n", [
                $header,
                '',
                'Abstrak berbahasa Indonesia:',
                $this->required($project, 'abstrak', 'Isi dan simpan abstrak bahasa Indonesia lebih dulu.'),
                'Kata kunci: '.($project->front_matter['abstrak']['keywords'] ?? ''),
                '',
                'Terjemahkan abstrak dan kata kunci ke bahasa Inggris akademik. Jangan menambah atau menghilangkan isi.',
                'Jawab dengan JSON: {"text": "…", "keywords": ["…"], "limitations": ""}',
            ]),
            'kata_pengantar' => implode("\n", [
                $header,
                '',
                "Tulis draf kata pengantar {$type} berbahasa Indonesia yang formal, 2–4 paragraf.",
                'Nama orang, jabatan, dan instansi tidak diketahui: tulis sebagai placeholder dalam kurung siku, mis. [Nama Dosen Pembimbing], [Nama Program Studi], [Kota], [Tanggal]. Jangan mengarang nama.',
                'Jawab dengan JSON: {"text": "…", "keywords": [], "limitations": ""}',
            ]),
            default => throw new AiException('Bagian naskah tidak dikenal.'),
        };

        $data = $this->ai->json('Anda asisten penulisan akademik yang hanya memakai informasi yang diberikan. Jawab hanya dengan JSON.', $prompt);

        $text = $data['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new AiException('AI tidak menghasilkan teks. Bagian naskah tersimpan tidak berubah.');
        }

        $keywords = $data['keywords'] ?? '';
        $keywords = is_array($keywords) ? implode(', ', array_filter($keywords, 'is_string')) : (is_string($keywords) ? $keywords : '');

        return [
            // Abstrak tidak memuat sitasi: penanda yang terbawa dibuang.
            'text' => trim(Markers::strip(trim($text), [])[0]),
            'keywords' => trim($keywords),
            'limitations' => is_string($data['limitations'] ?? null) ? trim($data['limitations']) : '',
        ];
    }

    private function body(Project $project): string
    {
        $style = $project->style();
        $references = $project->references->keyBy('id');
        $parts = [];

        foreach ($project->units() as $unit) {
            $text = trim($project->draft[$unit['id']] ?? '');

            if ($text !== '') {
                $parts[] = "{$unit['number']} {$unit['title']}\n".Str::limit(Markers::render($text, $references, $style), self::EXCERPT);
            }
        }

        if ($parts === []) {
            throw new AiException('Draf masih kosong. Tulis draf lebih dulu sebelum menyusun abstrak.');
        }

        return implode("\n\n", $parts);
    }

    private function required(Project $project, string $key, string $message): string
    {
        $text = $project->frontText($key);

        if ($text === '') {
            throw new AiException($message);
        }

        return $text;
    }
}
