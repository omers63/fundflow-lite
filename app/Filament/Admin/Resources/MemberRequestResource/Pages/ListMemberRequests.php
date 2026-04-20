<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberRequestResource\Pages;

use App\Filament\Admin\Resources\MemberRequestResource;
use App\Models\MemberRequest;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMemberRequests extends ListRecords
{
    protected static string $resource = MemberRequestResource::class;

    /**
     * Filament sometimes passes an Eloquent builder whose model is null when applying filters
     * (where(function …) needs a real model). Replace before filters/search run; see laravel.log
     * "newQueryWithoutRelationships() on null" on this list page.
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if ($query->getModel() === null) {
            $query = MemberRequest::query();
        }

        return parent::applyFiltersToTableQuery($query, $isResolvingRecord);
    }

    public function filterTableQuery(Builder $query): Builder
    {
        if ($query->getModel() === null) {
            $query = MemberRequest::query();
        }

        return parent::filterTableQuery($query);
    }

    public function getTableQueryForExport(): Builder
    {
        $query = $this->getTable()->getQuery();

        if ($query->getModel() === null) {
            $query = MemberRequest::query();
        }

        $this->applyFiltersToTableQuery($query);
        $this->applySearchToTableQuery($query);
        $this->applySortingToTableQuery($query);

        return $query;
    }

    public function getSubheading(): ?string
    {
        return __('Review allocation and family changes submitted by members.');
    }
}
