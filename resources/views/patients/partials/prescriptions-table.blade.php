<section class="section">
    <div class="section-header">
        <h2>{{ __('patients.prescription_history') }}</h2>
        <span class="pill tone-blue">{{ $prescriptions->count() }}</span>
    </div>
    <div class="table">
        @forelse ($prescriptions as $prescription)
            <div class="row rx-row">
                <div><span class="strong">{{ $prescription->prescription_number }}</span><span class="sub">{{ $prescription->prescribed_on }}</span></div>
                <div>{{ $prescription->full_name }}<span class="sub">{{ $prescription->patient_code }} / {{ $prescription->branch_name ?? __('common.no_branch') }}</span></div>
                <div>{{ $prescription->optometrist_name ?? __('common.unassigned') }}</div>
                <div><span class="pill tone-{{ $prescription->status === 'locked' ? 'green' : ($prescription->status === 'signed' ? 'blue' : 'amber') }}">{{ str($prescription->status)->title() }}</span></div>
                <div>OD {{ $prescription->right_sph }} / OS {{ $prescription->left_sph }}</div>
                <div><a class="pill tone-blue" href="{{ route('documents.prescriptions.show', ['prescription' => $prescription->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a> <a class="pill tone-blue" href="{{ route('documents.prescriptions.show', ['prescription' => $prescription->id, 'lang' => app()->getLocale(), 'format' => 'pdf']) }}">PDF</a></div>
            </div>
        @empty
            <div class="empty">{{ __('patients.no_prescriptions') }}</div>
        @endforelse
    </div>
</section>
