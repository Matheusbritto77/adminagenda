<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationLabel = 'Serviços';

    protected static ?string $modelLabel = 'Serviço';

    protected static ?string $pluralModelLabel = 'Serviços Cadastrados';

    protected static ?string $navigationGroup = 'Gestão Agendae';

    protected static ?int $navigationSort = 4;

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

                TextColumn::make('name')
                    ->label('Nome do Serviço')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Preço (R$)')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('duration_minutes')
                    ->label('Duração (min)')
                    ->suffix(' min')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
        ];
    }
}
