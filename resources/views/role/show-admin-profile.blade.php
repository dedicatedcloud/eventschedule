@if ($role->description_html)
<div class="mt-5 overflow-hidden rounded-lg bg-white shadow-md">
    <div class="px-4 py-5 sm:p-6">
        {!! \App\Utils\UrlUtils::convertUrlsToLinks($role->description_html) !!}
    </div>
</div>
@endif

@if (config('services.google.maps') && $role->formatted_address)
<div class="pt-5">
    <div id="map" style="height: 200px;"></div>
</div>
@endif

<div class="pt-5 container mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white text-sm shadow ring-1 ring-black ring-opacity-5">
        <div class="relative flex items-center py-6 px-4 space-x-6">
            <div class="flex-auto pl-4">
                <h4 class="font-bold mb-2 flex justify-between items-center">
                    {{ __('messages.youtube_videos') }}
                    <button type="button"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        onclick="showAdd('youtube_links')">
                        {{ __('messages.add') }}
                    </button>
                </h4>

            @if ($role->youtube_links)
            <p class="text-gray-700">

            <ul role="list" class="divide-y divide-gray-200">
                @foreach(json_decode($role->youtube_links) as $link)
                @if ($link)
                <li class="py-4">
                    <div class="flex">
                        <div class="mr-4 flex-shrink-0 text-gray-500">
                            <x-url-icon>
                                {{ \App\Utils\UrlUtils::clean($link->url) }}
                            </x-url-icon>
                        </div>
                        <div>
                            <a href="{{ $link->url }}" target="_blank">
                                <h4 class="text-lg font-bold break-words line-clamp-2">{{ $link->name }}</h4>
                                <img src="{{ $link->thumbnail_url }}"/>
                            </a>
                            <button type="button"
                                class="mt-3 inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                onclick="removeLink('youtube_links', '{{ $link->url }}')">
                                {{ __('messages.remove') }}
                            </button>
                        </div>
                    </div>
                </li>
                @endif
                @endforeach
            </ul>

            </p>
            @endif
            </div>
        </div>
    </div>

    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white text-sm shadow ring-1 ring-black ring-opacity-5">
        <div class="relative flex items-center py-6 px-4 space-x-6">
            <div class="flex-auto pl-4">
                <h4 class="font-bold mb-2 flex justify-between items-center">
                    {{ __('messages.social_links') }}
                    <button type="button"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        onclick="showAdd('social_links')">
                        {{ __('messages.add') }}
                    </button>
                </h4>

            @if ($role->social_links)
            <p class="text-gray-700">
            <ul role="list" class="divide-y divide-gray-200">
                @foreach(json_decode($role->social_links) as $link)
                @if ($link)
                <li class="py-4">
                    <div class="flex">
                        <div class="mr-4 flex-shrink-0 pt-1 text-gray-500">
                            <x-url-icon>
                                {{ \App\Utils\UrlUtils::clean($link->url) }}
                            </x-url-icon>
                        </div>
                        <div>
                            <a href="{{ $link->url }}" target="_blank">
                                <h4 class="text-lg font-bold break-words line-clamp-2">
                                    {{ \App\Utils\UrlUtils::getBrand($link->url) }}</h4>
                                <p class="mt-1 line-clamp-2 break-all">{{ \App\Utils\UrlUtils::clean($link->url) }}
                                </p>
                            </a>
                            <button type="button"
                                class="mt-3 inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                onclick="removeLink('social_links', '{{ $link->url }}')">
                                {{ __('messages.remove') }}
                            </button>
                        </div>
                    </div>
                </li>
                @endif
                @endforeach
            </ul>
            </p>
            @endif
            </div>
        </div>
    </div>

    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white text-sm shadow ring-1 ring-black ring-opacity-5">
        <div class="relative flex items-center py-6 px-4 space-x-6">
            <div class="flex-auto pl-4">
                <h4 class="font-bold mb-2 flex justify-between items-center">
                    {{ __('messages.payment_links') }}
                    <button type="button"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        onclick="showAdd('payment_links')">
                        {{ __('messages.add') }}
                    </button>
                </h4>
            @if ($role->payment_links)
            <p class="text-gray-700">
            <ul role="list" class="divide-y divide-gray-200">
                @foreach(json_decode($role->payment_links) as $link)
                @if ($link)
                <li class="py-4">
                    <div class="flex">
                        <div class="mr-4 flex-shrink-0 pt-1 text-gray-500">
                            <x-url-icon>
                                {{ \App\Utils\UrlUtils::clean($link->url) }}
                            </x-url-icon>
                        </div>
                        <div>
                            <a href="{{ $link->url }}" target="_blank">
                                <h4 class="text-lg font-bold break-words line-clamp-2">
                                    {{ \App\Utils\UrlUtils::getBrand($link->url) }}</h4>
                                <p class="mt-1 line-clamp-2 break-all">{{ \App\Utils\UrlUtils::clean($link->url) }}
                                </p>
                            </a>
                            <button type="button"
                                class="mt-3 inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                onclick="removeLink('payment_links', '{{ $link->url }}')">
                                {{ __('messages.remove') }}
                            </button>
                        </div>
                    </div>
                </li>
                @endif
                @endforeach
            </ul>
            </p>
            @endif
            </div>
        </div>

    </div>
    </div>
</div>


<div id="add_modal" class="hidden relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!--
Background backdrop, show/hide based on modal state.

Entering: "ease-out duration-300"
    From: "opacity-0"
    To: "opacity-100"
Leaving: "ease-in duration-200"
    From: "opacity-100"
    To: "opacity-0"
-->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!--
    Modal panel, show/hide based on modal state.

    Entering: "ease-out duration-300"
        From: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        To: "opacity-100 translate-y-0 sm:scale-100"
    Leaving: "ease-in duration-200"
        From: "opacity-100 translate-y-0 sm:scale-100"
        To: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    -->
            <form method="POST" action="{{ route('role.update_links', ['subdomain' => $role->subdomain]) }}">

                @csrf

                <input type="hidden" id="link_type" name="link_type" />

                <div
                    class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2x1 sm:p-6">
                    <div>
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">
                                {{ __('messages.add_link') }}</h3>
                            <div class="mt-2">

                                <x-text-input id="link" name="link" type="url" class="mt-1 block w-full" required
                                    autofocus />
                                <x-input-error class="mt-2" :messages="$errors->get('link')" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                        <button type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-[#4E81FA] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3A6BE0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4E81FA] sm:col-start-2">{{ __('messages.save') }}</button>
                        <button type="button"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                            onclick="hideAdd()">{{ __('messages.cancel') }}</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<script {!! nonce_attr() !!}>
function showAdd(link_type) {
    $('#add_modal').fadeIn(function() {
        $('#link').focus();
        $('#link_type').val(link_type);
    });
}

function hideAdd() {
    $('#add_modal').fadeOut();
}

function removeLink(link_type, link) {
    var confirmed = confirm("{{ __('messages.are_you_sure') }}");

    if (confirmed) {
        $('#remove_link').val(link);
        $('#remove_link_type').val(link_type);
        $('#remove_link_form').submit();
    }
}

function initMap() {
    var latitude = "{{ $role->geo_lat }}";
    var longitude = "{{ $role->geo_lon }}";
    
    var latLng = new google.maps.LatLng(latitude, longitude);    
    var map = new google.maps.Map(document.getElementById('map'), {
        center: latLng,
        zoom: 15
    });
}
</script>
