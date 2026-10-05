<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Billing\Billing;
use App\Citation\Markers;
use App\Models\Project;
use App\Models\Reference;
use Illuminate\Support\Collection;

/**
 * F-06: draf satu bagian dari kerangka + referensi terpilih. AI hanya boleh merujuk referensi
 * yang catatannya sudah ditinjau pengguna (penanda [@id]); rujukan lain dibuang di server.
 * Setiap sitasi wajib disertai cuplikan catatan yang mendukungnya; cuplikan dicek ada di catatan.
 * Bab metode mengikuti rancangan penelitian, bab hasil/pembahasan/kesimpulan hanya memakai data
 * pengguna (kecuali studi literatur). Penulisan memakai kata-kata sendiri, bukan menyalin sumber.
 */
final class GenerateDraftSection
{
    public function __construct(private readonly AiClient $ai) {}

    /**
     * @param  array{id: string, number: string, title: string, level: int, kind: 'literatur'|'metode'|'empiris'}  $unit
     * @param  Collection<int, Reference>  $references
     * @return array{text: string, limitations: string, evidence: list<array{id: int, quote: string}>}
     *
     * @throws AiException
     */
    public function __invoke(Project $project, array $unit, Collection $references, ?int $targetWords = null): array
    {
        if ($reason = $project->blockedReason($unit)) {
            throw new AiException($reason);
        }

        $usable = $references->filter(fn (Reference $reference): bool => $reference->notesUsable())->values();
        $skipped = $references->reject(fn (Reference $reference): bool => $reference->notesUsable())
            ->map(fn (Reference $reference): string => '“'.$reference->title.'”'.($reference->notesPending() ? ' (catatan AI belum ditinjau)' : ' (belum ada catatan)'))
            ->all();

        $empirical = $unit['kind'] === 'empiris' && ! $project->isLiteratureStudy();
        // Bab literatur dengan sumber terpilih tapi tanpa catatan yang ditinjau = menulis dari metadata saja.
        if ($references->isNotEmpty() && $usable->isEmpty() && $unit['kind'] !== 'metode' && ! $empirical) {
            throw new AiException('Referensi terpilih belum punya catatan yang sudah ditinjau: '.implode(', ', $skipped).'. Isi atau tinjau catatannya di Referensi lalu coba lagi.');
        }

        $outline = array_map(fn (array $u): string => "{$u['number']} {$u['title']}", $project->units());

        $style = $project->style();
        $sources = $usable->map(function (Reference $reference) use ($style): string {
            $who = $style->isComplete($reference) ? $style->label($reference) : 'metadata belum lengkap';
            $basis = $reference->abstractOnly() ? ' Dasar catatan: abstrak saja — rujuk hanya untuk gambaran umum, bukan detail metode, angka, atau keterbatasan.' : '';

            return "[@{$reference->id}] {$reference->title} ({$who}). Kata kunci: ".implode(', ', $reference->metadata['keywords'] ?? []).".{$basis} Catatan: {$reference->notes}";
        })->all();

        $existing = trim($project->draft[$unit['id']] ?? '');

        $kindRules = match (true) {
            $empirical => [
                'Data dan temuan penelitian milik pengguna (satu-satunya dasar untuk hasil):',
                trim((string) $project->research_data),
                '',
                'Bagian ini melaporkan atau menafsirkan hasil penelitian pengguna. Semua hasil, angka, kutipan responden, dan temuan hanya dari data di atas; jangan menambah atau membulatkan angka. Referensi hanya untuk membandingkan atau menjelaskan temuan, bukan sebagai hasil penelitian ini. Bila data tidak cukup untuk bagian ini, tulis seperlunya dan jelaskan di limitations.',
            ],
            $unit['kind'] === 'empiris' => ['Pendekatan studi literatur: hasil berupa sintesis dari sumber terpilih, setiap temuan wajib bersitasi.'],
            $unit['kind'] === 'metode' => ['Tulis bagian metode sesuai rancangan pengguna. Jangan menambah populasi, sampel, lokasi, instrumen, jumlah, atau prosedur yang tidak ada di rancangan; tulis placeholder dalam kurung siku, mis. [jumlah responden], untuk detail yang belum diisi.'],
            default => ['Bila menulis rumusan masalah, tujuan, atau hipotesis, gunakan rancangan pengguna apa adanya.'],
        };

        $prompt = implode("\n", [
            "Judul: {$project->title}",
            "Jenis tulisan: {$project->document_type->label()}",
            $project->researchGapContext(),
            $project->designContext(),
            'Kerangka:',
            ...$outline,
            '',
            "Bagian yang ditulis: {$unit['number']} {$unit['title']}",
            $existing === '' ? 'Bagian ini masih kosong.' : "Teks yang sudah ada di bagian ini (lanjutkan tanpa mengulang):\n{$existing}",
            '',
            ...$kindRules,
            '',
            'Referensi yang boleh dirujuk (catatan sudah ditinjau pengguna):',
            ...($sources === [] ? ['(tidak ada)'] : $sources),
            '',
            'Aturan:',
            $targetWords
                ? '1. Tulis sekitar '.$targetWords.' kata ('.(int) round($targetWords * 0.9).'–'.(int) round($targetWords * 1.2).' kata) dalam bahasa Indonesia ragam akademik, beberapa paragraf. Pisahkan paragraf dengan satu baris kosong.'
                : '1. Tulis 2–4 paragraf bahasa Indonesia ragam akademik. Pisahkan paragraf dengan satu baris kosong.',
            '1b. Tulis dengan kata-kata sendiri (parafrase). Jangan menyalin kalimat dari catatan referensi atau teks lain; kutipan langsung paling banyak satu kalimat pendek dalam tanda kutip beserta sitasinya.',
            '2. Rujuk sumber hanya dengan penanda persis seperti [@12] dari daftar di atas. Jangan menulis nama penulis atau tahun sendiri, dan jangan menyebut sumber lain.',
            '2b. Sisipkan penanda sitasi tepat setelah kalimat atau klaim yang didukung, sebelum tanda titik; contoh: Literasi mendukung belajar mandiri [@12]. Jangan mengumpulkan seluruh sitasi di akhir draf atau menambahkan daftar pustaka.',
            '2c. Setiap kalimat yang memparafrasekan informasi dari catatan sumber harus memiliki penanda sumber yang mendukungnya. Tujuan, rencana metode, dan saran penulis sendiri tidak boleh diatribusikan ke artikel kecuali catatannya benar-benar mendukung.',
            '2d. Gunakan referensi yang relevan dengan topik bagian; jangan memaksakan sumber yang tidak relevan dan jelaskan alasannya di limitations.',
            '3. Untuk setiap penanda sitasi yang dipakai, isi "evidence" dengan cuplikan yang disalin persis dari catatan sumber itu (maksimal 200 karakter) yang mendukung klaimnya.',
            '4. Jika referensi atau data tidak cukup untuk bagian ini, jelaskan di "limitations". Jangan mengarang data, kutipan, atau nomor halaman.',
            'Jawab dengan JSON: {"text": "…", "evidence": [{"id": 12, "quote": "cuplikan persis catatan"}], "limitations": "…"} — isi "limitations" dengan string kosong bila tidak ada keterbatasan.',
        ]);

        $allowed = $usable->map(fn (Reference $r): int => $r->id)->all();
        $notes = $usable->mapWithKeys(fn (Reference $r): array => [$r->id => self::normalize((string) $r->notes)])->all();
        // Bagian hasil penelitian boleh tanpa sitasi; bagian lain yang punya catatan relevan wajib bersitasi.
        $needsCitation = $allowed !== [] && $unit['kind'] !== 'metode' && ! $empirical;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $data = $this->ai->json(
                'Anda asisten penulisan akademik yang jujur tentang batas sumber dan data. Jawab hanya dengan JSON.',
                $prompt,
            );
            $text = $data['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                throw new AiException('AI tidak menghasilkan teks. Draf tersimpan tidak berubah.');
            }
            $limitations = is_string($data['limitations'] ?? null) ? trim($data['limitations']) : '';
            [$text, $removed] = Markers::strip(trim($text), $allowed);
            // Model kadang meletakkan sitasi setelah titik; pindahkan hanya dalam baris yang sama.
            $text = preg_replace('/\.[ \t]+((?:\[@\d+\][ \t]*)*\[@\d+\])/', ' $1.', $text) ?? $text;

            if ($needsCitation && Markers::ids($text) === []) {
                if ($attempt === 0) {
                    app(Billing::class)->discardLast();
                    $prompt .= "\nJawaban sebelumnya tidak memuat sitasi dari catatan terpilih. Tulis ulang dengan penanda [@id] pada klaim yang didukung. Jangan menghasilkan draf jika catatan tidak mendukungnya; jelaskan sumber yang perlu dilengkapi.";

                    continue;
                }
                throw new AiException('AI belum menyertakan sitasi dari catatan referensi terpilih. Coba lagi; draf tersimpan tidak berubah.');
            }

            $evidence = self::verifiedEvidence($data['evidence'] ?? null, $notes);
            $unsupported = array_diff(Markers::ids($text), array_column($evidence, 'id'));
            $notices = array_filter([
                $removed !== [] ? 'Rujukan yang tidak ada di daftar referensi terpilih telah dihapus.' : '',
                $unsupported !== [] ? 'Sitasi tanpa cuplikan catatan yang cocok — periksa kembali: '.implode(', ', array_map(fn (int $id): string => "[@{$id}]", $unsupported)).'.' : '',
                $skipped !== [] ? 'Referensi berikut tidak dipakai: '.implode(', ', $skipped).'.' : '',
                $empirical ? self::numbersOutside($text, trim((string) $project->research_data).' '.implode(' ', $notes)) : '',
            ]);

            return ['text' => $text, 'limitations' => trim($limitations.' '.implode(' ', $notices)), 'evidence' => $evidence];
        }

