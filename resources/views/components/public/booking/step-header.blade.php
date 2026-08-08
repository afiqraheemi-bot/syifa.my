@props(['title', 'stepNumber', 'totalSteps'])
<div class="booking-step-header">
    <div class="booking-progress" aria-label="Booking progress: step {{ $stepNumber }} of {{ $totalSteps }}">
        <span class="booking-progress__label">Appointment booking</span>
        <span class="booking-progress__count">Step {{ $stepNumber }} of {{ $totalSteps }}</span>
        <span class="booking-progress__track" aria-hidden="true">
            <span style="width: {{ (int) round(($stepNumber / $totalSteps) * 100) }}%"></span>
        </span>
    </div>
    <h1>{{ $title }}</h1>
</div>
