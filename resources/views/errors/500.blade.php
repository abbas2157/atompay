<x-layouts.error code="500" title="Something went wrong on our side" eyebrow="Server error">
    <p class="lede">
        This one is our fault, not yours. The problem has been logged and our team
        can see it. Your account, your application and your approved limit are not
        affected.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('home') }}">Back to the homepage</a>
        <a class="btn btn-ghost" href="javascript:location.reload()">Try again</a>
    </div>
    <p class="hint">If you were part-way through an application, sign in again &mdash; your saved answers are still there.</p>
</x-layouts.error>
