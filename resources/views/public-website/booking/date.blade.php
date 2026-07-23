<x-public.booking.layout title="Choose a date">
    <x-public.booking.step-header title="When would you like to come in?" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />

    <form method="POST" action="{{ route('public-website.booking.date.update') }}">
        @csrf
        <div class="booking-chip-grid" role="radiogroup" aria-label="Choose a date">
            @foreach ($viewModel->dates as $date)
                <label>
                    <input type="radio" name="appointment_date" value="{{ $date->value }}" class="visually-hidden-radio"
                        @checked($date->selected) @disabled(! $date->tappable()) onchange="this.form.submit()">
                    <span class="booking-chip" aria-pressed="{{ $date->selected ? 'true' : 'false' }}">{{ $date->label }}</span>
                </label>
            @endforeach
        </div>

        @if (! $viewModel->hasAnyAvailableDate())
            <p>No available dates in the next two weeks. Please message us to arrange a time.</p>
        @endif

        @if ($timeViewModel !== null)
            <h2>Choose a time</h2>
            @if ($timeViewModel->hasNoAvailableTimes())
                <p>No available times on this date. Please choose another date.</p>
            @else
                <div class="booking-chip-grid" role="radiogroup" aria-label="Choose a time">
                    @foreach ($timeViewModel->times as $time)
                        <label>
                            <input type="radio" name="appointment_time" value="{{ $time->value }}" class="visually-hidden-radio"
                                @checked($time->selected) @disabled(! $time->tappable())>
                            <span class="booking-chip" aria-pressed="{{ $time->selected ? 'true' : 'false' }}">{{ $time->label }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="booking-sticky-actions">
            <button type="submit" class="button button--primary">Continue</button>
        </div>
    </form>
</x-public.booking.layout>
