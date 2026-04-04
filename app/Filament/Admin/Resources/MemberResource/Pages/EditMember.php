<?php

namespace App\Filament\Admin\Resources\MemberResource\Pages;

use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Widgets\MemberAccountStatsWidget;
use App\Filament\Admin\Widgets\MemberActivityWidget;
use App\Filament\Admin\Widgets\MemberProfileWidget;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    // Stash fields for related-model saves after the Member record is persisted
    private array $pendingUserUpdates = [];

    private array $pendingAppUpdates = [];

    // ── Header widgets ───────────────────────────────────────────────────────

    public function getSubheading(): ?string
    {
        return 'Edit member details — changes are saved together with their linked user and application records.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MemberAccountStatsWidget::class,
            MemberProfileWidget::class,
            MemberActivityWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getWidgetsData(): array
    {
        return ['record' => $this->record];
    }

    // ── Mutation hooks ───────────────────────────────────────────────────────

    /**
     * Pre-fill the virtual _user_* and _app_* fields from the related
     * User and MembershipApplication records so the form loads correctly.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;
        $app = $user?->membershipApplication;

        // User fields
        $data['_user_name'] = $user?->name;
        $data['_user_email'] = $user?->email;

        // Application fields
        $data['_app_application_type'] = $app?->application_type ?? 'new';
        $data['_app_gender'] = $app?->gender;
        $data['_app_marital_status'] = $app?->marital_status;
        $data['_app_membership_date'] = $app?->membership_date?->toDateString();
        $data['_app_national_id'] = $app?->national_id;
        $data['_app_date_of_birth'] = $app?->date_of_birth?->toDateString();
        $data['_app_city'] = $app?->city;
        $data['_app_address'] = $app?->address;
        $data['_app_home_phone'] = $app?->home_phone;
        $data['_app_work_phone'] = $app?->work_phone;
        $data['_app_mobile_phone'] = $app?->mobile_phone ?? $user?->phone;
        $data['_app_occupation'] = $app?->occupation;
        $data['_app_employer'] = $app?->employer;
        $data['_app_work_place'] = $app?->work_place;
        $data['_app_residency_place'] = $app?->residency_place;
        $data['_app_monthly_income'] = $app?->monthly_income;
        $data['_app_bank_account_number'] = $app?->bank_account_number;
        $data['_app_iban'] = $app?->iban;
        $data['_app_next_of_kin_name'] = $app?->next_of_kin_name;
        $data['_app_next_of_kin_phone'] = $app?->next_of_kin_phone;

        if ($app?->membership_date) {
            $data['joined_at'] = $app->membership_date->toDateString();
        }

        return $data;
    }

    /**
     * Strip all virtual fields before Eloquent saves the Member record.
     * Stash extracted values for afterSave().
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingUserUpdates = [];
        $this->pendingAppUpdates = [];

        // ── User fields ──────────────────────────────────────────────────────
        if (array_key_exists('_user_name', $data)) {
            $this->pendingUserUpdates['name'] = $data['_user_name'];
        }
        unset($data['_user_name'], $data['_user_email']); // email is display-only

        if (array_key_exists('_app_mobile_phone', $data)) {
            $this->pendingUserUpdates['phone'] = $data['_app_mobile_phone'] !== '' ? $data['_app_mobile_phone'] : null;
        }

        // ── Application fields ───────────────────────────────────────────────
        $appKeys = [
            '_app_application_type' => 'application_type',
            '_app_gender' => 'gender',
            '_app_marital_status' => 'marital_status',
            '_app_membership_date' => 'membership_date',
            '_app_national_id' => 'national_id',
            '_app_date_of_birth' => 'date_of_birth',
            '_app_city' => 'city',
            '_app_address' => 'address',
            '_app_home_phone' => 'home_phone',
            '_app_work_phone' => 'work_phone',
            '_app_mobile_phone' => 'mobile_phone',
            '_app_occupation' => 'occupation',
            '_app_employer' => 'employer',
            '_app_work_place' => 'work_place',
            '_app_residency_place' => 'residency_place',
            '_app_monthly_income' => 'monthly_income',
            '_app_bank_account_number' => 'bank_account_number',
            '_app_iban' => 'iban',
            '_app_next_of_kin_name' => 'next_of_kin_name',
            '_app_next_of_kin_phone' => 'next_of_kin_phone',
        ];

        foreach ($appKeys as $formKey => $dbColumn) {
            if (array_key_exists($formKey, $data)) {
                $this->pendingAppUpdates[$dbColumn] = $data[$formKey];
                unset($data[$formKey]);
            }
        }

        return $data;
    }

    /**
     * After the Member record is saved, persist changes to User and
     * MembershipApplication (creating the application record if it does
     * not yet exist for admin-created members).
     */
    protected function afterSave(): void
    {
        if (!empty($this->pendingUserUpdates)) {
            $this->record->user->update($this->pendingUserUpdates);
        }

        if (!empty($this->pendingAppUpdates)) {
            $this->record->user->membershipApplication()->updateOrCreate(
                ['user_id' => $this->record->user_id],
                $this->pendingAppUpdates,
            );
        }
    }
}
