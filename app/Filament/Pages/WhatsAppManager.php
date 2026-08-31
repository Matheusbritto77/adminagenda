<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppSession;
use App\Services\WhatsApp\GrpcBridgeClient;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class WhatsAppManager extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'WhatsApp Gateway';

    protected static ?string $title = 'Gerenciador do WhatsApp';

    protected static ?string $slug = 'whatsapp';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.whatsapp-manager';

    public ?array $data = [];
    public string $tenantId = 'default';
    public ?array $connectionTestResult = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ])
            ->statePath('data');
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

    public function testBridgeConnection(): void
    {
        $client = app(GrpcBridgeClient::class);
        $result = $client->testConnection();
        $this->connectionTestResult = $result;

        if ($result['success']) {
            $ports = [];
            if ($result['socket_connected']) $ports[] = "gRPC (:{$result['grpc_port']})";
            if ($result['http_connected']) $ports[] = "HTTP (:{$result['http_port']})";
            $portsStr = implode(' e ', $ports);

            Notification::make()
                ->title('Comunicação OK!')
                ->body("Conectado com sucesso em {$result['host']} via {$portsStr} em {$result['latency_ms']}ms.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Falha de Comunicação')
                ->body("Não foi possível conectar em {$result['host']}:{$result['grpc_port']}. Detalhes: " . ($result['error'] ?? 'Sem resposta'))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function connect(): void
    {
        $client = app(GrpcBridgeClient::class);
        $result = $client->connect($this->tenantId);

        Notification::make()
            ->title('Solicitação de Conexão')
            ->body($result['message'] ?? 'Conectando ao WhatsApp...')
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

    public function resetSession(): void
    {
        $client = app(GrpcBridgeClient::class);
        $client->disconnect($this->tenantId);
        $result = $client->connect($this->tenantId);

        Notification::make()
            ->title('Sessão Reiniciada')
            ->body('As credenciais foram limpas e um novo QR Code está sendo gerado.')
            ->info()
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

            $this->form->fill();
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
