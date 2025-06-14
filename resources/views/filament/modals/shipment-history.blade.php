{{-- resources/views/filament/modals/shipment-history.blade.php --}}

<div class="fi-modal-content p-6">
    {{-- Header Section using Filament's section component style --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
            <div class="fi-section-header-heading">
                <h3 class="fi-section-title text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <div class="flex items-center gap-x-3">
                        <div class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                            <svg class="fi-badge-icon h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            {{ $shipment->tracking_number }}
                        </div>
                        History & Timeline
                    </div>
                </h3>
            </div>

            <div class="fi-section-header-actions flex shrink-0 items-center gap-3">
                <div class="flex items-center gap-x-2">
                    <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 py-1
                        @switch($shipment->status)
                            @case('delivered')
                                fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30
                                @break
                            @case('in_transit')
                                fi-color-info bg-info-50 text-info-600 ring-info-600/10 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30
                                @break
                            @case('pending')
                                fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30
                                @break
                            @default
                                fi-color-warning bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30
                        @endswitch">
                        <div class="h-1.5 w-1.5 rounded-full bg-current"></div>
                        {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
                    </span>

                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $shipment->created_at->format('M d, Y H:i') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

  

    {{-- Activity Timeline Section --}}
    <div class="fi-section mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4 border-b border-gray-200 dark:border-white/10">
            <div class="fi-section-header-heading">
                <h3 class="fi-section-title text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center gap-x-2">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Activity Timeline
                </h3>
            </div>
            <div class="fi-section-header-actions flex shrink-0 items-center gap-3">
                <div class="flex items-center gap-x-2">
                    <div class="h-2 w-2 rounded-full bg-success-500 animate-pulse"></div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Live</span>
                </div>
            </div>
        </div>

        <div class="fi-section-content p-6" id="activityFeed">
            @forelse($histories as $history)
                <div class="activity-item fi-timeline-item relative pb-8 last:pb-0" data-action="{{ $history->action }}" data-user="{{ $history->user->name }}" data-description="{{ $history->description }}">
                    @if(!$loop->last)
                        <div class="absolute left-4 top-8 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                    @endif

                    <div class="relative flex space-x-4">
                        {{-- Timeline Icon --}}
                        <div class="relative flex h-8 w-8 flex-none items-center justify-center">
                            <div class="h-8 w-8 rounded-full ring-4 ring-white dark:ring-gray-900 flex items-center justify-center
                                @switch($history->action)
                                    @case('created') bg-success-500 @break
                                    @case('status_changed') bg-primary-500 @break
                                    @case('driver_assigned') bg-purple-500 @break
                                    @case('field_updated') bg-warning-500 @break
                                    @default bg-gray-500
                                @endswitch">

                                @switch($history->action)
                                    @case('created')
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        @break
                                    @case('status_changed')
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        @break
                                    @case('driver_assigned')
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        @break
                                    @case('field_updated')
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        @break
                                    @default
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                @endswitch
                            </div>
                        </div>

                        {{-- Content Card --}}
                        <div class="fi-card flex-1 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="fi-card-body p-4">
                                {{-- User Info Header --}}
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-x-3">
                                        <div class="fi-avatar fi-user-avatar h-9 w-9 rounded-full bg-gray-50 bg-cover bg-center dark:bg-gray-800 flex items-center justify-center">
                                            <img
                                                src="https://ui-avatars.com/api/?name={{ urlencode($history->user->name) }}&background=6b7280&color=fff&size=36"
                                                alt="{{ $history->user->name }}"
                                                class="h-full w-full rounded-full object-cover"
                                            >
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $history->user->name }}
                                            </div>
                                            <div class="flex items-center gap-x-2">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $history->user->email }}
                                                </div>
                                                @if($history->user->hasRole('Admin'))
                                                    <span class="fi-badge fi-badge-size-xs inline-flex items-center gap-x-1 rounded-md px-1.5 py-0.5 text-xs font-medium fi-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                        Admin
                                                    </span>
                                                @elseif($history->user->hasRole('Driver'))
                                                    <span class="fi-badge fi-badge-size-xs inline-flex items-center gap-x-1 rounded-md px-1.5 py-0.5 text-xs font-medium fi-color-info bg-info-50 text-info-600 ring-info-600/10 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30">
                                                        Driver
                                                    </span>
                                                @elseif($history->user->hasRole('Seller'))
                                                    <span class="fi-badge fi-badge-size-xs inline-flex items-center gap-x-1 rounded-md px-1.5 py-0.5 text-xs font-medium fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                                        Seller
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-950 dark:text-white">
                                            {{ $history->created_at->format('H:i') }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $history->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <div class="mb-3">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $history->description }}
                                    </p>
                                </div>

                                {{-- Value Changes --}}
                                @if($history->old_value || $history->new_value)
                                    <div class="flex items-center gap-x-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                        @if($history->old_value)
                                            <div class="flex items-center gap-x-2">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">FROM</span>
                                                <span class="fi-badge fi-badge-size-sm inline-flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium fi-color-danger bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                    {{ Str::limit($history->old_value, 20) }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($history->old_value && $history->new_value)
                                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                            </svg>
                                        @endif
                                        @if($history->new_value)
                                            <div class="flex items-center gap-x-2">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">TO</span>
                                                <span class="fi-badge fi-badge-size-sm inline-flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium fi-color-success bg-success-50 text-success-700 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                                    {{ Str::limit($history->new_value, 20) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Technical Details --}}
                                @if($history->metadata && (isset($history->metadata['ip_address']) || isset($history->metadata['user_agent'])))
                                    <details class="mt-3">
                                        <summary class="fi-link group/link relative inline-flex items-center justify-center font-semibold outline-none transition duration-75 hover:underline focus-visible:underline rounded-lg text-sm text-gray-950 hover:text-gray-700 focus-visible:text-gray-700 dark:text-white dark:hover:text-gray-300 dark:focus-visible:text-gray-300 cursor-pointer">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="h-3 w-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                                Technical Details
                                            </span>
                                        </summary>
                                        <div class="mt-2 rounded-lg bg-gray-900 p-3 dark:bg-gray-800">
                                            <div class="space-y-1 text-xs font-mono">
                                                @if(isset($history->metadata['ip_address']))
                                                    <div class="flex items-center gap-x-2">
                                                        <span class="text-green-400">IP:</span>
                                                        <code class="text-green-300">{{ $history->metadata['ip_address'] }}</code>
                                                    </div>
                                                @endif
                                                @if(isset($history->metadata['user_agent']))
                                                    <div class="flex items-start gap-x-2">
                                                        <span class="text-blue-400">Browser:</span>
                                                        <code class="text-blue-300 text-xs leading-relaxed">{{ Str::limit($history->metadata['user_agent'], 80) }}</code>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                {{-- Empty State --}}
                <div class="text-center py-12">
                    <div class="fi-empty-state-icon-wrapper mx-auto h-12 w-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="fi-empty-state-icon h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="fi-empty-state-heading mt-4 text-lg font-semibold text-gray-950 dark:text-white">
                        No Activity Yet
                    </h3>
                    <p class="fi-empty-state-description mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Once changes are made to this shipment, the activity timeline will appear here.
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Stats Footer --}}
    @if($histories->count() > 0)
        <div class="fi-section mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="fi-stats-card">
                        <div class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $histories->count() }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Total Changes
                        </div>
                    </div>
                    <div class="fi-stats-card">
                        <div class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $histories->unique('user_id')->count() }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Contributors
                        </div>
                    </div>
                    <div class="fi-stats-card">
                        <div class="text-2xl font-bold text-gray-950 dark:text-white">
                            {{ $histories->first()->created_at->diffInDays(now()) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Days Active
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('historySearch');
    const activityItems = document.querySelectorAll('.activity-item');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            activityItems.forEach(item => {
                const user = item.dataset.user.toLowerCase();
                const description = item.dataset.description.toLowerCase();

                if (user.includes(searchTerm) || description.includes(searchTerm) || searchTerm === '') {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active state
            filterButtons.forEach(btn => {
                btn.classList.remove('fi-btn-color-primary');
                btn.classList.add('fi-btn-color-gray');
                btn.classList.remove('active');
            });

            this.classList.add('fi-btn-color-primary');
            this.classList.remove('fi-btn-color-gray');
            this.classList.add('active');

            const filterType = this.dataset.filter;

            activityItems.forEach(item => {
                const itemAction = item.dataset.action;

                if (filterType === 'all' || itemAction === filterType) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});
</script>
