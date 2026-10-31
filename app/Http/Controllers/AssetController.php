<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetCapitalizationRequest;
use App\Http\Requests\AssetOperationRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetCapitalization;
use App\Models\AssetPeriod;
use App\Models\AssetStagingRow;
use App\Models\ChartAccount;
use App\Services\AssetAccounting;
use App\Services\AssetAudit;
use App\Services\AssetEntry;
use App\Services\AssetExcelExport;
use App\Services\AssetInventoryQuery;
use App\Services\AssetMonthlyHistory;
use App\Services\AssetOperations;
use App\Services\AssetQrCode;
use App\Services\AssetReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function create()
    {
        Gate::authorize('create', Asset::class);

        return view('assets.create', ['entryToken' => (string) Str::uuid(),
            'locations' => Asset::whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'categories' => Asset::whereNotNull('category')->distinct()->orderBy('category')->pluck('category')]);
    }

    public function store(StoreAssetRequest $request, AssetEntry $service)
    {
        $asset = $service->create($request->validated(), $request->user()->id);

        return redirect()->route('assets.show', $asset)->with('success', 'Aktiva berhasil disimpan. Lengkapi kapitalisasi Accounting bila memenuhi syarat aktiva tetap.');
    }

    public function index(Request $request, AssetAccounting $accounting)
    {
        Gate::authorize('viewAny', Asset::class);
        $q = app(AssetInventoryQuery::class)->build($request)->with('capitalization');
        $official = AssetCapitalization::where('status', 'POSTED')->whereHas('asset', fn ($s) => $s->where('status', 'ACTIVE'));
        $cost = (float) (clone $official)->sum('cost');
        $acc = (float) (clone $official)->sum('opening_accumulated');
        $dep = DB::table('asset_events')->join('assets', 'assets.id', '=', 'asset_events.asset_id')->whereNull('assets.deleted_at')->where('assets.status', 'ACTIVE')->where('type', 'DEPRECIATION')->select('data')->get()->sum(fn ($e) => (float) (json_decode($e->data, true)['amount'] ?? 0));
        $stats = ['Inventaris terverifikasi' => Asset::where('inventory_type', '!=', 'FACILITY')->where('status', 'ACTIVE')->count(), 'Aktiva tetap resmi' => (clone $official)->count(), 'Harga perolehan tervalidasi' => $cost, 'Akumulasi penyusutan' => $acc + $dep, 'Nilai buku' => $cost - $acc - $dep, 'Belum diverifikasi' => AssetStagingRow::whereIn('status', ['READY', 'REVIEW', 'DUPLICATE', 'FAILED'])->count(), 'Inventaris rusak' => Asset::where('status', 'ACTIVE')->where('condition', 'BROKEN')->count(), 'Fasilitas / kapasitas' => Asset::where('inventory_type', 'FACILITY')->count()];

        return view('assets.index', ['assets' => $q->latest()->paginate(30)->withQueryString(), 'stats' => $stats,
            'assetReport' => app(AssetReporting::class)->summary(now()->year),
            'units' => Asset::where('status', 'ACTIVE')->where('inventory_type', '!=', 'FACILITY')->selectRaw('location, COUNT(*) AS total, SUM(quantity) AS units')->groupBy('location')->get(),
            'due' => Asset::where('status', 'ACTIVE')->where('calibration_required', true)->whereDate('calibration_due_at', '<=', now()->addDays(30))->orderBy('calibration_due_at')->get(),
            'periods' => AssetPeriod::orderByDesc('starts_at')->get()]);
    }

    public function export(Request $request, AssetExcelExport $service)
    {
        Gate::authorize('viewAny', Asset::class);

        return $service->download($request);
    }

    public function edit(Asset $asset)
    {
        Gate::authorize('update', $asset);
        if ($asset->status !== 'ACTIVE') {
            return redirect()->route('assets.show', $asset)->with('error', 'Aktiva yang telah dilepas tidak dapat diedit.');
        }
        $asset->load('stagingRow.import', 'capitalization.journal');

        return view('assets.create', ['asset' => $asset,
            'locations' => Asset::whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'categories' => Asset::whereNotNull('category')->distinct()->orderBy('category')->pluck('category')]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset, AssetEntry $service)
    {
        $service->update($asset, $request->validated(), $request->user()->id);

        return redirect()->route('assets.show', $asset)->with('success', 'Perubahan aktiva tersimpan dan dicatat di audit trail.');
    }

    public function destroy(Request $request, Asset $asset, AssetEntry $service)
    {
        Gate::authorize('delete', $asset);
        $d = $request->validate(['delete_reason' => 'required|string|min:5|max:2000', 'lock_version' => 'required|integer|min:0']);
        $service->delete($asset, $d['delete_reason'], (int) $d['lock_version'], $request->user()->id);

        return redirect()->route('assets.index')->with('success', 'Aktiva dihapus dari daftar. Riwayat dan audit tetap tersimpan.');
    }

    public function show(Request $request, Asset $asset, AssetAccounting $accounting)
    {
        Gate::authorize('viewAny', Asset::class);
        $validated = $request->validate(['month' => 'nullable|date_format:Y-m']);
        $asset->load('stagingRow.import', 'capitalization.journal');

        return view('assets.show', ['asset' => $asset, 'events' => $asset->events()->latest('event_date')->latest('id')->paginate(30)->withQueryString(),
            'monthlyHistory' => app(AssetMonthlyHistory::class)->forMonth($asset, $validated['month'] ?? now()->format('Y-m')),
            'accounts' => ChartAccount::where('is_active', true)->orderBy('code')->get(), 'accumulated' => $accounting->accumulated($asset) / 100,
            'audits' => DB::table('asset_audits')->where(function ($q) use ($asset) {
                $q->where(fn ($s) => $s->where('subject_type', Asset::class)->where('subject_id', $asset->id));
                if ($asset->capitalization) {
                    $q->orWhere(fn ($s) => $s->where('subject_type', AssetCapitalization::class)->where('subject_id', $asset->capitalization->id));
                }
            })->orderByDesc('id')->limit(100)->get()]);
    }

    public function qr(Asset $asset, AssetQrCode $qr)
    {
        Gate::authorize('viewAny', Asset::class);

        return response($qr->svg('RSUVE:'.$asset->qr_token), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function operation(AssetOperationRequest $request, Asset $asset, AssetOperations $service)
    {
        $service->record($asset, $request->validated(), $request->user()->id);

        return back()->with('success', 'Riwayat operasional tersimpan.');
    }

    public function capitalize(AssetCapitalizationRequest $request, Asset $asset, AssetAccounting $service)
    {
        $service->draft($asset, $request->validated(), $request->user()->id);

        return back()->with('success', 'Draft kapitalisasi tersimpan.');
    }

    public function approve(Request $request, Asset $asset, AssetAccounting $service)
    {
        Gate::authorize('approve', Asset::class);
        $service->approve($asset, $request->user()->id);

        return back()->with('success', 'Kapitalisasi disetujui. Belum ada jurnal sampai posting.');
    }

    public function post(Request $request, Asset $asset, AssetAccounting $service)
    {
        Gate::authorize('account', Asset::class);
        $d = $request->validate(['journal_date' => 'required|date_format:Y-m-d|before_or_equal:today']);
        $j = $service->post($asset, $d['journal_date'], $request->user()->id);

        return back()->with('success', 'Jurnal '.$j->journal_no.' terposting.');
    }

    public function depreciate(Request $request, Asset $asset, AssetAccounting $service)
    {
        Gate::authorize('account', Asset::class);
        $d = $request->validate(['month' => 'required|date_format:Y-m|before_or_equal:'.now()->format('Y-m')]);
        $j = $service->depreciate($asset, $d['month'], $request->user()->id);

        return back()->with('success', 'Jurnal '.$j->journal_no.' terposting.');
    }

    public function dispose(Request $request, Asset $asset, AssetAccounting $service)
    {
        Gate::authorize('account', Asset::class);
        Gate::authorize('approve', Asset::class);
        $d = $request->validate(['journal_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'loss_account_id' => 'required|integer|exists:chart_accounts,id', 'notes' => 'required|string|max:5000']);
        $service->dispose($asset, $d['journal_date'], (int) $d['loss_account_id'], $d['notes'], $request->user()->id);

        return back()->with('success', 'Pelepasan disetujui dan dicatat.');
    }

    public function period(Request $request)
    {
        Gate::authorize('account', Asset::class);
        Gate::authorize('approve', Asset::class);
        $d = $request->validate(['name' => 'required|string|max:255', 'starts_at' => 'required|date_format:Y-m-d', 'ends_at' => 'required|date_format:Y-m-d|after_or_equal:starts_at']);
        DB::transaction(function () use ($d, $request) {
            // Lock a stable row even when there are no periods yet.
            DB::table('applications')->where('code', config('accounting.application_code') ?: 'ACCOUNTING')->lockForUpdate()->first();
            if (AssetPeriod::whereDate('starts_at', '<=', $d['ends_at'])->whereDate('ends_at', '>=', $d['starts_at'])->exists()) {
                throw ValidationException::withMessages(['period' => 'Periode tumpang tindih.']);
            }
            $p = AssetPeriod::create($d + ['created_by' => $request->user()->id]);
            AssetAudit::record($p, 'PERIOD_OPEN', $request->user()->id, [], $p->toArray());
        });

        return back()->with('success', 'Periode aktiva dibuka.');
    }

    public function closePeriod(Request $request, AssetPeriod $period)
    {
        Gate::authorize('account', Asset::class);
        Gate::authorize('approve', Asset::class);
        DB::transaction(function () use ($request, $period) {
            $period = AssetPeriod::lockForUpdate()->findOrFail($period->id);
            $period->update(['status' => 'CLOSED']);
            AssetAudit::record($period, 'PERIOD_CLOSE', $request->user()->id);
        });

        return back()->with('success', 'Periode aktiva ditutup.');
    }
}
