<div class="space-y-4 text-sm">
    <!-- Header info -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">Status do Envio</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide
                @if($record->status === 'sent') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20
                @elseif($record->status === 'processing') bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20
                @elseif($record->status === 'failed') bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20
                @else bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 @endif">
                @if($record->status === 'sent') ✅ Enviado com Sucesso
                @elseif($record->status === 'processing') ⏳ Processando
                @elseif($record->status === 'failed') ❌ Falhou
                @else ⏱️ Pendente na Fila @endif
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">Tipo de Mensagem</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide
                @if($record->message_type === 'booking_created') bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20
                @elseif($record->message_type === 'reminder') bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20
                @elseif($record->message_type === 'confirmed') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20
                @elseif($record->message_type === 'cancelled') bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20
                @elseif($record->message_type === 'pix_payment') bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20
                @else bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 @endif">
                @if($record->message_type === 'booking_created') 📅 Novo Agendamento
                @elseif($record->message_type === 'reminder') ⏰ Lembrete Antecipado
                @elseif($record->message_type === 'confirmed') ✨ Confirmado
                @elseif($record->message_type === 'cancelled') 🚫 Cancelado
                @elseif($record->message_type === 'pix_payment') 💰 Cobrança PIX
                @else {{ ucfirst($record->message_type) }} @endif
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">Tentativas de Disparo</span>
            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $record->attempts ?? 0 }} tentativa(s)</span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">Destinatário</span>
            <span class="font-semibold text-slate-900 dark:text-white">{{ $record->recipient_name ?: 'Não informado' }}</span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">WhatsApp</span>
            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $record->recipient_phone }}</span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5 font-medium">Empresa / Tenant</span>
            <span class="font-semibold text-slate-900 dark:text-white">{{ $record->user?->name ?? "Tenant #{$record->user_id}" }}</span>
        </div>

        @if($record->appointment_id)
            <div class="col-span-2 sm:col-span-3 pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400">Agendamento Vinculado:</span>
                    <strong class="text-slate-900 dark:text-white ml-1">#{{ $record->appointment_id }}</strong>
                    @if($record->appointment)
                        <span class="text-slate-500 dark:text-slate-400 ml-1">({{ $record->appointment->client_name }} - {{ $record->appointment->service?->name ?? 'Serviço' }})</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="col-span-2 sm:col-span-3 pt-2 border-t border-slate-200 dark:border-slate-800 grid grid-cols-2 gap-2 text-xs">
            <div>
                <span class="text-slate-500 dark:text-slate-400 block">Agendado para:</span>
                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $record->scheduled_for ? $record->scheduled_for->format('d/m/Y H:i:s') : '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 dark:text-slate-400 block">Efetivamente enviado em:</span>
                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $record->sent_at ? $record->sent_at->format('d/m/Y H:i:s') : 'Aguardando envio' }}</span>
            </div>
        </div>
    </div>

    <!-- Error / Failure Cause Banner if failed -->
    @if(!empty($record->error_message) || $record->status === 'failed')
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
            <div class="flex items-center gap-1.5 font-bold mb-1 uppercase tracking-wider">
                <span class="text-base">⚠️</span>
                <span>Motivo da Falha / Detalhe do Erro:</span>
            </div>
            <p class="font-mono text-xs whitespace-pre-wrap leading-relaxed bg-white/50 dark:bg-black/30 p-2.5 rounded-lg border border-rose-500/20">
                {{ $record->error_message ?: 'Falha desconhecida no disparo. Verifique se o agenwpp está conectado ou se o número possui WhatsApp ativo.' }}
            </p>
        </div>
    @endif

    <!-- Success Confirmation Details -->
    @if($record->status === 'sent')
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
            <span class="text-base">✅</span>
            <span>Mensagem transmitida e aceita com sucesso pelo gateway de WhatsApp!</span>
        </div>
    @endif

    <!-- Message Body -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 shadow-sm">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-2 uppercase tracking-wider">
            Conteúdo da Mensagem Disparada
        </span>
        <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 text-slate-800 dark:text-slate-200 whitespace-pre-wrap font-sans text-xs leading-relaxed">
            {{ $record->message_body ?: 'Nenhum conteúdo em texto registrado.' }}
        </div>
    </div>

    <!-- Media URL if exists -->
    @if(!empty($record->media_url))
        <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
            <span class="font-semibold block mb-1">Mídia Anexa:</span>
            <a href="{{ $record->media_url }}" target="_blank" class="text-blue-500 underline font-mono break-all">{{ $record->media_url }}</a>
        </div>
    @endif
</div>
