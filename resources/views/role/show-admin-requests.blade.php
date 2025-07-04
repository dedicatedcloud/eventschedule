@if(count($requests) == 0 || ! $role->email_verified_at)

<div class="text-center pt-20">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="#ccc" viewBox="0 0 24 24" stroke="currentColor"
        aria-hidden="true">
        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21,19V20H3V19L5,17V11C5,7.9 7.03,5.17 10,4.29C10,4.19 10,4.1 10,4A2,2 0 0,1 12,2A2,2 0 0,1 14,4C14,4.1 14,4.19 14,4.29C16.97,5.17 19,7.9 19,11V17L21,19M14,21A2,2 0 0,1 12,23A2,2 0 0,1 10,21" />
    </svg>
    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ __('messages.no_requests') }}</h3>
    <p class="mt-1 text-sm text-gray-500">{{ __('messages.share_your_sign_up_link_to_get_more_requests') }}</p>
    <div class="mt-3">
        <a href="{{ route('event.sign_up', ['subdomain' => $role->subdomain]) }}" target="_blank" class="hover:underline">
            {{ \App\Utils\UrlUtils::clean(route('event.sign_up', ['subdomain' => $role->subdomain])) }}
        </a>
    </div>
</div>

@else

<ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 pt-5">
    @foreach($requests as $event)
    <li class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow">
        <div class="flex flex-1 flex-col p-8 items-center">
            {{-- Profile Image --}}
            @if ($role->isVenue() || $role->isCurator())
                @if ($event->role() && $event->role()->profile_image_url)
                    <img class="mx-auto rounded-2xl h-24 w-24 object-cover mb-4" src="{{ $event->role()->profile_image_url }}" alt="Profile Image">
                @endif
                <a href="{{ $event->role() ? $event->role()->getGuestUrl() : '#' }}" target="_blank" class="text-lg font-bold text-gray-900 mb-1 hover:text-blue-600 hover:underline">{{ $event->role() ? $event->role()->name : $event->translatedName() }}</a>
            @else
                @if ($event->venue && $event->venue->profile_image_url)
                    <img class="mx-auto rounded-2xl h-24 w-24 object-cover mb-4" src="{{ $event->venue->profile_image_url }}" alt="Profile Image">
                @endif
                <a href="{{ $event->venue ? $event->venue->getGuestUrl() : '#' }}" target="_blank" class="text-lg font-bold text-gray-900 mb-1 hover:text-blue-600 hover:underline">{{ $event->venue ? $event->venue->name : $event->translatedName() }}</a>
            @endif

            {{-- Date/Time --}}
            @if ($event->starts_at)
                <div class="text-sm text-gray-500 mb-2">{{ $event->localStartsAt(true) }}</div>
            @endif

            {{-- Group/Curator Badge --}}
            @if ($role->isCurator())
                @php $group = $event->getGroupForSubdomain($role->subdomain); @endphp
                @if ($group)
                    <span class="inline-block bg-blue-100 text-[#4E81FA] text-xs font-semibold px-3 py-1 rounded-full mb-2">
                        {{ $group->translatedName() }}
                    </span>
                @endif
            @endif

            {{-- Description --}}
            <dl class="mt-1 flex-grow w-full">
                @if ($role->isVenue() || $role->isCurator())
                    <dd class="text-xs text-gray-500 line-clamp-3">{{ $event->role() ? $event->role()->description : '' }}</dd>
                @else
                    <dd class="text-xs text-gray-500 line-clamp-3">{{ $event->venue ? $event->venue->description : '' }}</dd>
                @endif
            </dl>

            {{-- View Event Button --}}
            <a href="{{ $event->getGuestUrl() }}" target="_blank" class="inline-block mt-4 px-4 py-2 bg-blue-500 text-white text-sm font-semibold rounded hover:bg-blue-600 transition">
                {{ __('messages.view_event') }}
            </a>
        </div>
        <div>
            <div class="-mt-px flex divide-x divide-gray-200">
                <div class="flex w-0 flex-1 cursor-pointer accept-event"
                    onclick="location.href = '{{ route('event.accept', ['subdomain' => $role->subdomain, 'hash' => App\Utils\UrlUtils::encodeId($event->id)]) }}'; return false;">
                    <div
                        class="relative -mr-px inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-bl-lg border border-transparent py-4 text-sm font-semibold text-gray-900">
                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd" />
                        </svg>
                        {{ __('messages.accept') }}
                    </div>
                </div>
                <div class="-ml-px flex w-0 flex-1 cursor-pointer"
                    onclick="var confirmed = confirm('{{ __('messages.are_you_sure') }}'); if (confirmed) { location.href = '{{ route('event.decline', ['subdomain' => $role->subdomain, 'hash' => App\Utils\UrlUtils::encodeId($event->id), 'redirect_to' => 'requests']) }}'; } return false;">
                    <div
                        class="relative inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-br-lg border border-transparent py-4 text-sm font-semibold text-gray-900">
                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path
                                d="M12,2C17.53,2 22,6.47 22,12C22,17.53 17.53,22 12,22C6.47,22 2,17.53 2,12C2,6.47 6.47,2 12,2M15.59,7L12,10.59L8.41,7L7,8.41L10.59,12L7,15.59L8.41,17L12,13.41L15.59,17L17,15.59L13.41,12L17,8.41L15.59,7Z" />
                        </svg>
                        {{ __('messages.decline') }}
                    </div>
                </div>
            </div>
        </div>
    </li>
    @endforeach
</ul>
@endif
