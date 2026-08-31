<x-filament-panels::page>
    @php
        $status = $this->statusData;
        $state = $status['state'] ?? 'disconnected';
        $qrCode = $status['qr_code'] ?? '';
        $phone = $status['phone_number'] ?? '';
        $profileName = $status['profile_name'] ?? '';
        $updatedAt = $status['updated_at'] ?? '';
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;" wire:poll.5s>
        {{-- Status & Connection Section --}}
        <div>
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <x-filament::icon
                                icon="heroicon-o-signal"
                                style="width: 1.25rem; height: 1.25rem; color: #f59e0b;"
                            />
                            <span>Status da Conexão</span>
                        </div>

                        <div>
                            @if($state === 'connected')
                                <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                    Conectado
                                </x-filament::badge>
                            @elseif($state === 'connecting' || $state === 'qr_ready')
                                <x-filament::badge color="warning" icon="heroicon-m-arrow-path">
                                    Aguardando QR Code
                                </x-filament::badge>
                            @else
                                <x-filament::badge color="gray" icon="heroicon-m-x-circle">
                                    Desconectado
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>
                </x-slot>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    {{-- QR Code Display --}}
                    @if($qrCode && $state !== 'connected')
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; background: rgba(0,0,0,0.03); border-radius: 0.75rem; border: 1px dashed rgba(0,0,0,0.15);">
                            <div style="padding: 0.5rem; background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <img src="{{ $qrCode }}" alt="QR Code WhatsApp" style="width: 13rem; height: 13rem; object-fit: contain;" />
                            </div>
                            <p style="font-size: 0.75rem; text-align: center; color: #6b7280; margin-top: 0.75rem;">
                                Abra o WhatsApp > <strong>Aparelhos conectados</strong> > <strong>Conectar um aparelho</strong> e aponte para a tela.
                            </p>
                        </div>
                    @endif

                    {{-- Connection Metadata --}}
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        @if($phone)
                            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(0,0,0,0.06);">
                                <span style="opacity: 0.7;">Número Conectado:</span>
                                <span style="font-family: monospace; font-weight: 600;">+{{ $phone }}</span>
                            </div>
                        @endif

                        @if($profileName)
                            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(0,0,0,0.06);">
                                <span style="opacity: 0.7;">Nome de Perfil:</span>
                                <span style="font-weight: 500;">{{ $profileName }}</span>
                            </div>
                        @endif

                        <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(0,0,0,0.06);">
                            <span style="opacity: 0.7;">Serviço gRPC:</span>
                            <span style="font-family: monospace; font-size: 0.75rem;">
                                {{ config('whatsapp.grpc_host', '127.0.0.1') }}:{{ config('whatsapp.grpc_port', 50051) }}
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; padding: 0.35rem 0;">
                            <span style="opacity: 0.7;">Instância Tenant:</span>
                            <span style="font-family: monospace; font-size: 0.75rem;">{{ $this->tenantId }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                        @if($state !== 'connected')
                            <x-filament::button
                                wire:click="connect"
                                icon="heroicon-m-qr-code"
                                color="warning"
                                size="lg"
                            >
                                Conectar / Gerar QR Code
                            </x-filament::button>
                        @else
                            <x-filament::button
                                wire:click="disconnect"
                                icon="heroicon-m-power"
                                color="danger"
                                size="lg"
                            >
                                Desconectar Sessão
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            wire:click="refreshStatus"
                            icon="heroicon-m-arrow-path"
                            color="gray"
                            outlined
                        >
                            Atualizar Status
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Test Message Section --}}
        <div>
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon
                            icon="heroicon-o-paper-airplane"
                            style="width: 1.25rem; height: 1.25rem; color: #f59e0b;"
                        />
                        <span>Enviar Mensagem de Teste</span>
                    </div>
                </x-slot>

                <x-slot name="description">
                    Dispare uma mensagem via bridge gRPC para testar a comunicação com o serviço Baileys.
                </x-slot>

                <form wire:submit.prevent="sendTestMessage" style="display: flex; flex-direction: column; gap: 1rem;">
                    {{ $this->form }}

                    <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                        <x-filament::button
                            type="submit"
                            icon="heroicon-m-paper-airplane"
                            color="primary"
                        >
                            Enviar Mensagem
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
