<?php

namespace App\Http\Controllers;

use App\Support\SetupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    private const PAGES = [
        'dashboard' => 'Appointment Dashboard',
        'calendar' => 'Appointment Calendar',
        'create' => 'Create Appointment',
        'details' => 'Appointment Details',
        'waiting-list' => 'Waiting List',
        'follow-ups' => 'Follow-up List',
        'no-shows' => 'No-show List',
        'reschedule-cancel' => 'Reschedule / Cancel',
        'reports' => 'Reports',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('patient_appointments') || ! Schema::hasTable('appointment_visits')) {
            return view('appointments.app', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('appointments.app', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->appointmentData($request),
        ]);
    }

    public function meta()
    {
        return response()->json([
            'module' => config('appointments.module'),
            'appointment_types' => app(SetupOptions::class)->options('appointment_types'),
            'statuses' => app(SetupOptions::class)->options('appointment_statuses'),
            'status_colors' => config('appointments.status_colors'),
            'calendar_views' => app(SetupOptions::class)->options('calendar_views'),
            'reminder_triggers' => app(SetupOptions::class)->options('reminder_triggers'),
            'roles' => config('appointments.roles'),
            'permissions' => config('appointments.permissions'),
            'reports' => config('appointments.reports'),
            'integration_rules' => config('appointments.integration_rules'),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! Schema::hasTable('patient_appointments')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $request->integer('branch_id') ?: null;
        $date = $request->query('date', now()->toDateString());

        return response()->json([
            'business_date' => $date,
            'branch_id' => $branchId,
            'metrics' => $this->metrics($branchId, $date),
            'today' => $this->appointments($branchId, '', null, $date, $date),
            'waiting_list' => $this->waitingList($branchId),
            'pending_follow_ups' => $this->followUps($branchId, false),
            'overdue_follow_ups' => $this->followUps($branchId, true),
        ]);
    }

    public function appointmentsApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'query' => $q,
            'branch_id' => $request->integer('branch_id') ?: null,
            'status' => $request->query('status'),
            'searchable_fields' => ['patient_name', 'phone', 'whatsapp', 'patient_code', 'appointment_number'],
            'data' => $this->appointments(
                $request->integer('branch_id') ?: null,
                $q,
                $request->query('status'),
                $request->query('date_from'),
                $request->query('date_to'),
                $request->integer('optometrist_id') ?: null,
            ),
        ]);
    }

    public function calendarApi(Request $request)
    {
        $range = $this->calendarRange($request->query('view', 'week'), $request->query('date', now()->toDateString()));

        return response()->json([
            'view' => $request->query('view', 'week'),
            'date_from' => $range['from'],
            'date_to' => $range['to'],
            'branch_id' => $request->integer('branch_id') ?: null,
            'optometrist_id' => $request->integer('optometrist_id') ?: null,
            'events' => $this->calendarEvents(
                $request->integer('branch_id') ?: null,
                $range['from'],
                $range['to'],
                $request->integer('optometrist_id') ?: null,
            ),
        ]);
    }

    public function showApi(int $appointment)
    {
        $details = $this->appointmentDetails($appointment);
        if (! $details) {
            abort(404);
        }

        return response()->json($details);
    }

    public function reportApi(Request $request, string $report)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $data = match ($report) {
            'appointments-by-day' => DB::table('patient_appointments')
                ->selectRaw('date(appointment_at) as appointment_date, count(*) as appointments')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereBetween(DB::raw('date(appointment_at)'), [$dateFrom, $dateTo])
                ->groupBy(DB::raw('date(appointment_at)'))
                ->orderBy('appointment_date')
                ->get(),
            'appointments-by-branch' => DB::table('patient_appointments')
                ->join('branches', 'branches.id', '=', 'patient_appointments.branch_id')
                ->selectRaw('branches.name as branch_name, count(*) as appointments')
                ->whereBetween(DB::raw('date(patient_appointments.appointment_at)'), [$dateFrom, $dateTo])
                ->groupBy('branches.name')
                ->orderByDesc('appointments')
                ->get(),
            'appointments-by-optometrist' => DB::table('patient_appointments')
                ->leftJoin('users', 'users.id', '=', 'patient_appointments.assigned_to')
                ->selectRaw('coalesce(users.name, "Unassigned") as optometrist_name, count(*) as appointments')
                ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
                ->whereBetween(DB::raw('date(patient_appointments.appointment_at)'), [$dateFrom, $dateTo])
                ->groupBy('users.name')
                ->orderByDesc('appointments')
                ->get(),
            'no-show' => $this->appointments($branchId, '', 'no_show', $dateFrom, $dateTo),
            'cancelled' => $this->appointments($branchId, '', 'cancelled', $dateFrom, $dateTo),
            'follow-up' => DB::table('patient_appointments')
                ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
                ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
                ->select('patient_appointments.*', 'patients.patient_code', 'patients.full_name as patient_name', 'patients.phone', 'patients.whatsapp_number', 'branches.name as branch_name')
                ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
                ->where('patient_appointments.appointment_type', 'follow_up')
                ->whereBetween(DB::raw('date(patient_appointments.appointment_at)'), [$dateFrom, $dateTo])
                ->orderBy('patient_appointments.appointment_at')
                ->get(),
            'waiting-time' => DB::table('appointment_visits')
                ->join('patient_appointments', 'patient_appointments.id', '=', 'appointment_visits.patient_appointment_id')
                ->join('patients', 'patients.id', '=', 'appointment_visits.patient_id')
                ->leftJoin('branches', 'branches.id', '=', 'appointment_visits.branch_id')
                ->leftJoin('users', 'users.id', '=', 'appointment_visits.optometrist_id')
                ->select('appointment_visits.visit_number', 'patients.full_name as patient_name', 'branches.name as branch_name', 'users.name as optometrist_name', 'appointment_visits.checked_in_at', 'appointment_visits.exam_started_at', 'appointment_visits.waiting_minutes')
                ->when($branchId, fn ($query) => $query->where('appointment_visits.branch_id', $branchId))
                ->whereBetween(DB::raw('date(appointment_visits.checked_in_at)'), [$dateFrom, $dateTo])
                ->orderByDesc('appointment_visits.checked_in_at')
                ->get(),
            default => abort(404),
        };

        return response()->json([
            'report' => $report,
            'filters' => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'data' => $data,
        ]);
    }

    public function storeAppointment(Request $request)
    {
        $validated = $this->appointmentRules($request);

        $appointmentId = DB::transaction(function () use ($validated): int {
            $now = now();
            $patient = DB::table('patients')->where('id', $validated['patient_id'])->first();
            if (! $patient) {
                throw ValidationException::withMessages(['patient_id' => 'Patient was not found.']);
            }

            $appointmentAt = $this->appointmentAt($validated);
            $duration = max(5, (int) ($validated['duration_minutes'] ?? 30));
            $status = $this->status($validated['status'] ?? 'scheduled');
            $type = $this->appointmentType($validated['appointment_type'] ?? 'eye_examination');
            $endAt = $appointmentAt->copy()->addMinutes($duration);

            $appointmentId = DB::table('patient_appointments')->insertGetId([
                'patient_id' => $patient->id,
                'branch_id' => $validated['branch_id'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'created_by' => $validated['created_by'] ?? null,
                'appointment_number' => $this->nextNumber('APT', 'patient_appointments', 'appointment_number'),
                'appointment_at' => $appointmentAt,
                'appointment_end_at' => $endAt,
                'appointment_type' => $type,
                'purpose' => $this->typeLabel($type),
                'visit_reason' => $validated['visit_reason'] ?? null,
                'duration_minutes' => $duration,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->statusEvent($appointmentId, null, $status, 'Appointment '.$this->statusLabel($status), 'Appointment booked.', $now, $validated['created_by'] ?? null);
            $this->timeline((int) $patient->id, $validated['branch_id'] ?? null, $validated['created_by'] ?? null, 'appointment', 'Appointment booked', $now, $appointmentAt->format('Y-m-d H:i').' / '.$this->typeLabel($type), 'patient_appointment', $appointmentId, ['status' => $status, 'type' => $type]);
            $this->createReminder($appointmentId, 'before_appointment', $appointmentAt->copy()->subDay(), $now);
            $this->audit('appointments.created', 'patient_appointment', $appointmentId, $validated['branch_id'] ?? null, null, ['status' => $status, 'appointment_at' => $appointmentAt->toDateTimeString()], $now);

            if ($status !== 'draft') {
                $this->sendWhatsapp($appointmentId, 'confirmation', $now);
            }

            return $appointmentId;
        });

        return $this->respond($request, ['status' => 'created', 'appointment_id' => $appointmentId], 'details', 'Appointment booked and confirmation saved to patient timeline.');
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'max:255'],
            'changed_by' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $appointment = DB::table('patient_appointments')->where('id', $validated['appointment_id'])->first();
            if (! $appointment) {
                throw ValidationException::withMessages(['appointment_id' => 'Appointment was not found.']);
            }

            $status = $this->status($validated['status']);
            $updates = [
                'status' => $status,
                'updated_at' => $now,
            ];

            if ($status === 'confirmed') {
                $updates['patient_confirmed_at'] = $appointment->patient_confirmed_at ?: $now;
                $this->sendWhatsapp((int) $appointment->id, 'confirmation', $now);
            }
            if ($status === 'checked_in') {
                $updates['checked_in_at'] = $appointment->checked_in_at ?: $now;
                $updates['waiting_started_at'] = $appointment->waiting_started_at ?: $now;
                $this->ensureVisit($appointment, $validated['changed_by'] ?? null, $now);
            }
            if ($status === 'waiting') {
                $updates['waiting_started_at'] = $appointment->waiting_started_at ?: $now;
                $this->ensureVisit($appointment, $validated['changed_by'] ?? null, $now);
            }
            if ($status === 'in_examination') {
                $updates['exam_started_at'] = $appointment->exam_started_at ?: $now;
                $this->startVisitExam($appointment, $now);
            }
            if ($status === 'completed') {
                $updates['completed_at'] = $appointment->completed_at ?: $now;
                $this->completeVisit($appointment, $now);
            }
            if ($status === 'no_show') {
                $updates['no_show_at'] = $appointment->no_show_at ?: $now;
            }

            DB::table('patient_appointments')->where('id', $appointment->id)->update($updates);
            $fresh = DB::table('patient_appointments')->where('id', $appointment->id)->first();

            $this->statusEvent((int) $appointment->id, $appointment->status, $status, 'Status changed to '.$this->statusLabel($status), $validated['notes'] ?? null, $now, $validated['changed_by'] ?? null);
            $this->timeline((int) $appointment->patient_id, $appointment->branch_id, $validated['changed_by'] ?? null, 'appointment', 'Appointment '.$this->statusLabel($status), $now, 'Appointment '.$fresh->appointment_number.' changed from '.$this->statusLabel($appointment->status).' to '.$this->statusLabel($status).'.', 'patient_appointment', (int) $appointment->id, ['from_status' => $appointment->status, 'to_status' => $status]);
            $this->audit('appointments.status.updated', 'patient_appointment', (int) $appointment->id, $appointment->branch_id, ['status' => $appointment->status], ['status' => $status], $now);
        });

        return $this->respond($request, ['status' => 'updated', 'appointment_id' => (int) $validated['appointment_id']], 'details', 'Appointment status updated.');
    }

    public function reschedule(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'changed_by' => ['nullable', 'integer'],
            'reschedule_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $newId = DB::transaction(function () use ($validated): int {
            $now = now();
            $old = DB::table('patient_appointments')->where('id', $validated['appointment_id'])->first();
            if (! $old) {
                throw ValidationException::withMessages(['appointment_id' => 'Appointment was not found.']);
            }

            $appointmentAt = Carbon::parse($validated['appointment_date'].' '.$validated['appointment_time']);
            $duration = (int) ($validated['duration_minutes'] ?? $old->duration_minutes ?? 30);
            $newId = DB::table('patient_appointments')->insertGetId([
                'patient_id' => $old->patient_id,
                'branch_id' => $old->branch_id,
                'assigned_to' => $old->assigned_to,
                'created_by' => $validated['changed_by'] ?? $old->created_by,
                'appointment_number' => $this->nextNumber('APT', 'patient_appointments', 'appointment_number'),
                'appointment_at' => $appointmentAt,
                'appointment_end_at' => $appointmentAt->copy()->addMinutes($duration),
                'appointment_type' => $old->appointment_type,
                'purpose' => $old->purpose,
                'visit_reason' => $old->visit_reason,
                'duration_minutes' => $duration,
                'status' => 'scheduled',
                'notes' => $old->notes,
                'rescheduled_from_id' => $old->id,
                'reschedule_reason' => $validated['reschedule_reason'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('patient_appointments')->where('id', $old->id)->update([
                'status' => 'rescheduled',
                'rescheduled_at' => $now,
                'reschedule_reason' => $validated['reschedule_reason'] ?? null,
                'updated_at' => $now,
            ]);

            $this->statusEvent((int) $old->id, $old->status, 'rescheduled', 'Appointment rescheduled', $validated['reschedule_reason'] ?? null, $now, $validated['changed_by'] ?? null);
            $this->statusEvent($newId, null, 'scheduled', 'Rescheduled appointment created', 'Created from '.$old->appointment_number.'.', $now, $validated['changed_by'] ?? null);
            $this->createReminder($newId, 'before_appointment', $appointmentAt->copy()->subDay(), $now);
            $this->timeline((int) $old->patient_id, $old->branch_id, $validated['changed_by'] ?? null, 'appointment', 'Appointment rescheduled', $now, $old->appointment_number.' moved to '.$appointmentAt->format('Y-m-d H:i').'.', 'patient_appointment', (int) $old->id, ['new_appointment_id' => $newId]);
            $this->sendWhatsapp($newId, 'reschedule', $now);
            $this->audit('appointments.rescheduled', 'patient_appointment', (int) $old->id, $old->branch_id, ['appointment_at' => $old->appointment_at], ['new_appointment_id' => $newId, 'appointment_at' => $appointmentAt->toDateTimeString()], $now);

            return $newId;
        });

        return $this->respond($request, ['status' => 'rescheduled', 'appointment_id' => $newId], 'details', 'Appointment rescheduled and patient notification saved.');
    }

    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'changed_by' => ['nullable', 'integer'],
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $appointment = DB::table('patient_appointments')->where('id', $validated['appointment_id'])->first();
            if (! $appointment) {
                throw ValidationException::withMessages(['appointment_id' => 'Appointment was not found.']);
            }

            DB::table('patient_appointments')->where('id', $appointment->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancellation_reason' => $validated['cancellation_reason'],
                'updated_at' => $now,
            ]);

            $this->statusEvent((int) $appointment->id, $appointment->status, 'cancelled', 'Appointment cancelled', $validated['cancellation_reason'], $now, $validated['changed_by'] ?? null);
            $this->timeline((int) $appointment->patient_id, $appointment->branch_id, $validated['changed_by'] ?? null, 'appointment', 'Appointment cancelled', $now, $validated['cancellation_reason'], 'patient_appointment', (int) $appointment->id);
            $this->sendWhatsapp((int) $appointment->id, 'cancellation', $now);
            $this->audit('appointments.cancelled', 'patient_appointment', (int) $appointment->id, $appointment->branch_id, ['status' => $appointment->status], ['status' => 'cancelled', 'reason' => $validated['cancellation_reason']], $now);
        });

        return $this->respond($request, ['status' => 'cancelled', 'appointment_id' => (int) $validated['appointment_id']], 'details', 'Appointment cancelled and patient notification saved.');
    }

    public function sendReminder(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'trigger' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $this->sendWhatsapp((int) $validated['appointment_id'], $validated['trigger'] ?? 'before_appointment', now());
        });

        return $this->respond($request, ['status' => 'sent', 'appointment_id' => (int) $validated['appointment_id']], 'details', 'Appointment reminder sent and saved.');
    }

    public function patientReply(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'reply' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $appointment = DB::table('patient_appointments')->where('id', $validated['appointment_id'])->first();
            if (! $appointment) {
                throw ValidationException::withMessages(['appointment_id' => 'Appointment was not found.']);
            }
            $patient = DB::table('patients')->where('id', $appointment->patient_id)->first();
            $reply = Str::lower(trim($validated['reply']));
            $confirmed = in_array($reply, ['yes', 'y', 'confirm', 'confirmed', 'ok'], true);

            $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
                'patient_id' => $appointment->patient_id,
                'direction' => 'in',
                'whatsapp_number' => $validated['whatsapp_number'] ?? $patient?->whatsapp_number,
                'message' => $validated['reply'],
                'status' => 'received',
                'sent_at' => $now,
                'provider_message_id' => 'reply-'.$appointment->id.'-'.Str::lower(Str::random(5)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('patient_appointments')->where('id', $appointment->id)->update([
                'status' => $confirmed ? 'confirmed' : $appointment->status,
                'patient_reply_status' => $confirmed ? 'confirmed' : 'received',
                'patient_confirmed_at' => $confirmed ? $now : $appointment->patient_confirmed_at,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $appointment->patient_id, $appointment->branch_id, null, 'whatsapp', 'Appointment reply received', $now, $validated['reply'], 'patient_whatsapp_message', $messageId, ['appointment_id' => $appointment->id, 'confirmed' => $confirmed]);
            if ($confirmed) {
                $this->statusEvent((int) $appointment->id, $appointment->status, 'confirmed', 'Patient confirmed by WhatsApp', $validated['reply'], $now, null);
            }
        });

        return response()->json(['status' => 'received']);
    }

    private function appointmentData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $date = $request->query('date', now()->toDateString());
        $view = $request->query('view', 'week');
        $status = $request->query('status');
        $appointmentId = $request->integer('appointment_id') ?: (int) (DB::table('patient_appointments')->latest('id')->value('id') ?? 0);
        $range = $this->calendarRange($view, $date);

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'date' => $date,
            'view' => $view,
            'statusFilter' => $status,
            'appointmentTypes' => app(SetupOptions::class)->options('appointment_types'),
            'statuses' => app(SetupOptions::class)->options('appointment_statuses'),
            'statusColors' => config('appointments.status_colors'),
            'calendarViews' => app(SetupOptions::class)->options('calendar_views'),
            'reports' => config('appointments.reports'),
            'patients' => $this->patients($query),
            'optometrists' => DB::table('users')->orderBy('name')->get(),
            'metrics' => $this->metrics($branchId, $date),
            'appointments' => $this->appointments($branchId, $query, $status, $range['from'], $range['to']),
            'todayAppointments' => $this->appointments($branchId, '', null, $date, $date),
            'calendarEvents' => $this->calendarEvents($branchId, $range['from'], $range['to'], $request->integer('optometrist_id') ?: null),
            'waitingList' => $this->waitingList($branchId),
            'followUps' => $this->followUps($branchId, false),
            'overdueFollowUps' => $this->followUps($branchId, true),
            'noShows' => $this->appointments($branchId, $query, 'no_show', now()->subDays(30)->toDateString(), now()->toDateString()),
            'selectedAppointment' => $appointmentId ? $this->appointmentDetails($appointmentId) : null,
        ];
    }

    private function metrics(?int $branchId, string $date): array
    {
        return [
            'today' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('appointment_at', $date)->count(),
            'confirmed' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('appointment_at', $date)->where('status', 'confirmed')->count(),
            'waiting' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereIn('status', ['checked_in', 'waiting', 'in_examination'])->count(),
            'completed' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('appointment_at', $date)->where('status', 'completed')->count(),
            'no_show_month' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('appointment_at', '>=', now()->startOfMonth()->toDateString())->where('status', 'no_show')->count(),
            'cancelled_month' => DB::table('patient_appointments')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('appointment_at', '>=', now()->startOfMonth()->toDateString())->where('status', 'cancelled')->count(),
            'pending_followups' => $this->followUps($branchId, false)->count(),
            'overdue_followups' => $this->followUps($branchId, true)->count(),
        ];
    }

    private function appointments(?int $branchId, string $query, ?string $status, ?string $dateFrom, ?string $dateTo, ?int $optometristId = null)
    {
        return DB::table('patient_appointments')
            ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
            ->leftJoin('users', 'users.id', '=', 'patient_appointments.assigned_to')
            ->select([
                'patient_appointments.*',
                'patients.patient_code',
                'patients.full_name as patient_name',
                'patients.phone',
                'patients.whatsapp_number',
                'branches.name as branch_name',
                'users.name as optometrist_name',
            ])
            ->when($branchId, fn ($builder) => $builder->where('patient_appointments.branch_id', $branchId))
            ->when($optometristId, fn ($builder) => $builder->where('patient_appointments.assigned_to', $optometristId))
            ->when($status, fn ($builder) => $builder->where('patient_appointments.status', $status))
            ->when($dateFrom, fn ($builder) => $builder->whereDate('patient_appointments.appointment_at', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->whereDate('patient_appointments.appointment_at', '<=', $dateTo))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('patient_appointments.appointment_number', 'like', "%{$query}%")
                        ->orWhere('patients.full_name', 'like', "%{$query}%")
                        ->orWhere('patients.patient_code', 'like', "%{$query}%")
                        ->orWhere('patients.phone', 'like', "%{$query}%")
                        ->orWhere('patients.whatsapp_number', 'like', "%{$query}%");
                });
            })
            ->orderBy('patient_appointments.appointment_at')
            ->limit(150)
            ->get();
    }

    private function calendarEvents(?int $branchId, string $dateFrom, string $dateTo, ?int $optometristId = null)
    {
        return $this->appointments($branchId, '', null, $dateFrom, $dateTo, $optometristId)
            ->map(fn ($appointment) => [
                'id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'title' => $appointment->patient_name.' - '.$this->typeLabel($appointment->appointment_type),
                'patient_name' => $appointment->patient_name,
                'branch_name' => $appointment->branch_name,
                'optometrist_name' => $appointment->optometrist_name,
                'start' => $appointment->appointment_at,
                'end' => $appointment->appointment_end_at,
                'status' => $appointment->status,
                'color' => config('appointments.status_colors')[$appointment->status] ?? '#64748b',
                'drag_drop_update_api' => '/api/v1/appointments/reschedule',
            ]);
    }

    private function appointmentDetails(int $appointmentId): ?array
    {
        $appointment = $this->appointments(null, '', null, null, null)
            ->first(fn ($item) => (int) $item->id === $appointmentId);

        if (! $appointment) {
            return null;
        }

        return [
            'appointment' => $appointment,
            'visits' => DB::table('appointment_visits')
                ->leftJoin('users', 'users.id', '=', 'appointment_visits.optometrist_id')
                ->select('appointment_visits.*', 'users.name as optometrist_name')
                ->where('appointment_visits.patient_appointment_id', $appointmentId)
                ->orderByDesc('appointment_visits.id')
                ->get(),
            'events' => DB::table('appointment_status_events')
                ->leftJoin('users', 'users.id', '=', 'appointment_status_events.changed_by')
                ->select('appointment_status_events.*', 'users.name as user_name')
                ->where('appointment_status_events.patient_appointment_id', $appointmentId)
                ->orderByDesc('appointment_status_events.event_at')
                ->get(),
            'reminders' => DB::table('appointment_reminders')
                ->where('patient_appointment_id', $appointmentId)
                ->orderByDesc('id')
                ->get(),
            'patient' => DB::table('patients')->where('id', $appointment->patient_id)->first(),
            'recent_exams' => DB::table('patient_eye_exams')->where('patient_id', $appointment->patient_id)->orderByDesc('exam_date')->limit(5)->get(),
            'recent_sales' => Schema::hasTable('sales_invoices')
                ? DB::table('sales_invoices')
                    ->join('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
                    ->where('sales_customers.patient_id', $appointment->patient_id)
                    ->orderByDesc('sales_invoices.invoice_date')
                    ->limit(5)
                    ->get()
                : collect(),
        ];
    }

    private function waitingList(?int $branchId)
    {
        return $this->appointments($branchId, '', null, now()->subDay()->toDateString(), now()->addDay()->toDateString())
            ->filter(fn ($appointment) => in_array($appointment->status, ['checked_in', 'waiting', 'in_examination'], true))
            ->values();
    }

    private function followUps(?int $branchId, bool $overdue)
    {
        return DB::table('patient_appointments')
            ->join('patients', 'patients.id', '=', 'patient_appointments.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_appointments.branch_id')
            ->leftJoin('users', 'users.id', '=', 'patient_appointments.assigned_to')
            ->select('patient_appointments.*', 'patients.patient_code', 'patients.full_name as patient_name', 'patients.phone', 'patients.whatsapp_number', 'branches.name as branch_name', 'users.name as optometrist_name')
            ->when($branchId, fn ($query) => $query->where('patient_appointments.branch_id', $branchId))
            ->where('patient_appointments.appointment_type', 'follow_up')
            ->whereIn('patient_appointments.status', ['scheduled', 'confirmed'])
            ->when($overdue, fn ($query) => $query->whereDate('patient_appointments.appointment_at', '<', now()->toDateString()))
            ->when(! $overdue, fn ($query) => $query->whereDate('patient_appointments.appointment_at', '>=', now()->toDateString()))
            ->orderBy('patient_appointments.appointment_at')
            ->limit(80)
            ->get();
    }

    private function patients(string $query)
    {
        return DB::table('patients')
            ->select('id', 'patient_code', 'full_name', 'phone', 'whatsapp_number')
            ->where('is_active', true)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('full_name', 'like', "%{$query}%")
                        ->orWhere('patient_code', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('whatsapp_number', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function appointmentRules(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'created_by' => ['nullable', 'integer'],
            'appointment_date' => ['nullable', 'date'],
            'appointment_time' => ['nullable', 'date_format:H:i'],
            'appointment_at' => ['nullable', 'date'],
            'appointment_type' => ['required', 'string', 'max:255'],
            'visit_reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function appointmentAt(array $validated): Carbon
    {
        if (! empty($validated['appointment_at'])) {
            return Carbon::parse($validated['appointment_at']);
        }

        if (! empty($validated['appointment_date']) && ! empty($validated['appointment_time'])) {
            return Carbon::parse($validated['appointment_date'].' '.$validated['appointment_time']);
        }

        throw ValidationException::withMessages(['appointment_at' => 'Choose appointment date and time.']);
    }

    private function ensureVisit(object $appointment, ?int $userId, $now): int
    {
        $existing = DB::table('appointment_visits')->where('patient_appointment_id', $appointment->id)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return DB::table('appointment_visits')->insertGetId([
            'patient_appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'branch_id' => $appointment->branch_id,
            'optometrist_id' => $appointment->assigned_to,
            'created_by' => $userId,
            'visit_number' => $this->nextNumber('VIS', 'appointment_visits', 'visit_number'),
            'status' => 'checked_in',
            'checked_in_at' => $now,
            'waiting_started_at' => $now,
            'visit_reason' => $appointment->visit_reason,
            'notes' => $appointment->notes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function startVisitExam(object $appointment, $now): void
    {
        $visitId = $this->ensureVisit($appointment, null, $now);
        $visit = DB::table('appointment_visits')->where('id', $visitId)->first();
        $waitStart = $visit->waiting_started_at ? Carbon::parse($visit->waiting_started_at) : Carbon::parse($visit->checked_in_at);

        DB::table('appointment_visits')->where('id', $visitId)->update([
            'status' => 'in_examination',
            'exam_started_at' => $visit->exam_started_at ?: $now,
            'waiting_minutes' => max(0, $waitStart->diffInMinutes($now)),
            'updated_at' => $now,
        ]);
    }

    private function completeVisit(object $appointment, $now): void
    {
        $visit = DB::table('appointment_visits')->where('patient_appointment_id', $appointment->id)->first();
        if (! $visit) {
            return;
        }

        DB::table('appointment_visits')->where('id', $visit->id)->update([
            'status' => 'completed',
            'completed_at' => $visit->completed_at ?: $now,
            'updated_at' => $now,
        ]);
    }

    private function sendWhatsapp(int $appointmentId, string $trigger, $now): void
    {
        $appointment = DB::table('patient_appointments')->where('id', $appointmentId)->first();
        if (! $appointment) {
            return;
        }
        $patient = DB::table('patients')->where('id', $appointment->patient_id)->first();
        if (! $patient || ! $patient->whatsapp_number) {
            return;
        }

        $dateTime = Carbon::parse($appointment->appointment_at)->format('Y-m-d H:i');
        $message = match ($trigger) {
            'confirmation' => 'Your appointment '.$appointment->appointment_number.' is scheduled for '.$dateTime.'. Reply YES to confirm.',
            'before_appointment' => 'Reminder: your optical appointment '.$appointment->appointment_number.' is on '.$dateTime.'.',
            'reschedule' => 'Your appointment has been rescheduled to '.$dateTime.'. Reply YES to confirm.',
            'cancellation' => 'Your appointment '.$appointment->appointment_number.' has been cancelled. Reason: '.($appointment->cancellation_reason ?: 'Not specified').'.',
            'follow_up_due' => 'Reminder: your follow-up appointment '.$appointment->appointment_number.' is due on '.$dateTime.'.',
            default => 'Appointment update for '.$appointment->appointment_number.' on '.$dateTime.'.',
        };

        $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
            'patient_id' => $patient->id,
            'direction' => 'out',
            'whatsapp_number' => $patient->whatsapp_number,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => $now,
            'provider_message_id' => 'appt-'.$trigger.'-'.$appointment->id.'-'.Str::lower(Str::random(5)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('appointment_reminders')->updateOrInsert(
            ['patient_appointment_id' => $appointment->id, 'trigger' => $trigger, 'channel' => 'whatsapp'],
            [
                'patient_id' => $patient->id,
                'scheduled_for' => $trigger === 'before_appointment' ? Carbon::parse($appointment->appointment_at)->subDay() : $now,
                'sent_at' => $now,
                'status' => 'sent',
                'whatsapp_number' => $patient->whatsapp_number,
                'message' => $message,
                'provider_message_id' => 'appt-'.$trigger.'-'.$appointment->id,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $column = $trigger === 'confirmation' ? 'confirmation_sent_at' : 'reminder_sent_at';
        if (Schema::hasColumn('patient_appointments', $column)) {
            DB::table('patient_appointments')->where('id', $appointment->id)->update([$column => $now, 'updated_at' => $now]);
        }

        $this->timeline((int) $patient->id, $appointment->branch_id, null, 'whatsapp', 'Appointment WhatsApp '.$this->triggerLabel($trigger), $now, $message, 'patient_whatsapp_message', $messageId, ['appointment_id' => $appointment->id, 'trigger' => $trigger]);
    }

    private function createReminder(int $appointmentId, string $trigger, $scheduledFor, $now): void
    {
        $appointment = DB::table('patient_appointments')->where('id', $appointmentId)->first();
        if (! $appointment) {
            return;
        }
        $patient = DB::table('patients')->where('id', $appointment->patient_id)->first();

        DB::table('appointment_reminders')->updateOrInsert(
            ['patient_appointment_id' => $appointmentId, 'trigger' => $trigger, 'channel' => 'whatsapp'],
            [
                'patient_id' => $appointment->patient_id,
                'scheduled_for' => $scheduledFor,
                'status' => 'pending',
                'whatsapp_number' => $patient?->whatsapp_number,
                'message' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function calendarRange(string $view, string $date): array
    {
        $base = Carbon::parse($date);

        return match ($view) {
            'day' => ['from' => $base->toDateString(), 'to' => $base->toDateString()],
            'month' => ['from' => $base->copy()->startOfMonth()->toDateString(), 'to' => $base->copy()->endOfMonth()->toDateString()],
            default => ['from' => $base->copy()->startOfWeek()->toDateString(), 'to' => $base->copy()->endOfWeek()->toDateString()],
        };
    }

    private function appointmentType(string $type): string
    {
        if (! in_array($type, app(SetupOptions::class)->keys('appointment_types'), true)) {
            throw ValidationException::withMessages(['appointment_type' => 'Unsupported appointment type.']);
        }

        return $type;
    }

    private function status(string $status): string
    {
        if (! in_array($status, app(SetupOptions::class)->keys('appointment_statuses'), true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported appointment status.']);
        }

        return $status;
    }

    private function typeLabel(?string $type): string
    {
        return app(SetupOptions::class)->label('appointment_types', $type);
    }

    private function statusLabel(?string $status): string
    {
        return app(SetupOptions::class)->label('appointment_statuses', $status);
    }

    private function triggerLabel(string $trigger): string
    {
        return str($trigger)->replace('_', ' ')->title();
    }

    private function statusEvent(int $appointmentId, ?string $fromStatus, string $toStatus, string $title, ?string $notes, $eventAt, ?int $changedBy): void
    {
        DB::table('appointment_status_events')->insert([
            'patient_appointment_id' => $appointmentId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_title' => $title,
            'notes' => $notes,
            'event_at' => $eventAt,
            'changed_by' => $changedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function timeline(int $patientId, ?int $branchId, ?int $userId, string $type, string $title, $eventAt, ?string $description, string $sourceType, int $sourceId, ?array $metadata = null): void
    {
        DB::table('patient_timeline_events')->insert([
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

    private function nextNumber(string $prefix, string $table, string $column): string
    {
        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (DB::table($table)->where($column, $number)->exists());

        return $number;
    }

    private function respond(Request $request, array $payload, string $page, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload);
        }

        $params = ['page' => $page];
        if ($payload['appointment_id'] ?? null) {
            $params['appointment_id'] = $payload['appointment_id'];
        }

        return redirect()->route('appointments.app', $params)->with('status', __($message));
    }
}
