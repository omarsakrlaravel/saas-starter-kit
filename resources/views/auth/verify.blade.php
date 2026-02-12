<x-auth::layouts.app title="{{ config('devdojo.auth.language.verify.page_title') }}">
    <x-auth::elements.container>

        <x-auth::elements.heading
            :text="config('devdojo.auth.language.verify.headline', 'Verify your email address')"
            :description="config('devdojo.auth.language.register.subheadline', '')"
            :show_subheadline="config('devdojo.auth.language.verify.show_subheadline', false)" />

        <div class="flex flex-col items-center text-center">
            @if (session('resent'))
                <div class="flex items-center w-full px-4 py-3 mb-5 text-sm text-white rounded-lg" style="background-color: #16a34a;" role="alert">
                    <svg class="mr-2 w-5 h-5 flex-shrink-0 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <p>{{ config('devdojo.auth.language.verify.new_link_sent', 'A fresh verification link has been sent to your email address.') }}</p>
                </div>
            @endif

            <p class="text-sm leading-6 text-gray-500">
                We've sent a verification link to <span class="font-medium text-gray-700">{{ auth()->user()->email }}</span>. Please check your inbox and click the link to activate your account.
            </p>

            {{-- Resend button --}}
            <form method="POST" action="{{ route('verification.send') }}" class="w-full mt-6">
                @csrf
                <button type="submit" class="auth-component-button px-4 py-2.5 text-sm font-semibold rounded-lg cursor-pointer inline-flex items-center w-full justify-center opacity-95 hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden" style="color: {{ config('devdojo.auth.appearance.color.button_text') }}; background-color: {{ config('devdojo.auth.appearance.color.button') }};">
                    Resend Verification Email
                </button>
            </form>

            {{-- Logout link --}}
            <form action="{{ route('logout') }}" method="POST" class="mt-4">
                @csrf
                <button type="submit" class="text-sm text-gray-400 cursor-pointer transition duration-150 ease-in-out hover:text-gray-600">
                    {{ config('devdojo.auth.language.verify.logout', 'Log out') }}
                </button>
            </form>
        </div>

    </x-auth::elements.container>
</x-auth::layouts.app>
