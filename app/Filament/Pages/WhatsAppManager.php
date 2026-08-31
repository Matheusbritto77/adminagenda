<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppSession;
use App\Services\WhatsApp\GrpcBridgeClient;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WhatsAppManager extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'WhatsApp Gateway';

    protected static ?string $title = 'Gerenciador do WhatsApp';

    protected static ?string $slug = 'whatsapp';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.whatsapp-manager';

    public ?string $recipient = '';
    public ?string $message = '';
    public string $tenantId = 'default';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('recipient')
                    ->label('Número do Destinatário')
                    ->placeholder('5511999998888 (com DDI e DDD)')
                    ->tel()
                    ->required(),

                Textarea::make('message')
                    ->label('Mensagem de Teste')
                    ->placeholder('Olá! Esta é uma mensagem de teste enviada pelo Agendae Admin.')
                    ->rows(3)
                    ->required(),
            ]);
    }

    public function getSessionProperty(): ?WhatsAppSession
    {
        return WhatsAppSession::where('tenant_id', $this->tenantId)->first();
    }

    public function getStatusDataProperty(): array
    {
        $client = app(GrpcBridgeClient::class);
        return $client->getStatus($this->tenantId);
    }

    public function connect(): void
    {
        $client = app(GrpcBridgeClient::class);
        $result = $client->connect($this->tenantId);

        Notification::make()
            ->title('Conexão iniciada')
            ->body($result['message'] ?? 'Aguardando leitura do QR Code.')
            ->info()
            ->send();
    }

    public function disconnect(): void
    {
        $client = app(GrpcBridgeClient::class);
        $result = $client->disconnect($this->tenantId);

        Notification::make()
            ->title('Desconectado')
            ->body($result['message'] ?? 'WhatsApp desconectado com sucesso.')
            ->warning()
            ->send();
    }

    public function sendTestMessage(): void
    {
        $data = $this->form->getState();
        $client = app(GrpcBridgeClient::class);

        $result = $client->sendMessage($data['recipient'], $data['message'], $this->tenantId);

        if (($result['status'] ?? '') === 'sent') {
            Notification::make()
                ->title('Mensagem enviada!')
                ->body("Mensagem enviada com sucesso para {$data['recipient']}.")
                ->success()
                ->send();

            $this->form->fill(['recipient' => '', 'message' => '']);
        } else {
            Notification::make()
                ->title('Erro ao enviar mensagem')
                ->body($result['error'] ?? 'Não foi possível enviar a mensagem.')
                ->danger()
                ->send();
        }
    }

    public function refreshStatus(): void
    {
        Notification::make()
            ->title('Status atualizado')
            ->success()
            ->send();
    }
}
