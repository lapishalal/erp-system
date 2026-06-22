{{-- resources/views/filament/components/sales-order-timeline.blade.php --}}
<div class="w-full py-4">
    <div class="relative">
        {{-- Progress Line --}}
        <div class="absolute top-5 left-0 w-full h-1 bg-gray-200 rounded">
            @php
                $completed = collect($getState()['stages'])->where('status', 'completed')->count();
                $total = count($getState()['stages']);
                $progress = $total > 1 ? (($completed - 1) / ($total - 1)) * 100 : 0;
            @endphp
            <div class="h-full bg-primary-600 rounded transition-all duration-500" 
                 style="width: {{ max(0, $progress) }}%"></div>
        </div>

        {{-- Steps --}}
        <div class="relative flex justify-between">
            @foreach($getState()['stages'] as $stage)
                <div class="flex flex-col items-center w-1/4">
                    {{-- Icon Circle --}}
                    <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 z-10
                        @if($stage['status'] === 'completed') bg-primary-600 border-primary-600 text-white
                        @elseif($stage['status'] === 'current') bg-white border-primary-600 text-primary-600 animate-pulse
                        @else bg-white border-gray-300 text-gray-400 @endif">
                        <x-dynamic-component :component="$stage['icon']" class="w-5 h-5" />
                    </div>
                    
                    {{-- Label --}}
                    <div class="mt-2 text-center">
                        <p class="text-sm font-semibold 
                            @if($stage['status'] === 'completed') text-primary-600
                            @elseif($stage['status'] === 'current') text-primary-600
                            @else text-gray-400 @endif">
                            {{ $stage['label'] }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">{{ $stage['detail'] }}</p>
                        @if($stage['date'])
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ \Carbon\Carbon::parse($stage['date'])->format('d M Y H:i') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>