<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentFlowLogResource\Pages;
use App\Models\AppointmentFlowLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentFlowLogResource extends Resource
{
    protected static ?string $model = AppointmentFlowLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Logs do Agendae (App)';

    protected static ?string $modelLabel = 'Log de Fluxo';

    protected static ?string $pluralModelLabel = 'Logs de Fluxo do Agendamento';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'email' => 'info',
                        'payment' => 'warning',
                        'system' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => '💬 WhatsApp',
                        'email' => '✉️ E-mail',
                        'payment' => '💳 PIX / Pagamento',
                        'system' => '⚙️ Sistema',
                        default => ucfirst($state),
                    }),

                TextColumn::make('level')
                    ->label('Nível')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'info' => 'info',
                        'warning' => 'warning',
                        'error', 'danger' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('title')
                    ->label('Evento / Ação')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('appointment_id')
                    ->label('Agendamento')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('user.name')
                    ->label('Empresa')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('description')
                    ->label('Descrição / Detalhe')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email' => 'E-mail',
                        'payment' => 'PIX / Pagamento',
                        'system' => 'Sistema',
                    ]),

                SelectFilter::make('level')
                    ->label('Nível')
                    ->options([
                        'success' => 'Sucesso',
                        'info' => 'Informativo',
                        'warning' => 'Aviso / Pendente',
                        'error' => 'Erro / Falha',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointmentFlowLogs::route('/'),
        ];
    }
}
