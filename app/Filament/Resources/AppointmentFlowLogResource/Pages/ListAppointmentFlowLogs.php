<?php

namespace App\Filament\Resources\AppointmentFlowLogResource\Pages;

use App\Filament\Resources\AppointmentFlowLogResource;
use App\Models\AppointmentFlowLog;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppointmentFlowLogs extends ListRecords
{
    protected static string $resource = AppointmentFlowLogResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos os Logs')
                ->badge(fn () => AppointmentFlowLog::count()),

            'whatsapp' => Tab::make('💬 WhatsApp')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'whatsapp'))
                ->badge(fn () => AppointmentFlowLog::where('channel', 'whatsapp')->count())
                ->badgeColor('success'),

            'email' => Tab::make('✉️ E-mail')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'email'))
                ->badge(fn () => AppointmentFlowLog::where('channel', 'email')->count())
                ->badgeColor('info'),

            'payment' => Tab::make('💳 PIX / Pagamentos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'payment'))
                ->badge(fn () => AppointmentFlowLog::where('channel', 'payment')->count())
                ->badgeColor('warning'),

            'errors' => Tab::make('⚠️ Erros / Falhas')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('level', ['error', 'danger']))
                ->badge(fn () => AppointmentFlowLog::whereIn('level', ['error', 'danger'])->count())
                ->badgeColor('danger'),
        ];
    }
}
