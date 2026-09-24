<x-layouts.error code="419" title="Your session expired" eyebrow="Page expired">
    <p class="lede">
        For your security, the form you were filling in timed out. Nothing was
        submitted and nothing was lost on our side &mdash; go back, reload the page,
        and send it again.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="javascript:history.back()">Go back and retry</a>
        <a class="btn btn-ghost" href="{{ route('login') }}">Sign in again</a>
    </div>
    <p class="hint">This also happens if you had the page open in two tabs, or left it open overnight.</p>
</x-layouts.error>
