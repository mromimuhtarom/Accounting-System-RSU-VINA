<?php
namespace App\Http\Controllers\Accounting;
use App\Http\Controllers\Controller;
use App\Models\BpjsClaim;
use App\Models\BpjsClaimEvent;
use App\Models\BpjsClaimPayment;
use App\Models\CashBankAccount;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BpjsClaimController extends Controller {
 public function index(Request $request){
  $q=BpjsClaim::withSum('payments as paid_amount','amount')->orderByDesc('service_date')->orderByDesc('id');
  if($request->filled('search')) {$s=trim($request->search);$q->where(fn($x)=>$x->where('sep_no','like',"%$s%")->orWhere('patient_name','like',"%$s%")->orWhere('batch_no','like',"%$s%"));}
  if($request->filled('status'))$q->where('status',$request->status);
  $totals=BpjsClaim::selectRaw('COUNT(*) as jumlah, COALESCE(SUM(claimed_amount),0) as diajukan, COALESCE(SUM(approved_amount),0) as disetujui')->first();
  $paid=BpjsClaimPayment::sum('amount');
  return view('accounting.bpjs.index', ['claims'=>$q->paginate(20)->withQueryString(),'totals'=>$totals,'paid'=>$paid]);
 }
 public function create(){return view('accounting.bpjs.create');}
 public function store(Request $request){
  $d=$request->validate(['sep_no'=>'required|string|max:60|unique:bpjs_claims,sep_no','patient_name'=>'required|string|max:150','medical_record_no'=>'nullable|string|max:50','service_date'=>'required|date','service_type'=>'required|in:RAWAT_INAP,RAWAT_JALAN','batch_no'=>'nullable|string|max:100','submitted_at'=>'nullable|date','hospital_tariff'=>'nullable|numeric|min:0','claimed_amount'=>'required|numeric|min:0','notes'=>'nullable|string|max:3000']);
  $d['created_by']=$request->user()->id;
  $c=BpjsClaim::create($d);
  return redirect()->route('accounting.bpjs.show',$c)->with('success','Klaim ditambahkan; belum dicatat sebagai kas masuk.');
 }
 public function show(BpjsClaim $claim){
  $claim->load(['payments.receipt','events']);
  return view('accounting.bpjs.show',['claim'=>$claim,'banks'=>CashBankAccount::where('is_active',1)->orderBy('name')->get(), 'receipts'=>Receipt::where('guarantor_id',2)->where('status','ACTIVE')->whereNotIn('id',BpjsClaimPayment::select('receipt_id'))->latest('id')->limit(250)->get()]);
 }
 public function verify(Request $request,BpjsClaim $claim){
  $d=$request->validate(['approved_amount'=>'required|numeric|min:0','rejected_amount'=>'required|numeric|min:0','status'=>'required|in:DRAFT,DIAJUKAN,VERIFIKASI,PENDING,DISPUTE,DISETUJUI_SEBAGIAN,DISETUJUI,DITOLAK,SELESAI','reason'=>'required|string|max:2000']);
  DB::transaction(function()use($claim,$d,$request){
   $claim=BpjsClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
   $paid=$claim->payments()->sum('amount');
   if($d['approved_amount']<$paid)throw ValidationException::withMessages(['approved_amount'=>'Nominal disetujui tidak boleh kurang dari nominal yang telah dicairkan.']);
   if($d['approved_amount']+$d['rejected_amount']>$claim->claimed_amount)throw ValidationException::withMessages(['approved_amount'=>'Total disetujui dan ditolak melampaui pengajuan.']);
   BpjsClaimEvent::create(['bpjs_claim_id'=>$claim->id,'event_type'=>'VERIFIKASI','old_approved'=>$claim->approved_amount,'new_approved'=>$d['approved_amount'],'old_status'=>$claim->status,'new_status'=>$d['status'],'reason'=>$d['reason'],'created_by'=>$request->user()->id]);
   $claim->update(['approved_amount'=>$d['approved_amount'],'rejected_amount'=>$d['rejected_amount'],'status'=>$d['status']]);
  });
  return back()->with('success','Hasil verifikasi tersimpan beserta riwayat perubahan.');
 }
 public function pay(Request $request,BpjsClaim $claim){
  $d=$request->validate(['amount'=>'required|numeric|gt:0','transaction_date'=>'required|date','cash_bank_account_id'=>'required|exists:cash_bank_accounts,id','receipt_id'=>'nullable|exists:receipts,id','reference_no'=>'nullable|string|max:100']);
  DB::transaction(function()use($request,$claim,$d){
   $claim=BpjsClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
   $remaining=round((float)$claim->approved_amount-(float)$claim->payments()->sum('amount'),2);
   if(round((float)$d['amount'],2)>$remaining)throw ValidationException::withMessages(['amount'=>'Pembayaran lebih besar daripada sisa klaim yang disetujui.']);
   if(!empty($d['receipt_id'])){
    $receipt=Receipt::whereKey($d['receipt_id'])->lockForUpdate()->firstOrFail();
    if($receipt->status!=='ACTIVE'||(int)$receipt->guarantor_id!==2||abs((float)$receipt->amount-(float)$d['amount'])>0.001||$receipt->transaction_date->format('Y-m-d')!==$d['transaction_date']||(int)$receipt->cash_bank_account_id!==(int)$d['cash_bank_account_id'])throw ValidationException::withMessages(['receipt_id'=>'Penerimaan harus BPJS aktif, dengan nominal, tanggal, dan rekening yang sama.']);
    if(BpjsClaimPayment::where('receipt_id',$receipt->id)->exists())throw ValidationException::withMessages(['receipt_id'=>'Transaksi penerimaan ini sudah dipakai.']);
   }else{
    $bank=CashBankAccount::findOrFail($d['cash_bank_account_id']);
    $receipt=Receipt::create(['transaction_no'=>'BPJSPAY-'.strtoupper(bin2hex(random_bytes(8))),'transaction_date'=>$d['transaction_date'],'chart_account_id'=>$bank->chart_account_id, 'patient_name'=>$claim->patient_name,'medical_record_no'=>$claim->medical_record_no,'guarantor_id'=>2,'service_type'=>$claim->service_type,'amount'=>$d['amount'],'payment_method'=>'TRANSFER','cash_bank_account_id'=>$bank->id,'notes'=>'PELUNASAN PIUTANG BPJS SEP '.$claim->sep_no.' / Ref: '.($d['reference_no']??'-').'; bukan pendapatan baru','cashier_id'=>$request->user()->id,'status'=>'ACTIVE']);
   }
   BpjsClaimPayment::create(['bpjs_claim_id'=>$claim->id,'receipt_id'=>$receipt->id,'amount'=>$d['amount']]);
   $paid=(float)$claim->payments()->sum('amount');
   if($paid>=(float)$claim->approved_amount && $paid>0)$claim->update(['status'=>'SELESAI']);
  });
  return back()->with('success','Pembayaran ditautkan ke Penerimaan satu kali. Tidak ada pengakuan pendapatan otomatis.');
 }
}
