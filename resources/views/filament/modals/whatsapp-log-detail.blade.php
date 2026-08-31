<div class="space-y-4 text-sm">
    <!-- Header info -->
    <div class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Direção</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide
                @if($record->direction === 'outbound') bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20
                @elseif($record->direction === 'inbound') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20
                @else bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 @endif">
                @if($record->direction === 'outbound') 📤 Enviada (Outbound)
                @elseif($record->direction === 'inbound') 📥 Recebida (Inbound)
                @else ⚙️ Sistema @endif
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Status</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide
                @if($record->status === 'sent' || $record->status === 'connected') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20
                @elseif($record->status === 'received') bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20
                @elseif($record->status === 'failed' || $record->status === 'error') bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20
                @else bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 @endif">
                {{ $record->status }}
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Data e Hora</span>
            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $record->created_at ? $record->created_at->format('d/m/Y H:i:s') : '-' }}</span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Telefone WhatsApp</span>
            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $record->phone ?: '-' }}</span>
        </div>

        <div class="col-span-2 pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center">
            <div>
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Tenant / Empresa</span>
                <span class="font-semibold text-slate-900 dark:text-white">{{ $record->tenant_id }}</span>
            </div>
            @if($record->message_id)
                <div class="text-right">
                    <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">ID da Mensagem</span>
                    <span class="font-mono text-xs text-slate-400">{{ $record->message_id }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Message Body -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1.5 uppercase tracking-wider">Conteúdo da Mensagem</span>
        <p class="text-slate-800 dark:text-slate-200 whitespace-pre-wrap font-sans text-xs leading-relaxed">{{ $record->message_body ?: 'Nenhum conteúdo em texto.' }}</p>
    </div>

    <!-- Error message if any -->
    @if(!empty($record->error_message))
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs">
            <span class="font-bold block mb-1 uppercase tracking-wider">Erro / Detalhe da Falha</span>
            <p class="font-mono whitespace-pre-wrap">{{ $record->error_message }}</p>
        </div>
    @endif

    <!-- Metadata JSON -->
    @if(!empty($record->metadata))
        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs">
            <span class="text-[11px] font-bold text-slate-400 block mb-2 uppercase tracking-wider font-mono">Metadados & Parâmetros (JSON)</span>
            <pre class="overflow-x-auto text-emerald-400 font-mono text-xs leading-relaxed">{{ json_encode($record->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
</div>
