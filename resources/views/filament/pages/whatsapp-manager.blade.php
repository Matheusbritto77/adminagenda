<x-filament-panels::page>
    @php
        $status = $this->statusData;
        $state = $status['state'] ?? 'disconnected';
        $qrCode = $status['qr_code'] ?? '';
        $pairingCode = $status['pairing_code'] ?? '';
        $phone = $status['phone_number'] ?? '';
        $profileName = $status['profile_name'] ?? '';
        $updatedAt = $status['updated_at'] ?? '';
        $testResult = $this->connectionTestResult;
    @endphp

    {{-- Diagnostic Test Result Banner --}}
    @if($testResult)
        <div style="margin-bottom: 1.5rem;">
            @if($testResult['success'])
                <div style="padding: 1rem 1.25rem; border-radius: 0.75rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); display: flex; align-items: flex-start; gap: 0.75rem;">
                    <x-filament::icon icon="heroicon-m-check-circle" style="width: 1.5rem; height: 1.5rem; color: #10b981; flex-shrink: 0;" />
                    <div style="font-size: 0.875rem;">
                        <strong style="color: #059669;">Comunicação estabelecida com sucesso!</strong>
                        <div style="margin-top: 0.25rem; color: #374151; display: flex; flex-wrap: wrap; gap: 1rem; font-family: monospace; font-size: 0.75rem;">
                            <span>Host: <strong>{{ $testResult['host'] }}</strong></span>
                            <span>gRPC (:{{ $testResult['grpc_port'] }}): <strong>{{ $testResult['socket_connected'] ? 'ONLINE' : 'OFFLINE' }}</strong></span>
                            <span>HTTP (:{{ $testResult['http_port'] }}): <strong>{{ $testResult['http_connected'] ? 'ONLINE' : 'OFFLINE' }}</strong></span>
                            <span>Latência: <strong>{{ $testResult['latency_ms'] }}ms</strong></span>
                        </div>
                    </div>
                </div>
            @else
                <div style="padding: 1rem 1.25rem; border-radius: 0.75rem; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); display: flex; align-items: flex-start; gap: 0.75rem;">
                    <x-filament::icon icon="heroicon-m-x-circle" style="width: 1.5rem; height: 1.5rem; color: #ef4444; flex-shrink: 0;" />
                    <div style="font-size: 0.875rem;">
                        <strong style="color: #dc2626;">Falha na comunicação com o agenwpp:</strong>
                        <p style="margin-top: 0.25rem; color: #6b7280; font-size: 0.8rem;">
                            {{ $testResult['error'] ?? 'Não foi possível alcançar o host e porta configurados.' }}
                        </p>
                        <div style="margin-top: 0.25rem; font-family: monospace; font-size: 0.75rem; color: #4b5563;">
                            Host testado: <strong>{{ $testResult['host'] }}:{{ $testResult['grpc_port'] }}</strong>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;" wire:poll.4s>
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
                            @elseif($state === 'pairing_ready')
                                <x-filament::badge color="info" icon="heroicon-m-key">
                                    Código Pronto
                                </x-filament::badge>
                            @elseif($state === 'connecting' || $state === 'qr_ready')
                                <x-filament::badge color="warning" icon="heroicon-m-arrow-path">
                                    Aguardando Conexão
                                </x-filament::badge>
                            @else
                                <x-filament::badge color="gray" icon="heroicon-m-x-circle">
                                    Desconectado
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>
                </x-slot>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    @if($state !== 'connected')
                        {{-- Connection Mode Tabs --}}
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: rgba(0,0,0,0.04); padding: 0.25rem; border-radius: 0.625rem;">
                            <button
                                type="button"
                                wire:click="$set('connectionMode', 'pairing_code')"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; {{ $this->connectionMode === 'pairing_code' ? 'background: #fff; color: #000; box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'background: transparent; color: #6b7280;' }}"
                            >
                                🔢 Código de Pareamento
                            </button>
                            <button
                                type="button"
                                wire:click="$set('connectionMode', 'qr')"
                                style="padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; {{ $this->connectionMode === 'qr' ? 'background: #fff; color: #000; box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'background: transparent; color: #6b7280;' }}"
                            >
                                📸 QR Code (Câmera)
                            </button>
                        </div>

                        {{-- 🔢 PAIRING CODE TAB CONTENT --}}
                        @if($this->connectionMode === 'pairing_code')
                            <div style="display: flex; flex-direction: column; gap: 1rem; padding: 1rem; background: rgba(0,0,0,0.02); border-radius: 0.75rem; border: 1px solid rgba(0,0,0,0.08);">
                                <div>
                                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; color: #374151;">
                                        Número do WhatsApp para Parear (com DDI e DDD):
                                    </label>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input
                                            type="tel"
                                            wire:model.defer="pairingPhoneNumber"
                                            placeholder="Ex: 5511999998888"
                                            style="flex: 1; padding: 0.55rem 0.85rem; border-radius: 0.5rem; border: 1px solid #d1d5db; font-size: 0.875rem; font-family: monospace;"
                                        />
                                        <x-filament::button
                                            wire:click="connect"
                                            icon="heroicon-m-key"
                                            color="primary"
                                        >
                                            Gerar Código
                                        </x-filament::button>
                                    </div>
                                    <span style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; display: block;">
                                        Informe o número com código do país (Brasil = 55) e DDD.
                                    </span>
                                </div>

                                {{-- Display Pairing Code if available --}}
                                @if($pairingCode)
                                    <div style="margin-top: 0.5rem; padding: 1.25rem; background: #0f172a; border-radius: 0.75rem; text-align: center; border: 1px solid #1e293b;">
                                        <span style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; display: block; margin-bottom: 0.5rem;">
                                            Código de Pareamento de 8 Dígitos
                                        </span>
                                        
                                        <div style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: rgba(255,255,255,0.06); padding: 0.5rem 1.25rem; border-radius: 0.5rem; border: 1px dashed rgba(255,255,255,0.2);">
                                            <span style="font-family: monospace; font-size: 2rem; font-weight: 900; letter-spacing: 0.15em; color: #38bdf8;">
                                                {{ $pairingCode }}
                                            </span>
                                        </div>

                                        <div style="margin-top: 1rem; text-align: left; background: rgba(255,255,255,0.04); padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.75rem; color: #cbd5e1; line-height: 1.5;">
                                            <strong style="color: #38bdf8; display: block; margin-bottom: 0.25rem;">📱 Como conectar no celular:</strong>
                                            1. Abra o WhatsApp no celular.<br>
                                            2. Toque em <strong>Aparelhos conectados</strong> > <strong>Conectar um aparelho</strong>.<br>
                                            3. Na parte inferior da tela de câmera, toque em <strong>Conectar com número de telefone</strong>.<br>
                                            4. Digite o código de 8 dígitos acima.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- 📸 QR CODE TAB CONTENT --}}
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                @if($qrCode)
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; background: rgba(0,0,0,0.03); border-radius: 0.75rem; border: 1px dashed rgba(0,0,0,0.15);">
                                        <div style="padding: 0.5rem; background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <img src="{{ $qrCode }}" alt="QR Code WhatsApp" style="width: 13rem; height: 13rem; object-fit: contain;" />
                                        </div>
                                        <p style="font-size: 0.75rem; text-align: center; color: #6b7280; margin-top: 0.75rem;">
                                            Abra o WhatsApp > <strong>Aparelhos conectados</strong> > <strong>Conectar um aparelho</strong> e aponte a câmera.
                                        </p>
                                    </div>
                                @else
                                    <x-filament::button
                                        wire:click="connect"
                                        icon="heroicon-m-qr-code"
                                        color="warning"
                                        size="lg"
                                    >
                                        Gerar Novo QR Code
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Connection Metadata --}}
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        @if($phone)
                            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(0,0,0,0.06);">
                                <span style="opacity: 0.7;">Número Conectado:</span>
                                <span style="font-family: monospace; font-weight: 600; color: #059669;">+{{ $phone }}</span>
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
                        @if($state === 'connected')
                            <x-filament::button
                                wire:click="disconnect"
                                icon="heroicon-m-power"
                                color="danger"
                                size="lg"
                            >
                                Desconectar Sessão
                            </x-filament::button>
                        @endif

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <x-filament::button
                                wire:click="testBridgeConnection"
                                icon="heroicon-m-cpu-chip"
                                color="info"
                                outlined
                            >
                                Testar gRPC
                            </x-filament::button>

                            <x-filament::button
                                wire:click="refreshStatus"
                                icon="heroicon-m-arrow-path"
                                color="gray"
                                outlined
                            >
                                Atualizar
                            </x-filament::button>
                        </div>

                        <x-filament::button
                            wire:click="resetSession"
                            icon="heroicon-m-arrow-path-rounded-square"
                            color="gray"
                            size="sm"
                        >
                            Resetar Credenciais / Novo Pareamento
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
