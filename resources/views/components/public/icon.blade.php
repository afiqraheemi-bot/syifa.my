@props(['name'])
@switch($name)
    @case('phone')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6.6 10.8a15.9 15.9 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24c1 .36 2.06.56 3.18.56a1 1 0 0 1 1 1V19.5a1 1 0 0 1-1 1C10.6 20.5 3.5 13.4 3.5 4.9a1 1 0 0 1 1-1h3.4a1 1 0 0 1 1 1c0 1.12.2 2.18.56 3.18a1 1 0 0 1-.24 1.02l-2.06 2.7Z" /></svg>
        @break
    @case('mail')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5.5" width="18" height="13" rx="2" /><path d="M3.5 6.5 12 13l8.5-6.5" /></svg>
        @break
    @case('message')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 5.5h16v10H9.5L5 19v-3.5H4v-10Z" /></svg>
        @break
    @case('location')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21c-4.2-4.4-7-8.2-7-11.5a7 7 0 1 1 14 0c0 3.3-2.8 7.1-7 11.5Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
        @break
    @case('clock')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></svg>
        @break
    @case('external')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 5h5v5" /><path d="M19 5l-9 9" /><path d="M8 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2" /></svg>
        @break
    @case('medical')
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.5 19 6v5.2c0 4.2-2.5 7.4-7 9.3-4.5-1.9-7-5.1-7-9.3V6l7-2.5Z" /><path d="M12 8v7M8.5 11.5h7" /></svg>
        @break
@endswitch
