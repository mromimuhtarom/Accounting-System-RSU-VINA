<?php

namespace Tests\Feature;

use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Services\SmartExcelImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SmartExcelImportTest extends ExpenseAccessTest
{
    private function accounting(): void
    {
        $role = DB::table('roles')->insertGetId(['application_id' => DB::table('applications')->value('id'), 'code' => 'ACCOUNTING', 'name' => 'Accounting']);
        auth()->user()->roles()->attach($role);
        auth()->user()->unsetRelation('roles');
    }

    private function upload(array $rows, string $module = 'expenses', string $format = 'Xlsx', string $route = 'accounting.excel-import')
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'import-test');
        try {
            IOFactory::createWriter($book, $format)->save($path);

            return $this->post(route($route, $route === 'insurance.receipts.excel-import' ? [] : ['module' => $module]), [
                'action' => 'upload', 'file' => new UploadedFile($path, 'test.'.strtolower($format), null, null, true),
            ]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            } $book->disconnectWorksheets();
        }
    }

    private function expenseRows(): array
    {
        return [['Nominal', 'Keterangan', 'Kas Bank', 'Metode Pembayaran', 'Kategori', 'Tanggal Transaksi'], ['150.000,50', 'Import uji', 'KAS', 'CASH', 'UMUM', '25/10/2026']];
    }

    public function test_smart_excel_cashier_can_import_both_modules(): void
    {
        $this->get(route('cashier.expenses.index'))->assertOk()->assertSee(route('cashier.excel-import',['module'=>'expenses']));
        $this->get(route('cashier.receipts.index'))->assertOk()->assertSee(route('cashier.excel-import',['module'=>'receipts']));
        ChartAccount::create(['code'=>'4001','name'=>'Pendapatan','type'=>'REVENUE','normal_balance'=>'CREDIT']);
        foreach (['expenses'=>$this->expenseRows(), 'receipts'=>[['Tanggal','COA','Keterangan','Nominal'],['2026-10-25','4001','Kasir impor',10000]]] as $module=>$rows) {
            $response = $this->upload($rows,$module,'Xlsx','cashier.excel-import')->assertOk();
            $this->assertSame($module === 'receipts' ? 'cashier.receipts.index' : 'cashier.expenses.index',$response->viewData('returnRoute'));
            $this->post(route('cashier.excel-import',['module'=>$module]),['action'=>'confirm','token'=>$response->viewData('token'),'mapping'=>$response->viewData('mapping')])->assertOk();
        }
        $this->assertSame(auth()->id(),Expense::firstOrFail()->created_by);
        $this->assertSame(auth()->id(),Receipt::firstOrFail()->cashier_id);
    }

    public function test_smart_excel_insurance_only_imports_insurance_receipts(): void
    {
        $user = \App\Models\User::factory()->create();
        $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'),'code'=>'ASURANSI','name'=>'Asuransi']);
        $user->roles()->attach($role); $this->actingAs($user);
        $general = \App\Models\Guarantor::create(['code'=>'SELF','name'=>'Umum','category'=>'SELF_PAY']);
        $insurance = \App\Models\Guarantor::create(['code'=>'BPJS','name'=>'BPJS','category'=>'BPJS']);
        ChartAccount::create(['code'=>'4001','name'=>'Pendapatan','type'=>'REVENUE','normal_balance'=>'CREDIT']);
        $this->get(route('insurance.receipts.index'))->assertOk()->assertSee(route('insurance.receipts.excel-import'));
        $this->get(route('cashier.excel-import',['module'=>'expenses']))->assertForbidden();
        $this->get(route('accounting.excel-import',['module'=>'expenses']))->assertForbidden();
        $rows = [['Tanggal','COA','Keterangan','Nominal','Penjamin'],['2026-10-25','4001','Asuransi impor',10000,$general->code]];
        $this->upload($rows,'receipts','Xlsx','insurance.receipts.excel-import')->assertOk()->assertSee('Error');
        $rows[1][4] = '';
        $this->upload($rows,'receipts','Xlsx','insurance.receipts.excel-import')->assertOk()->assertSee('Error');
        $rows[1][4] = $insurance->code;
        $response = $this->upload($rows,'receipts','Xlsx','insurance.receipts.excel-import')->assertOk();
        $this->assertCount(1,$response->viewData('masters')['guarantor_id']);
        $this->assertSame('Warning',$response->viewData('preview')[1]['status']);
        $this->post(route('insurance.receipts.excel-import'),['action'=>'confirm','token'=>$response->viewData('token'),'mapping'=>$response->viewData('mapping')])->assertOk();
        $this->assertSame($insurance->id,Receipt::firstOrFail()->guarantor_id);
        $this->assertSame($user->id,Receipt::first()->cashier_id);
    }

    public function test_smart_excel_expense_preview_confirm_and_duplicate(): void
    {
        $this->accounting();
        $response = $this->upload($this->expenseRows())->assertOk()->assertSee('Valid');
        $this->assertSame(0, Expense::count());
        $payload = ['action' => 'confirm', 'token' => $response->viewData('token'), 'mapping' => $response->viewData('mapping')];
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), $payload)->assertOk()->assertSee('Hasil Import');
        $this->assertSame('150000.50', Expense::firstOrFail()->amount);
        $this->assertNotNull(Expense::first()->journal);
        $this->upload($this->expenseRows())->assertOk()->assertSee('Duplicate');
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), $payload)->assertStatus(419);
    }

    public function test_smart_excel_xls_receipts_manual_mapping_and_skip(): void
    {
        $this->accounting();
        ChartAccount::create(['code' => '4001', 'name' => 'Pendapatan', 'type' => 'REVENUE', 'normal_balance' => 'CREDIT']);
        $response = $this->upload([['Date', 'COA', 'Description', 'Unknown'], ['2026-10-25', '4001', 'Pendapatan uji', 1200], ['2026-10-25', '4001', 'Dilewati', 2000]], 'receipts', 'Xls')->assertOk();
        $mapping = $response->viewData('mapping');
        $mapping[3] = 'amount';
        $payload = ['action' => 'preview', 'token' => $response->viewData('token'), 'mapping' => $mapping];
        $this->post(route('accounting.excel-import', ['module' => 'receipts']), $payload)->assertOk()->assertSee('Warning');
        $payload['action'] = 'confirm';
        $payload['skip'] = [2];
        $this->post(route('accounting.excel-import', ['module' => 'receipts']), $payload)->assertOk();
        $this->assertSame(1, Receipt::count());
        $this->assertSame('1200.00', Receipt::first()->amount);
    }

    public function test_smart_excel_invalid_unknown_master_and_corrupt_file(): void
    {
        $this->accounting();
        $rows = $this->expenseRows();
        $rows[1][4] = 'Tidak ada';
        $response = $this->upload($rows)->assertOk()->assertSee('tidak ditemukan');
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), ['action' => 'confirm', 'token' => $response->viewData('token'), 'mapping' => $response->viewData('mapping')])->assertOk();
        $this->assertSame(0, Expense::count());
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), ['action' => 'upload', 'file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'broken')])->assertSessionHasErrors('file');
        $rows[1][0] = '-100';
        $this->upload($rows)->assertOk()->assertSee('Nominal tidak valid');
        $rows[1][5] = '31/02/2026';
        $this->upload($rows)->assertOk()->assertSee('Tanggal tidak valid');
    }

    public function test_smart_excel_authorization_and_template(): void
    {
        $this->get(route('accounting.excel-import', ['module' => 'expenses']))->assertForbidden();
        $this->accounting();
        $response = $this->get(route('accounting.excel-import', ['module' => 'receipts', 'template' => 1]))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $rows = app(SmartExcelImport::class)->read($path);
            $this->assertSame('transaction_date', $rows[3][0]);
        } finally {
            unlink($path);
        }
    }

    public function test_smart_excel_atomic_rollback_and_mapping_change_guard(): void
    {
        $this->accounting();
        $rows = $this->expenseRows();
        $rows[] = $rows[1];
        $rows[2][1] = 'Baris kedua';
        $response = $this->upload($rows)->assertOk();
        $payload = ['action' => 'confirm', 'token' => $response->viewData('token'), 'mapping' => $response->viewData('mapping')];
        $changed = $payload;
        $changed['mapping'][0] = 'reference_no';
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), $changed)->assertStatus(422);
        $event = 'eloquent.creating: '.Expense::class;
        $count = 0;
        Event::listen($event, function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('Failure');
            }
        });
        try {
            $this->post(route('accounting.excel-import', ['module' => 'expenses']), $payload)->assertSessionHasErrors('import');
        } finally {
            Event::forget($event);
        }
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_smart_excel_type_inference_master_override_and_repeated_rows(): void
    {
        $this->accounting();
        $rows = $this->expenseRows();
        $rows[0][0] = 'Kolom nominal tidak dikenal';
        $rows[1][4] = 'Kategori belum cocok';
        $response = $this->upload($rows)->assertOk()->assertSee('Error');
        $this->assertSame('amount', $response->viewData('mapping')[0]);
        $payload = ['action' => 'preview', 'token' => $response->viewData('token'), 'mapping' => $response->viewData('mapping'), 'overrides' => [1 => ['expense_category_id' => (string) ExpenseCategory::first()->id]]];
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), $payload)->assertOk()->assertSee('Valid');
        $payload['action'] = 'confirm';
        $this->post(route('accounting.excel-import', ['module' => 'expenses']), $payload)->assertOk();
        $this->assertSame(1, Expense::count());
        $rows = $this->expenseRows();
        $rows[] = $rows[1];
        $this->upload($rows)->assertOk()->assertSee('Duplicate');
    }

    public function test_smart_excel_large_file_and_empty_rows(): void
    {
        $service = app(SmartExcelImport::class);
        $rows = [['Tanggal', 'Nominal']];
        for ($i = 0; $i < 5000; $i++) {
            $rows[] = ['2026-10-25', 1000 + $i];
        }
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'large-import');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $this->assertCount(5001, $service->read($path));
            $book->getActiveSheet()->setCellValue('A5032', 'exceeds limit');
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            try {
                $service->read($path);
                $this->fail('Expected row limit');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
        $this->accounting();
        $rows = $this->expenseRows();
        $rows[1] = ['', '', '', '', '', ''];
        $this->upload($rows)->assertSessionHasErrors('file');
    }
}
