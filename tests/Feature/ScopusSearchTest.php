<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.scopus.api_key' => 'key-uji', 'services.scopus.insttoken' => 'token-uji']);
    Http::preventStrayRequests();
});

it('filter Scopus memakai API Elsevier dengan kunci di header dan memetakan metadata', function () {
    $project = Project::factory()->create();
    Http::fake(['api.elsevier.com/*' => Http::response(['search-results' => ['entry' => [[
        'dc:title' => 'Digital literacy study', 'dc:creator' => 'Santoso B.',
        'prism:doi' => '10.1234/digital', 'prism:coverDate' => '2024-06-01',
        'prism:publicationName' => 'Journal of Digital Learning', 'prism:aggregationType' => 'Journal',
        'prism:volume' => '5', 'prism:issueIdentifier' => '2', 'prism:pageRange' => '10-20',
        'eid' => '2-s2.0-123', 'openaccess' => '1',
    ]]]])]);
    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=digital+literacy&index=scopus&year_from=2020&year_to=2024&type=article&open_access=1&page=2")
        ->assertOk()->assertJsonPath('results.0.source_name', 'Scopus')
        ->assertJsonPath('results.0.title', 'Digital literacy study')
        ->assertJsonPath('results.0.metadata.doi', '10.1234/digital')
        ->assertJsonPath('results.0.metadata.year', '2024')
        ->assertJsonMissingPath('results.0.metadata.authors')
        ->assertJsonPath('results.0.open_access_url', null);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('X-ELS-APIKey', 'key-uji')
        && $request->hasHeader('X-ELS-Insttoken', 'token-uji')
        && ! str_contains($request->url(), 'key-uji')
        && str_contains(urldecode($request->url()), 'PUBYEAR > 2019')
        && str_contains(urldecode($request->url()), 'PUBYEAR < 2025')
        && str_contains(urldecode($request->url()), 'SRCTYPE(j)')
        && str_contains(urldecode($request->url()), 'OPENACCESS(1)')
        && str_contains($request->url(), 'start=10'));
});

it('tidak meloloskan hasil sumber lain ketika filter indeks dipilih', function () {
    $project = Project::factory()->create();
    Http::fake(['doaj.org/*' => Http::response(['results' => [['bibjson' => [
        'title' => 'Artikel DOAJ', 'year' => '2024', 'identifier' => [['type' => 'doi', 'id' => '10.1/doaj']],
    ]]]])]);
    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&source=crossref&index=doaj")
        ->assertOk()->assertJsonPath('results.0.source_name', 'DOAJ');
    Http::assertSentCount(1);
});

it('menjelaskan penolakan akses Scopus dan tidak berpindah ke sumber umum', function () {
    $project = Project::factory()->create();
    Http::fake(['api.elsevier.com/*' => Http::response('<html>Access denied</html>', 403)]);
    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&index=scopus")
        ->assertStatus(502)->assertJsonPath('message', 'Pencarian gagal: Scopus menolak akses (HTTP 403). Periksa akses API/jaringan Elsevier dan akses institusi bila diperlukan. Coba lagi beberapa saat atau pilih sumber lain.');
    Http::assertSentCount(1);
});

it('menolak indeks yang belum bisa diverifikasi dan kombinasi cakupan Scopus', function () {
    $project = Project::factory()->create();
    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&index=sinta1")
        ->assertJsonValidationErrors('index');
    $this->getJson("/projects/{$project->id}/references/search?q=literasi&index=scopus&scope=national")
        ->assertStatus(502);
    Http::assertNothingSent();
});

it('memberi pesan saat key Scopus belum diisi tanpa mengirim permintaan', function () {
    config(['services.scopus.api_key' => null]);
    $project = Project::factory()->create();
    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&index=scopus")
        ->assertStatus(502)->assertJsonPath('message', 'Scopus belum dikonfigurasi. Isi SCOPUS_API_KEY di server.');
    Http::assertNothingSent();
});
