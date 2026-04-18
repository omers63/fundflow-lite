<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Filament sometimes passes an Eloquent builder whose model is null when applying filters
     * (where(function …) needs a real model). Same workaround as ListMemberRequests / ListSupportRequests.
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if ($query->getModel() === null) {
            $query = User::query();
        }

        return parent::applyFiltersToTableQuery($query, $isResolvingRecord);
    }

    public function filterTableQuery(Builder $query): Builder
    {
        if ($query->getModel() === null) {
            $query = User::query();
        }

        return parent::filterTableQuery($query);
    }

    public function getTableQueryForExport(): Builder
    {
        $query = $this->getTable()->getQuery();

        if ($query->getModel() === null) {
            $query = User::query();
        }

        $this->applyFiltersToTableQuery($query);
        $this->applySearchToTableQuery($query);
        $this->applySortingToTableQuery($query);

        return $query;
    }

    public function getSubheading(): ?string
    {
        return 'Manage login accounts, approval status, and Spatie roles (Shield permissions). You cannot delete your own account from this list.';
    }
}
