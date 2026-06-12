<div wire:poll.30s="refresh">
    <div class="card card-flush h-100">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Staff Leaderboard</span>
                <span class="text-gray-400 mt-1 fw-semibold fs-6">Top performers today</span>
            </h3>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fs-7">
                    {{ config('app.currency', '₱') }}{{ number_format($totalTeamSales, 2) }} total
                </span>
            </div>
        </div>
        <div class="card-body pt-5">
            @forelse($leaderboard as $index => $staff)
                <div class="d-flex align-items-center mb-5" wire:key="staff-{{ $staff->id }}">
                    {{-- Rank Badge --}}
                    <div class="me-4">
                        @if($index === 0)
                            <span class="badge badge-circle badge-warning fw-bold fs-6" style="width: 35px; height: 35px;">
                                <i class="ki-duotone ki-crown fs-4 text-white">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                        @elseif($index === 1)
                            <span class="badge badge-circle badge-light-primary fw-bold fs-6" style="width: 35px; height: 35px;">
                                2
                            </span>
                        @elseif($index === 2)
                            <span class="badge badge-circle badge-light-info fw-bold fs-6" style="width: 35px; height: 35px;">
                                3
                            </span>
                        @else
                            <span class="badge badge-circle badge-light fw-bold fs-6" style="width: 35px; height: 35px;">
                                {{ $index + 1 }}
                            </span>
                        @endif
                    </div>

                    {{-- Avatar --}}
                    <div class="symbol symbol-45px me-4">
                        @if($staff->image)
                            <img src="{{ asset($staff->image) }}" alt="{{ $staff->name }}" class="symbol-label">
                        @else
                            <span class="symbol-label bg-light-{{ ['primary', 'success', 'info', 'warning', 'danger'][$index % 5] }} text-{{ ['primary', 'success', 'info', 'warning', 'danger'][$index % 5] }} fw-bold fs-5">
                                {{ strtoupper(substr($staff->name, 0, 1)) }}
                            </span>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-grow-1">
                        <span class="text-dark fw-bold fs-6">{{ $staff->name }}</span>
                        <span class="text-muted fw-semibold d-block fs-7">
                            {{ $staff->transaction_count }} sales
                            <span class="bullet bullet-dot bg-gray-400 mx-1"></span>
                            Avg {{ config('app.currency', '₱') }}{{ number_format($staff->avg_transaction, 2) }}
                        </span>
                    </div>

                    {{-- Sales Amount --}}
                    <div class="text-end">
                        <span class="text-dark fw-bold fs-5">
                            {{ config('app.currency', '₱') }}{{ number_format($staff->total_sales, 2) }}
                        </span>
                        @if($totalTeamSales > 0)
                            <div class="progress h-6px w-80px mt-2" style="background-color: #f1f1f1;">
                                <div class="progress-bar bg-{{ ['warning', 'primary', 'info', 'success', 'secondary'][$index % 5] }}"
                                     role="progressbar"
                                     style="width: {{ ($staff->total_sales / $totalTeamSales) * 100 }}%">
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10">
                    <i class="ki-duotone ki-people fs-3x text-gray-300 mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    <p class="text-gray-500 fs-6 mb-0">No sales recorded today</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
