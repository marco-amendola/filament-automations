<?php

namespace Automations\FilamentAutomations\Observers;

use Illuminate\Database\Eloquent\Model;
use Automations\FilamentAutomations\Models\Automation;

class ModelObserver
{
    private function processAutomations(Model $model, string $event): void
    {
        if (! method_exists($model, 'getAutomations')) {
            return;
        }
        $automations = $model->getAutomations();

        $automations
            ->filter(fn (Automation $automation) => $automation->trigger[0]['event'] === $event)
            ->each(function (Automation $automation) use ($model) {
                if ($automation->shouldTrigger($model) && $automation->enabled) {
                    $automation->runActions($model);
                }
            });
    }

    public function created(Model $model): void
    {
        $this->processAutomations($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->processAutomations($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->processAutomations($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->processAutomations($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        $this->processAutomations($model, 'forceDeleted');
    }
}
