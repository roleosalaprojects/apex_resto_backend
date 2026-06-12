<div wire:poll.60s="refresh">
    <div class="card card-flush h-100">
        <div class="card-header pt-5">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-dark">Revenue Comparison</span>
                <span class="text-gray-400 mt-1 fw-semibold fs-6">Performance overview</span>
            </h3>
        </div>
        <div class="card-body pt-0">
            {{-- Today --}}
            <div class="d-flex flex-stack mb-6">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-calendar-tick fs-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                                <span class="path6"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-800 fw-bold fs-6">Today</span>
                        <span class="text-muted fw-semibold d-block fs-7">{{ $todayTransactions }} transactions</span>
                    </div>
                </div>
                <div class="text-end">
                    <span class="text-gray-800 fw-bold fs-3">
                        {{ config('app.currency', '₱') }}{{ number_format($todaySales, 2) }}
                    </span>
                </div>
            </div>

            {{-- Yesterday --}}
            <div class="d-flex flex-stack mb-6">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-warning">
                            <i class="ki-duotone ki-calendar fs-2 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-800 fw-bold fs-6">Yesterday</span>
                        <span class="text-muted fw-semibold d-block fs-7">{{ $yesterdayTransactions }} transactions</span>
                    </div>
                </div>
                <div class="text-end">
                    <span class="text-gray-800 fw-bold fs-4">
                        {{ config('app.currency', '₱') }}{{ number_format($yesterdaySales, 2) }}
                    </span>
                    <span class="badge badge-light-{{ $vsYesterdayPercent >= 0 ? 'success' : 'danger' }} fs-8 ms-2">
                        @if($vsYesterdayPercent >= 0)
                            <i class="ki-duotone ki-arrow-up fs-9 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        @else
                            <i class="ki-duotone ki-arrow-down fs-9 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        @endif
                        {{ abs($vsYesterdayPercent) }}%
                    </span>
                </div>
            </div>

            {{-- Last Week Same Day --}}
            <div class="d-flex flex-stack">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-info">
                            <i class="ki-duotone ki-calendar-2 fs-2 text-info">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-800 fw-bold fs-6">Last {{ now()->subWeek()->format('l') }}</span>
                        <span class="text-muted fw-semibold d-block fs-7">{{ $lastWeekTransactions }} transactions</span>
                    </div>
                </div>
                <div class="text-end">
                    <span class="text-gray-800 fw-bold fs-4">
                        {{ config('app.currency', '₱') }}{{ number_format($lastWeekSameDaySales, 2) }}
                    </span>
                    <span class="badge badge-light-{{ $vsLastWeekPercent >= 0 ? 'success' : 'danger' }} fs-8 ms-2">
                        @if($vsLastWeekPercent >= 0)
                            <i class="ki-duotone ki-arrow-up fs-9 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        @else
                            <i class="ki-duotone ki-arrow-down fs-9 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        @endif
                        {{ abs($vsLastWeekPercent) }}%
                    </span>
                </div>
            </div>

            {{-- Summary Bar --}}
            <div class="separator my-6"></div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted fs-7">vs Yesterday</span>
                    <div class="fw-bold fs-5 text-{{ $vsYesterdayPercent >= 0 ? 'success' : 'danger' }}">
                        {{ $vsYesterdayPercent >= 0 ? '+' : '' }}{{ config('app.currency', '₱') }}{{ number_format($todaySales - $yesterdaySales, 2) }}
                    </div>
                </div>
                <div class="text-end">
                    <span class="text-muted fs-7">vs Last Week</span>
                    <div class="fw-bold fs-5 text-{{ $vsLastWeekPercent >= 0 ? 'success' : 'danger' }}">
                        {{ $vsLastWeekPercent >= 0 ? '+' : '' }}{{ config('app.currency', '₱') }}{{ number_format($todaySales - $lastWeekSameDaySales, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
