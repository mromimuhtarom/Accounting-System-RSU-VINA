<?php
namespace App\Http\Controllers\Accounting;
use App\Http\Controllers\Controller;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Http\Request;
class JournalController extends Controller {
    public function index(){return view('accounting.journals.index',['journals'=>JournalEntry::with('creator')->latest('journal_date')->paginate(25)]);}
    public function create(){return view('accounting.journals.create',['accounts'=>ChartAccount::where('is_active',true)->orderBy('code')->get()]);}
    public function store(Request $request,JournalService $service){$data=$request->validate(['journal_date'=>['required','date'],'reference'=>['nullable','string','max:100'],'description'=>['required','string','max:500'],'lines'=>['required','array','min:2'],'lines.*.chart_account_id'=>['nullable','exists:chart_accounts,id'],'lines.*.description'=>['nullable','string','max:255'],'lines.*.debit'=>['nullable','numeric','min:0'],'lines.*.credit'=>['nullable','numeric','min:0']]);$data['lines']=collect($data['lines'])->filter(fn($line)=>!empty($line['chart_account_id']))->values()->all(); if(count($data['lines'])<2){return back()->withErrors(['lines'=>'Minimal dua baris jurnal harus diisi.'])->withInput();} $journal=$service->manual($data,$request->user()->id);return redirect()->route('accounting.jurnal.show',$journal)->with('success','Jurnal manual berhasil dibuat.');}
    public function show(JournalEntry $jurnal){$jurnal->load(['lines.account','creator']);return view('accounting.journals.show',['journal'=>$jurnal]);}
}
