<?php

namespace App\Application\Services;

use App\Domain\Events\OrganizationCreated;
use App\Domain\Events\UserRegistered;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private readonly EventBusService $eventBus,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return array{user: User, token: string, organization: Organization|null}
     */
    public function register(
        string $name,
        string $email,
        string $password,
        bool $createPersonalOrg = true,
        ?string $organizationName = null,
    ): array {
        return DB::transaction(function () use ($name, $email, $password, $createPersonalOrg, $organizationName) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $organization = null;

            if ($createPersonalOrg) {
                $organization = $this->createPersonalOrganization($user, $organizationName);
            }

            $this->eventBus->dispatch(new UserRegistered(
                $user->id,
                $user->email,
                $organization?->id,
            ));

            $this->audit->record('auth.register', $user, $organization?->id, metadata: [
                'email' => $email,
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'organization' => $organization,
            ];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \App\Domain\Shared\Exceptions\UnauthorizedException('Invalid credentials.');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->audit->record('auth.login', $user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user, ?string $plainTextToken = null): void
    {
        if ($plainTextToken) {
            PersonalAccessToken::findToken($plainTextToken)?->delete();
        } else {
            $user->currentAccessToken()?->delete();
        }

        $this->audit->record('auth.logout', $user);
    }

    private function createPersonalOrganization(User $user, ?string $name = null): Organization
    {
        $orgName = $name ?? "{$user->name}'s Organization";
        $slug = Str::slug($orgName).'-'.Str::random(6);

        $organization = Organization::create([
            'name' => $orgName,
            'slug' => $slug,
            'created_by' => $user->id,
        ]);

        $ownerRole = Role::query()
            ->where('name', 'owner')
            ->where('scope', 'organization')
            ->firstOrFail();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
        ]);

        $this->eventBus->dispatch(new OrganizationCreated(
            $organization->id,
            $organization->name,
            $user->id,
        ));

        return $organization;
    }
}
