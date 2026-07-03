<?php

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('déclare le fournisseur de double authentification par application sur le panel', function () {
    $providers = array_values(Filament::getPanel('admin')->getMultiFactorAuthenticationProviders());

    expect($providers)->toHaveCount(1)
        ->and($providers[0])->toBeInstanceOf(AppAuthentication::class)
        ->and($providers[0]->isRecoverable())->toBeTrue()
        ->and(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeFalse();
});

it('expose un modèle utilisateur compatible MFA', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(HasAppAuthentication::class)
        ->and($user)->toBeInstanceOf(HasAppAuthenticationRecovery::class)
        ->and($user->getAppAuthenticationHolderName())->toBe($user->email);
});

it('mémorise le secret TOTP et reflète l\'activation de la MFA', function () {
    $user = User::factory()->create();
    $provider = AppAuthentication::make();

    // Tant qu'aucun secret n'est enregistré, la MFA est inactive pour ce compte.
    expect($provider->isEnabled($user))->toBeFalse();

    $secret = $provider->generateSecret();
    $user->saveAppAuthenticationSecret($secret);

    expect($user->fresh()->getAppAuthenticationSecret())->toBe($secret)
        ->and($provider->isEnabled($user->fresh()))->toBeTrue();
});

it('chiffre le secret TOTP au repos (jamais en clair en base)', function () {
    $user = User::factory()->create();
    $secret = AppAuthentication::make()->generateSecret();
    $user->saveAppAuthenticationSecret($secret);

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe($secret);
});

it('mémorise et efface les codes de secours', function () {
    $user = User::factory()->create();
    $codes = ['aaaa-bbbb', 'cccc-dddd'];

    $user->saveAppAuthenticationRecoveryCodes($codes);
    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe($codes);

    $user->saveAppAuthenticationRecoveryCodes(null);
    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('n\'expose pas le secret ni les codes de secours dans la sérialisation', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    $array = $user->fresh()->toArray();

    expect($array)->not->toHaveKey('app_authentication_secret')
        ->and($array)->not->toHaveKey('app_authentication_recovery_codes');
});
