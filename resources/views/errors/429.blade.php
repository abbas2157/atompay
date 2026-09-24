<x-layouts.error code="429" title="Too many requests" eyebrow="Slow down">
    <p class="lede">
        You've made a lot of requests in a short time, so we've paused them for a
        moment. This limit protects everyone's account details &mdash; wait a minute
        and try again.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('home') }}">Back to the homepage</a>
    </div>
    <p class="hint">If you're sure you did nothing unusual, you may share an address with other users on the same network.</p>
</x-layouts.error>
