<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function uploadedArticle(string $text): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'riset-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    return new UploadedFile($path, 'article.docx', null, null, true);
}

beforeEach(function () {
    config(['services.ai' => ['base_url' => 'https://ai.test/v1', 'api_key' => null, 'model' => 'test', 'timeout' => 5, 'json_mode' => true]]);
});

it('fills metadata and notes from an uploaded Word article without saving until review', function () {
    $project = Project::factory()->for(User::factory()->unlimited())->create();
    $file = uploadedArticle('Latihan berbicara. DOI 10.1234/practice. '.str_repeat('Metode survei siswa. ', 1000).'Temuan akhir: siswa menyukai latihan interaktif.');
    $answer = fn (array $data) => ['choices' => [['message' => ['content' => json_encode($data)]]]];
    Http::fake(['ai.test/*' => Http::sequence()->push($answer([
        'title' => 'Latihan berbicara', 'source_url' => '', 'notes' => 'Survei kebutuhan siswa. Temuan akhir siswa menyukai latihan interaktif.',
        'metadata' => ['type' => 'article', 'authors' => ['Santoso, Budi'], 'year' => '2024', 'doi' => '10.1234/practice', 'publication' => 'Jurnal Bahasa', 'volume' => '2', 'issue' => '1', 'pages' => '1-9', 'keywords' => ['media digital']],
    ]))]);
    try {
        $response = $this->actingAs($project->user)->postJson("/projects/{$project->id}/references/import", ['article' => $file]);
        $response->assertOk()->assertJsonPath('title', 'Latihan berbicara')->assertJsonPath('source_url', 'https://doi.org/10.1234/practice')->assertJsonPath('metadata.authors.0', 'Santoso, Budi')->assertJsonPath('metadata.pages', '1-9');
        expect($response['notes'])->toContain('Catatan AI', 'Survei kebutuhan siswa.', 'Temuan akhir');
        expect($project->references()->count())->toBe(0);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Temuan akhir: siswa menyukai latihan interaktif.'));
        $this->post("/projects/{$project->id}/references", $response->json())->assertSessionHasNoErrors();
        expect($project->references()->count())->toBe(1);
    } finally {
        unlink($file->getPathname());
    }
});

it('rejects invalid files and another owner before calling AI', function () {
    $project = Project::factory()->for(User::factory()->unlimited())->create();
    Http::fake();
    $this->actingAs($project->user)->postJson("/projects/{$project->id}/references/import", ['article' => UploadedFile::fake()->create('article.txt', 1, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('article');
    $this->actingAs(User::factory()->unlimited()->create())->postJson("/projects/{$project->id}/references/import")->assertForbidden();
    Http::assertNothingSent();
});

it('rejects empty Word content without charging an AI request', function () {
    $project = Project::factory()->for(User::factory()->unlimited())->create();
    $file = uploadedArticle('Teks terlalu pendek.');
    Http::fake();
    try {
        $this->actingAs($project->user)->postJson("/projects/{$project->id}/references/import", ['article' => $file])->assertStatus(502);
        Http::assertNothingSent();
    } finally {
        unlink($file->getPathname());
    }
});

it('reads a text PDF and leaves unavailable source metadata blank', function (bool $poppler) {
    $path = getenv('PATH');
    if (! $poppler) {
        // Shared hosting tanpa pdftotext: ekstraksi jatuh ke parser PHP.
        putenv('PATH=/nonexistent');
    }
    $project = Project::factory()->for(User::factory()->unlimited())->create();
    $stream = 'BT /F1 10 Tf 50 750 Td ';
    for ($i = 0; $i < 20; $i++) {
        $stream .= '(Survey of English speaking practice. Students prefer interactive tools.) Tj 0 -20 Td ';
    }
    $stream .= 'ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $start = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";
    Http::fake(['ai.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['title' => 'Speaking practice', 'source_url' => 'https://invented.test/article', 'metadata' => ['doi' => '10.1234/invented'], 'notes' => 'Siswa menyukai latihan interaktif.'])]]]])]);
    $response = $this->actingAs($project->user)->postJson("/projects/{$project->id}/references/import", ['article' => UploadedFile::fake()->createWithContent('article.pdf', $pdf)]);
    putenv("PATH={$path}");
    $response->assertOk()->assertJsonPath('source_url', '')->assertJsonPath('metadata.doi', '')->assertJsonPath('metadata.authors', []);
    Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Students prefer interactive tools.'));
    expect($project->references()->count())->toBe(0);
})->with(['pdftotext' => [true], 'parser PHP' => [false]]);
