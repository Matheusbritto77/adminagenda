<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgendaeUserResource\Pages;
use App\Models\AgendaeUser;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgendaeUserResource extends Resource
{
    protected static ?string $model = AgendaeUser::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Empresas & Contas';

    protected static ?string $modelLabel = 'Empresa / Conta';

    protected static ?string $pluralModelLabel = 'Empresas & Contas';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão Agendae';
    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome / Razão')
                    ->required(),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required(),

                TextInput::make('subdomain')
                    ->label('Subdomínio'),

                TextInput::make('custom_domain')
                    ->label('Domínio Personalizado'),

                TextInput::make('role_title')
                    ->label('Cargo / Título'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nome / Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('subdomain')
                    ->label('Subdomínio')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('custom_domain')
                    ->label('Domínio Próprio')
                    ->placeholder('Não configurado')
                    ->searchable(),

                TextColumn::make('appointments_count')
                    ->label('Agendamentos')
                    ->counts('appointments')
                    ->sortable(),

                TextColumn::make('services_count')
                    ->label('Serviços')
                    ->counts('services')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('active_domain_type')
                    ->label('Tipo de Domínio')
                    ->options([
                        'subdomain' => 'Subdomínio',
                        'custom' => 'Domínio Personalizado',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgendaeUsers::route('/'),
        ];
    }
}
