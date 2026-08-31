<?php

namespace App\Filament\Resources\WhatsAppQueueResource\Pages;

use App\Filament\Resources\WhatsAppQueueResource;
use App\Models\WhatsAppNotificationQueue;
use App\Services\WhatsApp\GrpcBridgeClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsAppQueues extends ListRecords
{
    protected static string $resource = WhatsAppQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processNow')
                ->label('Processar Fila Agora')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function (GrpcBridgeClient $client) {
                    $pending = WhatsAppNotificationQueue::query()
                        ->where('status', 'pending')
                        ->where('scheduled_for', '<=', now())
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($pending->isEmpty()) {
                        Notification::make()
                            ->info()
                            ->title('Nenhuma mensagem pendente no momento')
                            ->send();
                        return;
                    }

                    $sentCount = 0;
                    $failedCount = 0;

                    foreach ($pending as $item) {
                        $item->update(['status' => 'processing', 'attempts' => $item->attempts + 1]);

                        $result = $client->sendMessage(
                            to: $item->recipient_phone,
                            body: $item->message_body,
                            tenantId: 'default'
                        );

                        if (($result['status'] ?? '') === 'sent') {
                            $item->update([
                                'status' => 'sent',
                                'sent_at' => now(),
                                'error_message' => null,
                            ]);
                            $sentCount++;
                        } else {
                            $item->update([
                                'status' => $item->attempts >= 3 ? 'failed' : 'pending',
                                'error_message' => $result['error'] ?? 'Falha no envio',
                            ]);
                            $failedCount++;
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title("Fila Processada")
                        ->body("{$sentCount} enviada(s) com sucesso. {$failedCount} com falha.")
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas as Mensagens')
                ->badge(fn () => WhatsAppNotificationQueue::count()),

            'pending' => Tab::make('Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => WhatsAppNotificationQueue::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'processing' => Tab::make('Processando')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge(fn () => WhatsAppNotificationQueue::where('status', 'processing')->count())
                ->badgeColor('info'),

            'sent' => Tab::make('Enviadas (Processadas)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent'))
                ->badge(fn () => WhatsAppNotificationQueue::where('status', 'sent')->count())
                ->badgeColor('success'),

            'failed' => Tab::make('Falhas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(fn () => WhatsAppNotificationQueue::where('status', 'failed')->count())
                ->badgeColor('danger'),
        ];
    }
}
