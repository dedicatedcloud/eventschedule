<x-auth-layout>

    <x-slot name="head">
        @if(session('pending_venue'))
            <x-step-indicator :compact="true" />
        @endif
    </x-slot>

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('messages.thanks_for_signing_up') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ __('messages.verification_link_sent', ['email' => auth()->user()->email]) }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('messages.resend_verification_email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4E81FA] dark:focus:ring-offset-gray-800">
                {{ __('messages.log_out') }}
            </button>
        </form>
    </div>
</x-auth-layout>
