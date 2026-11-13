<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB, Schema};

/** Reuse metadata and audit labels only within the current HTTP request. */
class RequestDatabaseLookup
{
    public function hasTable(string $table): bool
    {
        $request = request();
        $known = $request->attributes->get('accounting.existing_tables', []);
        if (array_key_exists($table, $known)) return $known[$table];
        $exists = Schema::hasTable($table);
        // HTTP handlers do not migrate schemas. Outside a routed request (e.g.
        // installer/tests), don't retain absence across later table creation.
        if (!$exists && !$request->route()) return false;
        $known[$table] = $exists;
        $request->attributes->set('accounting.existing_tables', $known);
        return $exists;
    }

    public function label(string $table, string $column, $id): ?string
    {
        $request = request();
        $labels = $request->attributes->get('accounting.audit_labels', []);
        if (isset($labels[$table][(string)$id][$column])) return $labels[$table][(string)$id][$column];
        if (!$this->hasTable($table)) return null;
        $value = DB::table($table)->where('id', $id)->value($column);
        if ($value !== null) {
            $labels[$table][(string)$id][$column] = $value;
            $request->attributes->set('accounting.audit_labels', $labels);
        }
        return $value;
    }

    public function forgetRecord(string $table, $id): void
    {
        $labels = request()->attributes->get('accounting.audit_labels', []);
        unset($labels[$table][(string)$id]);
        request()->attributes->set('accounting.audit_labels', $labels);
    }
}
