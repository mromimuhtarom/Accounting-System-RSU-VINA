<?php

use App\Services\AssetExcelImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['assets', 'asset_staging_rows'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                foreach (['serial_key', 'name_location_key', 'code_location_key'] as $key) {
                    $t->string($key, 64)->nullable()->index();
                }
            });
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $data = $table === 'assets' ? (array) $row : json_decode($row->normalized, true);
                    DB::table($table)->where('id', $row->id)->update(AssetExcelImport::identityKeys($data));
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['assets', 'asset_staging_rows'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                foreach (['serial_key', 'name_location_key', 'code_location_key'] as $key) {
                    $t->dropIndex([$key]);
                    $t->dropColumn($key);
                }
            });
        }
    }
};
