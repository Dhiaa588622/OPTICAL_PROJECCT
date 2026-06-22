<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AppointmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('appointment_visits') || ! Schema::hasTable('patient_appointments')) {
            return;
        }

        $now = now();
        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchIds = DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $patientIds = DB::table('patients')->pluck('id', 'patient_code')->map(fn ($id) => (int) $id)->all();

        if (! $companyId || $branchIds === [] || $patientIds === []) {
            return;
        }

        $optometristId = $this->user('optometrist@example.com', 'Dr. Noor Optometrist', $now);
        $receptionistId = $this->user('appointments@example.com', 'Appointment Receptionist', $now);

        $this->permissionsAndRoles($companyId, $now);

        $appointments = [
            ['APT-3001', 'PT-1001', 'MAIN', $optometristId, now()->setTime(9, 30), 'eye_examination', 'Annual exam and prescription update', 'confirmed', 40],
            ['APT-3002', 'PT-1002', 'MAIN', $optometristId, now()->setTime(10, 30), 'prescription_check', 'Progressive lens prescription review', 'checked_in', 30],
            ['APT-3003', 'PT-1003', 'NORTH', $optometristId, now()->setTime(11, 15), 'follow_up', 'Contact lens follow-up', 'waiting', 20],
            ['APT-3004', 'PT-1001', 'MAIN', $optometristId, now()->setTime(13, 0), 'frame_fitting', 'Frame adjustment and fitting', 'scheduled', 20],
            ['APT-3005', 'PT-1002', 'MAIN', $optometristId, now()->subDay()->setTime(16, 30), 'complaint_remake_check', 'Remake complaint check', 'no_show', 30],
            ['APT-3006', 'PT-1003', 'NORTH', $optometristId, now()->subDays(2)->setTime(12, 0), 'general_consultation', 'General consultation', 'cancelled', 30],
            ['APT-3007', 'PT-1002', 'MAIN', $optometristId, now()->addDays(5)->setTime(15, 0), 'follow_up', 'Post progressive adaptation follow-up', 'scheduled', 20],
            ['APT-3008', 'PT-1001', 'MAIN', $optometristId, now()->subDays(3)->setTime(14, 0), 'follow_up', 'Overdue dry eye follow-up', 'confirmed', 20],
            ['APT-3009', 'PT-1003', 'NORTH', $optometristId, now()->setTime(12, 0), 'lens_pickup', 'Contact lens pickup', 'in_examination', 20],
        ];

        foreach ($appointments as $row) {
            $this->appointment($row, $branchIds, $patientIds, $receptionistId, $now);
        }
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

    private function appointment(array $row, array $branchIds, array $patientIds, int $receptionistId, $now): void
    {
        [$number, $patientCode, $branchCode, $optometristId, $at, $type, $reason, $status, $duration] = $row;
        $appointmentAt = Carbon::parse($at);
        $patientId = $patientIds[$patientCode] ?? reset($patientIds);
        $branchId = $branchIds[$branchCode] ?? reset($branchIds);

        DB::table('patient_appointments')->updateOrInsert(
            ['appointment_number' => $number],
            [
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'assigned_to' => $optometristId,
                'created_by' => $receptionistId,
                'appointment_at' => $appointmentAt,
                'appointment_end_at' => $appointmentAt->copy()->addMinutes($duration),
                'appointment_type' => $type,
                'purpose' => config('appointments.appointment_types')[$type] ?? $type,
                'visit_reason' => $reason,
                'duration_minutes' => $duration,
                'status' => $status,
                'notes' => 'Demo appointment for Appointments module.',
                'checked_in_at' => in_array($status, ['checked_in', 'waiting', 'in_examination', 'completed'], true) ? now()->subMinutes(35) : null,
                'waiting_started_at' => in_array($status, ['checked_in', 'waiting', 'in_examination', 'completed'], true) ? now()->subMinutes(35) : null,
                'exam_started_at' => in_array($status, ['in_examination', 'completed'], true) ? now()->subMinutes(12) : null,
                'completed_at' => $status === 'completed' ? now()->subMinutes(2) : null,
                'cancelled_at' => $status === 'cancelled' ? now()->subDays(2)->setTime(9, 10) : null,
                'no_show_at' => $status === 'no_show' ? now()->subDay()->setTime(17, 0) : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Patient requested cancellation.' : null,
                'confirmation_sent_at' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? now()->subDay() : null,
                'reminder_sent_at' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? now()->subHours(3) : null,
                'patient_confirmed_at' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? now()->subHours(20) : null,
                'patient_reply_status' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? 'confirmed' : null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $appointmentId = (int) DB::table('patient_appointments')->where('appointment_number', $number)->value('id');
        $this->events($appointmentId, $status, $receptionistId, $now);
        $this->reminders($appointmentId, $patientId, $appointmentAt, $status, $now);
        $this->visit($appointmentId, $patientId, $branchId, $optometristId, $receptionistId, $status, $reason, $now);
        $this->timelineAndWhatsapp($appointmentId, $patientId, $branchId, $status, $number, $appointmentAt, $now);
    }

    private function events(int $appointmentId, string $status, int $userId, $now): void
    {
        $events = [
            ['scheduled', 'Appointment scheduled', 'Appointment booked by reception.', now()->subDays(2)],
        ];

        if (in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination', 'completed'], true)) {
            $events[] = ['confirmed', 'Appointment confirmed', 'Patient confirmed by WhatsApp or staff.', now()->subDay()];
        }
        if (in_array($status, ['checked_in', 'waiting', 'in_examination', 'completed'], true)) {
            $events[] = ['checked_in', 'Patient checked in', 'Reception marked arrival.', now()->subMinutes(35)];
        }
        if (in_array($status, ['waiting', 'in_examination', 'completed'], true)) {
            $events[] = ['waiting', 'Patient waiting', 'Patient added to optometrist waiting list.', now()->subMinutes(30)];
        }
        if (in_array($status, ['in_examination', 'completed'], true)) {
            $events[] = ['in_examination', 'Examination started', 'Optometrist started the visit.', now()->subMinutes(12)];
        }
        if ($status === 'completed') {
            $events[] = ['completed', 'Appointment completed', 'Visit completed.', now()->subMinutes(2)];
        }
        if ($status === 'cancelled') {
            $events[] = ['cancelled', 'Appointment cancelled', 'Patient requested cancellation.', now()->subDay()];
        }
        if ($status === 'no_show') {
            $events[] = ['no_show', 'Marked no-show', 'Patient did not arrive.', now()->subDay()];
        }

        foreach ($events as [$to, $title, $notes, $at]) {
            if (DB::table('appointment_status_events')->where('patient_appointment_id', $appointmentId)->where('to_status', $to)->where('event_title', $title)->exists()) {
                continue;
            }

            DB::table('appointment_status_events')->insert([
                'patient_appointment_id' => $appointmentId,
                'from_status' => null,
                'to_status' => $to,
                'event_title' => $title,
                'notes' => $notes,
                'event_at' => $at,
                'changed_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function reminders(int $appointmentId, int $patientId, Carbon $appointmentAt, string $status, $now): void
    {
        $patient = DB::table('patients')->where('id', $patientId)->first();
        foreach (['confirmation', 'before_appointment'] as $trigger) {
            DB::table('appointment_reminders')->updateOrInsert(
                ['patient_appointment_id' => $appointmentId, 'trigger' => $trigger, 'channel' => 'whatsapp'],
                [
                    'patient_id' => $patientId,
                    'scheduled_for' => $trigger === 'before_appointment' ? $appointmentAt->copy()->subDay() : $now,
                    'sent_at' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? now()->subHours(3) : null,
                    'status' => in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination'], true) ? 'sent' : 'pending',
                    'whatsapp_number' => $patient?->whatsapp_number,
                    'message' => 'Appointment '.$trigger.' for '.$appointmentAt->format('Y-m-d H:i'),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function visit(int $appointmentId, int $patientId, int $branchId, int $optometristId, int $receptionistId, string $status, string $reason, $now): void
    {
        if (! in_array($status, ['checked_in', 'waiting', 'in_examination', 'completed'], true)) {
            return;
        }

        DB::table('appointment_visits')->updateOrInsert(
            ['patient_appointment_id' => $appointmentId],
            [
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'optometrist_id' => $optometristId,
                'created_by' => $receptionistId,
                'visit_number' => 'VIS-'.$appointmentId,
                'status' => $status,
                'checked_in_at' => now()->subMinutes(35),
                'waiting_started_at' => now()->subMinutes(35),
                'exam_started_at' => in_array($status, ['in_examination', 'completed'], true) ? now()->subMinutes(12) : null,
                'completed_at' => $status === 'completed' ? now()->subMinutes(2) : null,
                'waiting_minutes' => in_array($status, ['in_examination', 'completed'], true) ? 23 : null,
                'visit_reason' => $reason,
                'notes' => 'Demo visit encounter.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function timelineAndWhatsapp(int $appointmentId, int $patientId, int $branchId, string $status, string $number, Carbon $appointmentAt, $now): void
    {
        DB::table('patient_timeline_events')->updateOrInsert(
            ['source_type' => 'patient_appointment', 'source_id' => $appointmentId, 'event_type' => 'appointment'],
            [
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'event_title' => 'Appointment '.$status,
                'event_at' => $appointmentAt,
                'description' => $number.' '.$status,
                'metadata' => json_encode(['status' => $status]),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $patient = DB::table('patients')->where('id', $patientId)->first();
        if (! $patient?->whatsapp_number || ! in_array($status, ['confirmed', 'checked_in', 'waiting', 'in_examination', 'cancelled'], true)) {
            return;
        }

        $message = match ($status) {
            'cancelled' => 'Your appointment '.$number.' has been cancelled.',
            default => 'Your appointment '.$number.' is scheduled for '.$appointmentAt->format('Y-m-d H:i').'. Reply YES to confirm.',
        };

        DB::table('patient_whatsapp_messages')->updateOrInsert(
            ['patient_id' => $patientId, 'message' => $message],
            [
                'direction' => 'out',
                'whatsapp_number' => $patient->whatsapp_number,
                'status' => 'sent',
                'sent_at' => now()->subHours(3),
                'provider_message_id' => 'demo-'.$number,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function permissionsAndRoles(int $companyId, $now): void
    {
        foreach (config('appointments.permissions') as $permission) {
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

        foreach (config('appointments.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'Appointments module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
