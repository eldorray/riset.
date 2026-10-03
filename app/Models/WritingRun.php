<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $kind
 * @property string $status
 * @property array<string, mixed> $payload
 * @property list<array<string, mixed>> $results
 * @property int $cursor
 * @property int $consecutive_failures
 * @property bool $stop_requested
 * @property bool $has_suggestions
 * @property string|null $error
 * @property CarbonInterface $updated_at
 */
class WritingRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'results' => 'array', 'stop_requested' => 'boolean', 'has_suggestions' => 'boolean'];
    }
}
