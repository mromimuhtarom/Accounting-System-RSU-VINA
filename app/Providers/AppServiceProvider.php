<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Models\{Receipt,Expense};
use App\Services\SafeJournalPosting;
class AppServiceProvider extends ServiceProvider
{
    public function register():void {}
    public function boot():void
    {
        // afterCommit avoids producing a journal for a rolled-back source transaction.
        // Posting is deliberately skipped when data is uncertain; source entry still succeeds.
        $autopost = function ($model): void {
            try { app(SafeJournalPosting::class)->post($model); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('RSUVE journal autopost skipped',['type'=>get_class($model),'id'=>$model->id,'reason'=>$e->getMessage()]); }
        };
        Receipt::created($autopost);
        Expense::created($autopost);
    }
}
