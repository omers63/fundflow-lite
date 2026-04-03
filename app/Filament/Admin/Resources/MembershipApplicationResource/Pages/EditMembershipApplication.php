<?php

namespace App\Filament\Admin\Resources\MembershipApplicationResource\Pages;

use App\Filament\Admin\Resources\MembershipApplicationResource;
use Filament\Resources\Pages\EditRecord;

class EditMembershipApplication extends EditRecord
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->getRecord()->loadMissing('user');
        $user = $this->getRecord()->user;
        if ($user) {
            $data['_display_user_name'] = $user->name;
            $data['_display_user_email'] = $user->email;
        }

        if (($data['mobile_phone'] ?? null) === null && $user?->phone) {
            $data['mobile_phone'] = $user->phone;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['_display_user_name'], $data['_display_user_email']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->user) {
            $this->record->user->update([
                'phone' => $this->record->mobile_phone !== '' && $this->record->mobile_phone !== null
                    ? $this->record->mobile_phone
                    : null,
            ]);
        }
    }
}
