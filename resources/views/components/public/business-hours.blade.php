@props(['hours', 'language' => 'en'])
@php
    $ms = $language === 'ms';
    $days = $ms
        ? [1 => 'Isnin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Khamis', 5 => 'Jumaat', 6 => 'Sabtu', 7 => 'Ahad']
        : [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
@endphp
@if ($hours !== [])
    <div class="business-hours">
        <h3><x-public.icon name="clock" /> {{ $ms ? 'Waktu operasi' : 'Business hours' }}</h3>
        <dl>
            @foreach ($hours as $hour)
                <div><dt>{{ $days[$hour->dayOfWeek] }}</dt><dd><time>{{ $hour->opensAt }}</time>–<time>{{ $hour->closesAt }}</time></dd></div>
            @endforeach
        </dl>
    </div>
@endif
