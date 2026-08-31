<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppQueueResource\Pages;
use App\Models\WhatsAppNotificationQueue;
use App\Services\WhatsApp\GrpcBridgeClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppQueueResource extends Resource
{
    protected static ?string $model = WhatsAppNotificationQueue::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Fila do WhatsApp';

    protected static ?string $modelLabel = 'Mensagem Enfileirada';

    protected static ?string $pluralModelLabel = 'Fila de Disparos WhatsApp';

    protected static ?int $navigationSort = 11;

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

                TextColumn::make('user.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('recipient_name')
                    ->label('Destinatário')
                    ->searchable(),

                TextColumn::make('recipient_phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),

                TextColumn::make('message_type')
                    ->label('Tipo de Mensagem')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booking_created' => 'info',
                        'reminder' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'pix_payment' => 'emerald',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booking_created' => 'Novo Agendamento',
                        'reminder' => 'Lembrete Antecipado',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        'pix_payment' => 'Cobrança PIX',
                        default => ucfirst($state),
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Enviado / Processado',
                        'processing' => 'Processando',
                        'failed' => 'Falhou',
                        'pending' => 'Pendente',
                        default => ucfirst($state),
                    }),

                TextColumn::make('message_body')
                    ->label('Conteúdo')
                    ->limit(45)
                    ->tooltip(fn ($record) => $record->message_body),

                TextColumn::make('error_message')
                    ->label('Erro')
                    ->limit(25)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('scheduled_for')
                    ->label('Agendado para')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Aguardando envio')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'processing' => 'Processando',
                        'sent' => 'Enviado / Processado',
                        'failed' => 'Falhou',
                    ]),

                SelectFilter::make('message_type')
                    ->label('Tipo')
                    ->options([
                        'booking_created' => 'Novo Agendamento',
                        'reminder' => 'Lembrete Antecipado',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        'pix_payment' => 'Cobrança PIX',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppQueues::route('/'),
        ];
    }
}
