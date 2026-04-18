<?php

declare(strict_types=1);

namespace App\Filament\Member\Resources\MyDependentsResource\Pages;

use App\Filament\Member\Resources\MyDependentsResource;
use App\Filament\Member\Widgets\MyMemberRequestsTableWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListMyDependents extends ListRecords
{
    protected static string $resource = MyDependentsResource::class;

    protected static ?string $title = 'My Dependents';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
                Livewire::make(MyMemberRequestsTableWidget::class),
            ]);
    }
}
