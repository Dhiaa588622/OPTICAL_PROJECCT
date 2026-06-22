<?php

namespace App\Http\Controllers;

use App\Support\SetupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientController extends Controller
{
    private const PAGES = [
        'dashboard' => 'Patient Dashboard',
        'patients' => 'Patient List',
        'patient-create' => 'Create / Edit Patient',
        'profile' => 'Patient Profile',
        'eye-exam' => 'Eye Exam',
        'prescription' => 'Prescription',
        'prescription-print' => 'Prescription Print',
        'timeline' => 'Timeline',
        'documents' => 'Documents',
        'follow-ups' => 'Follow-ups',
        'reports' => 'Reports',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('patients')) {
            return view('patients.app', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('patients.app', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->patientData($request),
        ]);
    }

    public function storePatient(Request $request)
    {
        $validated = $this->patientRules($request);

        $patientId = DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $companyId = $this->companyId();
            $patientCode = trim((string) ($validated['patient_code'] ?? '')) ?: $this->nextNumber('PT', 'patients', 'patient_code');

            $patientId = DB::table('patients')->insertGetId([
                'company_id' => $companyId,
                'patient_code' => $patientCode,
                'full_name' => $validated['full_name'],
                'gender' => $validated['gender'] ?? 'not_specified',
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncSalesCustomer($patientId, $companyId, $now);
            $this->timeline($patientId, 'registration', 'Patient registered', $now, 'Patient file created.', 'patient', $patientId);
            $this->audit('patients.record.created', 'patient', $patientId, null, null, ['patient_code' => $patientCode], $now);

            return $patientId;
        });

        return $this->respond($request, ['status' => 'created', 'patient_id' => $patientId], 'profile', 'Patient registered and synced to POS customers.');
    }

    public function updatePatient(Request $request, int $patient)
    {
        $validated = $this->patientRules($request, $patient);

        DB::transaction(function () use ($validated, $request, $patient): void {
            $now = now();
            $before = DB::table('patients')->where('id', $patient)->first();
            if (! $before) {
                throw ValidationException::withMessages(['patient_id' => 'Patient was not found.']);
            }

            DB::table('patients')->where('id', $patient)->update([
                'patient_code' => trim((string) ($validated['patient_code'] ?? '')) ?: $before->patient_code,
                'full_name' => $validated['full_name'],
                'gender' => $validated['gender'] ?? 'not_specified',
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'updated_at' => $now,
            ]);

            $this->syncSalesCustomer($patient, (int) $before->company_id, $now);
            $this->timeline($patient, 'note', 'Patient profile updated', $now, 'Registration details were updated.', 'patient', $patient);
            $this->audit('patients.record.updated', 'patient', $patient, null, (array) $before, $validated, $now);
        });

        return $this->respond($request, ['status' => 'updated', 'patient_id' => $patient], 'profile', 'Patient profile updated.');
    }

    public function storeExam(Request $request)
    {
        $validated = $this->examRules($request);

        $examId = DB::transaction(function () use ($validated): int {
            $now = now();
            $examId = DB::table('patient_eye_exams')->insertGetId([
                'patient_id' => $validated['patient_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'optometrist_id' => $validated['optometrist_id'] ?? null,
                'created_by' => $validated['created_by'] ?? null,
                'exam_number' => $this->nextNumber('EXM', 'patient_eye_exams', 'exam_number'),
                'exam_date' => $validated['exam_date'] ?? $now->toDateString(),
                'status' => $validated['status'] ?? 'completed',
                ...$this->eyeFields($validated),
                'diagnosis' => $validated['diagnosis'] ?? null,
                'optometrist_notes' => $validated['optometrist_notes'] ?? null,
                'recommendation' => $validated['recommendation'] ?? null,
                'next_visit_date' => $validated['next_visit_date'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $validated['patient_id'], 'exam', 'Eye exam completed', $now, $validated['diagnosis'] ?? 'Optical examination recorded.', 'patient_eye_exam', $examId, $validated['branch_id'] ?? null, $validated['optometrist_id'] ?? null);
            $this->audit('patients.exam.created', 'patient_eye_exam', $examId, $validated['branch_id'] ?? null, null, ['patient_id' => $validated['patient_id']], $now);

            return $examId;
        });

        return $this->respond($request, ['status' => 'created', 'exam_id' => $examId, 'patient_id' => $validated['patient_id']], 'profile', 'Eye exam saved to the patient file.');
    }

    public function storePrescription(Request $request)
    {
        $validated = $this->prescriptionRules($request);

        $prescriptionId = DB::transaction(function () use ($validated): int {
            $now = now();
            $status = $validated['status'] ?? 'draft';
            $prescriptionId = DB::table('patient_prescriptions')->insertGetId([
                'patient_id' => $validated['patient_id'],
                'patient_eye_exam_id' => $validated['patient_eye_exam_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'optometrist_id' => $validated['optometrist_id'] ?? null,
                'created_by' => $validated['created_by'] ?? null,
                'prescription_number' => $this->nextNumber('RX', 'patient_prescriptions', 'prescription_number'),
                'status' => $status,
                'prescribed_on' => $validated['prescribed_on'] ?? $now->toDateString(),
                'expires_on' => $validated['expires_on'] ?? null,
                'signed_at' => in_array($status, ['signed', 'locked'], true) ? $now : null,
                'locked_at' => $status === 'locked' ? $now : null,
                ...$this->eyeFields($validated),
                'diagnosis' => $validated['diagnosis'] ?? null,
                'recommendation' => $validated['recommendation'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $validated['patient_id'], 'prescription', 'Prescription created', $now, 'Prescription status: '.$status.'.', 'patient_prescription', $prescriptionId, $validated['branch_id'] ?? null, $validated['optometrist_id'] ?? null);
            $this->audit('patients.prescription.created', 'patient_prescription', $prescriptionId, $validated['branch_id'] ?? null, null, ['status' => $status], $now);

            return $prescriptionId;
        });

        return $this->respond($request, ['status' => 'created', 'prescription_id' => $prescriptionId, 'patient_id' => $validated['patient_id']], 'prescription-print', 'Prescription created.');
    }

    public function updatePrescription(Request $request, int $prescription)
    {
        $validated = $this->prescriptionRules($request);

        DB::transaction(function () use ($validated, $prescription): void {
            $now = now();
            $existing = DB::table('patient_prescriptions')->where('id', $prescription)->first();
            if (! $existing) {
                throw ValidationException::withMessages(['prescription_id' => 'Prescription was not found.']);
            }
            if ($existing->status !== 'draft') {
                throw ValidationException::withMessages(['prescription_id' => 'Only draft prescriptions can be edited.']);
            }

            DB::table('patient_prescriptions')->where('id', $prescription)->update([
                'patient_eye_exam_id' => $validated['patient_eye_exam_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'optometrist_id' => $validated['optometrist_id'] ?? null,
                'prescribed_on' => $validated['prescribed_on'] ?? $existing->prescribed_on,
                'expires_on' => $validated['expires_on'] ?? null,
                ...$this->eyeFields($validated),
                'diagnosis' => $validated['diagnosis'] ?? null,
                'recommendation' => $validated['recommendation'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $existing->patient_id, 'prescription', 'Draft prescription edited', $now, 'Draft prescription was updated.', 'patient_prescription', $prescription, $validated['branch_id'] ?? null, $validated['optometrist_id'] ?? null);
        });

        return $this->respond($request, ['status' => 'updated', 'prescription_id' => $prescription, 'patient_id' => $validated['patient_id']], 'prescription-print', 'Draft prescription updated.');
    }

    public function lockPrescription(Request $request, int $prescription)
    {
        $prescriptionRow = DB::transaction(function () use ($prescription) {
            $now = now();
            $existing = DB::table('patient_prescriptions')->where('id', $prescription)->first();
            if (! $existing) {
                throw ValidationException::withMessages(['prescription_id' => 'Prescription was not found.']);
            }

            DB::table('patient_prescriptions')->where('id', $prescription)->update([
                'status' => 'locked',
                'signed_at' => $existing->signed_at ?: $now,
                'locked_at' => $now,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $existing->patient_id, 'prescription', 'Prescription locked', $now, 'Signed prescription locked for printing and order creation.', 'patient_prescription', $prescription, $existing->branch_id, $existing->optometrist_id);
            $this->audit('patients.prescription.locked', 'patient_prescription', $prescription, $existing->branch_id, ['status' => $existing->status], ['status' => 'locked'], $now);

            return DB::table('patient_prescriptions')->where('id', $prescription)->first();
        });

        return $this->respond($request, ['status' => 'locked', 'prescription_id' => $prescription, 'patient_id' => $prescriptionRow->patient_id], 'prescription-print', 'Prescription locked.');
    }

    public function storeDocument(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'uploaded_by' => ['nullable', 'integer'],
            'document_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'max:10240'],
        ]);

        $documentId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $path = null;
            $original = null;
            $mime = null;
            $size = null;

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $path = $file->store('patient-documents', 'public');
                $original = $file->getClientOriginalName();
                $mime = $file->getClientMimeType();
                $size = $file->getSize();
            }

            $documentId = DB::table('patient_documents')->insertGetId([
                'patient_id' => $validated['patient_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'uploaded_by' => $validated['uploaded_by'] ?? null,
                'document_number' => $this->nextNumber('DOC', 'patient_documents', 'document_number'),
                'document_type' => $validated['document_type'],
                'title' => $validated['title'],
                'original_filename' => $original,
                'file_path' => $path,
                'mime_type' => $mime,
                'file_size' => $size,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $validated['patient_id'], 'document', 'Document uploaded', $now, $validated['title'], 'patient_document', $documentId, $validated['branch_id'] ?? null, $validated['uploaded_by'] ?? null);

            return $documentId;
        });

        return $this->respond($request, ['status' => 'uploaded', 'document_id' => $documentId, 'patient_id' => $validated['patient_id']], 'documents', 'Patient document saved.');
    }

    public function storeTimelineNote(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'event_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $eventId = $this->timeline(
            (int) $validated['patient_id'],
            'note',
            $validated['event_title'],
            now(),
            $validated['description'] ?? null,
            'manual_note',
            null,
            $validated['branch_id'] ?? null,
            $validated['user_id'] ?? null,
        );

        return $this->respond($request, ['status' => 'created', 'event_id' => $eventId, 'patient_id' => $validated['patient_id']], 'timeline', 'Timeline note added.');
    }

    public function meta()
    {
        return response()->json([
            'module' => config('patients.module'),
            'genders' => app(SetupOptions::class)->options('genders'),
            'exam_statuses' => app(SetupOptions::class)->options('exam_statuses'),
            'prescription_statuses' => app(SetupOptions::class)->options('prescription_statuses'),
            'document_types' => app(SetupOptions::class)->options('document_types'),
            'timeline_types' => config('patients.timeline_types'),
            'roles' => config('patients.roles'),
            'permissions' => config('patients.permissions'),
            'reports' => config('patients.reports'),
            'integration_rules' => config('patients.integration_rules'),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! Schema::hasTable('patients')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        return response()->json([
            'business_date' => now()->toDateString(),
            'metrics' => $this->metrics(),
            'follow_ups' => $this->followUps()->take(10)->values(),
            'recent_patients' => $this->patients('', null)->take(10)->values(),
        ]);
    }

    public function patientsApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'query' => $q,
            'searchable_fields' => ['full_name', 'phone', 'whatsapp_number', 'patient_code'],
            'data' => $this->patients($q, $request->query('status')),
        ]);
    }

    public function showApi(int $patient)
    {
        return response()->json($this->patientProfile($patient));
    }

    public function timelineApi(int $patient)
    {
        return response()->json([
            'patient_id' => $patient,
            'data' => $this->timelineEvents($patient),
        ]);
    }

    public function reportApi(Request $request, string $report)
    {
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $data = match ($report) {
            'new-patients' => DB::table('patients')
                ->select('patient_code', 'full_name', 'phone', 'whatsapp_number', 'created_at')
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->orderByDesc('created_at')
                ->get(),
            'active-patients' => DB::table('patients')
                ->select('patient_code', 'full_name', 'phone', 'whatsapp_number', 'created_at')
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),
            'patient-visit-history' => DB::table('patient_timeline_events')
                ->join('patients', 'patients.id', '=', 'patient_timeline_events.patient_id')
                ->select('patients.patient_code', 'patients.full_name', 'patient_timeline_events.event_type', 'patient_timeline_events.event_title', 'patient_timeline_events.event_at')
                ->whereIn('patient_timeline_events.event_type', ['appointment', 'exam', 'prescription'])
                ->whereDate('patient_timeline_events.event_at', '>=', $dateFrom)
                ->whereDate('patient_timeline_events.event_at', '<=', $dateTo)
                ->orderByDesc('patient_timeline_events.event_at')
                ->get(),
            'prescription-history' => DB::table('patient_prescriptions')
                ->join('patients', 'patients.id', '=', 'patient_prescriptions.patient_id')
                ->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
                ->select('patients.patient_code', 'patients.full_name', 'patient_prescriptions.prescription_number', 'patient_prescriptions.status', 'patient_prescriptions.prescribed_on', 'users.name as optometrist_name')
                ->whereBetween('patient_prescriptions.prescribed_on', [$dateFrom, $dateTo])
                ->orderByDesc('patient_prescriptions.prescribed_on')
                ->get(),
            'exams-by-optometrist' => DB::table('patient_eye_exams')
                ->leftJoin('users', 'users.id', '=', 'patient_eye_exams.optometrist_id')
                ->selectRaw('coalesce(users.name, "Unassigned") as optometrist_name, count(*) as exams')
                ->whereBetween('patient_eye_exams.exam_date', [$dateFrom, $dateTo])
                ->groupBy('users.name')
                ->orderByDesc('exams')
                ->get(),
            'follow-up' => $this->followUps($dateFrom, $dateTo),
            default => abort(404),
        };

        return response()->json([
            'report' => $report,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'data' => $data,
        ]);
    }

    private function patientData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $patientId = $request->integer('patient_id') ?: (int) (DB::table('patients')->orderByDesc('id')->value('id') ?? 0);
        $prescriptionId = $request->integer('prescription_id');

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'metrics' => $this->metrics(),
            'patients' => $this->patients($query, $request->query('status')),
            'selectedPatient' => $patientId ? DB::table('patients')->where('id', $patientId)->first() : null,
            'profile' => $patientId ? $this->patientProfile($patientId) : [],
            'genders' => app(SetupOptions::class)->options('genders'),
            'documentTypes' => app(SetupOptions::class)->options('document_types'),
            'examStatuses' => app(SetupOptions::class)->options('exam_statuses'),
            'prescriptionStatuses' => app(SetupOptions::class)->options('prescription_statuses'),
            'reports' => config('patients.reports'),
            'optometrists' => DB::table('users')->orderBy('name')->get(),
            'recentExams' => $this->recentExams($patientId ?: null),
            'recentPrescriptions' => $this->recentPrescriptions($patientId ?: null),
            'printPrescription' => $this->printPrescription($prescriptionId, $patientId),
            'followUps' => $this->followUps(),
        ];
    }

    private function patientProfile(int $patientId): array
    {
        $patient = DB::table('patients')->where('id', $patientId)->first();
        if (! $patient) {
            return [];
        }

        return [
            'patient' => $patient,
            'appointments' => DB::table('patient_appointments')
                ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
                ->select('patient_appointments.*', 'branches.name as branch_name')
                ->where('patient_appointments.patient_id', $patientId)
                ->orderByDesc('appointment_at')
                ->limit(20)
                ->get(),
            'exams' => $this->recentExams($patientId),
            'prescriptions' => $this->recentPrescriptions($patientId),
            'documents' => DB::table('patient_documents')->where('patient_id', $patientId)->orderByDesc('id')->get(),
            'timeline' => $this->timelineEvents($patientId),
            'whatsapp' => DB::table('patient_whatsapp_messages')->where('patient_id', $patientId)->orderByDesc('sent_at')->limit(20)->get(),
            'sales' => $this->salesHistory($patientId),
            'payments' => $this->paymentHistory($patientId),
        ];
    }

    private function patients(string $query, ?string $status)
    {
        return DB::table('patients')
            ->select('patients.*')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('full_name', 'like', "%{$query}%")
                        ->orWhere('patient_code', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('whatsapp_number', 'like', "%{$query}%");
                });
            })
            ->when($status === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($status === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function metrics(): array
    {
        return [
            'total_patients' => DB::table('patients')->count(),
            'active_patients' => DB::table('patients')->where('is_active', true)->count(),
            'new_today' => DB::table('patients')->whereDate('created_at', now()->toDateString())->count(),
            'new_this_month' => DB::table('patients')->whereDate('created_at', '>=', now()->startOfMonth()->toDateString())->count(),
            'exams_today' => DB::table('patient_eye_exams')->whereDate('exam_date', now()->toDateString())->count(),
            'prescriptions_month' => DB::table('patient_prescriptions')->whereDate('prescribed_on', '>=', now()->startOfMonth()->toDateString())->count(),
            'followups_due' => DB::table('patient_eye_exams')
                ->whereNotNull('next_visit_date')
                ->whereDate('next_visit_date', '<=', now()->addDays(14)->toDateString())
                ->count(),
            'documents' => DB::table('patient_documents')->count(),
        ];
    }

    private function recentExams(?int $patientId = null)
    {
        return DB::table('patient_eye_exams')
            ->join('patients', 'patients.id', '=', 'patient_eye_exams.patient_id')
            ->leftJoin('users', 'users.id', '=', 'patient_eye_exams.optometrist_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_eye_exams.branch_id')
            ->select('patient_eye_exams.*', 'patients.full_name', 'patients.patient_code', 'users.name as optometrist_name', 'branches.name as branch_name')
            ->when($patientId, fn ($query) => $query->where('patient_eye_exams.patient_id', $patientId))
            ->orderByDesc('patient_eye_exams.exam_date')
            ->limit(30)
            ->get();
    }

    private function recentPrescriptions(?int $patientId = null)
    {
        return DB::table('patient_prescriptions')
            ->join('patients', 'patients.id', '=', 'patient_prescriptions.patient_id')
            ->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_prescriptions.branch_id')
            ->select('patient_prescriptions.*', 'patients.full_name', 'patients.patient_code', 'users.name as optometrist_name', 'branches.name as branch_name')
            ->when($patientId, fn ($query) => $query->where('patient_prescriptions.patient_id', $patientId))
            ->orderByDesc('patient_prescriptions.prescribed_on')
            ->limit(30)
            ->get();
    }

    private function timelineEvents(int $patientId)
    {
        return DB::table('patient_timeline_events')
            ->leftJoin('users', 'users.id', '=', 'patient_timeline_events.user_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_timeline_events.branch_id')
            ->select('patient_timeline_events.*', 'users.name as user_name', 'branches.name as branch_name')
            ->where('patient_timeline_events.patient_id', $patientId)
            ->orderByDesc('patient_timeline_events.event_at')
            ->limit(80)
            ->get();
    }

    private function salesHistory(int $patientId)
    {
        if (! Schema::hasTable('sales_invoices')) {
            return collect();
        }

        return DB::table('sales_invoices')
            ->join('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->select('sales_invoices.*', 'branches.name as branch_name')
            ->where('sales_customers.patient_id', $patientId)
            ->orderByDesc('sales_invoices.invoice_date')
            ->limit(20)
            ->get();
    }

    private function paymentHistory(int $patientId)
    {
        if (! Schema::hasTable('sales_payments')) {
            return collect();
        }

        return DB::table('sales_payments')
            ->join('sales_customers', 'sales_customers.id', '=', 'sales_payments.customer_id')
            ->select('sales_payments.*')
            ->where('sales_customers.patient_id', $patientId)
            ->orderByDesc('sales_payments.paid_at')
            ->limit(20)
            ->get();
    }

    private function followUps(?string $dateFrom = null, ?string $dateTo = null)
    {
        $from = $dateFrom ?: now()->subDays(30)->toDateString();
        $to = $dateTo ?: now()->addDays(14)->toDateString();

        return DB::table('patient_eye_exams')
            ->join('patients', 'patients.id', '=', 'patient_eye_exams.patient_id')
            ->leftJoin('users', 'users.id', '=', 'patient_eye_exams.optometrist_id')
            ->select('patient_eye_exams.id', 'patient_eye_exams.patient_id', 'patients.patient_code', 'patients.full_name', 'patients.phone', 'patients.whatsapp_number', 'patient_eye_exams.next_visit_date', 'patient_eye_exams.recommendation', 'users.name as optometrist_name')
            ->whereNotNull('patient_eye_exams.next_visit_date')
            ->whereBetween('patient_eye_exams.next_visit_date', [$from, $to])
            ->orderBy('patient_eye_exams.next_visit_date')
            ->limit(80)
            ->get();
    }

    private function printPrescription(int $prescriptionId, int $patientId): ?object
    {
        $prescription = DB::table('patient_prescriptions')
            ->join('patients', 'patients.id', '=', 'patient_prescriptions.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_prescriptions.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'patients.company_id')
            ->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
            ->select('patient_prescriptions.*', 'patients.patient_code', 'patients.full_name', 'patients.phone', 'patients.whatsapp_number', 'patients.date_of_birth', 'branches.name as branch_name', 'branches.phone as branch_phone', 'branches.address as branch_address', 'companies.name as company_name', 'users.name as optometrist_name')
            ->when($prescriptionId > 0, fn ($query) => $query->where('patient_prescriptions.id', $prescriptionId))
            ->when($prescriptionId <= 0 && $patientId > 0, fn ($query) => $query->where('patient_prescriptions.patient_id', $patientId)->orderByDesc('patient_prescriptions.id'))
            ->when($prescriptionId <= 0 && $patientId <= 0, fn ($query) => $query->orderByDesc('patient_prescriptions.id'))
            ->first();

        if (! $prescription) {
            return null;
        }

        $previous = DB::table('patient_prescriptions')
            ->where('patient_id', $prescription->patient_id)
            ->where('id', '<>', $prescription->id)
            ->orderByDesc('prescribed_on')
            ->first();
        $prescription->previous = $previous;

        return $prescription;
    }

    private function patientRules(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'patient_code' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function examRules(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'optometrist_id' => ['nullable', 'integer'],
            'created_by' => ['nullable', 'integer'],
            'exam_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:255'],
            ...$this->eyeValidationRules(),
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'optometrist_notes' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'next_visit_date' => ['nullable', 'date'],
        ]);
    }

    private function prescriptionRules(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer'],
            'patient_eye_exam_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'optometrist_id' => ['nullable', 'integer'],
            'created_by' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:255'],
            'prescribed_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            ...$this->eyeValidationRules(),
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function eyeValidationRules(): array
    {
        return [
            'right_sph' => ['nullable', 'numeric'],
            'right_cyl' => ['nullable', 'numeric'],
            'right_axis' => ['nullable', 'integer', 'min:0', 'max:180'],
            'right_add' => ['nullable', 'numeric'],
            'right_pd' => ['nullable', 'numeric'],
            'right_va' => ['nullable', 'string', 'max:255'],
            'left_sph' => ['nullable', 'numeric'],
            'left_cyl' => ['nullable', 'numeric'],
            'left_axis' => ['nullable', 'integer', 'min:0', 'max:180'],
            'left_add' => ['nullable', 'numeric'],
            'left_pd' => ['nullable', 'numeric'],
            'left_va' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function eyeFields(array $validated): array
    {
        return [
            'right_sph' => $validated['right_sph'] ?? null,
            'right_cyl' => $validated['right_cyl'] ?? null,
            'right_axis' => $validated['right_axis'] ?? null,
            'right_add' => $validated['right_add'] ?? null,
            'right_pd' => $validated['right_pd'] ?? null,
            'right_va' => $validated['right_va'] ?? null,
            'left_sph' => $validated['left_sph'] ?? null,
            'left_cyl' => $validated['left_cyl'] ?? null,
            'left_axis' => $validated['left_axis'] ?? null,
            'left_add' => $validated['left_add'] ?? null,
            'left_pd' => $validated['left_pd'] ?? null,
            'left_va' => $validated['left_va'] ?? null,
        ];
    }

    private function syncSalesCustomer(int $patientId, int $companyId, $now): void
    {
        if (! Schema::hasTable('sales_customers')) {
            return;
        }

        $patient = DB::table('patients')->where('id', $patientId)->first();
        if (! $patient) {
            return;
        }

        $existingId = DB::table('sales_customers')->where('patient_id', $patientId)->value('id');
        if (! $existingId && $patient->phone) {
            $existingId = DB::table('sales_customers')
                ->where('company_id', $companyId)
                ->where('phone', $patient->phone)
                ->value('id');
        }

        $values = [
            'company_id' => $companyId,
            'patient_id' => $patientId,
            'name' => $patient->full_name,
            'phone' => $patient->phone ?: $patient->whatsapp_number,
            'email' => $patient->email,
            'whatsapp_opt_in' => (bool) $patient->whatsapp_number,
            'notes' => 'Synced from Patient Management.',
            'updated_at' => $now,
        ];

        if ($existingId) {
            DB::table('sales_customers')->where('id', $existingId)->update($values);
        } else {
            DB::table('sales_customers')->insert([
                ...$values,
                'created_at' => $now,
            ]);
        }
    }

    private function timeline(int $patientId, string $type, string $title, $eventAt, ?string $description = null, ?string $sourceType = null, ?int $sourceId = null, ?int $branchId = null, ?int $userId = null, ?array $metadata = null): int
    {
        return DB::table('patient_timeline_events')->insertGetId([
            'patient_id' => $patientId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'event_type' => $type,
            'event_title' => $title,
            'event_at' => $eventAt,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function companyId(): int
    {
        return (int) DB::table('companies')->orderBy('id')->value('id');
    }

    private function nextNumber(string $prefix, string $table, string $column): string
    {
        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (DB::table($table)->where($column, $number)->exists());

        return $number;
    }

    private function audit(string $action, string $type, int $id, ?int $branchId, ?array $before, ?array $after, $now): void
    {
        DB::table('audit_logs')->insert([
            'branch_id' => $branchId,
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'before_values' => $before ? json_encode($before) : null,
            'after_values' => $after ? json_encode($after) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function respond(Request $request, array $payload, string $page, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload);
        }

        $params = ['page' => $page];
        if ($payload['patient_id'] ?? null) {
            $params['patient_id'] = $payload['patient_id'];
        }
        if (($payload['prescription_id'] ?? null) && $page === 'prescription-print') {
            $params['prescription_id'] = $payload['prescription_id'];
        }

        return redirect()->route('patients.app', $params)->with('status', __($message));
    }
}
