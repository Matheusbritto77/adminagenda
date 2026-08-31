<?php

namespace App\Filament\Resources\WhatsAppLogResource\Pages;

use App\Filament\Resources\WhatsAppLogResource;
use App\Models\WhatsAppLog;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsAppLogs extends ListRecords
{
    protected static string $resource = WhatsAppLogResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos os Logs')
                ->badge(fn () => WhatsAppLog::count()),

            'outbound' => Tab::make('📤 Enviadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('direction', 'outbound'))
                ->badge(fn () => WhatsAppLog::where('direction', 'outbound')->count())
                ->badgeColor('info'),

            'inbound' => Tab::make('📥 Recebidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('direction', 'inbound'))
                ->badge(fn () => WhatsAppLog::where('direction', 'inbound')->count())
                ->badgeColor('success'),

            'errors' => Tab::make('⚠️ Falhas & Erros')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['failed', 'error']))
                ->badge(fn () => WhatsAppLog::whereIn('status', ['failed', 'error'])->count())
                ->badgeColor('danger'),

            'system' => Tab::make('⚙️ Eventos de Sistema')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('direction', 'system'))
                ->badge(fn () => WhatsAppLog::where('direction', 'system')->count())
                ->badgeColor('gray'),
        ];
    }
}