        throw new AiException('AI belum menghasilkan draf bersitasi.');
    }

    /**
     * Hanya cuplikan yang benar-benar ada di catatan sumbernya yang dianggap bukti.
     *
     * @param  array<int, string>  $notes  catatan ternormalisasi per id referensi
     * @return list<array{id: int, quote: string}>
     */
    private static function verifiedEvidence(mixed $evidence, array $notes): array
    {
        $verified = [];
        foreach (is_array($evidence) ? $evidence : [] as $item) {
            $id = is_array($item) && is_numeric($item['id'] ?? null) ? (int) $item['id'] : null;
            $quote = is_array($item) && is_string($item['quote'] ?? null) ? self::normalize($item['quote']) : '';
            if ($id !== null && isset($notes[$id]) && mb_strlen($quote) >= 10 && str_contains($notes[$id], $quote)) {
                $verified[] = ['id' => $id, 'quote' => mb_substr($quote, 0, 300)];
            }
        }

        return $verified;
    }

    /**
     * Angka di bagian hasil yang tidak ada di data pengguna maupun catatan sumber.
     * ponytail: cek angka literal saja (bukan angka yang ditulis dengan huruf); cukup untuk menangkap angka karangan.
     */
    private static function numbersOutside(string $text, string $allowed): string
    {
        preg_match_all('/\d+(?:[.,]\d+)*%?/u', (string) preg_replace('/\[@\d+\]/', ' ', $text), $found);
        $foreign = array_values(array_unique(array_filter($found[0], fn (string $number): bool => ! str_contains($allowed, rtrim($number, '%')))));

        return $foreign === [] ? '' : 'Angka berikut tidak ditemukan di data penelitian Anda — periksa atau hapus: '.implode(', ', array_slice($foreign, 0, 10)).'.';
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
