<?php
namespace App\Http\Controllers;
use App\Models\Guarantor;
use App\Models\InsuranceMonthlyClaim as Claim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class InsuranceClaimController extends Controller {
 private function role(Request $r): string{return strtoupper((string)($r->user()->accountingRole()??''));}
 private function editor(Request $r): bool{return in_array($this->role($r),['ASURANSI','ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN'],true);}
 private function supervisor(Request $r): bool{return in_array($this->role($r),['ACCOUNTING','ADMIN_ACCOUNTING','KEPALA_KEUANGAN'],true);}
 public function index(Request $r){
   $q=Claim::with('guarantor')->orderByDesc('year')->orderByDesc('month')->orderBy('guarantor_id');
   if($r->filled('year'))$q->where('year',$r->integer('year'));
   if($r->filled('month'))$q->where('month',$r->integer('month'));
   if($r->filled('guarantor_id'))$q->where('guarantor_id',$r->integer('guarantor_id'));
   $claims=$q->paginate(30)->withQueryString();
   $totalQuery=Claim::query();
   if($r->filled('year'))$totalQuery->where('year',$r->integer('year'));
   if($r->filled('month'))$totalQuery->where('month',$r->integer('month'));
   if($r->filled('guarantor_id'))$totalQuery->where('guarantor_id',$r->integer('guarantor_id'));
   $totals=['claimed'=>(clone $totalQuery)->sum('claimed_amount'),'approved'=>(clone $totalQuery)->sum('approved_amount'),'paid'=>(clone $totalQuery)->sum('paid_amount')];
   $byGuarantor=(clone $totalQuery)->select('guarantor_id')->selectRaw('SUM(claimed_amount) claimed, SUM(COALESCE(approved_amount,0)) approved, SUM(paid_amount) paid')->groupBy('guarantor_id')->with('guarantor')->get();
   return view('insurance.index',['claims'=>$claims,'totals'=>$totals,'byGuarantor'=>$byGuarantor,'guarantors'=>$this->guarantors(),'canEdit'=>$this->editor($r),'canFinish'=>$this->supervisor($r)]);
 }
 public function create(Request $r){return $this->form($r,new Claim());}
 public function form(Request $r,Claim $claim){abort_unless($this->editor($r),403);return view('insurance.form',['claim'=>$claim,'guarantors'=>$this->guarantors()]);}
 private function guarantors(){return Guarantor::whereIn('category',['BPJS','PRIVATE_INSURANCE','CORPORATE','OTHER'])->orderBy('name')->get();}
 private function validateClaim(Request $r,?Claim $claim=null):array{
   $data=$r->validate(['guarantor_id'=>['required','integer',Rule::exists('guarantors','id')->where(fn($q)=>$q->whereIn('category',['BPJS','PRIVATE_INSURANCE','CORPORATE','OTHER']))],
     'year'=>'required|integer|between:2020,2100','month'=>'required|integer|between:1,12','service_type'=>'required|in:RAWAT_JALAN,RAWAT_INAP',
     'claimed_amount'=>'required|numeric|min:0','approved_amount'=>'nullable|numeric|min:0','paid_amount'=>'nullable|numeric|min:0',
     'status'=>'required|in:DIAJUKAN,PENDING,DISETUJUI,SEBAGIAN,DICLAIM,DITOLAK','submitted_at'=>'nullable|date',
     'settled_at'=>'nullable|date','notes'=>'nullable|string|max:5000']);
   $dup=Claim::where('guarantor_id',$data['guarantor_id'])->where('year',$data['year'])->where('month',$data['month'])->where('service_type',$data['service_type']);
   if($claim)$dup->where('id','!=',$claim->id);
   if($dup->exists())throw \Illuminate\Validation\ValidationException::withMessages(['month'=>'Sudah ada klaim penjamin dan jenis pelayanan tersebut pada bulan ini. Edit data yang ada.']);
   if(isset($data['approved_amount']) && $data['approved_amount']>$data['claimed_amount'])throw \Illuminate\Validation\ValidationException::withMessages(['approved_amount'=>'Nilai disetujui lebih besar daripada nilai diajukan.']);
   if(!isset($data['approved_amount']) && ($data['paid_amount']??0)>0)throw \Illuminate\Validation\ValidationException::withMessages(['paid_amount'=>'Isi nilai yang disetujui sebelum pencairan.']);
   if(isset($data['approved_amount']) && ($data['paid_amount']??0)>$data['approved_amount'])throw \Illuminate\Validation\ValidationException::withMessages(['paid_amount'=>'Nilai pencairan tidak boleh melebihi persetujuan.']);
   return $data;
 }
 public function store(Request $r){abort_unless($this->editor($r),403);$data=$this->validateClaim($r);$data['created_by']=$r->user()->id;$data['updated_by']=$r->user()->id;Claim::create($data);return redirect()->route('insurance.index')->with('success','Klaim bulanan berhasil disimpan. Nominal klaim tidak otomatis menjadi penerimaan kas.');}
 public function update(Request $r,Claim $claim){abort_unless($this->editor($r),403);$data=$this->validateClaim($r,$claim);$data['updated_by']=$r->user()->id;$claim->update($data);return redirect()->route('insurance.index')->with('success','Klaim diperbarui.');}
 public function finish(Request $r,Claim $claim){abort_unless($this->supervisor($r),403);$r->validate(['settled_at'=>'required|date']);abort_if($claim->approved_amount===null,422,'Isi nilai persetujuan terlebih dahulu.');abort_if((float)$claim->paid_amount < (float)$claim->approved_amount,422,'Belum lunas: nominal dibayar kurang dari nominal disetujui.');$claim->update(['status'=>'DICLAIM','settled_at'=>$r->settled_at,'updated_by'=>$r->user()->id]);return back()->with('success','Klaim ditandai selesai. Pencairan tidak otomatis ditambahkan ke Penerimaan.');}
 public function destroy(Request $r,Claim $claim){abort_unless($this->supervisor($r),403);abort_if((float)$claim->paid_amount>0,422,'Data sudah memiliki pencairan. Koreksi nominal, jangan hapus histori pembayaran.');$claim->delete();return back()->with('success','Data klaim belum cair dihapus.');}
}
