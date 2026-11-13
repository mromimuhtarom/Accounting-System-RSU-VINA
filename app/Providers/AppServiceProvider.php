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
        \Illuminate\Support\Facades\View::composer([
            'cashier.receipts.create', 'cashier.receipts.edit',
            'accounting.receipts.create', 'accounting.receipts.edit', 'transactions.batch',
        ], function ($view) {
            if ($view->name() === 'transactions.batch' && ($view->getData()['module'] ?? null) !== 'receipts') {
                $view->with('doctorNames', collect());
                return;
            }
            $view->with('doctorNames', Receipt::query()->whereNotNull('doctor_name')
                ->where('doctor_name', '<>', '')->select('doctor_name')->distinct()
                ->orderBy('doctor_name')->pluck('doctor_name')->map(fn ($name) => trim($name))->filter()->unique()->values());
        });
        foreach (array_keys(\App\Services\ActivityLogger::FORMS) as $model) {
            $class = 'App\\Models\\'.$model;
            $class::observe(\App\Observers\ActivityObserver::class);
        }
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
