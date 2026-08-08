<x-public.booking.layout title="What do you need help with?" :theme="$theme">
    <x-public.booking.step-header title="What do you need help with?" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />
    <p class="booking-intro">Choose one service so the clinic can prepare for your visit.</p>

    <form method="POST" action="{{ route('public-website.booking.service.update') }}" data-booking-step-form>
        @csrf
        <div class="booking-option-list" role="radiogroup" aria-label="Choose a service">
            @foreach ($viewModel->options as $option)
                <label class="booking-option{{ $option->id === '' ? ' booking-not-sure' : '' }}">
                    <input type="radio" name="service_id" value="{{ $option->id }}" @checked($option->selected)
                        @required($viewModel->serviceRequired) @if ($viewModel->serviceRequired && $option->id === '') disabled @endif>
                    <span class="booking-option__order" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="booking-option__content">
                        <span class="booking-option__label">{{ $option->label }}</span>
                        <span class="booking-option__hint">Select this service</span>
                    </span>
                    <span class="booking-option__indicator" aria-hidden="true"></span>
                </label>
            @endforeach
        </div>
        @error('service_id')<p class="booking-field-error">{{ $message }}</p>@enderror

        <div class="booking-form-actions">
            <a class="booking-secondary-action" href="{{ route('public-website.home') }}">Cancel</a>
            <button type="submit" class="button button--primary" data-booking-submit data-pending-label="Loading…">Continue</button>
        </div>
    </form>
</x-public.booking.layout>
