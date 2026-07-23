@if ($timeViewModel !== null)
    @if ($timeViewModel->hasNoAvailableTimes())
        <p>No available times on this date. Please choose another date.</p>
    @else
        <div class="booking-chip-grid" role="radiogroup" aria-label="Choose a time">
            @foreach ($timeViewModel->times as $time)
                <label>
                    <input type="radio" name="appointment_time" value="{{ $time->value }}" class="visually-hidden-radio" @disabled(! $time->tappable())>
                    <span class="booking-chip" aria-pressed="false">{{ $time->label }}</span>
                </label>
            @endforeach
        </div>
    @endif
@endif
