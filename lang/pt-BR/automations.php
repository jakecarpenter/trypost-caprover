<?php

declare(strict_types=1);

return [
    'title' => 'Automações',
    'default_name' => 'Nova automação',

    'actions' => [
        'new' => 'Nova automação',
        'edit' => 'Editar',
        'save' => 'Salvar',
        'activate' => 'Ativar',
        'pause' => 'Pausar',
        'delete' => 'Excluir',
        'retry' => 'Tentar novamente',
    ],

    'tabs' => [
        'build' => 'Montar',
        'variables' => 'Variáveis',
        'test' => 'Testar',
    ],

    'categories' => [
        'sources' => 'Fontes',
        'content' => 'Conteúdo',
        'flow' => 'Fluxo',
        'output' => 'Saída',
    ],

    'variables' => [
        'title' => 'Variáveis do workflow',
        'hint' => 'Valores reutilizáveis referenciados em qualquer lugar com {{ variables.KEY }}. Armazenados encriptados.',
        'empty' => 'Nenhuma variável ainda.',
        'key' => 'Chave',
        'value' => 'Valor',
        'key_placeholder' => 'API_KEY',
        'value_placeholder' => 'Valor',
        'add' => 'Nova variável',
    ],

    'test' => [
        'description' => 'Executa a automação ponta a ponta usando um payload de gatilho sintético. Útil pra validar cada nó sem esperar o agendamento ou o feed real.',
        'starting' => 'Iniciando execução de teste…',
        'in_progress' => 'Em andamento',
        'completed' => 'Concluído',
        'failed' => 'Falhou',
        'waiting' => 'Aguardando',
        'close' => 'Fechar',
        'no_node_runs' => 'Aguardando o primeiro nó começar…',
        'node_input' => 'Entrada',
        'node_output' => 'Saída',
        'node_error' => 'Erro',
        'no_new_items' => 'Nenhum item novo — nada foi executado adiante.',
        'error_starting' => 'Não foi possível iniciar a execução de teste.',
        'with_real_data' => 'Com dados reais',
        'run' => 'Rodar teste',
        'idle_hint' => 'Clique em Rodar teste para executar a automação de ponta a ponta.',
        'real_data_hint' => 'Este teste vai publicar posts, avançar watermarks e disparar efeitos colaterais externos.',
        'dry_badge' => 'Teste seco',
    ],

    'status' => [
        'draft' => 'Rascunho',
        'active' => 'Ativa',
        'paused' => 'Pausada',
    ],

    'index' => [
        'empty_title' => 'Nenhuma automação ainda',
        'empty_description' => 'Crie sua primeira automação para começar a publicar no piloto automático.',
        'columns' => [
            'name' => 'Nome',
            'status' => 'Status',
            'created' => 'Criada em',
        ],
    ],

    'show' => [
        'activated' => 'Ativada',
        'tabs' => [
            'overview' => 'Visão geral',
            'runs' => 'Execuções',
            'trigger_items' => 'Itens do trigger',
        ],
        'canvas_placeholder' => 'Pré-visualização do canvas (somente leitura)',
        'empty_runs' => 'Nenhuma execução ainda.',
        'empty_trigger_items' => 'Nenhum item de trigger ainda.',
        'started' => 'Iniciada',
        'run_label' => 'Execução',
    ],

    'form' => [
        'activate_error_fallback' => 'Não foi possível ativar a automação.',
        'pause_error_fallback' => 'Não foi possível pausar a automação.',
        'save_error_fallback' => 'Não foi possível salvar a automação.',
        'save_success' => 'Automação salva.',
        'empty_canvas_title' => 'Comece a construir sua automação',
        'empty_canvas_description' => 'Arraste um nó do painel esquerdo para começar.',
        'name_placeholder' => 'Automação sem título',
    ],

    'nodes' => [
        'trigger' => 'Trigger',
        'generate' => 'Gerar',
        'delay' => 'Esperar',
        'condition' => 'Condição',
        'publish' => 'Publicar',
        'webhook' => 'Webhook',
        'end' => 'Encerrar',
        'end_summary' => 'Encerra a automação aqui',
        'fetch_rss' => 'Buscar RSS',
        'http_request' => 'Requisição HTTP',
        'handles' => [
            'items' => 'tem itens',
            'no_items' => 'sem itens',
        ],
    ],

    'config' => [
        'select_placeholder' => 'Selecione…',
        'invalid_json' => 'Isto ainda não é um JSON válido.',

        'trigger' => [
            'type' => 'Tipo de trigger',
            'types' => [
                'schedule' => 'Agendamento',
                'post_published' => 'Quando um post é publicado',
                'post_scheduled' => 'Quando um post é agendado',
            ],
            'post_published_hint' => 'Roda toda vez que algum post nesta workspace é publicado. O post fica disponível em {{ trigger.post }} pros próximos nós.',
            'post_scheduled_hint' => 'Roda toda vez que algum post nesta workspace é agendado. O post fica disponível em {{ trigger.post }}.',

            'schedule' => [
                'field' => 'Intervalo de disparo',
                'fields' => [
                    'minutes' => 'Minutos',
                    'hours' => 'Horas',
                    'days' => 'Dias',
                    'weeks' => 'Semanas',
                    'months' => 'Meses',
                    'custom' => 'Personalizado (Cron)',
                ],
                'minutes_interval' => 'Minutos entre disparos',
                'hours_interval' => 'Horas entre disparos',
                'days_interval' => 'Dias entre disparos',
                'hour' => 'Disparar na hora',
                'minute' => 'Disparar no minuto',
                'weekdays' => 'Disparar nos dias',
                'day_of_month' => 'Dia do mês',
                'custom_cron' => 'Expressão cron',
                'custom_cron_hint' => 'Formato: minuto hora dia mês dia-da-semana',
                'timezone_hint' => 'Todos os horários em :tz',
                'weekday_names' => [
                    'sun' => 'Dom',
                    'mon' => 'Seg',
                    'tue' => 'Ter',
                    'wed' => 'Qua',
                    'thu' => 'Qui',
                    'fri' => 'Sex',
                    'sat' => 'Sáb',
                ],
                'summary' => [
                    'every_n_minutes' => 'Roda a cada minuto|Roda a cada :count minutos',
                    'every_n_hours' => 'Roda a cada hora no minuto :minute|Roda a cada :count horas no minuto :minute',
                    'every_n_days' => 'Roda todo dia às :time|Roda a cada :count dias às :time',
                    'weekly' => 'Roda :days às :time',
                    'monthly' => 'Roda no dia :day de cada mês às :time',
                ],
            ],
        ],
        'generate' => [
            'social_accounts' => 'Contas sociais',
            'social_accounts_empty' => 'Nenhuma conta social conectada. Conecte uma primeiro.',
            'target_slide_count' => 'Slides a gerar',
            'prompt_template' => 'Template do prompt',
            'include_image' => 'Incluir imagem',
            'include_image_hint' => 'Gerar uma imagem com IA para esta publicação',
        ],
        'delay' => [
            'duration' => 'Duração',
            'unit' => 'Unidade',
            'units' => [
                'minutes' => 'Minutos',
                'hours' => 'Horas',
                'days' => 'Dias',
            ],
        ],
        'condition' => [
            'field' => 'Campo',
            'operator' => 'Operador',
            'operators' => [
                'contains' => 'contém',
                'not_contains' => 'não contém',
                'equals' => 'igual a',
                'not_equals' => 'diferente de',
                'matches' => 'corresponde (regex)',
                'greater_than' => 'maior que',
                'less_than' => 'menor que',
            ],
            'value' => 'Valor',
        ],
        'publish' => [
            'mode' => 'Modo',
            'modes' => [
                'now' => 'Publicar agora',
                'scheduled' => 'Agendar',
                'draft' => 'Salvar como rascunho',
            ],
            'scheduled_offset' => 'Atraso a partir do trigger (minutos)',
        ],
        'webhook' => [
            'url' => 'URL',
            'method' => 'Método',
            'payload_template' => 'Template do payload (JSON)',
        ],
        'end' => [
            'reason' => 'Motivo (opcional)',
            'reason_placeholder' => 'ex: Filtrado pela condição',
        ],
        'fetch_rss' => [
            'feed_url' => 'URL do feed',
            'feed_url_hint' => 'Na primeira execução, o watermark é setado pra "agora" pra não inundar os próximos nós com items históricos. Execuções seguintes só veem items novos.',
        ],
        'http_request' => [
            'url' => 'URL',
            'method' => 'Método',
            'auth_type' => 'Autenticação',
            'auth' => [
                'none' => 'Nenhuma (público)',
                'bearer' => 'Bearer token',
                'basic' => 'Basic auth',
                'api_key' => 'Header de API key',
            ],
            'bearer_token' => 'Bearer token',
            'basic_username' => 'Usuário',
            'basic_password' => 'Senha',
            'api_key_header' => 'Nome do header',
            'api_key_value' => 'API key',
            'body_template' => 'Template do body (JSON)',
            'headers' => 'Headers',
            'header_name' => 'Nome do header',
            'header_value' => 'Valor',
            'add_header' => 'Adicionar header',
            'polling_section' => 'Polling (opcional)',
            'polling_hint' => 'Deixe vazio para usar a resposta inteira como payload único. Preencha para extrair um array de itens e disparar um run por item.',
            'items_path' => 'Caminho dos itens',
            'item_key_path' => 'Caminho da chave do item',
            'item_date_path' => 'Caminho da data do item (opcional)',
            'item_date_path_hint' => 'Caminho JSON pro timestamp do item. Quando definido, só items mais novos que a última busca são encaminhados — evita que a primeira busca inunde os próximos nós.',
        ],
    ],

    'delete' => [
        'title' => 'Excluir automação',
        'description' => 'Tem certeza que deseja excluir esta automação? Todas as execuções e itens de gatilho também serão removidos. Esta ação não pode ser desfeita.',
        'confirm' => 'Excluir',
        'cancel' => 'Cancelar',
    ],

    'flash' => [
        'deleted' => 'Automação excluída com sucesso!',
    ],

    'errors' => [
        'no_active_social_accounts' => 'Nenhuma conta social ativa configurada para esta automação.',
        'must_have_one_trigger' => 'A automação precisa ter exatamente um nó de trigger.',
        'trigger_must_be_connected' => 'O nó de trigger precisa estar conectado a pelo menos um nó.',
        'graph_contains_cycle' => 'O grafo da automação contém um ciclo.',
        'only_failed_can_retry' => 'Apenas execuções que falharam podem ser repetidas.',
        'no_generated_post' => 'Nenhum post gerado encontrado para esta execução.',
        'webhook_server_error' => 'Erro no servidor do webhook.',
        'webhook_invalid_payload_json' => 'O template do payload não é um JSON válido.',
        'url_not_allowed' => 'A URL da requisição aponta para um endereço privado ou inacessível e foi bloqueada.',
        'node_no_longer_exists' => 'O nó :node_id não existe mais nesta automação.',
        'no_trigger_connection' => 'Nenhum nó conectado ao nó de trigger.',
    ],
];
