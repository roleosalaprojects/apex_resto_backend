@props([
    'sumId',
    'title',
    'chartId',
    'useWhite' => false,
    'useCurrency' => true,
])

<!--begin::Card widget 8-->
<div class="card overflow-hidden card-flush card-px-0 card-py-0 h-100 h-lg-225px" {{ $attributes }}>
    <div class="card-header pt-5 mb-3 px-6">
        <!--begin::Title-->
        <h3 class="card-title align-items-start flex-column">
            <!--begin::Info-->
            <div class="d-flex align-items-center mb-2">
                <!--begin::Currency-->
                <span class="fs-4 fw-semibold {{ $useWhite ? 'text-white' : 'text-gray-400' }} align-self-start me-1">{{ $useCurrency === true ? '₱' : '' }}</span>
                <!--end::Currency-->
                <!--begin::Value-->
                <span class="fs-2hx fw-bold {{ $useWhite ? 'text-white' : 'text-gray-800' }} me-2 lh-1" id="{{ $sumId }}">0.00</span>
                <!--end::Value-->
            </div>
            <!--end::Info-->
            <!--begin::Description-->
            <span class="fs-6 fw-semibold {{ $useWhite ? 'text-white' : 'text-gray-400' }}">{{ $title }}</span>
            <!--end::Description-->
        </h3>
        <!--end::Title-->
    </div>
    <!--begin::Card body-->
    <div class="card-body d-flex align-items-end pt-0">
        <div id="{{ $chartId }}" class="min-h-auto w-100 mb-2" style="height: 125px; min-height: 120px;"></div>
    </div>
    <!--end::Card body-->
</div>
<!--end::Card widget 8-->