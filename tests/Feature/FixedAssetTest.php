<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetImport;
use App\Models\AssetPeriod;
use App\Models\AssetStagingRow;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AssetAccounting;
use App\Services\AssetExcelImport;
use App\Services\AssetMonthlyHistory;
use App\Services\AssetReporting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class FixedAssetTest extends TestCase
{
    private User $admin;

    private AssetExcelImport $importer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_08_18_000001_add_accounting_fields_to_users_table.php', '2026_08_18_000002_create_applications_and_roles_tables.php', '2026_08_18_000003_create_chart_accounts_table.php', '2026_08_18_000007_create_journal_tables.php', '2026_10_29_000001_create_fixed_asset_module.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Storage::fake('local');
        (require database_path('migrations/2026_10_29_000002_index_asset_duplicate_candidates.php'))->up();
        (require database_path('migrations/2026_10_29_000003_support_manual_asset_entry.php'))->up();
        (require database_path('migrations/2026_10_29_000004_support_asset_editing_and_soft_deletes.php'))->up();
        $this->admin = User::factory()->create();
        $app = DB::table('applications')->insertGetId(['code' => 'ACCOUNTING', 'name' => 'Accounting']);
        foreach (['ADMIN_ACCOUNTING', 'ADMIN_ASET', 'ACCOUNTING', 'PETUGAS_ASET', 'KASIR'] as $code) {
            $id = DB::table('roles')->insertGetId(['application_id' => $app, 'code' => $code, 'name' => $code]);
            if ($code === 'ADMIN_ACCOUNTING') {
                $this->admin->roles()->attach($id);
            }
        }
        $this->actingAs($this->admin);
        $this->importer = app(AssetExcelImport::class);
    }

    private function upload(array $rows, string $format = 'Xlsx', string $sheet = 'Klasifikasi Alat'): AssetImport
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle($sheet)->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'asset-test-');
        try {
            IOFactory::createWriter($book, $format)->save($path);

            return $this->importer->upload(new UploadedFile($path, 'test.'.strtolower($format), null, null, true), $this->admin->id);
        } finally {
            $book->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function rows(string $name = 'Monitor', string $serial = 'SERIAL-123'): array
    {
        return [['Nama', 'Kode', 'Serial', 'Lokasi', 'Kondisi', 'Kategori', 'Harga', 'Tahun', 'Ada'], [$name, 'CODE-1', $serial, 'IGD', 'Baik', 'Alat Medis', 1200000, 2020, 'Ya']];
    }

    private function stage(AssetImport $batch): void
    {
        $this->importer->stage($batch, 'Klasifikasi Alat', 'classification', 1, ['name' => 'A', 'code' => 'B', 'serial_number' => 'C', 'location' => 'D', 'condition' => 'E', 'category' => 'F', 'source_price' => 'G', 'procurement_year' => 'H', 'availability' => 'I'], $this->admin->id);
    }

    private function asset(string $serial = 'SERIAL-123'): Asset
    {
        $batch = $this->upload($this->rows('Monitor', $serial));
        $this->stage($batch);
        $this->importer->approve($batch, $this->admin->id);
        $this->importer->post($batch, $this->admin->id);

        return Asset::latest('id')->firstOrFail();
    }

    private function draftData(): array
    {
        $accounts = [];
        foreach (['asset_account_id' => ['ASSET', 'DEBIT'], 'expense_account_id' => ['EXPENSE', 'DEBIT'], 'accumulated_account_id' => ['ASSET', 'CREDIT'], 'offset_account_id' => ['EQUITY', 'CREDIT']] as $key => [$type,$balance]) {
            $accounts[$key] = ChartAccount::firstOrCreate(['code' => $key], ['name' => $key, 'type' => $type, 'normal_balance' => $balance, 'is_active' => true])->id;
        }

        return $accounts + ['cost' => '1200.00', 'residual_value' => '200.00', 'opening_accumulated' => '100.00', 'opening_book_value' => '1100.00', 'acquired_at' => '2020-01-01', 'depreciation_starts_at' => '2026-01-01', 'useful_life_months' => 3, 'evidence' => 'Invoice dan rekonsiliasi saldo awal telah diperiksa'];
    }

    private function manualData(array $changes = []): array
    {
        return array_replace(['entry_token' => (string) Str::uuid(), 'name' => 'Infusion Pump Baru', 'inventory_type' => 'MEDICAL', 'category' => 'alat medis', 'code' => 'IP-NEW', 'serial_number' => 'NEW-SERIAL-001', 'location' => 'igd', 'custodian' => 'Kepala IGD', 'quantity' => 1, 'condition' => 'GOOD', 'procurement_year' => 2020, 'source_price' => '1500000.00', 'funding' => 'BLU', 'notes' => 'Pemeriksaan dokumen pengadaan selesai', 'calibration_required' => '1', 'calibration_due_at' => '2026-12-01'], $changes);
    }

    public function test_manual_entry_creates_asset_without_staging_or_journal_and_is_idempotent(): void
    {
        $this->get(route('assets.create'))->assertOk()->assertSee('Input Aktiva');
        $data = $this->manualData();
        foreach ([1, 2] as $_) {
            $this->post(route('assets.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        }
        $asset = Asset::sole();
        $this->assertNull($asset->asset_staging_row_id);
        $this->assertSame('IGD', $asset->location);
        $this->assertSame('ALAT MEDIS', $asset->category);
        $this->assertSame($this->admin->id, (int) $asset->created_by);
        $this->assertTrue($asset->calibration_required);
        $this->assertSame(0, AssetStagingRow::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertDatabaseHas('asset_audits', ['subject_id' => $asset->id, 'action' => 'MANUAL_CREATE']);
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Input manual');
        $this->get(route('assets.index'))->assertOk()->assertSee('Tambah Barang')->assertDontSee('Import Aktiva');
    }

    public function test_aggregate_software_detail_explains_why_capitalization_form_is_unavailable(): void
    {
        $this->post(route('assets.store'), $this->manualData(['name' => 'Windows 10 64 Bit', 'inventory_type' => 'SOFTWARE', 'quantity' => 2, 'source_price' => null, 'serial_number' => null]))->assertSessionHasNoErrors();
        $asset = Asset::sole();
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Form kapitalisasi belum tersedia untuk catatan agregat')->assertSee('2 instalasi')->assertSee('bukti lisensi atau pembelian')->assertDontSee('action="'.route('assets.capitalize', $asset).'"', false);
        $this->assertSame(2, $asset->fresh()->quantity);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_individual_asset_has_capitalization_form_and_field_guidance(): void
    {
        $asset = $this->asset();
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Panduan mengisi kapitalisasi')->assertSee('action="'.route('assets.capitalize', $asset).'"', false)->assertSee('name="cost"', false)->assertSee('buku besar lama');
        $asset->update(['inventory_type' => 'FACILITY']);
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Ini adalah parameter fasilitas / kapasitas')->assertDontSee('action="'.route('assets.capitalize', $asset).'"', false);
    }

    public function test_edit_explains_excel_origin_and_preserves_original_price_after_master_correction(): void
    {
        $asset = $this->asset();
        $this->put(route('assets.update', $asset), $this->editData($asset, ['source_price' => '1250000.00']))->assertSessionHasNoErrors();
        $this->get(route('assets.edit', $asset))->assertOk()->assertSee('test.xlsx')->assertSee('Klasifikasi Alat')->assertSee('baris Excel')->assertSee('Rp 1.200.000,00')->assertSee('Rp 1.250.000,00')->assertSee('Periksa data per bulan')->assertSee('Belum dibuat kapitalisasi');
        $this->assertEquals(1200000, $asset->stagingRow->raw['G']);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_asset_detail_month_selector_shows_posted_sources_and_period_balances(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $service = app(AssetAccounting::class);
        $service->depreciate($asset, '2026-01', $this->admin->id);
        $service->depreciate($asset, '2026-02', $this->admin->id);
        $january = $this->get(route('assets.show', [$asset, 'month' => '2026-01']))->assertOk()->assertSee('Cek kapitalisasi dan penyusutan per bulan')->assertSee('AST-CAP-'.$asset->id)->assertSee('AST-DEP-'.$asset->id.'-2026-01')->assertDontSee('AST-DEP-'.$asset->id.'-2026-02</td>', false);
        $history = $january->viewData('monthlyHistory');
        $this->assertEquals(0, $history['opening']['book']);
        $this->assertEquals(1200, $history['capitalization']);
        $this->assertEquals(100, $history['opening_accumulated_added']);
        $this->assertEquals(300, $history['depreciation']);
        $this->assertEquals(800, $history['closing']['book']);
        $this->assertCount(2, $history['journals']);
        $february = $this->get(route('assets.show', [$asset, 'month' => '2026-02']))->assertOk()->viewData('monthlyHistory');
        $this->assertEquals(800, $february['opening']['book']);
        $this->assertEquals(500, $february['closing']['book']);
        $this->assertCount(1, $february['journals']);
        $this->get(route('assets.show', [$asset, 'month' => '2026-99']))->assertSessionHasErrors('month');
    }

    public function test_monthly_history_for_uncapitalized_and_disposed_assets(): void
    {
        $asset = $this->asset();
        $reader = app(AssetMonthlyHistory::class);
        $empty = $reader->forMonth($asset, '2026-01');
        $this->assertEquals(0, $empty['closing']['book']);
        $this->assertCount(0, $empty['journals']);
        $this->official($asset);
        app(AssetAccounting::class)->depreciate($asset, '2026-01', $this->admin->id);
        $asset->events()->create(['type' => 'DISPOSAL_REQUEST', 'event_date' => '2026-02-01', 'data' => ['notes' => 'Penghapusan'], 'created_by' => $this->admin->id]);
        app(AssetAccounting::class)->dispose($asset, '2026-02-15', $this->draftData()['expense_account_id'], 'Penghapusan disetujui', $this->admin->id);
        $february = $reader->forMonth($asset->fresh(), '2026-02');
        $this->assertEquals(800, $february['opening']['book']);
        $this->assertEquals(800, $february['released_book_value']);
        $this->assertEquals(0, $february['closing']['book']);
    }

    public function test_asset_reporting_respects_accounting_cutoff_and_keeps_source_prices_separate(): void
    {
        $asset = $this->asset();
        $service = app(AssetReporting::class);
        $before = $service->summary(2026, 1);
        $this->assertSame(1, $before['inventory_count']);
        $this->assertEquals(0, $before['position']['cost']);
        $this->official($asset);
        $accounting = app(AssetAccounting::class);
        $accounting->depreciate($asset, '2026-01', $this->admin->id);
        $accounting->depreciate($asset, '2026-02', $this->admin->id);
        $january = $service->summary(2026, 1);
        $february = $service->summary(2026, 2);
        $this->assertEquals(['fixed_assets' => 1, 'cost' => 1200, 'accumulated' => 400, 'book_value' => 800], $january['position']);
        $this->assertEquals(500, $february['position']['book_value']);
        $this->assertEquals(1200, $january['flow']['additions']);
        $this->assertEquals(300, $january['flow']['depreciation']);
        $this->assertEquals(0, $february['flow']['additions']);
        $this->assertEquals(0, $service->summary(2025)['position']['cost']);
        $this->assertEquals(500, $service->summary(2026)['trend'][2]['book_value']);
        $this->assertSame(3, JournalEntry::count());
    }

    public function test_asset_reporting_includes_asset_before_disposal_and_excludes_it_afterwards(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $service = app(AssetAccounting::class);
        $service->depreciate($asset, '2026-01', $this->admin->id);
        $asset->events()->create(['type' => 'DISPOSAL_REQUEST', 'event_date' => '2026-02-01', 'data' => ['notes' => 'Dilepas'], 'created_by' => $this->admin->id]);
        $service->dispose($asset, '2026-02-15', $this->draftData()['expense_account_id'], 'Penghapusan tanpa penerimaan', $this->admin->id);
        $report = app(AssetReporting::class);
        $this->assertSame(1, $report->summary(2026, 1)['position']['fixed_assets']);
        $this->assertEquals(800, $report->summary(2026, 1)['position']['book_value']);
        $this->assertSame(0, $report->summary(2026, 2)['position']['fixed_assets']);
        $this->assertEquals(0, $report->summary(2026, 2)['position']['book_value']);
        $this->assertSame(0, $report->summary(2026, 1)['inventory_count']);
    }

    public function test_inventory_export_uses_filters_all_pages_and_safe_excel_cell_types(): void
    {
        for ($i = 0; $i < 33; $i++) {
            $this->post(route('assets.store'), $this->manualData(['name' => $i === 1 ? '=Export(1)' : 'Export perangkat '.$i, 'serial_number' => sprintf('%06d', $i), 'inventory_type' => $i === 32 ? 'MEDICAL' : 'IT']))->assertSessionHasNoErrors();
        }
        $deleted = Asset::orderBy('id')->first();
        $this->delete(route('assets.destroy', $deleted), ['lock_version' => $deleted->lock_version, 'delete_reason' => 'Tidak masuk export'])->assertSessionHasNoErrors();
        $filters = ['q' => 'Export', 'type' => 'IT', 'location' => 'IGD', 'condition' => 'GOOD', 'page' => 2];
        $this->get(route('assets.index', $filters))->assertOk()->assertSee('Export Excel');
        $response = $this->get(route('assets.export', $filters))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('Inventaris');
            $this->assertSame(36, $sheet->getHighestDataRow());
            $this->assertSame('=Export(1)', $sheet->getCell('C6')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C6')->getDataType());
            $this->assertSame('000001', $sheet->getCell('G6')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('G6')->getDataType());
            $this->assertEquals(1500000, $sheet->getCell('O6')->getValue());
            $this->assertNull($sheet->getCell('T6')->getValue());
            $this->assertEquals(31, $book->getSheetByName('Ringkasan')->getCell('B6')->getValue());
            $this->assertEquals(0, $book->getSheetByName('Ringkasan')->getCell('D6')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_inventory_export_handles_empty_results_and_denies_cashier(): void
    {
        $response = $this->get(route('assets.export', ['q' => 'Tidak ada']))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($path);
            $this->assertSame(5, $book->getSheetByName('Inventaris')->getHighestDataRow());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $user = User::factory()->create();
        $user->roles()->attach(DB::table('roles')->where('code', 'KASIR')->value('id'));
        $this->actingAs($user);
        $this->get(route('assets.export'))->assertForbidden();
    }

    public function test_inventory_export_separates_source_cost_from_posted_values(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        app(AssetAccounting::class)->depreciate($asset, '2026-01', $this->admin->id);
        $response = $this->get(route('assets.export'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('Inventaris');
            $this->assertEquals(1200000, $sheet->getCell('O6')->getValue());
            $this->assertEquals(1200, $sheet->getCell('T6')->getValue());
            $this->assertEquals(400, $sheet->getCell('U6')->getValue());
            $this->assertEquals(800, $sheet->getCell('V6')->getValue());
            $this->assertEquals(1200, $book->getSheetByName('Ringkasan')->getCell('D6')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    private function editData(Asset $asset, array $changes = []): array
    {
        $fields = $asset->only(['name', 'code', 'serial_number', 'brand', 'model', 'inventory_type', 'category', 'location', 'custodian', 'quantity', 'condition', 'procurement_year', 'source_price', 'funding', 'capacity', 'notes', 'calibration_required']);
        $fields['custodian'] = $asset->custodian ?: 'Kepala unit';
        $fields['notes'] = $asset->notes ?: 'Pemeriksaan dokumen inventaris';
        $fields['calibration_due_at'] = $asset->calibration_due_at?->format('Y-m-d');

        return array_replace($fields, ['lock_version' => $asset->lock_version, 'edit_reason' => 'Koreksi data berdasarkan dokumen'], $changes);
    }

    public function test_imported_asset_can_be_edited_with_source_qr_and_transfer_history_preserved(): void
    {
        $asset = $this->asset();
        $origin = $asset->asset_staging_row_id;
        $qr = $asset->qr_token;
        $this->get(route('assets.edit', $asset))->assertOk()->assertSee('SERIAL-123')->assertSee('Simpan Perubahan');
        $this->put(route('assets.update', $asset), $this->editData($asset, ['location' => 'icu', 'name' => 'Monitor diperbarui', 'brand' => 'Merk baru']))->assertSessionHasNoErrors()->assertRedirect(route('assets.show', $asset));
        $asset->refresh();
        $this->assertSame('ICU', $asset->location);
        $this->assertSame('Merk baru', $asset->brand);
        $this->assertSame($origin, $asset->asset_staging_row_id);
        $this->assertSame($qr, $asset->qr_token);
        $this->assertSame(1, $asset->lock_version);
        $this->assertSame('IGD', $asset->events()->where('type', 'TRANSFER')->sole()->data['from_location']);
        $this->assertSame(app(AssetExcelImport::class)->fingerprint($asset->toArray()), $asset->fingerprint);
        $this->assertDatabaseHas('asset_audits', ['subject_id' => $asset->id, 'action' => 'MANUAL_UPDATE']);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_edit_excludes_self_and_rejects_another_asset_serial(): void
    {
        $asset = $this->asset();
        $this->put(route('assets.update', $asset), $this->editData($asset, ['brand' => 'Merk uji']))->assertSessionHasNoErrors();
        $this->post(route('assets.store'), $this->manualData())->assertSessionHasNoErrors();
        $this->put(route('assets.update', $asset), $this->editData($asset->fresh(), ['serial_number' => 'NEW-SERIAL-001']))->assertSessionHasErrors('serial_number');
        $this->assertSame('SERIAL-123', $asset->fresh()->serial_number);
        $this->put(route('assets.update', $asset), $this->editData($asset->fresh(), ['quantity' => 0, 'edit_reason' => '']))->assertSessionHasErrors(['quantity', 'edit_reason']);
    }

    public function test_delete_hides_asset_and_preserves_origin_events_and_audit(): void
    {
        $asset = $this->asset();
        $origin = $asset->asset_staging_row_id;
        $asset->events()->create(['type' => 'MAINTENANCE', 'event_date' => '2026-01-01', 'data' => ['notes' => 'Riwayat tetap disimpan'], 'created_by' => $this->admin->id]);
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Pencatatan ganda dari dokumen', 'lock_version' => $asset->lock_version])->assertSessionHasNoErrors()->assertRedirect(route('assets.index'));
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertSame(0, Asset::count());
        $this->assertDatabaseHas('asset_staging_rows', ['id' => $origin]);
        $this->assertDatabaseHas('asset_events', ['asset_id' => $asset->id, 'type' => 'MAINTENANCE']);
        $this->assertDatabaseHas('asset_audits', ['subject_id' => $asset->id, 'action' => 'MANUAL_DELETE']);
        $this->get(route('assets.show', $asset))->assertNotFound();
        $this->get(route('assets.edit', $asset))->assertNotFound();
        $this->get(route('assets.index'))->assertOk()->assertDontSee('SERIAL-123');
    }

    public function test_replaying_deleted_manual_form_does_not_recreate_asset(): void
    {
        $data = $this->manualData();
        $this->post(route('assets.store'), $data)->assertSessionHasNoErrors();
        $asset = Asset::sole();
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Koreksi pencatatan awal', 'lock_version' => $asset->lock_version])->assertSessionHasNoErrors();
        $this->post(route('assets.store'), $data)->assertSessionHasErrors('entry_token');
        $this->assertSame(1, Asset::withTrashed()->count());
    }

    public function test_stale_edit_and_delete_do_not_overwrite_newer_operational_changes(): void
    {
        $asset = $this->asset();
        $old = $this->editData($asset);
        $asset->update(['condition' => 'BROKEN']);
        $this->put(route('assets.update', $asset), $old)->assertSessionHasErrors('lock_version');
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Permintaan hapus lama', 'lock_version' => $old['lock_version']])->assertSessionHasErrors('lock_version');
        $this->assertSame('BROKEN', $asset->fresh()->condition);
        $this->assertSame(1, Asset::count());
    }

    public function test_capitalized_asset_cannot_be_deleted_or_change_accounting_identity(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $this->put(route('assets.update', $asset), $this->editData($asset->fresh(), ['quantity' => 2]))->assertSessionHasErrors('quantity');
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Tidak boleh menghapus jurnal', 'lock_version' => $asset->fresh()->lock_version])->assertSessionHasErrors('asset');
        $this->put(route('assets.update', $asset), $this->editData($asset->fresh(), ['notes' => 'Koreksi keterangan tanpa mengubah nilai accounting']))->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame('POSTED', $asset->fresh()->capitalization->status);
        $this->assertSame(1, Asset::count());
    }

    public function test_edit_and_delete_permissions_are_enforced_on_endpoints(): void
    {
        $asset = $this->asset();
        foreach (['KASIR', 'PETUGAS_ASET', 'ADMIN_ASET'] as $code) {
            $user = User::factory()->create();
            $user->roles()->attach(DB::table('roles')->where('code', $code)->value('id'));
            $this->actingAs($user);
            if ($code === 'KASIR') {
                $this->get(route('assets.edit', $asset))->assertForbidden();
                $this->put(route('assets.update', $asset), $this->editData($asset))->assertForbidden();
            } else {
                $this->get(route('assets.edit', $asset))->assertOk();
            }
            if ($code !== 'ADMIN_ASET') {
                $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Pengujian hak akses', 'lock_version' => $asset->lock_version])->assertForbidden();
            } else {
                $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Pengujian hak akses', 'lock_version' => $asset->lock_version])->assertSessionHasNoErrors();
            }
        }
    }

    public function test_accounting_sees_delete_on_list_and_detail_and_can_delete_unposted_asset(): void
    {
        $asset = $this->asset();
        $user = User::factory()->create();
        $user->roles()->attach(DB::table('roles')->where('code', 'ACCOUNTING')->value('id'));
        $this->actingAs($user);
        foreach (['assets.index' => null, 'assets.show' => $asset] as $route => $parameter) {
            $this->get(route($route, $parameter))->assertOk()->assertSee('Hapus Aktiva')->assertSee(route('assets.destroy', $asset));
        }
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Koreksi duplikat oleh Accounting', 'lock_version' => $asset->lock_version])->assertSessionHasNoErrors()->assertRedirect(route('assets.index'));
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('asset_audits', ['subject_id' => $asset->id, 'action' => 'MANUAL_DELETE', 'actor_id' => $user->id]);
    }

    public function test_accounting_still_cannot_delete_posted_capitalization(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $user = User::factory()->create();
        $user->roles()->attach(DB::table('roles')->where('code', 'ACCOUNTING')->value('id'));
        $this->actingAs($user);
        $this->get(route('assets.show', $asset))->assertOk()->assertDontSee('action="'.route('assets.destroy', $asset).'"', false);
        $this->delete(route('assets.destroy', $asset), ['delete_reason' => 'Tidak boleh menghilangkan jurnal', 'lock_version' => $asset->lock_version])->assertSessionHasErrors('asset');
        $this->assertSame(1, Asset::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_import_http_endpoints_are_removed(): void
    {
        $this->get('/aktiva/import')->assertNotFound();
        $this->post('/aktiva/import')->assertNotFound();
        $this->get('/aktiva/import/1')->assertNotFound();
        $this->post('/aktiva/import/1/post')->assertNotFound();
        $this->post('/aktiva/staging/1/review')->assertNotFound();
    }

    public function test_manual_validation_rejects_missing_fields_and_invalid_calibration(): void
    {
        $this->post(route('assets.store'), [])->assertSessionHasErrors(['name', 'inventory_type', 'category', 'quantity', 'condition', 'entry_token']);
        $this->post(route('assets.store'), $this->manualData(['quantity' => 0, 'location' => ' ', 'custodian' => '', 'source_price' => -1, 'calibration_due_at' => null]))->assertSessionHasErrors(['quantity', 'location', 'custodian', 'source_price', 'calibration_due_at']);
        $this->assertSame(0, Asset::count());
    }

    public function test_manual_duplicate_serial_rejected_and_similar_unit_needs_explanation(): void
    {
        $this->post(route('assets.store'), $this->manualData())->assertSessionHasNoErrors();
        $this->post(route('assets.store'), $this->manualData(['name' => 'Nama lain']))->assertSessionHasErrors('serial_number');
        $this->post(route('assets.store'), $this->manualData(['serial_number' => 'NEW-SERIAL-002']))->assertSessionHasNoErrors();
        $similar = $this->manualData(['serial_number' => null]);
        $this->post(route('assets.store'), $similar)->assertSessionHasErrors('duplicate_reason');
        $this->post(route('assets.store'), $similar + ['allow_similar' => '1', 'duplicate_reason' => 'Unit kedua; label sementara dan bukti pengadaan terpisah'])->assertSessionHasNoErrors();
        $this->assertSame(3, Asset::count());
    }

    public function test_manual_facility_requires_capacity_and_ignores_accounting_fields(): void
    {
        $data = $this->manualData(['inventory_type' => 'FACILITY', 'location' => null, 'custodian' => null, 'code' => null, 'serial_number' => null, 'calibration_required' => '0', 'calibration_due_at' => '2026-12-01']);
        $this->post(route('assets.store'), $data)->assertSessionHasErrors('capacity');
        $this->post(route('assets.store'), $data + ['capacity' => '500 KVA', 'status' => 'DISPOSED', 'verified_by' => 999, 'cost' => 1500000])->assertSessionHasNoErrors();
        $asset = Asset::sole();
        $this->assertSame('ACTIVE', $asset->status);
        $this->assertSame($this->admin->id, (int) $asset->verified_by);
        $this->assertNull($asset->calibration_due_at);
        $this->assertNull($asset->capitalization);
    }

    public function test_manual_entry_policy_denies_cashier_and_read_only_users(): void
    {
        foreach (['KASIR', 'OWNER', 'PETUGAS_ASET'] as $code) {
            $role = DB::table('roles')->where('code', $code)->value('id') ?: DB::table('roles')->insertGetId(['application_id' => DB::table('applications')->value('id'), 'code' => $code, 'name' => $code]);
            $user = User::factory()->create();
            $user->roles()->attach($role);
            $this->actingAs($user);
            if ($code === 'PETUGAS_ASET') {
                $this->get(route('assets.create'))->assertOk();
                $this->post(route('assets.store'), $this->manualData())->assertSessionHasNoErrors();
            } else {
                $this->get(route('assets.create'))->assertForbidden();
                $this->post(route('assets.store'), $this->manualData())->assertForbidden();
            }
        }
    }

    private function official(Asset $asset): void
    {
        $service = app(AssetAccounting::class);
        $service->draft($asset, $this->draftData(), $this->admin->id);
        $service->approve($asset, $this->admin->id);
        AssetPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'OPEN', 'created_by' => $this->admin->id]);
        $service->post($asset, '2026-01-01', $this->admin->id);
    }

    public function test_import_preview_provenance_and_repeated_post_are_idempotent(): void
    {
        $batch = $this->upload($this->rows());
        $this->assertSame('Monitor', $this->importer->read($batch, 'Klasifikasi Alat')[2]['A']);
        $this->stage($batch);
        $row = $batch->rows()->firstOrFail();
        $this->assertSame(2, $row->source_row);
        $this->assertSame('READY', $row->status);
        $this->assertSame('1200000.00', $row->normalized['source_price']);
        try {
            $this->importer->post($batch, $this->admin->id);
            $this->fail('Unapproved posting must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('posting', $e->errors());
        }
        $this->importer->approve($batch, $this->admin->id);
        foreach ([1, 2] as $_) {
            $this->importer->post($batch, $this->admin->id);
        }
        $this->assertSame(1, Asset::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertDatabaseHas('asset_audits', ['action' => 'IMPORT_POST']);
        $this->get(route('assets.index'))->assertOk()->assertSee('Belum diverifikasi');
        $this->get(route('assets.show', Asset::first()))->assertOk()->assertSee('SERIAL-123');
        $this->get(route('assets.qr', Asset::first()))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->expectException(ValidationException::class);
        $this->importer->review($row, [], 'READY', 'Tidak dapat diubah setelah posting', $this->admin->id);
    }

    public function test_identical_workbook_reuses_batch_and_sheet_cannot_be_staged_twice(): void
    {
        $batch = $this->upload($this->rows());
        $file = new UploadedFile(Storage::disk('local')->path($batch->path), 'ulang.xlsx', null, null, true);
        $this->assertSame($batch->id, $this->importer->upload($file, $this->admin->id)->id);
        $this->stage($batch);
        $this->expectException(ValidationException::class);
        $this->importer->stage($batch, 'Klasifikasi Alat', 'classification', 1, ['name' => 'A'], $this->admin->id);
    }

    public function test_overlapping_source_is_flagged_and_can_be_skipped_with_evidence(): void
    {
        $this->asset();
        $batch = $this->upload($this->rows('Monitor variant'));
        $this->stage($batch);
        $row = $batch->rows()->firstOrFail();
        $this->assertSame('DUPLICATE', $row->status);
        try {
            $this->importer->approve($batch, $this->admin->id);
            $this->fail('Duplicate requires review');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('approval', $e->errors());
        }
        $this->importer->review($row, [], 'SKIPPED', 'Unit sama dengan inventaris master', $this->admin->id);
        $this->importer->approve($batch, $this->admin->id);
        $this->importer->post($batch, $this->admin->id);
        $this->assertSame(1, Asset::count());
    }

    public function test_new_duplicate_after_approval_invalidates_batch_before_any_post(): void
    {
        $batch = $this->upload($this->rows());
        $this->stage($batch);
        $this->importer->approve($batch, $this->admin->id);
        $other = $this->upload($this->rows('Changed name'));
        $this->stage($other);
        $this->importer->post($batch, $this->admin->id);
        $this->assertSame('STAGED', $batch->fresh()->status);
        $this->assertSame(0, Asset::count());
        $this->assertSame('DUPLICATE', $batch->rows()->first()->status);
    }

    public function test_excel_xls_hierarchical_rooms_and_absent_units_are_handled(): void
    {
        $rows = [['Title'], [], [], [], ['mark', 'Keterangan', null, null, null, 'Ada', 'No seri', 'Merk', 'Type', 'Tahun', 'Kondisi', 'Harga', 'Pendanaan', 'Distributor', 'Registrasi', 'Catatan'], ['***', null, null, 'ICU'], [102, null, null, 123, 'Ventilator', 'Ya', 'SER-100', 'Brand', 'Model', 2020, 'Baik', 1200], [102, null, null, 456, 'Tidak ada', 'Tidak']];
        $batch = $this->upload($rows, 'Xls', 'Format Import Alkes.xls');
        [$header,$mapping] = $this->importer->preset('alkes', 'Format Import Alkes.xls');
        $this->importer->stage($batch, 'Format Import Alkes.xls', 'alkes', $header, $mapping, $this->admin->id);
        $this->assertSame('ICU', $batch->rows()->where('source_row', 7)->firstOrFail()->normalized['location']);
        $this->assertSame('SKIPPED', $batch->rows()->where('source_row', 8)->firstOrFail()->status);
    }

    public function test_it_merged_location_is_carried_and_aggregate_needs_review(): void
    {
        $batch = $this->upload([['Software'], [], [], ['No', 'Posisi', 'Nama', 'Jumlah'], [1, 'Kasir', 'Office', 2], [null, null, 'Browser', 1]], 'Xlsx', 'Sheet2');
        [$h,$m] = $this->importer->preset('it', 'Sheet2');
        $this->importer->stage($batch, 'Sheet2', 'it', $h, $m, $this->admin->id);
        $row = $batch->rows()->where('source_row', 6)->firstOrFail();
        $this->assertSame('KASIR', $row->normalized['location']);
        $this->assertSame('SOFTWARE', $row->normalized['inventory_type']);
        $this->assertSame('REVIEW', $row->status);
        $this->assertSame('2', $batch->rows()->where('source_row', 5)->first()->normalized['quantity']);
    }

    public function test_capacity_and_formulas_are_not_automatically_assets(): void
    {
        $batch = $this->upload([['Title'], [], ['No', 'Kategori', 'Subkategori', 'Parameter', 'Ada', 'Kapasitas', 'Catatan'], [1, 'Listrik', 'PLN', 'Daya listrik', 'Ada', '500 KVA', '=1+2']], 'Xlsx', 'Prasarana Ada');
        [$h,$m] = $this->importer->preset('infrastructure', 'Prasarana Ada');
        $this->importer->stage($batch, 'Prasarana Ada', 'infrastructure', $h, $m, $this->admin->id);
        $row = $batch->rows()->first();
        $this->assertSame('FACILITY', $row->normalized['inventory_type']);
        $this->assertSame('REVIEW', $row->status);
        $this->assertSame('500 KVA', $row->normalized['capacity']);
        $this->assertContains('Formula disimpan sebagai teks; perlu nilai tervalidasi', $row->issues);
    }

    public function test_auxiliary_worksheet_cannot_create_inventory(): void
    {
        $batch = $this->upload($this->rows(), 'Xlsx', 'Perlu Verifikasi');
        $this->expectException(ValidationException::class);
        $this->importer->stage($batch, 'Perlu Verifikasi', 'classification', 1, ['name' => 'A'], $this->admin->id);
    }

    public function test_roles_cannot_bypass_asset_approval_or_accounting(): void
    {
        $asset = $this->asset();
        foreach (['PETUGAS_ASET', 'ACCOUNTING', 'KASIR'] as $role) {
            $user = User::factory()->create();
            $user->roles()->attach(DB::table('roles')->where('code', $role)->value('id'));
            $this->actingAs($user);
            $this->post(route('assets.approve', $asset))->assertForbidden();
            $this->post(route('assets.dispose', $asset))->assertForbidden();
            if ($role !== 'ACCOUNTING') {
                $this->post(route('assets.capitalize', $asset), $this->draftData())->assertForbidden();
            }
            if ($role === 'KASIR') {
                $this->get(route('assets.index'))->assertForbidden();
            }
        }
    }

    public function test_book_value_validation_and_closed_period_block_journal(): void
    {
        $asset = $this->asset();
        $data = $this->draftData();
        $data['opening_book_value'] = '1000.00';
        $this->post(route('assets.capitalize', $asset), $data)->assertSessionHasErrors('accounting');
        $this->assertSame(0, JournalEntry::count());
        $this->official($asset);
        $period = AssetPeriod::first();
        $period->update(['status' => 'CLOSED']);
        $this->post(route('assets.depreciate', $asset), ['month' => '2026-01'])->assertSessionHasErrors('accounting');
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_capitalization_requires_approval_and_open_period_and_posts_once(): void
    {
        $asset = $this->asset();
        $data = $this->draftData();
        $this->post(route('assets.capitalize', $asset), $data)->assertSessionHasNoErrors();
        $this->post(route('assets.post', $asset), ['journal_date' => '2026-01-01'])->assertSessionHasErrors('accounting');
        $this->post(route('assets.approve', $asset))->assertSessionHasNoErrors();
        $this->post(route('assets.post', $asset), ['journal_date' => '2026-01-01'])->assertSessionHasErrors('accounting');
        $this->post(route('assets.periods.store'), ['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31'])->assertSessionHasNoErrors();
        foreach ([1, 2] as $_) {
            $this->post(route('assets.post', $asset), ['journal_date' => '2026-01-01'])->assertSessionHasNoErrors();
        }
        $j = JournalEntry::firstOrFail();
        $this->assertEquals(1200, $j->lines()->sum('debit'));
        $this->assertEquals(1200, $j->lines()->sum('credit'));
        $this->assertSame(1, JournalEntry::count());
        $this->post(route('assets.capitalize', $asset), $data)->assertSessionHasErrors('accounting');
    }

    public function test_depreciation_is_explicit_ordered_idempotent_and_stops_at_residual(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $this->post(route('assets.depreciate', $asset), ['month' => '2026-02'])->assertSessionHasErrors('accounting');
        foreach (['2026-01', '2026-01', '2026-02', '2026-03'] as $month) {
            $this->post(route('assets.depreciate', $asset), ['month' => $month])->assertSessionHasNoErrors();
        }
        $this->assertSame(4, JournalEntry::count());
        $this->assertSame(100000, app(AssetAccounting::class)->accumulated($asset->fresh()));
        $this->post(route('assets.depreciate', $asset), ['month' => '2026-04'])->assertSessionHasErrors('accounting');
        $this->get(route('assets.index'))->assertOk()->assertViewHas('stats', fn ($s) => $s['Aktiva tetap resmi'] === 1 && $s['Nilai buku'] === 200.0);
    }

    public function test_transfer_stocktake_calibration_and_disposal_keep_history(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $base = ['event_date' => '2026-01-01', 'notes' => 'Dokumen kegiatan telah diperiksa'];
        $this->post(route('assets.operation', $asset), $base + ['type' => 'TRANSFER', 'location' => 'ICU', 'custodian' => 'Kepala ICU'])->assertSessionHasNoErrors();
        $this->assertSame('ICU', $asset->fresh()->location);
        $this->assertSame('IGD', $asset->events()->first()->data['from_location']);
        $this->post(route('assets.operation', $asset), $base + ['type' => 'STOCKTAKE', 'location' => 'ICU', 'condition' => 'BROKEN'])->assertSessionHasNoErrors();
        $this->post(route('assets.operation', $asset), $base + ['type' => 'CALIBRATION', 'provider' => 'Lab kalibrasi', 'certificate' => 'CERT-123', 'next_due_at' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->assertTrue($asset->fresh()->calibration_required);
        $this->post(route('assets.operation', $asset), $base + ['type' => 'DISPOSAL_REQUEST'])->assertSessionHasNoErrors();
        $this->assertSame('ACTIVE', $asset->fresh()->status);
        $payload = ['journal_date' => '2026-02-01', 'notes' => 'Penghapusan disetujui', 'loss_account_id' => $this->draftData()['expense_account_id']];
        foreach ([1, 2] as $_) {
            $this->post(route('assets.dispose', $asset), $payload)->assertSessionHasNoErrors();
        }
        $this->assertSame('DISPOSED', $asset->fresh()->status);
        $j = JournalEntry::where('journal_no', 'AST-DIS-'.$asset->id)->firstOrFail();
        $this->assertEquals($j->lines()->sum('debit'), $j->lines()->sum('credit'));
        $this->post(route('assets.operation', $asset), $base + ['type' => 'MAINTENANCE', 'provider' => 'Teknisi'])->assertSessionHasErrors('asset');
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('CERT-123');
    }

    public function test_period_overlap_and_normal_account_types_are_validated(): void
    {
        $asset = $this->asset();
        $this->official($asset);
        $this->post(route('assets.periods.store'), ['name' => 'Overlap', 'starts_at' => '2026-01-02', 'ends_at' => '2026-02-01'])->assertSessionHasErrors('period');
        $this->post(route('assets.periods.close', AssetPeriod::first()))->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', AssetPeriod::first()->status);
    }

    public function test_invalid_master_field_is_reported_failed_without_partial_posting(): void
    {
        $batch = $this->upload($this->rows(str_repeat('X', 256)));
        $this->stage($batch);
        $this->importer->approve($batch, $this->admin->id);
        $result = $this->importer->post($batch, $this->admin->id);
        $this->assertSame(1, $result['FAILED']);
        $this->assertSame(0, Asset::count());
        $this->assertSame('STAGED', $batch->fresh()->status);
    }

    public function test_asset_roles_land_on_asset_dashboard_and_cannot_post_accounting(): void
    {
        $asset = $this->asset();
        foreach (['PETUGAS_ASET', 'ADMIN_ASET'] as $role) {
            $user = User::factory()->create();
            $user->roles()->attach(DB::table('roles')->where('code', $role)->value('id'));
            $this->actingAs($user);
            $this->get(route('dashboard'))->assertRedirect(route('assets.index'));
            $this->get(route('assets.index'))->assertOk();
            $this->post(route('assets.post', $asset), ['journal_date' => '2026-01-01'])->assertForbidden();
        }
    }

    public function test_facility_and_aggregate_cannot_be_capitalized(): void
    {
        $asset = $this->asset();
        $asset->update(['inventory_type' => 'FACILITY']);
        $this->post(route('assets.capitalize', $asset), $this->draftData())->assertSessionHasErrors('accounting');
        $asset->update(['inventory_type' => 'IT', 'quantity' => 2]);
        $this->post(route('assets.capitalize', $asset), $this->draftData())->assertSessionHasErrors('accounting');
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_depreciation_rounding_preserves_exact_residual(): void
    {
        $asset = $this->asset();
        $data = array_replace($this->draftData(), ['cost' => '100.03', 'opening_accumulated' => '0.00', 'opening_book_value' => '100.03', 'residual_value' => '0.02', 'useful_life_months' => 3]);
        $service = app(AssetAccounting::class);
        $service->draft($asset, $data, $this->admin->id);
        $service->approve($asset, $this->admin->id);
        AssetPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'created_by' => $this->admin->id]);
        $service->post($asset, '2026-01-01', $this->admin->id);
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            $service->depreciate($asset, $month, $this->admin->id);
        }
        $this->assertSame(10001, $service->accumulated($asset->fresh()));
    }

    public function test_unreadable_workbook_returns_validation_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'broken-asset-');
        file_put_contents($path, 'This is not an Excel workbook');
        try {
            $this->expectException(ValidationException::class);
            $this->importer->upload(new UploadedFile($path, 'broken.xlsx', null, null, true), $this->admin->id);
        } finally {
            unlink($path);
        }
    }

    public function test_original_workbooks_stage_without_creating_master_assets_or_journals(): void
    {
        $directory = getenv('ASSET_SOURCE_FIXTURE_DIR');
        if (! $directory) {
            $this->markTestSkipped('Set ASSET_SOURCE_FIXTURE_DIR to validate original workbooks.');
        }
        $args = ['directory' => $directory, '--user' => $this->admin->id];
        $this->artisan('assets:stage-sources', $args)->assertSuccessful();
        $this->assertSame(4, AssetImport::count());
        $batch = AssetImport::where('source_type', 'classification')->firstOrFail();
        $this->assertSame(1313, $batch->rows()->count());
        $this->assertSame('Examination Bed', $batch->rows()->where('source_row', 2)->firstOrFail()->normalized['name']);
        $this->assertSame('REVIEW', $batch->rows()->where('source_row', 2)->firstOrFail()->status);
        $infra = AssetImport::where('source_type', 'infrastructure')->firstOrFail();
        $this->assertSame(25, $infra->rows()->count());
        $this->assertSame(0, $infra->rows()->where('status', 'SKIPPED')->count());
        $alkes = AssetImport::where('source_type', 'alkes')->firstOrFail();
        $this->assertGreaterThan(500, $alkes->rows()->where('status', 'DUPLICATE')->count());
        $it = AssetImport::where('source_type', 'it')->firstOrFail();
        $this->assertSame(2, $it->rows()->distinct()->count('sheet'));
        $this->assertSame('CPU', $it->rows()->where('sheet', 'Sheet1')->where('source_row', 5)->firstOrFail()->normalized['name']);
        $count = AssetStagingRow::count();
        $this->artisan('assets:stage-sources', $args)->assertSuccessful();
        $this->assertSame($count, AssetStagingRow::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame(0, JournalEntry::count());
    }
}
