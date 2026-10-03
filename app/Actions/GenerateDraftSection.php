<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Billing\Billing;
use App\Citation\Markers;
use App\Models\Project;
use App\Models\Reference;
use App\References\ArticleReader;
use Illuminate\Support\Collection;

/**
 * F-06: draf satu bagian dari kerangka + referensi terpilih. AI hanya boleh merujuk
 * referensi yang dikirim (penanda [@id]); rujukan lain dibuang di server. Isi sumber yang
 * boleh dipakai hanya catatan pengguna, karena aplikasi tidak menyimpan teks penuh sumber.
 * Penulisan memakai kata-kata sendiri (parafrase) dengan sitasi, bukan menyalin kalimat sumber.
 * Mode "rewrite" menulis ulang teks yang ada dengan parafrase, mempertahankan makna dan sitasinya.
 */
final class GenerateDraftSection
{
    public function __construct(private readonly AiClient $ai, private readonly ArticleReader $reader) {}

    /**
     * @param  array{id: string, number: string, title: string, level: int}  $unit
     * @param  Collection<int, Reference>  $references
     * @param  'continue'|'rewrite'  $mode
     * @return array{text: string, limitations: string}
     *
     * @throws AiException
     */
    public function __invoke(Project $project, array $unit, Collection $references, ?int $targetWords = null, string $mode = 'continue'): array
    {
        $unreadable = [];
        foreach ($references as $reference) {
            if (blank($reference->notes)) {
                $mark = app(Billing::class)->mark();
                try {
                    $notes = $this->reader->read($reference);
                } catch (AiException $e) {
                    app(Billing::class)->refundTo($mark);
                    $unreadable[$reference->id] = 'Referensi “'.$reference->title.'”: '.$e->getMessage();

                    continue;
                }
                $query = $reference->newQuery()->whereKey($reference->id)->where('source_url', $reference->source_url);
                $reference->notes === null ? $query->whereNull('notes') : $query->where('notes', $reference->notes);
                if (! $query->update(['notes' => $notes])) {
                    throw new AiException('Referensi berubah selama pembacaan artikel. Coba lagi; perubahan Anda tetap tersimpan.');
                }
                $reference->notes = $notes;
                app(Billing::class)->checkpoint();
            }
        }

        $references = $references->reject(fn (Reference $reference): bool => isset($unreadable[$reference->id]));
        if ($references->isEmpty() && $unreadable !== []) {
            throw new AiException(implode(' ', $unreadable));
        }

        $outline = array_map(fn (array $u): string => "{$u['number']} {$u['title']}", $project->units());

        $style = $project->style();
        $sources = $references->map(function (Reference $reference) use ($style): string {
            $who = $style->isComplete($reference) ? $style->label($reference) : 'metadata belum lengkap';
            $notes = filled($reference->notes)
                ? 'Catatan pengguna: '.$reference->notes
                : 'Tidak ada catatan pengguna — jangan menyimpulkan isi sumber ini.';

            return "[@{$reference->id}] {$reference->title} ({$who}). Kata kunci: ".implode(', ', $reference->metadata['keywords'] ?? []).". {$notes}";
        })->all();

        $existing = trim($project->draft[$unit['id']] ?? '');

        $prompt = implode("\n", [
            "Judul: {$project->title}",
            "Jenis tulisan: {$project->document_type->label()}",
            'Kerangka:',
            ...$outline,
            '',
            "Bagian yang ditulis: {$unit['number']} {$unit['title']}",
            match (true) {
                $existing === '' => 'Bagian ini masih kosong.',
                $mode === 'rewrite' => "Teks yang harus ditulis ulang dengan parafrase (pertahankan makna, fakta, dan penanda sitasinya; ubah susunan kalimat dan pilihan kata):\n{$existing}",
                default => "Teks yang sudah ada di bagian ini (lanjutkan tanpa mengulang):\n{$existing}",
            },
            '',
            'Referensi yang boleh dirujuk:',
            ...($sources === [] ? ['(tidak ada)'] : $sources),
            '',
            'Aturan:',
            $targetWords
                ? '1. Tulis sekitar '.$targetWords.' kata ('.(int) round($targetWords * 0.9).'–'.(int) round($targetWords * 1.2).' kata) dalam bahasa Indonesia ragam akademik, beberapa paragraf. Pisahkan paragraf dengan satu baris kosong.'
                : '1. Tulis 2–4 paragraf bahasa Indonesia ragam akademik. Pisahkan paragraf dengan satu baris kosong.',
            '1b. Tulis dengan kata-kata sendiri (parafrase). Jangan menyalin kalimat dari catatan referensi atau teks lain; kutipan langsung paling banyak satu kalimat pendek dalam tanda kutip beserta sitasinya.',
            '2. Rujuk sumber hanya dengan penanda persis seperti [@12] dari daftar di atas. Jangan menulis nama penulis atau tahun sendiri, dan jangan menyebut sumber lain.',
            '2b. Sisipkan penanda sitasi tepat setelah kalimat atau klaim yang didukung, sebelum tanda titik; contoh: Literasi mendukung belajar mandiri [@12]. Jangan mengumpulkan seluruh sitasi di akhir draf atau menambahkan daftar pustaka.',
            '2bb. Setiap kalimat yang memparafrasekan informasi dari catatan sumber harus memiliki penanda sumber yang mendukung kalimat itu, bukan satu sitasi untuk seluruh paragraf. Tujuan, rencana metode, dan saran penulis sendiri tidak boleh diatribusikan ke artikel kecuali catatan artikel benar-benar mendukungnya. Pesan limitations tidak menggantikan sitasi untuk klaim yang memakai sumber.',
            '2c. Cocokkan topik bagian, kata kunci, dan catatan sumber. Gunakan setiap referensi terpilih yang relevan, boleh beberapa sumber untuk satu klaim. Jangan memaksakan sumber yang tidak relevan; jelaskan sumber yang tidak dipakai dan alasannya di limitations.',
            '3. Klaim tentang isi sebuah sumber hanya boleh berdasarkan catatan pengguna untuk sumber itu. Bila ada catatan yang relevan, draf wajib mengandung penanda sitasinya. Jika tidak ada catatan relevan, nyatakan keterbatasan ini secara jelas di limitations.',
            '4. Jika referensi tidak cukup untuk mendukung bagian ini, jelaskan keterbatasannya di "limitations". Jangan mengarang data, kutipan, atau nomor halaman.',
            'Jawab dengan JSON: {"text": "…", "limitations": "…"} — isi "limitations" dengan string kosong bila tidak ada keterbatasan.',
        ]);

        $allowed = $references->map(fn (Reference $r): int => $r->id)->all();
        $withNotes = $references->filter(fn (Reference $r): bool => filled($r->notes))->map(fn (Reference $r): int => $r->id)->all();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $data = $this->ai->json(
                'Anda asisten penulisan akademik yang jujur tentang batas sumber. Jawab hanya dengan JSON.',
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

            if ($withNotes !== [] && array_intersect(Markers::ids($text), $withNotes) === []) {
                if ($attempt === 0) {
                    app(Billing::class)->discardLast();
                    $prompt .= "\nJawaban sebelumnya tidak memuat sitasi dari catatan terpilih. Tulis ulang dengan penanda [@id] pada klaim yang didukung. Jangan menghasilkan draf jika catatan tidak mendukungnya; jelaskan sumber yang perlu dilengkapi.";

                    continue;
                }
                throw new AiException('AI belum menyertakan sitasi dari catatan referensi terpilih. Coba lagi; draf tersimpan tidak berubah.');
            }
            if ($removed !== []) {
                $limitations = trim($limitations.' Rujukan yang tidak ada di daftar referensi terpilih telah dihapus.');
            }

            if ($unreadable !== []) {
                $limitations = trim($limitations.' Referensi berikut tidak digunakan karena pembacaan gagal: '.implode(' ', $unreadable));
            }

            return ['text' => $text, 'limitations' => $limitations];
        }

        throw new AiException('AI belum menghasilkan draf bersitasi.');
    }
}
