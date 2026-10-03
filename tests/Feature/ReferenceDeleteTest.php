<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Reference;
use App\Models\User;

it('menghapus langsung tanpa konfirmasi dan menawarkan batalkan', function () {
    $project = Project::factory()->create();
    $reference = Reference::factory()->for($project)->create();

    $this->actingAs($project->user)->delete("/projects/{$project->id}/references/{$reference->id}")
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('success', 'Referensi dihapus dari proyek.')
        ->assertInertiaFlash('undo', route('projects.references.restore', [$project, $reference]));

    expect($project->references()->count())->toBe(0)
        ->and(Reference::withTrashed()->find($reference->id)?->trashed())->toBeTrue();
});

it('membuang sitasi dari draf lalu mengembalikannya saat dibatalkan', function () {
    $project = Project::factory()->withOutline()->create();
    $cited = Reference::factory()->for($project)->create();
    $other = Reference::factory()->for($project)->create();
    $project->update(['draft' => ['s1' => "Satu [@{$cited->id}] dan [@{$other->id}].", 'c2' => "Dua [@{$cited->id}].", 's2' => 'Tanpa sitasi.']]);

    $this->actingAs($project->user)->delete("/projects/{$project->id}/references/{$cited->id}")
        ->assertInertiaFlash('success', 'Referensi dan sitasinya di 2 bagian dihapus.');

    expect($project->fresh()->draft)->toBe(['s1' => "Satu dan [@{$other->id}].", 'c2' => 'Dua.', 's2' => 'Tanpa sitasi.']);

    // Bagian yang disunting setelah hapus tidak ditimpa saat dibatalkan.
    $project->update(['draft' => [...$project->fresh()->draft, 'c2' => 'Dua, sudah disunting.']]);

    $this->post("/projects/{$project->id}/references/{$cited->id}/restore")->assertInertiaFlash('success', 'Referensi dikembalikan.');

    expect($project->fresh()->draft)->toBe(['s1' => "Satu [@{$cited->id}] dan [@{$other->id}].", 'c2' => 'Dua, sudah disunting.', 's2' => 'Tanpa sitasi.'])
        ->and($project->references()->count())->toBe(2)
        ->and($cited->fresh()->removed_citations)->toBeNull();
});

it('menolak batalkan untuk referensi yang tidak dihapus', function () {
    $project = Project::factory()->create();
    $reference = Reference::factory()->for($project)->create();

    $this->actingAs($project->user)->post("/projects/{$project->id}/references/{$reference->id}/restore")->assertNotFound();
});

it('membersihkan permanen referensi terhapus yang lebih dari sehari', function () {
    $project = Project::factory()->create();
    $old = Reference::factory()->for($project)->create();
    $old->delete();
    Reference::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(2)]);
    $reference = Reference::factory()->for($project)->create();

    $this->actingAs($project->user)->delete("/projects/{$project->id}/references/{$reference->id}");

    expect(Reference::withTrashed()->find($old->id))->toBeNull()
        ->and(Reference::withTrashed()->find($reference->id))->not->toBeNull();
});

it('menampilkan bagian yang menyitasi di daftar referensi', function () {
    $project = Project::factory()->withOutline()->create();
    $reference = Reference::factory()->for($project)->create();
    $project->update(['draft' => ['s2' => "Teks [@{$reference->id}]."]]);

    $this->actingAs($project->user)->get("/projects/{$project->id}/references")
        ->assertInertia(fn ($page) => $page->where('references.0.cited_in', ['1.2']));
});

it('menolak menghapus atau mengembalikan referensi proyek lain atau milik pengguna lain', function () {
    $project = Project::factory()->create();
    $foreign = Reference::factory()->create();

    $this->actingAs($project->user)->delete("/projects/{$project->id}/references/{$foreign->id}")->assertNotFound();
    $this->actingAs(User::factory()->create())->delete("/projects/{$foreign->project_id}/references/{$foreign->id}")->assertForbidden();

    $foreign->delete();
    $this->actingAs($project->user)->post("/projects/{$project->id}/references/{$foreign->id}/restore")->assertNotFound();

    expect(Reference::withTrashed()->find($foreign->id))->not->toBeNull();
});
