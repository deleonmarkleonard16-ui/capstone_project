@if($appointment->status === 'Under review' && !$appointment->is_archived && !$appointment->archived_at)
    <section class="col-12 no-print" aria-label="Counselor evaluation">
        <hr>
        <h3 class="h5 text-warning"><i class="bi bi-clipboard2-check me-2"></i>Counselor Evaluation</h3>
        <p class="text-muted small">Review the student's results above, fill in your remarks and recommendation, then choose an action.</p>
        <form method="post"
              data-evaluation-form
              action="{{ route(auth()->user()->role.'.guidance-appointments.evaluate', $appointment) }}"
              @if($evaluationInModal ?? false) data-review-evaluate @endif>
            @csrf
            @if($errors->any())
                <div class="alert alert-danger" role="alert"><ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
            @endif
            <div class="mb-3">
                <label class="form-label fw-bold" for="eval-remarks-{{ $appointment->getKey() }}">
                    IV. Remarks <span class="text-muted fw-normal">(Counselor notes)</span>
                </label>
                <textarea class="form-control" id="eval-remarks-{{ $appointment->getKey() }}" name="remarks" rows="4" maxlength="5000"
                          placeholder="Enter your clinical remarks here…">{{ old('remarks', filled($appointment->remarks) ? $appointment->remarks : $appointment->defaultEvaluationRemarks()) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">V. Recommendation</label>
                @if($appointment->test_category === 'psychological')
                    @foreach(\App\Services\PsychologicalReportService::RECOMMENDATIONS as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendations"
                                   id="rec-{{ $appointment->getKey() }}-{{ $loop->index }}"
                                   value="{{ $option }}"
                                   @checked(old('recommendations', $appointment->recommendations) === $option)>
                            <label class="form-check-label" for="rec-{{ $appointment->getKey() }}-{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                @elseif($appointment->test_category === 'personality')
                    @foreach(['For Further Development', 'Satisfactory', 'Needs Improvement', 'For Counseling'] as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendations"
                                   id="rec-{{ $appointment->getKey() }}-{{ $loop->index }}"
                                   value="{{ $option }}"
                                   @checked(old('recommendations', $appointment->recommendations) === $option)>
                            <label class="form-check-label" for="rec-{{ $appointment->getKey() }}-{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                @else
                    @foreach(['Highly Interested / Strong Affinity', 'Moderately Interested', 'Mildly Interested', 'For Career Counseling'] as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendations"
                                   id="rec-{{ $appointment->getKey() }}-{{ $loop->index }}"
                                   value="{{ $option }}"
                                   @checked(old('recommendations', $appointment->recommendations) === $option)>
                            <label class="form-check-label" for="rec-{{ $appointment->getKey() }}-{{ $loop->index }}">{{ $option }}</label>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="border rounded p-3 mb-3 bg-light">
                <h4 class="h6">Evaluation checklist</h4>
                <ul class="small mb-3" aria-live="polite">
                    <li data-evaluation-check="remarks">Remarks: review the notes above.</li>
                    <li data-evaluation-check="recommendation">Recommendation: select a disposition.</li>
                    <li data-evaluation-check="confirmation">Review confirmation: pending.</li>
                </ul>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="review_confirmed" value="1" id="review-confirmed-{{ $appointment->getKey() }}"
                           @checked(old('review_confirmed', $appointment->evaluationIsComplete()))>
                    <label class="form-check-label" for="review-confirmed-{{ $appointment->getKey() }}">I reviewed the assessment results, remarks, and recommendation, including any default text.</label>
                </div>
                <p class="small text-muted mt-2 mb-0">You can save a draft without confirmation. Complete this checklist before archiving or marking a batch assessment as reviewed.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" name="action" value="save" class="btn btn-outline-primary" formnovalidate>
                    <i class="bi bi-floppy me-1"></i>Save Remarks and Recommendation
                </button>
                @unless($appointment->batch_id)
                <button type="submit" name="action" value="complete_and_archive" class="btn btn-success">
                    <i class="bi bi-archive me-1"></i>Save &amp; Move to Archive
                </button>
                @endunless
            </div>
        </form>
    </section>
    @endif

