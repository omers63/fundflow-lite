<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SupportRequestResource\Pages;

use App\Filament\Admin\Resources\SupportRequestResource;
use App\Models\SupportRequest;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSupportRequests extends ListRecords
{
    protected static string $resource = SupportRequestResource::class;

    /**
     * Filament sometimes passes an Eloquent builder whose model is null when applying filters
     * (where(function …) needs a real model). Same workaround as ListMemberRequests.
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if ($query->getModel() === null) {
            $query = SupportRequest::query();
        }

        return parent::applyFiltersToTableQuery($query, $isResolvingRecord);
    }

    public function filterTableQuery(Builder $query): Builder
    {
        if ($query->getModel() === null) {
            $query = SupportRequest::query();
        }

        return parent::filterTableQuery($query);
    }

    public function getTableQueryForExport(): Builder
    {
        $query = $this->getTable()->getQuery();

        if ($query->getModel() === null) {
            $query = SupportRequest::query();
        }

        $this->applyFiltersToTableQuery($query);
        $this->applySearchToTableQuery($query);
        $this->applySortingToTableQuery($query);

        return $query;
    }

    public function getSubheading(): ?string
    {
        return 'Messages submitted by members from Support & Requests in the member portal. Stored permanently; not tied to notification history.';
    }
}
