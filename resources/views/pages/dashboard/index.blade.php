<?php
    use function Laravel\Folio\{middleware, name};
	middleware(['auth', 'verified']);
    name('dashboard');
?>

<x-layouts.app>
	<x-app.container x-data class="lg:space-y-6" x-cloak>
        
		<x-app.alert id="dashboard_alert" class="hidden lg:flex">This is the user dashboard where users can manage settings and access features. <a href="https://devdojo.com/wave/docs" target="_blank" class="mx-1 underline">View the docs</a> to learn more.</x-app.alert>

        <x-app.heading
                title="Dashboard"
                description="Welcome to an example application dashboard. Find more resources below."
                :border="false"
            />

        <div class="flex flex-col w-full mt-6 space-y-5 md:flex-row lg:mt-0 md:space-y-0 md:space-x-5">
            <x-app.dashboard-card
				href="https://devdojo.com/wave/docs"
				target="_blank"
				title="Documentation"
				description="Learn how to customize your app and make it shine!"
				link_text="View The Docs"
				image="/wave/img/docs.png"
			/>
			<x-app.dashboard-card
				href="https://devdojo.com/questions"
				target="_blank"
				title="Ask The Community"
				description="Share your progress and get help from other builders."
				link_text="Ask a Question"
				image="/wave/img/community.png"
			/>
        </div>

		<div class="flex flex-col w-full mt-5 space-y-5 md:flex-row md:space-y-0 md:mb-0 md:space-x-5">
			<x-app.dashboard-card
				href="https://github.com/thedevdojo/wave"
				target="_blank"
				title="Github Repo"
				description="View the source code and submit a Pull Request"
				link_text="View on Github"
				image="/wave/img/laptop.png"
			/>
			<x-app.dashboard-card
				href="https://devdojo.com"
				target="_blank"
				title="Resources"
				description="View resources that will help you build your SaaS"
				link_text="View Resources"
				image="/wave/img/globe.png"
			/>
		</div>

		<div class="mt-5 p-4 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
			<h3 class="text-sm font-medium text-neutral-500 dark:text-neutral-400 mb-3">Feature Flags</h3>
			<div class="flex flex-wrap gap-3">
				@php
					$org = auth()->user()->currentOrganizationForContext();
					$features = [
						'ai-reports' => 'AI Reports',
						'new-editor' => 'New Editor',
						'maintenance-mode' => 'Maintenance Mode',
					];
				@endphp
				@foreach($features as $key => $label)
					@php $active = $org ? \Laravel\Pennant\Feature::for($org)->active($key) : false; @endphp
					<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium {{ $active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400' }}">
						<span class="w-2 h-2 rounded-full {{ $active ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-500' }}"></span>
						{{ $label }}
					</span>
				@endforeach
			</div>
		</div>

		<div class="mt-5 space-y-5">
			@subscriber
				<p>You are subscribed to the <strong>{{ auth()->user()->plan()->name }}</strong> plan. Learn <a href="https://devdojo.com/wave/docs" target="_blank" class="underline">more</a> about managing your subscription.</p>
				<x-app.message-for-subscriber />
			@else
				<p>You do not have an active subscription. To get started, <a href="{{ route('settings.subscription') }}" wire:navigate class="underline">subscribe to a plan</a>.</p>
			@endsubscriber
			
			@admin
				<x-app.message-for-admin />
			@endadmin
		</div>
    </x-app.container>
</x-layouts.app>
