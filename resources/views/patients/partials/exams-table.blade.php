<section class="section">
    <div class="section-header">
        <h2>{{ __('patients.eye_exam_history') }}</h2>
        <span class="pill tone-green">{{ $exams->count() }}</span>
    </div>
    <div class="table">
        @forelse ($exams as $exam)
            <div class="row exam-row">
                <div><span class="strong">{{ $exam->exam_number }}</span><span class="sub">{{ $exam->exam_date }}</span></div>
                <div>{{ $exam->full_name }}<span class="sub">{{ $exam->patient_code }} / {{ $exam->branch_name ?? __('common.no_branch') }}</span></div>
                <div>{{ $exam->optometrist_name ?? __('common.unassigned') }}</div>
                <div><span class="pill tone-blue">OD {{ $exam->right_sph }} / OS {{ $exam->left_sph }}</span></div>
                <div>{{ $exam->next_visit_date ?? __('patients.no_follow_up') }}</div>
            </div>
        @empty
            <div class="empty">{{ __('patients.no_exams') }}</div>
        @endforelse
    </div>
</section>
