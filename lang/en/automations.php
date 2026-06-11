<?php

declare(strict_types=1);

return [
    'title' => 'Automations',
    'default_name' => 'New automation',

    'actions' => [
        'new' => 'New automation',
        'edit' => 'Edit',
        'save' => 'Save',
        'activate' => 'Activate',
        'pause' => 'Pause',
        'delete' => 'Delete',
        'retry' => 'Retry',
    ],

    'tabs' => [
        'build' => 'Build',
        'variables' => 'Variables',
        'test' => 'Test',
    ],

    'categories' => [
        'sources' => 'Sources',
        'content' => 'Content',
        'flow' => 'Flow',
        'output' => 'Output',
    ],

    'variables' => [
        'title' => 'Workflow variables',
        'hint' => 'Reusable values referenced anywhere with {{ variables.KEY }}. Stored encrypted.',
        'empty' => 'No variables yet.',
        'key' => 'Key',
        'value' => 'Value',
        'key_placeholder' => 'API_KEY',
        'value_placeholder' => 'Value',
        'add' => 'New variable',
    ],

    'test' => [
        'description' => 'Runs the automation end-to-end using a synthesized trigger payload. Useful for validating each node without waiting for the real schedule or feed.',
        'starting' => 'Starting test run…',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'waiting' => 'Waiting',
        'close' => 'Close',
        'no_node_runs' => 'Waiting for the first node to start…',
        'node_input' => 'Input',
        'node_output' => 'Output',
        'node_error' => 'Error',
        'no_new_items' => 'No new items — nothing downstream ran.',
        'error_starting' => 'Could not start the test run.',
        'with_real_data' => 'With real data',
        'run' => 'Run test',
        'idle_hint' => 'Hit Run test to execute the automation end-to-end.',
        'real_data_hint' => 'This test will publish posts, advance polling watermarks, and trigger external side effects.',
        'dry_badge' => 'Dry run',
    ],

    'status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'paused' => 'Paused',
    ],

    'index' => [
        'empty_title' => 'No automations yet',
        'empty_description' => 'Create your first automation to start publishing on autopilot.',
        'columns' => [
            'name' => 'Name',
            'status' => 'Status',
            'created' => 'Created',
        ],
    ],

    'show' => [
        'activated' => 'Activated',
        'tabs' => [
            'overview' => 'Overview',
            'runs' => 'Runs',
            'trigger_items' => 'Trigger items',
        ],
        'canvas_placeholder' => 'Canvas preview (read-only)',
        'empty_runs' => 'No runs yet.',
        'empty_trigger_items' => 'No trigger items yet.',
        'started' => 'Started',
        'run_label' => 'Run',
    ],

    'form' => [
        'activate_error_fallback' => 'Could not activate automation.',
        'pause_error_fallback' => 'Could not pause automation.',
        'save_error_fallback' => 'Could not save automation.',
        'save_success' => 'Automation saved.',
        'empty_canvas_title' => 'Start building your automation',
        'empty_canvas_description' => 'Drag a node from the left panel to get started.',
        'name_placeholder' => 'Untitled automation',
    ],

    'nodes' => [
        'trigger' => 'Trigger',
        'generate' => 'Generate',
        'delay' => 'Delay',
        'condition' => 'Condition',
        'publish' => 'Publish',
        'webhook' => 'Webhook',
        'end' => 'End',
        'end_summary' => 'Stops the automation here',
        'fetch_rss' => 'Fetch RSS',
        'http_request' => 'HTTP Request',
        'handles' => [
            'items' => 'has items',
            'no_items' => 'no items',
        ],
    ],

    'config' => [
        'select_placeholder' => 'Select…',
        'invalid_json' => 'This isn’t valid JSON yet.',

        'trigger' => [
            'type' => 'Trigger type',
            'types' => [
                'schedule' => 'Schedule',
                'post_published' => 'When a post is published',
                'post_scheduled' => 'When a post is scheduled',
            ],
            'post_published_hint' => 'Runs whenever any post in this workspace is published. The published post becomes available at {{ trigger.post }} for downstream nodes.',
            'post_scheduled_hint' => 'Runs whenever any post in this workspace is scheduled. The scheduled post is available at {{ trigger.post }}.',

            'schedule' => [
                'field' => 'Trigger interval',
                'fields' => [
                    'minutes' => 'Minutes',
                    'hours' => 'Hours',
                    'days' => 'Days',
                    'weeks' => 'Weeks',
                    'months' => 'Months',
                    'custom' => 'Custom (Cron)',
                ],
                'minutes_interval' => 'Minutes between triggers',
                'hours_interval' => 'Hours between triggers',
                'days_interval' => 'Days between triggers',
                'hour' => 'Trigger at hour',
                'minute' => 'Trigger at minute',
                'weekdays' => 'Trigger on weekdays',
                'day_of_month' => 'Day of month',
                'custom_cron' => 'Cron expression',
                'custom_cron_hint' => 'Format: minute hour day month weekday',
                'timezone_hint' => 'All times in :tz',
                'weekday_names' => [
                    'sun' => 'Sun',
                    'mon' => 'Mon',
                    'tue' => 'Tue',
                    'wed' => 'Wed',
                    'thu' => 'Thu',
                    'fri' => 'Fri',
                    'sat' => 'Sat',
                ],
                'summary' => [
                    'every_n_minutes' => 'Runs every minute|Runs every :count minutes',
                    'every_n_hours' => 'Runs every hour at minute :minute|Runs every :count hours at minute :minute',
                    'every_n_days' => 'Runs every day at :time|Runs every :count days at :time',
                    'weekly' => 'Runs every :days at :time',
                    'monthly' => 'Runs on day :day of every month at :time',
                ],
            ],
        ],
        'generate' => [
            'social_accounts' => 'Social accounts',
            'social_accounts_empty' => 'No connected social accounts. Connect one first.',
            'target_slide_count' => 'Slides to generate',
            'prompt_template' => 'Prompt template',
            'include_image' => 'Include image',
            'include_image_hint' => 'Generate an AI image for this post',
        ],
        'delay' => [
            'duration' => 'Duration',
            'unit' => 'Unit',
            'units' => [
                'minutes' => 'Minutes',
                'hours' => 'Hours',
                'days' => 'Days',
            ],
        ],
        'condition' => [
            'field' => 'Field',
            'operator' => 'Operator',
            'operators' => [
                'contains' => 'contains',
                'not_contains' => 'not contains',
                'equals' => 'equals',
                'not_equals' => 'not equals',
                'matches' => 'matches (regex)',
                'greater_than' => 'greater than',
                'less_than' => 'less than',
            ],
            'value' => 'Value',
        ],
        'publish' => [
            'mode' => 'Mode',
            'modes' => [
                'now' => 'Publish now',
                'scheduled' => 'Schedule',
                'draft' => 'Save as draft',
            ],
            'scheduled_offset' => 'Offset from trigger (minutes)',
        ],
        'webhook' => [
            'url' => 'URL',
            'method' => 'Method',
            'payload_template' => 'Payload template (JSON)',
        ],
        'end' => [
            'reason' => 'Reason (optional)',
            'reason_placeholder' => 'e.g. Filtered out by condition',
        ],
        'fetch_rss' => [
            'feed_url' => 'Feed URL',
            'feed_url_hint' => 'On first run, the watermark is set to "now" so historical items don\'t flood downstream nodes. Subsequent runs only see items newer than the previous poll.',
        ],
        'http_request' => [
            'url' => 'URL',
            'method' => 'Method',
            'auth_type' => 'Authentication',
            'auth' => [
                'none' => 'None (public)',
                'bearer' => 'Bearer token',
                'basic' => 'Basic auth',
                'api_key' => 'API key header',
            ],
            'bearer_token' => 'Bearer token',
            'basic_username' => 'Username',
            'basic_password' => 'Password',
            'api_key_header' => 'Header name',
            'api_key_value' => 'API key',
            'body_template' => 'Body template (JSON)',
            'headers' => 'Headers',
            'header_name' => 'Header name',
            'header_value' => 'Value',
            'add_header' => 'Add header',
            'polling_section' => 'Polling (optional)',
            'polling_hint' => 'Leave blank to use the whole response as a single payload. Fill in to extract an array of items and spawn one run per item.',
            'items_path' => 'Items path',
            'item_key_path' => 'Item key path',
            'item_date_path' => 'Item date path (optional)',
            'item_date_path_hint' => 'JSON path to the item timestamp. When set, only items newer than the previous fetch are forwarded — prevents the first fetch from flooding downstream nodes.',
        ],
    ],

    'delete' => [
        'title' => 'Delete automation',
        'description' => 'Are you sure you want to delete this automation? All runs and trigger items will also be removed. This action cannot be undone.',
        'confirm' => 'Delete',
        'cancel' => 'Cancel',
    ],

    'flash' => [
        'deleted' => 'Automation deleted successfully!',
    ],

    'errors' => [
        'no_active_social_accounts' => 'No active social accounts configured for this automation.',
        'must_have_one_trigger' => 'Automation must have exactly one trigger node.',
        'trigger_must_be_connected' => 'Trigger node must be connected to at least one node.',
        'graph_contains_cycle' => 'Automation graph contains a cycle.',
        'only_failed_can_retry' => 'Only failed runs can be retried.',
        'no_generated_post' => 'No generated post found on run.',
        'webhook_server_error' => 'Webhook server error.',
        'webhook_invalid_payload_json' => 'The payload template is not valid JSON.',
        'url_not_allowed' => 'The request URL points to a private or unreachable address and was blocked.',
        'node_no_longer_exists' => 'Node :node_id no longer exists in the automation.',
        'no_trigger_connection' => 'No node connected to the Trigger node.',
    ],
];
