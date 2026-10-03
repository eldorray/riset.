<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Reference;
use App\References\Metadata;
use Illuminate\Support\Facades\Http;

function crossrefBody(): array
{
    return ['message' => ['items' => [
        [
            'DOI' => '10.0000/contoh.001',
            'title' => ['Literasi <i>digital</i> dan kemandirian belajar'],
            'author' => [['family' => 'Santoso', 'given' => 'Budi Arief'], ['name' => 'Tim Riset Contoh']],
            'issued' => ['date-parts' => [[2024, 3]]],
            'container-title' => ['Jurnal Contoh Pendidikan'],
            'volume' => '12', 'issue' => '3', 'page' => '45-60',
            'type' => 'journal-article',
        ],
        ['DOI' => '10.0000/tanpa-judul', 'title' => []],
        ['DOI' => '10.0000/buku.002', 'title' => ['Pengantar literasi informasi'], 'publisher' => 'Penerbit Contoh', 'type' => 'monograph'],
    ]]];
}

function openAlexBody(): array
{
    return ['results' => [
        [
            'doi' => 'https://doi.org/10.0000/oa.003',
            'display_name' => 'Analisis kemampuan literasi digital mahasiswa',
            'publication_year' => 2021,
            'type' => 'article',
            'authorships' => [['author' => ['display_name' => 'Karsoni Berta Dinata']]],
            'primary_location' => ['source' => ['display_name' => 'Jurnal Edukasi']],
            'biblio' => ['volume' => '19', 'issue' => '1', 'first_page' => '10', 'last_page' => '20'],
            'open_access' => ['is_oa' => true, 'oa_url' => 'https://contoh.ac.id/oa.pdf'],
        ],
        // Duplikat judul dari sumber lain dibuang.
        ['doi' => null, 'display_name' => 'LITERASI DIGITAL DAN KEMANDIRIAN BELAJAR', 'type' => 'article', 'primary_location' => ['landing_page_url' => 'https://contoh.ac.id/x']],
    ]];
}

function doajBody(): array
{
    return ['results' => [['bibjson' => [
        'title' => 'Motivasi belajar daring',
        'identifier' => [['type' => 'doi', 'id' => '10.0000/doaj.004']],
        'author' => [['name' => 'Riana']],
        'journal' => ['title' => 'Paedagoria', 'volume' => '12', 'number' => '2'],
        'year' => '2021', 'start_page' => '155', 'end_page' => '158',
        'link' => [['url' => 'https://journal.contoh.ac.id/4950']],
    ]]]];
}

function fakeProviders(int $semanticStatus = 429): void
{
    Http::fake([
        'api.crossref.org/*' => Http::response(crossrefBody()),
        'api.openalex.org/*' => Http::response(openAlexBody()),
        'api.semanticscholar.org/*' => Http::response(['message' => 'Too Many Requests'], $semanticStatus),
        'doaj.org/*' => Http::response(doajBody()),
    ]);
}

it('mencari di semua sumber, membuang duplikat, dan tetap jalan bila satu sumber gagal', function () {
    $project = Project::factory()->create();
    fakeProviders();

    $response = $this->actingAs($project->user)
        ->getJson("/projects/{$project->id}/references/search?q=literasi+digital")
        ->assertOk();

    $titles = array_column($response->json('results'), 'title');

    expect($titles)->toContain('Literasi digital dan kemandirian belajar', 'Analisis kemampuan literasi digital mahasiswa', 'Motivasi belajar daring', 'Pengantar literasi informasi')
        ->not->toContain('LITERASI DIGITAL DAN KEMANDIRIAN BELAJAR')
        ->and($response->json('notes.0'))->toContain('Semantic Scholar (batas permintaan penuh)')
        ->and(array_column($response->json('links'), 'name'))->toBe(['Google Scholar', 'ResearchGate']);

    $openalex = collect($response->json('results'))->firstWhere('source_name', 'OpenAlex');
    expect($openalex['metadata']['authors'])->toBe(['Dinata, Karsoni Berta'])
        ->and($openalex['metadata']['pages'])->toBe('10-20')
        ->and($openalex['open_access_url'])->toBe('https://contoh.ac.id/oa.pdf');

    $book = collect($response->json('results'))->firstWhere('title', 'Pengantar literasi informasi');
    expect($book['metadata'])->toBe(['type' => 'book', 'publisher' => 'Penerbit Contoh', 'doi' => '10.0000/buku.002', 'keywords' => ['literasi digital']]);
});

