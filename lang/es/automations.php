<?php

declare(strict_types=1);

return [
    'title' => 'Automatizaciones',
    'default_name' => 'Nueva automatización',

    'actions' => [
        'new' => 'Nueva automatización',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'activate' => 'Activar',
        'pause' => 'Pausar',
        'delete' => 'Eliminar',
        'retry' => 'Reintentar',
    ],

    'tabs' => [
        'build' => 'Construir',
        'variables' => 'Variables',
        'test' => 'Probar',
    ],

    'categories' => [
        'sources' => 'Fuentes',
        'content' => 'Contenido',
        'flow' => 'Flujo',
        'output' => 'Salida',
    ],

    'variables' => [
        'title' => 'Variables del workflow',
        'hint' => 'Valores reutilizables referenciados en cualquier lugar con {{ variables.KEY }}. Almacenados cifrados.',
        'empty' => 'Aún no hay variables.',
        'key' => 'Clave',
        'value' => 'Valor',
        'key_placeholder' => 'API_KEY',
        'value_placeholder' => 'Valor',
        'add' => 'Nueva variable',
    ],

    'test' => [
        'description' => 'Ejecuta la automatización de punta a punta usando un payload de disparo sintético. Útil para validar cada nodo sin esperar el cronograma o el feed real.',
        'starting' => 'Iniciando ejecución de prueba…',
        'in_progress' => 'En progreso',
        'completed' => 'Completado',
        'failed' => 'Fallido',
        'waiting' => 'Esperando',
        'close' => 'Cerrar',
        'no_node_runs' => 'Esperando que el primer nodo comience…',
        'node_input' => 'Entrada',
        'node_output' => 'Salida',
        'node_error' => 'Error',
        'no_new_items' => 'Sin elementos nuevos — no se ejecutó nada después.',
        'error_starting' => 'No se pudo iniciar la ejecución de prueba.',
        'with_real_data' => 'Con datos reales',
        'run' => 'Ejecutar prueba',
        'idle_hint' => 'Pulsa Ejecutar prueba para correr la automatización de principio a fin.',
        'real_data_hint' => 'Esta prueba publicará posts, avanzará marcadores de polling y disparará efectos secundarios externos.',
        'dry_badge' => 'Prueba seca',
    ],

    'status' => [
        'draft' => 'Borrador',
        'active' => 'Activa',
        'paused' => 'Pausada',
    ],

    'index' => [
        'empty_title' => 'Aún no hay automatizaciones',
        'empty_description' => 'Crea tu primera automatización para empezar a publicar en piloto automático.',
        'columns' => [
            'name' => 'Nombre',
            'status' => 'Estado',
            'created' => 'Creada',
        ],
    ],

    'show' => [
        'activated' => 'Activada',
        'tabs' => [
            'overview' => 'Resumen',
            'runs' => 'Ejecuciones',
            'trigger_items' => 'Elementos del disparador',
        ],
        'canvas_placeholder' => 'Vista previa del canvas (solo lectura)',
        'empty_runs' => 'Aún no hay ejecuciones.',
        'empty_trigger_items' => 'Aún no hay elementos del disparador.',
        'started' => 'Iniciada',
        'run_label' => 'Ejecución',
    ],

    'form' => [
        'activate_error_fallback' => 'No se pudo activar la automatización.',
        'pause_error_fallback' => 'No se pudo pausar la automatización.',
        'save_error_fallback' => 'No se pudo guardar la automatización.',
        'save_success' => 'Automatización guardada.',
        'empty_canvas_title' => 'Empieza a construir tu automatización',
        'empty_canvas_description' => 'Arrastra un nodo del panel izquierdo para empezar.',
        'name_placeholder' => 'Automatización sin título',
    ],

    'nodes' => [
        'trigger' => 'Disparador',
        'generate' => 'Generar',
        'delay' => 'Retraso',
        'condition' => 'Condición',
        'publish' => 'Publicar',
        'webhook' => 'Webhook',
        'end' => 'Terminar',
        'end_summary' => 'Termina la automatización aquí',
        'fetch_rss' => 'Obtener RSS',
        'http_request' => 'Petición HTTP',
        'handles' => [
            'items' => 'con elementos',
            'no_items' => 'sin elementos',
        ],
    ],

    'config' => [
        'select_placeholder' => 'Selecciona…',
        'invalid_json' => 'Esto aún no es un JSON válido.',

        'trigger' => [
            'type' => 'Tipo de disparador',
            'types' => [
                'schedule' => 'Programación',
                'post_published' => 'Cuando un post se publica',
                'post_scheduled' => 'Cuando un post se programa',
            ],
            'post_published_hint' => 'Se ejecuta cada vez que un post en este workspace se publica. El post queda disponible en {{ trigger.post }} para los siguientes nodos.',
            'post_scheduled_hint' => 'Se ejecuta cada vez que un post en este workspace se programa. El post queda disponible en {{ trigger.post }}.',

            'schedule' => [
                'field' => 'Intervalo de disparo',
                'fields' => [
                    'minutes' => 'Minutos',
                    'hours' => 'Horas',
                    'days' => 'Días',
                    'weeks' => 'Semanas',
                    'months' => 'Meses',
                    'custom' => 'Personalizado (Cron)',
                ],
                'minutes_interval' => 'Minutos entre disparos',
                'hours_interval' => 'Horas entre disparos',
                'days_interval' => 'Días entre disparos',
                'hour' => 'Disparar a la hora',
                'minute' => 'Disparar al minuto',
                'weekdays' => 'Disparar en días',
                'day_of_month' => 'Día del mes',
                'custom_cron' => 'Expresión cron',
                'custom_cron_hint' => 'Formato: minuto hora día mes día-de-semana',
                'timezone_hint' => 'Todos los horarios en :tz',
                'weekday_names' => [
                    'sun' => 'Dom',
                    'mon' => 'Lun',
                    'tue' => 'Mar',
                    'wed' => 'Mié',
                    'thu' => 'Jue',
                    'fri' => 'Vie',
                    'sat' => 'Sáb',
                ],
                'summary' => [
                    'every_n_minutes' => 'Se ejecuta cada minuto|Se ejecuta cada :count minutos',
                    'every_n_hours' => 'Se ejecuta cada hora en el minuto :minute|Se ejecuta cada :count horas en el minuto :minute',
                    'every_n_days' => 'Se ejecuta cada día a las :time|Se ejecuta cada :count días a las :time',
                    'weekly' => 'Se ejecuta :days a las :time',
                    'monthly' => 'Se ejecuta el día :day de cada mes a las :time',
                ],
            ],
        ],
        'generate' => [
            'social_accounts' => 'Cuentas sociales',
            'social_accounts_empty' => 'Sin cuentas sociales conectadas. Conecta una primero.',
            'target_slide_count' => 'Diapositivas a generar',
            'prompt_template' => 'Plantilla de prompt',
            'include_image' => 'Incluir imagen',
            'include_image_hint' => 'Generar una imagen con IA para esta publicación',
        ],
        'delay' => [
            'duration' => 'Duración',
            'unit' => 'Unidad',
            'units' => [
                'minutes' => 'Minutos',
                'hours' => 'Horas',
                'days' => 'Días',
            ],
        ],
        'condition' => [
            'field' => 'Campo',
            'operator' => 'Operador',
            'operators' => [
                'contains' => 'contiene',
                'not_contains' => 'no contiene',
                'equals' => 'es igual a',
                'not_equals' => 'no es igual a',
                'matches' => 'coincide (regex)',
                'greater_than' => 'mayor que',
                'less_than' => 'menor que',
            ],
            'value' => 'Valor',
        ],
        'publish' => [
            'mode' => 'Modo',
            'modes' => [
                'now' => 'Publicar ahora',
                'scheduled' => 'Programar',
                'draft' => 'Guardar como borrador',
            ],
            'scheduled_offset' => 'Diferencia desde el disparador (minutos)',
        ],
        'webhook' => [
            'url' => 'URL',
            'method' => 'Método',
            'payload_template' => 'Plantilla de payload (JSON)',
        ],
        'end' => [
            'reason' => 'Razón (opcional)',
            'reason_placeholder' => 'p.ej. Filtrado por la condición',
        ],
        'fetch_rss' => [
            'feed_url' => 'URL del feed',
            'feed_url_hint' => 'En la primera ejecución, el watermark se fija en "ahora" para no inundar los siguientes nodos con ítems históricos. Ejecuciones siguientes solo ven ítems nuevos.',
        ],
        'http_request' => [
            'url' => 'URL',
            'method' => 'Método',
            'auth_type' => 'Autenticación',
            'auth' => [
                'none' => 'Ninguna (pública)',
                'bearer' => 'Bearer token',
                'basic' => 'Basic auth',
                'api_key' => 'Header de API key',
            ],
            'bearer_token' => 'Bearer token',
            'basic_username' => 'Usuario',
            'basic_password' => 'Contraseña',
            'api_key_header' => 'Nombre del header',
            'api_key_value' => 'API key',
            'body_template' => 'Plantilla del body (JSON)',
            'headers' => 'Headers',
            'header_name' => 'Nombre del header',
            'header_value' => 'Valor',
            'add_header' => 'Agregar header',
            'polling_section' => 'Polling (opcional)',
            'polling_hint' => 'Deja vacío para usar la respuesta completa como un solo payload. Rellena para extraer un array de ítems y disparar un run por ítem.',
            'items_path' => 'Ruta de ítems',
            'item_key_path' => 'Ruta de clave del ítem',
            'item_date_path' => 'Ruta de fecha del ítem (opcional)',
            'item_date_path_hint' => 'Ruta JSON al timestamp del ítem. Cuando se define, solo los ítems más nuevos que la última obtención se reenvían — evita que la primera obtención inunde los siguientes nodos.',
        ],
    ],

    'delete' => [
        'title' => 'Eliminar automatización',
        'description' => '¿Estás seguro de que deseas eliminar esta automatización? Todas las ejecuciones y elementos del disparador también serán eliminados. Esta acción no se puede deshacer.',
        'confirm' => 'Eliminar',
        'cancel' => 'Cancelar',
    ],

    'flash' => [
        'deleted' => '¡Automatización eliminada correctamente!',
    ],

    'errors' => [
        'no_active_social_accounts' => 'No hay cuentas sociales activas configuradas para esta automatización.',
        'must_have_one_trigger' => 'La automatización debe tener exactamente un nodo disparador.',
        'trigger_must_be_connected' => 'El nodo disparador debe estar conectado a al menos un nodo.',
        'graph_contains_cycle' => 'El grafo de la automatización contiene un ciclo.',
        'only_failed_can_retry' => 'Solo se pueden reintentar ejecuciones fallidas.',
        'no_generated_post' => 'No se encontró un post generado en la ejecución.',
        'webhook_server_error' => 'Error del servidor del webhook.',
        'webhook_invalid_payload_json' => 'La plantilla de payload no es un JSON válido.',
        'url_not_allowed' => 'La URL de la petición apunta a una dirección privada o inaccesible y fue bloqueada.',
        'node_no_longer_exists' => 'El nodo :node_id ya no existe en la automatización.',
        'no_trigger_connection' => 'Ningún nodo está conectado al nodo disparador.',
    ],
];
