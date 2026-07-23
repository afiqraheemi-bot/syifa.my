@props(['title', 'stepNumber', 'totalSteps'])
<div class="booking-step-header">
    <p class="booking-progress" aria-live="polite">Step {{ $stepNumber }} of {{ $totalSteps }}</p>
    <h1>{{ $title }}</h1>
</div>
