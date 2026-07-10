<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function utilisateurActif(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

it('affiche la page profil avec le champ photo de profil', function () {
    Livewire::actingAs(utilisateurActif())
        ->test(EditProfile::class)
        ->assertOk()
        ->assertFormFieldExists('avatar_url');
});

it('téléverse une photo de profil et la relie à l\'avatar Filament', function () {
    Storage::fake('public');

    $user = utilisateurActif();

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm([
            'avatar_url' => UploadedFile::fake()->image('moi.jpg', 200, 200),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->avatar_url)->not->toBeNull()
        ->and($user->getFilamentAvatarUrl())->toContain($user->avatar_url);

    Storage::disk('public')->assertExists($user->avatar_url);
});

it('retombe sur l\'avatar par initiales quand aucune photo n\'est définie', function () {
    $user = User::factory()->create(['is_active' => true, 'avatar_url' => null]);

    expect($user->getFilamentAvatarUrl())->toBeNull();
});
