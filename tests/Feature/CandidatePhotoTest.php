<?php

use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('permet de déposer une photo de profil, exposée ensuite via photoUrl()', function () {
    $candidate = Candidate::factory()->create([
        'prenom' => 'Raslen',
        'nom' => 'Saadi',
        'email' => 'raslen.saadi@example.test',
        'telephone' => '+33612345678',
    ]);

    // Aucune photo au départ : la fiche retombe sur les initiales.
    expect($candidate->photoUrl())->toBeNull();

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'photo' => [UploadedFile::fake()->image('avatar.jpg', 300, 300)],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->getFirstMedia('photo'))->not->toBeNull()
        ->and($candidate->photoUrl())->not->toBeNull();
});
