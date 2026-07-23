<x-public.booking.layout title="What do you need help with?">
    <x-public.booking.step-header title="What do you need help with?" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />

    <form method="POST" action="{{ route('public-website.booking.service.update') }}">
        @csrf
        <div class="booking-option-list" role="radiogroup" aria-label="Choose a service">
            @foreach ($viewModel->options as $option)
                <label class="booking-option{{ $option->id === '' ? ' booking-not-sure' : '' }}">
                    <input type="radio" name="service_id" value="{{ $option->id }}" @checked($option->selected) @if ($viewModel->serviceRequired && $option->id === '') disabled @endif>
                    {{ $option->label }}
                </label>
            @endforeach
        </div>

        <div class="booking-sticky-actions">
            <button type="submit" class="button button--primary">Continue</button>
        </div>
    </form>
</x-public.booking.layout>
