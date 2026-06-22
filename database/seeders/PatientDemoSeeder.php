<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PatientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchIds = DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        if (! $companyId || $branchIds === []) {
            return;
        }

        $optometristId = $this->user('optometrist@example.com', 'Dr. Noor Optometrist', $now);
        $receptionistId = $this->user('reception@example.com', 'Clinic Receptionist', $now);

        $this->permissionsAndRoles($companyId, $now);
        $patientIds = $this->patients($companyId, $now);
        $this->syncSalesCustomers($companyId, $patientIds, $now);
        $this->appointments($branchIds, $patientIds, $optometristId, $now);
        $examIds = $this->exams($branchIds, $patientIds, $optometristId, $receptionistId, $now);
        $this->prescriptions($branchIds, $patientIds, $examIds, $optometristId, $receptionistId, $now);
        $this->documents($branchIds, $patientIds, $receptionistId, $now);
        $this->whatsapp($patientIds, $now);
        $this->timeline($branchIds, $patientIds, $examIds, $optometristId, $receptionistId, $now);
    }

    private function user(string $email, string $name, $now): int
    {
        $id = DB::table('users')->where('email', $email)->value('id');
        if ($id) {
            return (int) $id;
        }

        return DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function patients(int $companyId, $now): array
    {
        $rows = [
            'PT-1001' => ['Sara Ahmed', 'female', '1992-04-12', '+966500111222', '+966500111222', 'sara@example.test', 'Olaya, Riyadh', 'Huda Ahmed', '+966500111223', 'Prefers WhatsApp reminders.'],
            'PT-1002' => ['Omar Khaled', 'male', '1988-11-03', '+966500333444', '+966500333444', 'omar@example.test', 'Al Malqa, Riyadh', 'Noura Khaled', '+966500333445', 'Progressive lens wearer.'],
            'PT-1003' => ['Maha Saleh', 'female', '2001-07-22', '+966500555666', '+966500555666', 'maha@example.test', 'North Riyadh', 'Saleh Ali', '+966500555667', 'Contact lens follow-up due.'],
            'PT-1004' => ['Walk-in Patient', 'not_specified', null, null, null, null, null, null, null, 'Generic walk-in profile.'],
        ];

        foreach ($rows as $code => [$name, $gender, $dob, $phone, $whatsapp, $email, $address, $emergencyName, $emergencyPhone, $notes]) {
            DB::table('patients')->updateOrInsert(
                ['patient_code' => $code],
                [
                    'company_id' => $companyId,
                    'full_name' => $name,
                    'gender' => $gender,
                    'date_of_birth' => $dob,
                    'phone' => $phone,
                    'whatsapp_number' => $whatsapp,
                    'email' => $email,
                    'address' => $address,
                    'emergency_contact_name' => $emergencyName,
                    'emergency_contact_phone' => $emergencyPhone,
                    'notes' => $notes,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('patients')->pluck('id', 'patient_code')->map(fn ($id) => (int) $id)->all();
    }

    private function syncSalesCustomers(int $companyId, array $patientIds, $now): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sales_customers')) {
            return;
        }

        foreach ($patientIds as $code => $patientId) {
            $patient = DB::table('patients')->where('id', $patientId)->first();
            $existingId = null;
            if ($patient->phone) {
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
                'notes' => 'Synced from Patient Management demo data.',
                'updated_at' => $now,
            ];

            if ($existingId) {
                DB::table('sales_customers')->where('id', $existingId)->update($values);
            } else {
                DB::table('sales_customers')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $patient->full_name],
                    [...$values, 'created_at' => $now],
                );
            }
        }
    }

    private function appointments(array $branchIds, array $patientIds, int $optometristId, $now): void
    {
        $rows = [
            ['APT-1001', 'PT-1001', 'MAIN', now()->subDays(12)->setTime(10, 0), 'Annual eye exam', 'completed'],
            ['APT-1002', 'PT-1002', 'MAIN', now()->subDays(4)->setTime(15, 30), 'Progressive lens review', 'completed'],
            ['APT-1003', 'PT-1003', 'NORTH', now()->addDays(7)->setTime(12, 15), 'Contact lens follow-up', 'scheduled'],
        ];

        foreach ($rows as [$number, $code, $branch, $at, $purpose, $status]) {
            DB::table('patient_appointments')->updateOrInsert(
                ['appointment_number' => $number],
                [
                    'patient_id' => $patientIds[$code],
                    'branch_id' => $branchIds[$branch] ?? $branchIds['MAIN'],
                    'assigned_to' => $optometristId,
                    'appointment_at' => $at,
                    'purpose' => $purpose,
                    'status' => $status,
                    'notes' => $purpose,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function exams(array $branchIds, array $patientIds, int $optometristId, int $receptionistId, $now): array
    {
        $rows = [
            'EXM-1001' => ['PT-1001', 'MAIN', now()->subDays(12)->toDateString(), -1.25, -0.50, 90, 1.50, 31.5, '6/6', -1.00, -0.25, 85, 1.50, 31.5, '6/6', 'Myopia with mild astigmatism', 'Stable prescription', 'Frame and progressive lens package', now()->addMonths(12)->toDateString()],
            'EXM-1002' => ['PT-1002', 'MAIN', now()->subDays(4)->toDateString(), -2.00, -0.75, 100, 1.75, 32.0, '6/9', -1.75, -0.50, 95, 1.75, 32.0, '6/9', 'Progressive correction review', 'Slight change OD', 'Update progressive lenses', now()->addMonths(6)->toDateString()],
            'EXM-1003' => ['PT-1003', 'NORTH', now()->subDays(1)->toDateString(), -3.25, -0.25, 80, null, 30.5, '6/6', -3.00, -0.25, 75, null, 30.5, '6/6', 'Contact lens fitting', 'Trial lenses comfortable', 'Follow up after one week', now()->addDays(7)->toDateString()],
        ];

        foreach ($rows as $number => [$code, $branch, $date, $rs, $rc, $ra, $radd, $rpd, $rva, $ls, $lc, $la, $ladd, $lpd, $lva, $diagnosis, $notes, $recommendation, $next]) {
            DB::table('patient_eye_exams')->updateOrInsert(
                ['exam_number' => $number],
                [
                    'patient_id' => $patientIds[$code],
                    'branch_id' => $branchIds[$branch] ?? $branchIds['MAIN'],
                    'optometrist_id' => $optometristId,
                    'created_by' => $receptionistId,
                    'exam_date' => $date,
                    'status' => 'completed',
                    'right_sph' => $rs,
                    'right_cyl' => $rc,
                    'right_axis' => $ra,
                    'right_add' => $radd,
                    'right_pd' => $rpd,
                    'right_va' => $rva,
                    'left_sph' => $ls,
                    'left_cyl' => $lc,
                    'left_axis' => $la,
                    'left_add' => $ladd,
                    'left_pd' => $lpd,
                    'left_va' => $lva,
                    'diagnosis' => $diagnosis,
                    'optometrist_notes' => $notes,
                    'recommendation' => $recommendation,
                    'next_visit_date' => $next,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('patient_eye_exams')->pluck('id', 'exam_number')->map(fn ($id) => (int) $id)->all();
    }

    private function prescriptions(array $branchIds, array $patientIds, array $examIds, int $optometristId, int $receptionistId, $now): void
    {
        $examMap = DB::table('patient_eye_exams')->get()->keyBy('exam_number');
        $rows = [
            ['RX-1001', 'PT-1001', 'EXM-1001', 'locked', now()->subDays(12)->toDateString()],
            ['RX-1002', 'PT-1002', 'EXM-1002', 'signed', now()->subDays(4)->toDateString()],
            ['RX-1003', 'PT-1003', 'EXM-1003', 'draft', now()->subDay()->toDateString()],
        ];

        foreach ($rows as [$number, $code, $examNumber, $status, $date]) {
            $exam = $examMap[$examNumber];
            DB::table('patient_prescriptions')->updateOrInsert(
                ['prescription_number' => $number],
                [
                    'patient_id' => $patientIds[$code],
                    'patient_eye_exam_id' => $exam->id,
                    'branch_id' => $exam->branch_id,
                    'optometrist_id' => $optometristId,
                    'created_by' => $receptionistId,
                    'status' => $status,
                    'prescribed_on' => $date,
                    'expires_on' => now()->addYear()->toDateString(),
                    'signed_at' => in_array($status, ['signed', 'locked'], true) ? now()->subDays(4) : null,
                    'locked_at' => $status === 'locked' ? now()->subDays(3) : null,
                    'right_sph' => $exam->right_sph,
                    'right_cyl' => $exam->right_cyl,
                    'right_axis' => $exam->right_axis,
                    'right_add' => $exam->right_add,
                    'right_pd' => $exam->right_pd,
                    'right_va' => $exam->right_va,
                    'left_sph' => $exam->left_sph,
                    'left_cyl' => $exam->left_cyl,
                    'left_axis' => $exam->left_axis,
                    'left_add' => $exam->left_add,
                    'left_pd' => $exam->left_pd,
                    'left_va' => $exam->left_va,
                    'diagnosis' => $exam->diagnosis,
                    'recommendation' => $exam->recommendation,
                    'notes' => 'Generated from '.$examNumber.'.',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function documents(array $branchIds, array $patientIds, int $receptionistId, $now): void
    {
        $rows = [
            ['DOC-1001', 'PT-1001', 'prescription_file', 'Signed prescription scan', 'rx-sara-1001.pdf'],
            ['DOC-1002', 'PT-1002', 'scan', 'Retinal scan image', 'omar-scan.jpg'],
            ['DOC-1003', 'PT-1003', 'medical_report', 'Contact lens fitting report', 'maha-fitting.pdf'],
        ];

        foreach ($rows as [$number, $code, $type, $title, $filename]) {
            DB::table('patient_documents')->updateOrInsert(
                ['document_number' => $number],
                [
                    'patient_id' => $patientIds[$code],
                    'branch_id' => $branchIds['MAIN'],
                    'uploaded_by' => $receptionistId,
                    'document_type' => $type,
                    'title' => $title,
                    'original_filename' => $filename,
                    'file_path' => 'demo/'.$filename,
                    'mime_type' => str_ends_with($filename, '.jpg') ? 'image/jpeg' : 'application/pdf',
                    'file_size' => 420000,
                    'notes' => 'Demo document record.',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function whatsapp(array $patientIds, $now): void
    {
        $rows = [
            ['PT-1001', 'out', '+966500111222', 'Your prescription is ready to view in store.', 'sent', now()->subDays(10)],
            ['PT-1002', 'out', '+966500333444', 'Reminder: your pickup balance is due at collection.', 'sent', now()->subDays(2)],
            ['PT-1003', 'out', '+966500555666', 'Reminder: contact lens follow-up is next week.', 'queued', null],
        ];

        foreach ($rows as [$code, $direction, $number, $message, $status, $sentAt]) {
            DB::table('patient_whatsapp_messages')->updateOrInsert(
                ['patient_id' => $patientIds[$code], 'message' => $message],
                [
                    'direction' => $direction,
                    'whatsapp_number' => $number,
                    'status' => $status,
                    'sent_at' => $sentAt,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function timeline(array $branchIds, array $patientIds, array $examIds, int $optometristId, int $receptionistId, $now): void
    {
        foreach ($patientIds as $code => $patientId) {
            $this->timelineEvent($patientId, $branchIds['MAIN'], $receptionistId, 'registration', 'Patient registered', now()->subDays(20), 'Patient file created.', 'patient', $patientId, $now);
        }

        foreach (DB::table('patient_appointments')->get() as $appointment) {
            $this->timelineEvent((int) $appointment->patient_id, $appointment->branch_id, $appointment->assigned_to, 'appointment', 'Appointment '.$appointment->status, $appointment->appointment_at, $appointment->purpose, 'patient_appointment', $appointment->id, $now);
        }

        foreach (DB::table('patient_eye_exams')->get() as $exam) {
            $this->timelineEvent((int) $exam->patient_id, $exam->branch_id, $exam->optometrist_id, 'exam', 'Eye exam completed', $exam->exam_date.' 10:00:00', $exam->diagnosis, 'patient_eye_exam', $exam->id, $now);
        }

        foreach (DB::table('patient_prescriptions')->get() as $prescription) {
            $this->timelineEvent((int) $prescription->patient_id, $prescription->branch_id, $prescription->optometrist_id, 'prescription', 'Prescription '.$prescription->status, $prescription->prescribed_on.' 11:00:00', 'Prescription '.$prescription->prescription_number, 'patient_prescription', $prescription->id, $now);
        }

        foreach (DB::table('patient_documents')->get() as $document) {
            $this->timelineEvent((int) $document->patient_id, $document->branch_id, $document->uploaded_by, 'document', 'Document uploaded', $document->created_at, $document->title, 'patient_document', $document->id, $now);
        }

        foreach (DB::table('patient_whatsapp_messages')->get() as $message) {
            $this->timelineEvent((int) $message->patient_id, null, null, 'whatsapp', 'WhatsApp '.$message->status, $message->sent_at ?: $now, $message->message, 'patient_whatsapp_message', $message->id, $now);
        }
    }

    private function timelineEvent(int $patientId, ?int $branchId, ?int $userId, string $type, string $title, $eventAt, ?string $description, string $sourceType, int $sourceId, $now): void
    {
        DB::table('patient_timeline_events')->updateOrInsert(
            ['source_type' => $sourceType, 'source_id' => $sourceId, 'event_type' => $type],
            [
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'event_title' => $title,
                'event_at' => $eventAt,
                'description' => $description,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function permissionsAndRoles(int $companyId, $now): void
    {
        foreach (config('patients.permissions') as $permission) {
            [$module, $area, $action] = array_pad(explode('.', $permission), 3, null);
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission],
                [
                    'module' => $module.'.'.$area,
                    'action' => $action ?: 'access',
                    'name' => str($permission)->replace('.', ' ')->title(),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        foreach (config('patients.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'Patient Management module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
