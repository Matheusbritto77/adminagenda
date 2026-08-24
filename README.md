# adminagenda

Painel administrativo separado para a Agendae.

Stack inicial:

- Laravel 13
- Filament 5
- PHP 8.3+
- Integração gRPC com o serviço `agenwpp`

Objetivos do repo:

- administrar empresas, usuários, permissões e automações
- consumir o serviço WhatsApp em um processo isolado
- manter o painel desacoplado do worker do WhatsApp

Estrutura base de integração:

- `app/Providers/Filament/AdminPanelProvider.php` - painel `/admin`
- `app/Services/WhatsApp/GrpcBridgeClient.php` - cliente gRPC do worker WhatsApp
- `config/whatsapp.php` - configuração de conexão com o serviço Node

Próximo passo:

1. Registrar os recursos do Filament para empresas, equipes e automações.
2. Implementar o cliente gRPC com idempotência e retries.
3. Conectar a fila para envio/recebimento de mensagens.
