<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppLogResource\Pages;
use App\Models\WhatsAppLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Schema as InfolistSchema;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppLogResource extends Resource
{
    protected static ?string $model = WhatsAppLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Logs do WhatsApp';

    protected static ?string $modelLabel = 'Log do WhatsApp';

    protected static ?string $pluralModelLabel = 'Logs de Eventos do WhatsApp';

    protected static ?int $navigationSort = 12;

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

                TextColumn::make('direction')
                    ->label('Direção')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'outbound' => 'info',
                        'inbound' => 'success',
                        'system' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'outbound' => '📤 Enviada',
                        'inbound' => '📥 Recebida',
                        'system' => '⚙️ Sistema',
                        default => ucfirst($state),
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'received' => 'emerald',
                        'failed', 'error' => 'danger',
                        'connected' => 'success',
                        'disconnected' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Enviado',
                        'received' => 'Recebido',
                        'failed' => 'Falha no Envio',
                        'error' => 'Erro',
                        'connected' => 'Conectado',
                        'disconnected' => 'Desconectado',
                        default => ucfirst($state),
                    }),

                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold')
                    ->placeholder('-'),

                TextColumn::make('tenant_id')
                    ->label('Tenant / Empresa')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('message_body')
                    ->label('Conteúdo da Mensagem')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->message_body)
                    ->placeholder('-'),

                TextColumn::make('error_message')
                    ->label('Erro / Detalhe')
                    ->limit(35)
                    ->color('danger')
                    ->tooltip(fn ($record) => $record->error_message)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Direção')
                    ->options([
                        'outbound' => '📤 Enviadas',
                        'inbound' => '📥 Recebidas',
                        'system' => '⚙️ Sistema',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'sent' => 'Enviado com Sucesso',
                        'received' => 'Recebido',
                        'failed' => 'Falha no Envio',
                        'error' => 'Erro de Sistema',
                        'connected' => 'Sessão Conectada',
                        'disconnected' => 'Sessão Desconectada',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('details')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => "WhatsApp Log #{$record->id} - {$record->direction}")
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Informações da Mensagem')
                            ->columns(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('direction')
                                    ->label('Direção')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'outbound' => 'info',
                                        'inbound' => 'success',
                                        'system' => 'gray',
                                        default => 'gray',
                                    }),

                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'sent' => 'success',
                                        'received' => 'emerald',
                                        'failed', 'error' => 'danger',
                                        'connected' => 'success',
                                        'disconnected' => 'warning',
                                        default => 'gray',
                                    }),

                                \Filament\Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data / Hora')
                                    ->dateTime('d/m/Y H:i:s'),

                                \Filament\Infolists\Components\TextEntry::make('phone')
                                    ->label('Telefone WhatsApp')
                                    ->copyable(),

                                \Filament\Infolists\Components\TextEntry::make('tenant_id')
                                    ->label('Tenant / Empresa'),

                                \Filament\Infolists\Components\TextEntry::make('message_id')
                                    ->label('ID da Mensagem')
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),

                        \Filament\Infolists\Components\Section::make('Conteúdo da Mensagem')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('message_body')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->placeholder('Nenhum texto'),
                            ]),

                        \Filament\Infolists\Components\Section::make('Erro / Stack Trace')
                            ->visible(fn ($record) => !empty($record->error_message))
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('error_message')
                                    ->label('')
                                    ->color('danger')
                                    ->columnSpanFull(),
                            ]),

                        \Filament\Infolists\Components\Section::make('Metadados & Payload')
                            ->collapsed(false)
                            ->schema([
                                \Filament\Infolists\Components\KeyValueEntry::make('metadata')
                                    ->label('Parâmetros')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->recordAction('details')
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppLogs::route('/'),
        ];
    }
}
