<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Spatie role IDs captured before relationship data is stripped for {@see User::$fillable} mass assignment.
     *
     * @var list<int|string>|null
     */
    protected ?array $pendingSpatieRoleIds = null;
    protected ?string $pendingUserTypeRole = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_type'] = $data['role'] ?? 'member';
        unset($data['role']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $roles = $data['roles'] ?? null;
        $this->pendingSpatieRoleIds = is_array($roles)
            ? array_values(array_filter($roles, static fn (mixed $id): bool => $id !== null && $id !== ''))
            : [];

        if (isset($data['user_type'])) {
            $data['role'] = $data['user_type'];
            $this->pendingUserTypeRole = is_string($data['user_type']) ? $data['user_type'] : null;
            unset($data['user_type']);
        }

        unset($data['roles'], $data['password_confirmation']);

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $rolesToSync = $this->pendingSpatieRoleIds ?? [];

        if (
            ($rolesToSync === []) &&
            filled($this->pendingUserTypeRole) &&
            Role::query()
                ->where('name', $this->pendingUserTypeRole)
                ->where('guard_name', 'web')
                ->exists()
        ) {
            $rolesToSync = [$this->pendingUserTypeRole];
        }

        $this->record->syncRoles($rolesToSync);
    }
}
