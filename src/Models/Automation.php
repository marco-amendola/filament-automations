<?php

namespace Automations\FilamentAutomations\Models;

use Illuminate\Database\Eloquent\Model;
use Automations\FilamentAutomations\AutomationActions\BaseAction;

class Automation extends Model
{
    protected $table = 'filament_automations';

    protected $casts = [
        'enabled' => 'boolean',
        'trigger' => 'array',
        'actions' => 'array',
    ];

    protected $guarded = [];

    public function shouldTrigger(Model $model): bool
    {
        // All Triggers must return true
        return collect($this->trigger[0]['triggers'])->map(function ($trigger) use ($model) {
            $field = $trigger['field'];
            $operator = $trigger['operator'];
            $value = $trigger['value'];

            $modelValue = $model->{$field};

            return match ($operator) {
                '==' => $modelValue == $value,
                '===' => $modelValue === $value,
                '!=' => $modelValue != $value,
                '!==' => $modelValue !== $value,
                '>' => $modelValue > $value,
                '<' => $modelValue < $value,
                '>=' => $modelValue >= $value,
                '<=' => $modelValue <= $value,
                default => false,
            };
        })->filter(fn($trigger) => $trigger === true)->count() === count($this->trigger[0]['triggers']);
    }
    public function runActions(Model $model): void
    {
        collect($this->actions)->each(function ($action) use ($model) {
            // 1️⃣ Sostituiamo tutti i tag con i valori reali
            $action = array_map(fn($value) => is_string($value) ? $this->replaceSmartTags($model, $value) : $value, $action);

            // 2️⃣ Recuperiamo la classe dell'azione
            $actionClass = $action['action_class'];

            if (!class_exists($actionClass)) {
                Log::error("Classe azione non trovata: {$actionClass}");
                return;
            }

            // 3️⃣ Gestione del delay
            $has_delay = $action['delay_enabled'];
            $delay_number = (int) $action['delay_number'];

            $actionInstance = new $actionClass($action, $model);
            if ($has_delay && $delay_number > 0) {
                $delay_unit = $action['delay_unit'];
                $actionInstance::dispatch($action, $model)->delay(now()->add($delay_number, $delay_unit));
            } else {
                $actionInstance::dispatch($action, $model);
            }
        });
    }

    public function replaceSmartTags(Model $record, string $text)
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($record) {
            return $this->resolveToken($record, $matches[1]) ?? '';
        }, $text);
    }

    private function resolveToken(Model $record, string $token)
    {
        $parts = explode('::', $token);
        $field = array_shift($parts);

        // 🔹 Se il campo è una relazione Eloquent, carica i dati
        if (method_exists($record, $field)) {
            $relation = $record->$field();

            if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                // 🔹 Se è HasOneThrough o BelongsTo, prendi il primo elemento
                if (
                    $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOneThrough ||
                    $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo
                ) {
                    $related = $relation->first();
                    $value = $related ? $related->toArray() : [];
                } else {
                    $value = $relation->get()->toArray();
                }
            }
        } else {
            // 🔹 Se non è una relazione, è un campo normale (anche JSON)
            $value = $record->$field ?? null;
        }

        // 🔹 Se è NULL, restituisci stringa vuota
        if (is_null($value)) {
            return '';
        }

        // 🔹 Se è JSON o array, naviga nei dati
        if (is_array($value)) {
            foreach ($parts as $subfield) {
                if (isset($value[$subfield])) {
                    $value = $value[$subfield];
                } else {
                    return '';
                }
            }
        }

        return $this->applyTransformations($value, $parts);
    }


    private function applyTransformations($value, array $commands)
    {
        foreach ($commands as $command) {
            if ($command === 'first' && is_array($value)) {
                $value = reset($value);
            } elseif (str_starts_with($command, 'pluck(')) {
                preg_match('/pluck\(([^)]+)\)/', $command, $matches);
                $field = $matches[1] ?? null;
                if ($field && is_array($value)) {
                    $value = array_column($value, $field);
                }
            } elseif (str_starts_with($command, 'limit(')) {
                preg_match('/limit\((\d+)\)/', $command, $matches);
                $limit = (int) ($matches[1] ?? 0);
                if ($limit > 0 && is_array($value)) {
                    $value = array_slice($value, 0, $limit);
                }
            } elseif (str_starts_with($command, 'where(')) {
                preg_match('/where\(([^)]+)\)/', $command, $matches);
                if (isset($matches[1]) && is_array($value)) {
                    [$field, $operator, $filterValue] = preg_split('/(>=|<=|=|>|<)/', $matches[1], 2, PREG_SPLIT_DELIM_CAPTURE);
                    $value = array_values(array_filter($value, function ($item) use ($field, $operator, $filterValue) {
                        return isset($item[$field]) && match ($operator) {
                            '>' => $item[$field] > $filterValue,
                            '<' => $item[$field] < $filterValue,
                            '>=' => $item[$field] >= $filterValue,
                            '<=' => $item[$field] <= $filterValue,
                            '=' => $item[$field] == $filterValue,
                            default => false,
                        };
                    }));
                }
            }
        }

        return is_array($value) ? json_encode($value) : $value;
    }
}
