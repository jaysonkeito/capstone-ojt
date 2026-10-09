<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\OjtLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one trail entry per model event for everything the System Admin's
 * activity log covers. Attach per model in AppServiceProvider — the observer
 * is generic and reads each model's auditLabel() for the human-readable line.
 */
class AuditObserver
{
    /** Updates to these columns are never written to the trail. */
    private const SKIP = [...AuditLog::IGNORED, ...AuditLog::REDACTED];

    /**
     * Set while the kiosk recorder writes scan rows — those carry their own
     * explicit time-in/time-out trail entries (recorded by the caller with
     * the intern as the actor), so the generic created/updated lines would
     * only duplicate them.
     */
    public static bool $quiet = false;

    public function created(Model $model): void
    {
        if ($this->silenced($model)) {
            return;
        }

        AuditLog::record('created', $model);
    }

    public function updated(Model $model): void
    {
        if ($this->silenced($model)) {
            return;
        }

        $changes = [];

        foreach ($model->getChanges() as $field => $new) {
            if (in_array($field, self::SKIP, true)) {
                continue;
            }

            $old = $model->getOriginal($field);

            if ($old === $new) {
                continue;
            }

            $changes[$field] = ['old' => $this->displayable($old), 'new' => $this->displayable($new)];
        }

        // Attribute-only touches (timestamps, token refreshes) are noise.
        if ($changes === []) {
            return;
        }

        AuditLog::record('updated', $model, $changes);
    }

    public function deleted(Model $model): void
    {
        if ($this->silenced($model)) {
            return;
        }

        AuditLog::record('deleted', $model);
    }

    public function restored(Model $model): void
    {
        if ($this->silenced($model)) {
            return;
        }

        AuditLog::record('restored', $model);
    }

    /**
     * A change value the trail's diff view can print. Array/json casts
     * (a log's scan captures, the settings' working days) arrive as
     * arrays from getChanges()/getOriginal() — store their JSON form so
     * rendering never hits an array-to-string conversion.
     */
    private function displayable(mixed $value): mixed
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
    }

    private function silenced(Model $model): bool
    {
        return self::$quiet && $model instanceof OjtLog;
    }
}
