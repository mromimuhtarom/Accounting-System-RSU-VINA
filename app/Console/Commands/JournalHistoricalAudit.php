<?php
namespace App\Console\Commands;

use App\Models\{Receipt,Expense};
use App\Services\SafeJournalPosting;
use Illuminate\Console\Command;

class JournalHistoricalAudit extends Command
{
    protected $signature='rsuve:jurnal-histori {--apply : Posting yang lolos verifikasi. Tanpa opsi ini hanya pratinjau.} {--year= : Batasi tahun transaksi}';
    protected $description='Audit dan posting aman jurnal historis; otomatis melewati klaim belum cair atau pemetaan tidak lengkap.';

    public function handle(SafeJournalPosting $service): int
    {
        $apply=(bool)$this->option('apply');
        $this->warn($apply ? 'MODE POSTING: jurnal akan dibuat hanya untuk transaksi valid.' : 'MODE PRATINJAU: tidak ada perubahan database.');
        $stats=['siap'=>0,'diposting'=>0,'sudah'=>0,'ditunda'=>0,'gagal'=>0];
        $reasons=[];
        foreach ([[Receipt::class,'transaction_date'],[Expense::class,'expense_date']] as [$model,$dateField]) {
            $q=$model::query()->where('status','ACTIVE');
            if ($this->option('year')) $q->whereYear($dateField,(int)$this->option('year'));
            $q->orderBy('id')->chunkById(300,function($items) use ($service,$apply,&$stats,&$reasons) {
                foreach ($items as $item) {
                    [$ok,$reason]=$service->assess($item);
                    if (!$ok) {
                        $stats[$reason==='Sudah memiliki jurnal'?'sudah':'ditunda']++;
                        if ($reason!=='Sudah memiliki jurnal') $reasons[$reason]=($reasons[$reason]??0)+1;
                        continue;
                    }
                    $stats['siap']++;
                    if ($apply) {
                        try {[$posted,$message]=$service->post($item); $stats[$posted?'diposting':'gagal']++; if (!$posted) $reasons[$message]=($reasons[$message]??0)+1;}
                        catch (\Throwable $e) {$stats['gagal']++; $reasons['Kesalahan posting: '.substr($e->getMessage(),0,100)]=($reasons['Kesalahan posting: '.substr($e->getMessage(),0,100)]??0)+1;}
                    }
                }
            });
        }
        $this->table(['Status','Jumlah'],array_map(fn($k,$v)=>[$k,$v],array_keys($stats),array_values($stats)));
        arsort($reasons); foreach($reasons as $reason=>$count) $this->line($count.' × '.$reason);
        return self::SUCCESS;
    }
}
