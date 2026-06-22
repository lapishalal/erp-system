{{-- resources/views/filament/infolists/components/sales-order-timeline.blade.php --}}

@php
    $data = $getState();
    $stages = $data['stages'];
    $progress = $data['progress_percent'];
    $isOverdue = $data['is_overdue'];
    $quickActions = $data['quick_actions'];
@endphp

<div class="w-full space-y-6">
    
    {{-- Overdue Alert --}}
    @if($isOverdue)
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-center gap-3">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500 flex-shrink-0" />
            <div>
                <p class="text-sm font-semibold text-red-800">Invoice Overdue!</p>
                <p class="text-xs text-red-600">Invoice jatuh tempo tetapi belum lunas. Segera follow up customer.</p>
            </div>
        </div>
    @endif

    {{-- Timeline Container --}}
    <div class="relative px-4 py-6 bg-white rounded-xl border border-gray-200 shadow-sm">
        
        {{-- Progress Line Background --}}
        <div class="absolute top-[2.25rem] left-12 right-12 h-1 bg-gray-200 rounded-full">
            <div 
                class="h-full rounded-full transition-all duration-700 ease-out
                {{ $isOverdue ? 'bg-red-500' : 'bg-primary-600' }}"
                style="width: {{ max(0, $progress) }}%"
            ></div>
        </div>

        {{-- Steps --}}
        <div class="relative flex justify-between items-start">
            @foreach($stages as $index => $stage)
                @php
                    $statusClass = match($stage['status']) {
                        'completed' => $isOverdue && $stage['key'] === 'payment' 
                            ? 'bg-red-500 border-red-500 text-white' 
                            : 'bg-primary-600 border-primary-600 text-white',
                        'current' => 'bg-white border-primary-600 text-primary-600 ring-4 ring-primary-100 animate-pulse',
                        'failed' => 'bg-red-100 border-red-500 text-red-500',
                        default => 'bg-white border-gray-300 text-gray-400',
                    };
                    
                    $labelClass = match($stage['status']) {
                        'completed' => $isOverdue && $stage['key'] === 'payment' ? 'text-red-600' : 'text-primary-700',
                        'current' => 'text-primary-700 font-semibold',
                        'failed' => 'text-red-600',
                        default => 'text-gray-400',
                    };
                @endphp

                <div class="flex flex-col items-center w-1/4 relative group">
                    
                    {{-- Icon Circle --}}
                    <div class="w-11 h-11 rounded-full flex items-center justify-center border-2 z-10 transition-all duration-300 {{ $statusClass }}">
                        <x-dynamic-component :component="$stage['icon']" class="w-5 h-5" />
                    </div>

                    {{-- Connector dots for completed stages --}}
                    @if($index < count($stages) - 1 && $stage['status'] === 'completed')
                        <div class="absolute top-5 left-1/2 w-full h-0.5 bg-primary-600 -z-0"></div>
                    @endif

                    {{-- Label & Details --}}
                    <div class="mt-3 text-center w-full px-1">
                        <p class="text-sm {{ $labelClass }}">{{ $stage['label'] }}</p>
                        
                        {{-- Badge --}}
                        <span class="inline-block mt-1 px-2 py-0.5 text-[10px] rounded-full font-medium
                            {{ $stage['status'] === 'completed' ? 'bg-primary-100 text-primary-700' : '' }}
                            {{ $stage['status'] === 'current' ? 'bg-primary-50 text-primary-600 border border-primary-200' : '' }}
                            {{ $stage['status'] === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $stage['status'] === 'pending' ? 'bg-gray-100 text-gray-500' : '' }}">
                            {{ $stage['badge'] }}
                        </span>

                        {{-- Detail --}}
                        <p class="text-xs text-gray-500 mt-1.5 leading-tight">{{ $stage['detail'] }}</p>

                        {{-- Date --}}
                        @if($stage['date'])
                            <p class="text-[10px] text-gray-400 mt-1">
                                {{ \Carbon\Carbon::parse($stage['date'])->format('d M Y H:i') }}
                            </p>
                        @endif

                        {{-- Expandable Meta Info --}}
                        @if(!empty($stage['meta']) && $stage['status'] !== 'pending')
                            <div class="mt-2 p-2 bg-gray-50 rounded-lg text-left opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                @foreach($stage['meta'] as $key => $value)
                                    @if(is_string($value) && !empty($value))
                                        <p class="text-[10px] text-gray-500 truncate" title="{{ $value }}">
                                            <span class="capitalize">{{ str_replace('_', ' ', $key) }}:</span> 
                                            <span class="font-medium text-gray-700">{{ $value }}</span>
                                        </p>
                                    @endif
                                @endforeach
                                
                                {{-- Progress Bar for Payment --}}
                                @if($stage['key'] === 'payment' && isset($stage['meta']['percent']))
                                    <div class="mt-1.5 w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ $stage['meta']['percent'] }}%"></div>
                                    </div>
                                    <p class="text-[10px] text-gray-500 mt-0.5 text-right">{{ $stage['meta']['percent'] }}%</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Quick Actions --}}
    @if(count($quickActions) > 0)
        <div class="flex flex-wrap gap-2">
            @foreach($quickActions as $action)
                <a 
                    href="{{ $action['url'] }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors
                    {{ $action['color'] === 'primary' ? 'bg-primary-600 text-white hover:bg-primary-700' : '' }}
                    {{ $action['color'] === 'success' ? 'bg-success-600 text-white hover:bg-success-700' : '' }}
                    {{ $action['color'] === 'warning' ? 'bg-warning-500 text-white hover:bg-warning-600' : '' }}"
                >
                    <x-dynamic-component :component="$action['icon']" class="w-3.5 h-3.5" />
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Summary Footer --}}
    <div class="flex items-center justify-between text-xs text-gray-500 bg-gray-50 rounded-lg px-4 py-2">
        <span>Stage saat ini: <strong class="text-gray-700">{{ $data['current_stage'] }}</strong></span>
        <span>Progress: <strong class="text-gray-700">{{ round($progress) }}%</strong></span>
    </div>
</div>