it('meneruskan filter tahun, jenis, akses terbuka, dan halaman ke penyedia', function () {
    $project = Project::factory()->create();
    fakeProviders(200);

    $this->actingAs($project->user)
        ->getJson("/projects/{$project->id}/references/search?".http_build_query(['q' => 'literasi', 'source' => 'openalex', 'year_from' => 2020, 'year_to' => 2024, 'type' => 'article', 'open_access' => 1, 'page' => 3]))
        ->assertOk();

    $this->getJson("/projects/{$project->id}/references/search?".http_build_query(['q' => 'literasi', 'source' => 'crossref', 'year_from' => 2020, 'page' => 2]))->assertOk();
    $this->getJson("/projects/{$project->id}/references/search?".http_build_query(['q' => 'literasi', 'source' => 'doaj', 'year_from' => 2020, 'year_to' => 2024]))->assertOk();

    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter=publication_year:2020-2024,type:article,is_oa:true') && str_contains($r->url(), 'page=3'));
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'filter=from-pub-date:2020') && str_contains($r->url(), 'offset=10'));
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'bibjson.year:[2020 TO 2024]'));
});

it('tidak menampilkan lagi referensi yang sudah tersimpan agar yang muncul judul lain', function () {
    $project = Project::factory()->create();
    Reference::factory()->for($project)->create(['source_url' => 'https://doi.org/10.0000/contoh.001']);
    fakeProviders(200);

    $response = $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&source=crossref")->assertOk();

    expect(array_column($response->json('results'), 'title'))->toBe(['Pengantar literasi informasi'])
        ->and($response->json('hidden'))->toBe(1);
});

it('melewati sumber yang tidak mendukung filter', function () {
    $project = Project::factory()->create();
    fakeProviders(200);

    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&source=doaj&type=book")
        ->assertStatus(502)->assertJsonPath('message', 'DOAJ hanya memuat artikel jurnal.');

    $this->getJson("/projects/{$project->id}/references/search?q=literasi&open_access=1")
        ->assertOk()->assertJsonPath('notes.0', 'Crossref tidak mendukung filter akses terbuka.');
});

it('menampilkan kegagalan bila semua sumber gagal', function () {
    $project = Project::factory()->create();
    Http::fake(['*' => Http::response('down', 503)]);

    $this->actingAs($project->user)
        ->getJson("/projects/{$project->id}/references/search?q=literasi&source=crossref")
        ->assertStatus(502)
        ->assertJsonPath('message', 'Pencarian gagal: Crossref tidak merespons. Coba lagi beberapa saat atau pilih sumber lain.')
        ->assertJsonMissingPath('results');
});

it('memvalidasi kata kunci dan rentang tahun', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=")->assertJsonValidationErrors('q');
    $this->getJson("/projects/{$project->id}/references/search?q=literasi&year_from=2024&year_to=2020")->assertJsonValidationErrors('year_to');
});

it('menyimpan hasil pencarian dengan asal entri dan nama sumber', function () {
    $project = Project::factory()->create();

    $this->actingAs($project->user)->post("/projects/{$project->id}/references", [
        'title' => 'Literasi digital',
        'source_url' => 'https://doi.org/10.0000/oa.003',
        'source_name' => 'OpenAlex',
        'input_method' => 'search',
        'metadata' => ['type' => 'article', 'authors' => ['Dinata, Karsoni Berta'], 'year' => '2021', 'publication' => 'Jurnal Edukasi'],
    ])->assertSessionHasNoErrors();

    expect($project->references()->firstOrFail())->input_method->toBe('search')->source_name->toBe('OpenAlex');
});

