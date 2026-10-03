<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows a public landing page with only active plans and current prices', function () {
    DB::table('billing_plans')->where('id', 1)->update(['name' => 'Paket Awal', 'price' => 23000]);
    DB::table('billing_plans')->where('id', 2)->update(['active' => false]);
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('plans.0.name', 'Paket Awal')
        ->where('plans.0.price', 23000)
        ->has('plans', 2));
    $this->get('/projects')->assertRedirect('/masuk');
});

it('keeps the public page available for signed in users', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Landing'));
});
