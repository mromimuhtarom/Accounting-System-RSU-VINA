<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('medical_record_no', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return view('cashier.patients.index', [
            'patients' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create() { return view('cashier.patients.create'); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        Patient::create($data);

        return redirect()->route('cashier.patients.index')->with('success', 'Data pasien berhasil ditambahkan.');
    }

    public function edit(Patient $patient) { return view('cashier.patients.edit', compact('patient')); }

    public function update(Request $request, Patient $patient)
    {
        $data = $this->validated($request, $patient->id);

        DB::transaction(function () use ($patient, $data) {
            $patient->update($data);

            // Kolom snapshot lama tetap disinkronkan agar pencarian/data legacy ikut berubah.
            // Relasi patient_id tetap menjadi sumber data utama.
            Receipt::where('patient_id', $patient->id)->each(fn ($receipt) => $receipt->update([
                'medical_record_no' => $patient->medical_record_no,
                'patient_name' => $patient->name,
                'updated_at' => now(),
            ]));
        });

        return redirect()->route('cashier.patients.index')->with('success', 'Data pasien berhasil diperbarui. Penerimaan dan pengeluaran terkait otomatis menggunakan data pasien terbaru.');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('cashier.patients.index')->with('success', 'Data pasien dihapus dari daftar aktif. Riwayat transaksi tetap tersimpan.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'medical_record_no' => ['required', 'string', 'max:50', Rule::unique('patients', 'medical_record_no')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'in:MALE,FEMALE'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
