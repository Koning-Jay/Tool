<x-filament-panels::page>
    <div class="grid gap-6">
        {{-- Stats Overview --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($stats as $stat)
                {{ $stat }}
            @endforeach
        </div>

        {{-- Recent Activity --}}
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Recent Magento Sites --}}
            <x-filament::section>
                <x-slot name="heading">Recent Magento Sites</x-slot>

                <div class="space-y-4">
                    @forelse($recentMagentos as $magento)
                        <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                            <div>
                                <div class="font-medium">{{ $magento->name }}</div>
                                <div class="text-sm text-gray-500">{{ $magento->url }}</div>
                            </div>
                            <div class="text-sm text-gray-500">
                                Added {{ $magento->created_at->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No recent Magento sites</div>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- Recent Custom Checks --}}
            <x-filament::section>
                <x-slot name="heading">Recent Custom Checks</x-slot>

                <div class="space-y-4">
                    @forelse($recentChecks as $check)
                        <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                            <div>
                                <div class="font-medium">{{ $check->name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ ucfirst($check->check_type) }} - {{ $check->threshold_value }}
                                    @if($check->is_active)
                                        <span class="px-2 py-1 text-xs text-green-800 bg-green-100 rounded-full">Active</span>
                                    @else
                                        <span class="px-2 py-1 text-xs text-gray-800 bg-gray-100 rounded-full">Inactive</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                Added {{ $check->created_at->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No recent custom checks</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
