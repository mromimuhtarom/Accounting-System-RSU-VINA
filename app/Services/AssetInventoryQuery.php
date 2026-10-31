<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetInventoryQuery
{
    public function build(Request $request): Builder
    {
        $d = $request->validate(['q' => 'nullable|string|max:255', 'type' => ['nullable', Rule::in(array_keys(Asset::TYPES))],
            'location' => 'nullable|string|max:255', 'condition' => ['nullable', Rule::in(['GOOD', 'BROKEN', 'INACTIVE', 'UNKNOWN'])]]);

        return Asset::query()->when(! empty($d['q']), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$d['q'].'%')->orWhere('code', 'like', '%'.$d['q'].'%')->orWhere('serial_number', 'like', '%'.$d['q'].'%')->orWhere('qr_token', str_replace('RSUVE:', '', $d['q']))))
            ->when(! empty($d['type']), fn ($q) => $q->where('inventory_type', $d['type']))
            ->when(! empty($d['location']), fn ($q) => $q->where('location', $d['location']))
            ->when(! empty($d['condition']), fn ($q) => $q->where('condition',$d['condition']));
    }
}