it('mengubah nama lengkap ke format Nama Belakang, Nama Depan', function () {
    expect(Metadata::familyFirst('Karsoni Berta Dinata'))->toBe('Dinata, Karsoni Berta')
        ->and(Metadata::familyFirst('Riana'))->toBe('Riana')
        ->and(Metadata::familyFirst('Santoso, Budi'))->toBe('Santoso, Budi');
});

function fakeScoped(): void
{
    Http::fake([
        'api.crossref.org/members/111*' => Http::response(['message' => ['location' => 'Tangerang Selatan, Banten, Indonesia']]),
        'api.crossref.org/members/222*' => Http::response(['message' => ['location' => 'Amsterdam, Netherlands']]),
        'api.crossref.org/members/*' => Http::response(['message' => ['location' => '']]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => [
            ['DOI' => '10.1/id', 'title' => ['Jurnal terbitan Indonesia'], 'type' => 'journal-article', 'member' => '111'],
            ['DOI' => '10.1/nl', 'title' => ['Journal published abroad'], 'type' => 'journal-article', 'member' => '222'],
            ['DOI' => '10.1/unknown', 'title' => ['Penerbit tanpa lokasi'], 'type' => 'journal-article', 'member' => '333'],
        ]]]),
        'api.openalex.org/sources*' => Http::response(['results' => [
            ['id' => 'https://openalex.org/S1', 'country_code' => 'ID'],
            ['id' => 'https://openalex.org/S2', 'country_code' => 'US'],
        ]]),
        'api.openalex.org/*' => Http::response(['results' => [
            ['doi' => 'https://doi.org/10.2/id', 'display_name' => 'Artikel OpenAlex nasional', 'type' => 'article', 'primary_location' => ['source' => ['id' => 'https://openalex.org/S1']]],
            ['doi' => 'https://doi.org/10.2/us', 'display_name' => 'OpenAlex international article', 'type' => 'article', 'primary_location' => ['source' => ['id' => 'https://openalex.org/S2']]],
            ['doi' => 'https://doi.org/10.2/x', 'display_name' => 'Sumber tidak dikenal', 'type' => 'article', 'primary_location' => []],
        ]]),
        'doaj.org/*' => Http::response(['results' => [['bibjson' => [
            'title' => 'Artikel DOAJ nasional', 'identifier' => [['type' => 'doi', 'id' => '10.3/id']], 'journal' => ['title' => 'Paedagoria', 'country' => 'ID'],
        ]]]]),
        'api.semanticscholar.org/*' => Http::response(['data' => []]),
    ]);
}

it('hanya menampilkan jurnal terbitan Indonesia saat filter nasional', function () {
    $project = Project::factory()->create();
    fakeScoped();

    $response = $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&scope=national&type=book")->assertOk();

    expect(array_column($response->json('results'), 'title'))->toEqualCanonicalizing(['Jurnal terbitan Indonesia', 'Artikel OpenAlex nasional', 'Artikel DOAJ nasional'])
        ->and(array_unique(array_column($response->json('results'), 'country')))->toBe(['ID'])
        ->and($response->json('notes'))->toContain('Semantic Scholar dilewati: tidak memuat data negara jurnal.')
        ->and($response->json('results.0'))->not->toHaveKey('lookup');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.crossref.org/works') && str_contains(urldecode($r->url()), 'type:journal-article'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openalex.org/works') && str_contains(urldecode($r->url()), 'primary_location.source.type:journal'));
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'AND bibjson.journal.country:ID'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'semanticscholar'));
});

it('hanya menampilkan jurnal terbitan luar Indonesia saat filter internasional', function () {
    $project = Project::factory()->create();
    fakeScoped();

    $response = $this->actingAs($project->user)->getJson("/projects/{$project->id}/references/search?q=literasi&scope=international&source=crossref")->assertOk();

    expect(array_column($response->json('results'), 'title'))->toBe(['Journal published abroad']);

    $this->getJson("/projects/{$project->id}/references/search?q=literasi&scope=international&source=openalex")->assertOk()
        ->assertJsonPath('results.0.title', 'OpenAlex international article')
        ->assertJsonCount(1, 'results');

    $this->getJson("/projects/{$project->id}/references/search?q=literasi&scope=international&source=doaj")->assertOk();
    Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'AND NOT bibjson.journal.country:ID'));
});
