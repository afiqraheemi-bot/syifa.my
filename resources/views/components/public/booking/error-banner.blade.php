@props(['errors'])
<div class="booking-error-banner" role="alert" aria-live="assertive">
    <div>
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
        @if ($errors->has('infrastructure'))
            <p><a href="{{ $whatsAppUrl ?? '#' }}">Message us on WhatsApp</a> if this keeps happening.</p>
        @endif
    </div>
</div>
