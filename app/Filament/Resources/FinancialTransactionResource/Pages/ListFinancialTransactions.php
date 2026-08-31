<?php

namespace App\Filament\Resources\FinancialTransactionResource\Pages;

use App\Filament\Resources\FinancialTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListFinancialTransactions extends ListRecords
{
    protected static string $resource = FinancialTransactionResource::class;
}
