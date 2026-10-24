<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $id=DB::table('applications')->where('code','ACCOUNTING')->value('id');
        if (!$id) return;
        foreach (['KASIR'=>'Kasir','ACCOUNTING'=>'Accounting (Legacy)','ADMIN_ACCOUNTING'=>'Admin Accounting','ATASAN'=>'Atasan (Legacy)','FINANCE'=>'Staf Keuangan','CASEMIX'=>'Petugas Klaim BPJS / Casemix','KEPALA_KEUANGAN'=>'Kepala Bagian Keuangan / Accounting','DIREKTUR'=>'Direktur','OWNER'=>'Owner / Yayasan','IT'=>'Administrator IT'] as $code=>$name) {
            DB::table('roles')->insertOrIgnore(['application_id'=>$id,'code'=>$code,'name'=>$name]);
        }
    }
    public function down(): void { /* Role tidak dihapus agar relasi akun lama aman. */ }
};
