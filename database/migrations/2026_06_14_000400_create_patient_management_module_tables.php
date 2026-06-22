<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('patient_code')->unique();
            $table->string('full_name');
            $table->string('gender')->default('not_specified')->index();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('whatsapp_number')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'full_name']);
        });

        Schema::create('patient_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('appointment_number')->unique();
            $table->dateTime('appointment_at')->index();
            $table->string('purpose')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_eye_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('optometrist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exam_number')->unique();
            $table->date('exam_date')->index();
            $table->string('status')->default('completed')->index();
            $table->decimal('right_sph', 5, 2)->nullable();
            $table->decimal('right_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();
            $table->decimal('right_add', 5, 2)->nullable();
            $table->decimal('right_pd', 5, 2)->nullable();
            $table->string('right_va')->nullable();
            $table->decimal('left_sph', 5, 2)->nullable();
            $table->decimal('left_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->decimal('left_add', 5, 2)->nullable();
            $table->decimal('left_pd', 5, 2)->nullable();
            $table->string('left_va')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('optometrist_notes')->nullable();
            $table->text('recommendation')->nullable();
            $table->date('next_visit_date')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('patient_prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_eye_exam_id')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('optometrist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prescription_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->date('prescribed_on')->index();
            $table->date('expires_on')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->decimal('right_sph', 5, 2)->nullable();
            $table->decimal('right_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();
            $table->decimal('right_add', 5, 2)->nullable();
            $table->decimal('right_pd', 5, 2)->nullable();
            $table->string('right_va')->nullable();
            $table->decimal('left_sph', 5, 2)->nullable();
            $table->decimal('left_cyl', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->decimal('left_add', 5, 2)->nullable();
            $table->decimal('left_pd', 5, 2)->nullable();
            $table->string('left_va')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->foreign('patient_eye_exam_id', 'patient_rx_exam_fk')->references('id')->on('patient_eye_exams')->nullOnDelete();
        });

        Schema::create('patient_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('document_type')->default('other')->index();
            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('direction')->default('out')->index();
            $table->string('whatsapp_number')->nullable();
            $table->text('message');
            $table->string('status')->default('queued')->index();
            $table->dateTime('sent_at')->nullable()->index();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_timeline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('event_title');
            $table->dateTime('event_at')->index();
            $table->text('description')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id'], 'patient_timeline_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_timeline_events');
        Schema::dropIfExists('patient_whatsapp_messages');
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('patient_prescriptions');
        Schema::dropIfExists('patient_eye_exams');
        Schema::dropIfExists('patient_appointments');
        Schema::dropIfExists('patients');
    }
};
