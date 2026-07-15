<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'avatar_url', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            // Le secret TOTP et les codes de secours sont chiffrés au repos.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Détermine si l'utilisateur peut accéder à un panneau donné.
     *
     * - `editeur` : réservé à l'exploitant de la solution (création/suspension des
     *   CFA). Exige la permission dédiée — un administrateur de CFA, même avec tous
     *   les modules, n'y a pas accès.
     * - `admin` (panneau CFA) : compte actif, portant au moins un rôle, et membre
     *   d'au moins un CFA actif.
     *
     * La condition « membre d'un CFA » n'est pas cosmétique. Les deux panneaux
     * partagent la même session : un compte éditeur (sans CFA, par nature)
     * connecté sur /editeur puis arrivant sur /admin passait cette porte, et
     * Filament terminait en abort(404) faute de CFA vers lequel rediriger —
     * un 404 nu, sans explication. Mieux vaut refuser la porte que la casser.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($panel->getId() === 'editeur') {
            return $this->can(RolePermissionSeeder::PERMISSION_EDITEUR);
        }

        return $this->roles()->exists()
            && $this->organisations()->where('actif', true)->exists();
    }

    // --- Multi-tenant (Filament) : rattachement du personnel aux CFA ---

    /** CFA (organisations) auxquels cet utilisateur appartient. */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class);
    }

    /**
     * Organisations proposées à l'utilisateur dans le sélecteur de tenant Filament.
     *
     * @return Collection<int, Organisation>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->organisations()->where('actif', true)->get();
    }

    /** L'utilisateur peut-il accéder à ce CFA ? (membre + CFA actif) */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Organisation
            && $tenant->actif
            && $this->organisations()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Photo de profil affichée par Filament (topbar, menu utilisateur). Renvoie
     * l'URL publique de l'image téléversée, ou null pour retomber sur l'avatar
     * généré à partir des initiales.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return filled($this->avatar_url)
            ? Storage::disk('public')->url($this->avatar_url)
            : null;
    }

    // --- Authentification multi-facteurs (application TOTP) — story P0-01-2 ---

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /** Nom affiché dans l'application d'authentification, à côté du code. */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
