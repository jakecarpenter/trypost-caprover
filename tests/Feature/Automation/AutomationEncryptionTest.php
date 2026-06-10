<?php

declare(strict_types=1);

use App\Http\Resources\AutomationResource;
use App\Models\Automation;
use Illuminate\Support\Facades\Crypt;

it('encrypts auth credentials inside node data on save', function () {
    $automation = Automation::factory()->create([
        'nodes' => [[
            'id' => 'http_1',
            'type' => 'http_request',
            'position' => ['x' => 0, 'y' => 0],
            'data' => [
                'url' => 'https://api.example.com',
                'auth_type' => 'bearer',
                'auth_token' => 'plain-secret-token',
            ],
        ]],
        'connections' => [],
    ]);

    $stored = $automation->fresh()->nodes[0]['data']['auth_token'];

    expect($stored)->not->toBe('plain-secret-token');
    expect(Crypt::decryptString($stored))->toBe('plain-secret-token');
});

it('does not re-encrypt already-encrypted values on subsequent saves', function () {
    $automation = Automation::factory()->create([
        'nodes' => [[
            'id' => 'http_1',
            'type' => 'http_request',
            'position' => ['x' => 0, 'y' => 0],
            'data' => ['url' => 'https://api.example.com', 'auth_type' => 'bearer', 'auth_token' => 'first-token'],
        ]],
        'connections' => [],
    ]);

    $firstCipher = $automation->fresh()->nodes[0]['data']['auth_token'];

    // Save again with the already-encrypted value (simulates editing other fields).
    $nodes = $automation->fresh()->nodes;
    $nodes[0]['data']['url'] = 'https://api.example.com/v2';
    $automation->update(['nodes' => $nodes]);

    $secondCipher = $automation->fresh()->nodes[0]['data']['auth_token'];

    expect($secondCipher)->toBe($firstCipher);
    expect(Crypt::decryptString($secondCipher))->toBe('first-token');
});

it('keeps the existing secret when the placeholder is submitted unchanged', function () {
    $automation = Automation::factory()->create([
        'nodes' => [[
            'id' => 'http_1',
            'type' => 'http_request',
            'position' => ['x' => 0, 'y' => 0],
            'data' => ['url' => 'https://api.example.com', 'auth_type' => 'bearer', 'auth_token' => 'real-token'],
        ]],
        'connections' => [],
    ]);

    $originalCipher = $automation->fresh()->nodes[0]['data']['auth_token'];

    // Simulate the frontend submitting the masked placeholder back unchanged.
    $automation->update(['nodes' => [[
        'id' => 'http_1',
        'type' => 'http_request',
        'position' => ['x' => 10, 'y' => 0],
        'data' => ['url' => 'https://api.example.com', 'auth_type' => 'bearer', 'auth_token' => Automation::SENSITIVE_PLACEHOLDER],
    ]]]);

    $afterCipher = $automation->fresh()->nodes[0]['data']['auth_token'];

    expect($afterCipher)->toBe($originalCipher);
    expect(Crypt::decryptString($afterCipher))->toBe('real-token');
});

it('masks credentials when serialized through AutomationResource', function () {
    $automation = Automation::factory()->create([
        'nodes' => [[
            'id' => 'http_1',
            'type' => 'http_request',
            'position' => ['x' => 0, 'y' => 0],
            'data' => ['url' => 'https://api.example.com', 'auth_type' => 'bearer', 'auth_token' => 'real-token'],
        ]],
        'connections' => [],
    ]);

    $serialized = AutomationResource::make($automation->fresh())->resolve();

    expect($serialized['nodes'][0]['data']['auth_token'])->toBe(Automation::SENSITIVE_PLACEHOLDER);
    expect($serialized['nodes'][0]['data']['url'])->toBe('https://api.example.com');
});
