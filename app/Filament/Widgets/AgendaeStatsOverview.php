<?php

namespace App\Filament\Widgets;

use App\Models\AgendaeUser;
use App\Models\Appointment;
use App\Models\ClientAccount;
use App\Models\FinancialTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class AgendaeStatsOverview extends BaseWidget
{
    protected ?string $heading = 'Métricas Globais da Plataforma Agendae';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        try {
            $totalCompanies = AgendaeUser::count();
            $totalAppointments = Appointment::count();
            $todayAppointments = Appointment::whereDate('appointment_date', today())->count();
            $totalIncome = FinancialTransaction::where('type', 'income')->sum('amount');
            $totalClients = ClientAccount::count();

            return [
                Stat::make('Empresas / Contas', number_format($totalCompanies, 0, ',', '.'))
                    ->description('Cadastradas no SaaS')
                    ->descriptionIcon('heroicon-m-building-storefront')
                    ->color('primary'),

                Stat::make('Agendamentos Totais', number_format($totalAppointments, 0, ',', '.'))
                    ->description("{$todayAppointments} agendamentos hoje")
                    ->descriptionIcon('heroicon-m-calendar')
                    ->color('success'),

                Stat::make('Faturamento Registrado', 'R$ ' . number_format((float) $totalIncome, 2, ',', '.'))
                    ->description('Receitas movimentadas')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('info'),

                Stat::make('Clientes Finais', number_format($totalClients, 0, ',', '.'))
                    ->description('Base de clientes Agendae')
                    ->descriptionIcon('heroicon-m-user-group')
                    ->color('warning'),
            ];
        } catch (Throwable $e) {
            return [
                Stat::make('Banco Agendae', 'Aguardando Conexão')
                    ->description($e->getMessage())
                    ->color('danger'),
            ];
        }
    }
}
