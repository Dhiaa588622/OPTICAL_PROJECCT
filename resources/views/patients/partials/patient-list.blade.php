<section class="section">
    <div class="section-header">
        <h2>{{ __('nav.patients') }}</h2>
        <a class="pill tone-brand" href="{{ route('patients.app', ['page' => 'patient-create']) }}">{{ __('patients.new_patient') }}</a>
    </div>
    <div class="table">
        @forelse ($patients as $patient)
            <div class="row patient-row">
                <div><span class="strong">{{ $patient->patient_code }}</span><span class="sub">{{ $patient->is_active ? __('status.active') : __('status.inactive') }}</span></div>
                <div>{{ $patient->full_name }}<span class="sub">{{ $patient->email ?? __('common.no_email') }}</span></div>
                <div>{{ $patient->phone ?? '-' }}</div>
                <div>{{ $patient->whatsapp_number ?? '-' }}</div>
                <span class="pill tone-{{ $patient->is_active ? 'green' : 'red' }}">{{ $patient->is_active ? __('status.active') : __('status.inactive') }}</span>
                <a class="pill tone-blue" href="{{ route('patients.app', ['page' => 'profile', 'patient_id' => $patient->id]) }}">{{ __('common.open_file') }}</a>
            </div>
        @empty
            <div class="empty">{{ __('patients.no_patients') }}</div>
        @endforelse
    </div>
</section>
