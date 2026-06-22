<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_appointments')) {
            Schema::table('patient_appointments', function (Blueprint $table): void {
                if (! Schema::hasColumn('patient_appointments', 'appointment_type')) {
                    $table->string('appointment_type')->default('eye_examination')->index();
                }
                if (! Schema::hasColumn('patient_appointments', 'visit_reason')) {
                    $table->text('visit_reason')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'duration_minutes')) {
                    $table->unsignedSmallInteger('duration_minutes')->default(30);
                }
                if (! Schema::hasColumn('patient_appointments', 'appointment_end_at')) {
                    $table->dateTime('appointment_end_at')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_appointments', 'checked_in_at')) {
                    $table->dateTime('checked_in_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'waiting_started_at')) {
                    $table->dateTime('waiting_started_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'exam_started_at')) {
                    $table->dateTime('exam_started_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'completed_at')) {
                    $table->dateTime('completed_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'no_show_at')) {
                    $table->dateTime('no_show_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'rescheduled_at')) {
                    $table->dateTime('rescheduled_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'reschedule_reason')) {
                    $table->text('reschedule_reason')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'confirmation_sent_at')) {
                    $table->dateTime('confirmation_sent_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'reminder_sent_at')) {
                    $table->dateTime('reminder_sent_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'patient_confirmed_at')) {
                    $table->dateTime('patient_confirmed_at')->nullable();
                }
                if (! Schema::hasColumn('patient_appointments', 'patient_reply_status')) {
                    $table->string('patient_reply_status')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_appointments', 'rescheduled_from_id')) {
                    $table->unsignedBigInteger('rescheduled_from_id')->nullable()->index();
                }
                if (! Schema::hasColumn('patient_appointments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });
        }

        Schema::create('appointment_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('optometrist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visit_number')->unique();
            $table->string('status')->default('checked_in')->index();
            $table->dateTime('checked_in_at')->index();
            $table->dateTime('waiting_started_at')->nullable();
            $table->dateTime('exam_started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('waiting_minutes')->nullable();
            $table->text('visit_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_appointment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->string('event_title');
            $table->text('notes')->nullable();
            $table->dateTime('event_at')->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('appointment_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('trigger')->index();
            $table->string('channel')->default('whatsapp')->index();
            $table->dateTime('scheduled_for')->nullable()->index();
            $table->dateTime('sent_at')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('whatsapp_number')->nullable();
            $table->text('message')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();
            $table->unique(['patient_appointment_id', 'trigger', 'channel'], 'appt_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('appointment_status_events');
        Schema::dropIfExists('appointment_visits');
    }
};
