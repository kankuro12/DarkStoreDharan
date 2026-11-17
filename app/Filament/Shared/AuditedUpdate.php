<?php

namespace App\Filament\Shared;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** Saves an admin edit and records exactly which fields changed; secret fields are masked. */
class AuditedUpdate
{
    public const SECRET_FIELDS = ['password', 'bank_account_number', 'registration_number', 'remember_token'];

    /**
     * Edit forms are filled from attributesToArray(), which omits $hidden attributes; without this an
     * untouched hidden field (bank account, registration number) would be saved back as null.
     */
    public static function withHiddenFields(array $data, Model $record): array
    {
        foreach (array_diff($record->getHidden(), ['password', 'remember_token']) as $field) {
            $data[$field] = $record->getAttribute($field);
        }

        return $data;
    }

    public static function save(Model $record, array $data, string $action): Model
    {
        $record->fill($data);
        $changed = array_keys($record->getDirty());

        if ($changed === []) {
            return $record;
        }

        $mask = fn (array $values) => collect($values)
            ->map(fn ($value, $key) => in_array($key, self::SECRET_FIELDS, true) ? '[changed]' : $value)
            ->all();

        $before = $mask(Arr::only($record->getRawOriginal(), $changed));
        $record->save();
        $after = $mask(collect($changed)->mapWithKeys(fn ($key) => [$key => $record->getRawOriginal($key)])->all());

        AuditLog::record($action, $record, $before, $after);

        return $record;
    }
}
