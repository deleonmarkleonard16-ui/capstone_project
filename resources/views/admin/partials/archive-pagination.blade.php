<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3" aria-label="{{ $label }} pagination">
    <p class="text-muted small mb-0">{{ $label }}: {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</p>
    {{ $records->fragment($section)->links('pagination::bootstrap-5') }}
</div>
