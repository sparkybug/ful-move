<?php

use App\Models\User;
use App\Services\ShuttleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Race workers only run against a dedicated test database.');
}
$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($input['go'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race barrier timed out.');
    }
    usleep(1000);
}
try {
    $user = User::findOrFail($input['user']);
    $service = app(ShuttleService::class);
    $result = match ($input['action']) {
        'book' => $service->book($user, $input['run'], $input['key']),
        'walk' => $service->updateRun($user, $input['run'], $input['count'], null),
        'cancel' => $service->transition($user, $input['run'], 'cancelled'),
    };
    echo json_encode(['ok' => true, 'id' => $result->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'errors' => $e->errors()], JSON_THROW_ON_ERROR);
}
