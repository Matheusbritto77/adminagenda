<x-filament-panels::page>
    @php
        $status = $this->statusData;
        $state = $status['state'] ?? 'disconnected';
        $qrCode = $status['qr_code'] ?? '';
        $phone = $status['phone_number'] ?? '';
        $profileName = $status['profile_name'] ?? '';
        $updatedAt = $status['updated_at'] ?? '';
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" wire:poll.5s>
        {{-- Status & Connection Card --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-signal class="w-5 h-5 text-amber-500" />
                        Status da Conexão
                    </h3>

                    @if($state === 'connected')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                            Conectado
                        </span>
                    @elseif($state === 'connecting' || $state === 'qr_ready')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                            <span class="w-2 h-2 rounded-full bg-amber-500 mr-1.5 animate-ping"></span>
                            Aguardando QR Code
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                            <span class="w-2 h-2 rounded-full bg-gray-400 mr-1.5"></span>
                            Desconectado
                        </span>
                    @endif
                </div>

                {{-- QR Code Display when Available --}}
                @if($qrCode && $state !== 'connected')
                    <div class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                        <div class="p-2 bg-white rounded-lg shadow-sm">
                            <img src="{{ $qrCode }}" alt="QR Code WhatsApp" class="w-48 h-48 object-contain" />
                        </div>
                        <p class="text-xs text-center text-gray-500 dark:text-gray-400">
                            Abra o WhatsApp no seu celular > <strong>Aparelhos conectados</strong> > <strong>Conectar um aparelho</strong> e aponte para a tela.
                        </p>
                    </div>
                @endif

                {{-- Connection Details --}}
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    @if($phone)
                        <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-gray-500 dark:text-gray-400">Número Conectado:</span>
                            <span class="font-mono font-semibold text-gray-900 dark:text-white">+{{ $phone }}</span>
                        </div>
                    @endif

                    @if($profileName)
                        <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-gray-500 dark:text-gray-400">Nome de Perfil:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $profileName }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-gray-500 dark:text-gray-400">Serviço gRPC:</span>
                        <span class="font-mono text-xs text-gray-700 dark:text-gray-300">
                            {{ config('whatsapp.grpc_host', '127.0.0.1') }}:{{ config('whatsapp.grpc_port', 50051) }}
                        </span>
                    </div>

                    <div class="flex justify-between py-2">
                        <span class="text-gray-500 dark:text-gray-400">Instância Tenant:</span>
                        <span class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ $this->tenantId }}</span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-2 flex flex-col gap-2">
                    @if($state !== 'connected')
                        <button
                            type="button"
                            wire:click="connect"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm rounded-xl transition duration-150 shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            <x-heroicon-o-qr-code class="w-5 h-5" />
                            <span>Conectar / Gerar QR Code</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="disconnect"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-xl transition duration-150 shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                            <x-heroicon-o-power class="w-5 h-5" />
                            <span>Desconectar Sessão</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click="refreshStatus"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium text-sm rounded-xl transition duration-150"
                    >
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        <span>Atualizar Status</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Test Message Card --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-o-paper-airplane class="w-5 h-5 text-amber-500" />
                            Enviar Mensagem de Teste
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Dispare uma mensagem via bridge gRPC para testar a comunicação com o serviço Baileys.
                        </p>
                    </div>
                </div>

                <form wire:submit.prevent="sendTestMessage" class="space-y-4">
                    {{ $this->form }}

                    <div class="pt-4 flex justify-end">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm rounded-xl transition duration-150 shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            <x-heroicon-o-paper-airplane class="w-4 h-4" />
                            <span>Enviar Mensagem</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
