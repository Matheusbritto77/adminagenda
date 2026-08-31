<div class="space-y-4 text-sm">
    <!-- Header info -->
    <div class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Canal / Origem</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                @if($record->channel === 'whatsapp')
                    💬 WhatsApp
                @elseif($record->channel === 'email')
                    ✉️ E-mail
                @elseif($record->channel === 'payment')
                    💳 PIX / Pagamento
                @else
                    ⚙️ Sistema
                @endif
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Nível do Evento</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide
                @if($record->level === 'success') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20
                @elseif($record->level === 'warning') bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20
                @elseif($record->level === 'error' || $record->level === 'danger') bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20
                @else bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 @endif">
                {{ $record->level }}
            </span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Data e Hora</span>
            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $record->created_at ? $record->created_at->format('d/m/Y H:i:s') : '-' }}</span>
        </div>

        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Agendamento Vinculado</span>
            @if($record->appointment_id)
                <span class="font-bold text-indigo-600 dark:text-indigo-400">#{{ $record->appointment_id }}</span>
            @else
                <span class="text-slate-400">Nenhum</span>
            @endif
        </div>

        <div class="col-span-2 pt-2 border-t border-slate-200 dark:border-slate-800">
            <span class="text-xs text-slate-500 dark:text-slate-400 block mb-0.5">Empresa / Estabelecimento</span>
            <span class="font-semibold text-slate-900 dark:text-white">{{ $record->user?->name ?? "Tenant #{$record->user_id}" }}</span>
        </div>
    </div>

    <!-- Description -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1.5 uppercase tracking-wider">Descrição Completa</span>
        <p class="text-slate-800 dark:text-slate-200 leading-relaxed font-sans">{{ $record->description ?: 'Sem descrição detalhada.' }}</p>
    </div>

    <!-- Metadata JSON -->
    @if(!empty($record->metadata))
        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs">
            <span class="text-[11px] font-bold text-slate-400 block mb-2 uppercase tracking-wider font-mono">Metadados & Payload Técnico (JSON)</span>
            <pre class="overflow-x-auto text-emerald-400 font-mono text-xs leading-relaxed">{{ json_encode($record->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
</div>
