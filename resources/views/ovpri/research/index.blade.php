@extends('layouts.app')

@section('title', __('All research'))

@section('navbar-context')
    {{ __('OVPRI') }}
@endsection

@section('content')
    @php
        $stageOptions = [
            '' => __('All'),
            'draft' => __('Draft'),
            'dean_review' => __('Dean Review'),
            'ovpri_review' => __('OVPRI Review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ];

        $approvalStageBadgeStatus = static function (string $stage): string {
            return match ($stage) {
                'draft' => 'draft',
                'dean_review', 'ovpri_review' => 'pending',
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'info',
            };
        };

        $selectedCollege = request('college', '');
        $selectedStage = request('stage', '');
        $selectedStatus = request('status', '');

        $tableRows = collect($research->items())->map(function ($item) use ($approvalStageBadgeStatus) {
            $stageLabel = ucwords(str_replace('_', ' ', $item->approval_stage));

            return [
                'title' => str($item->title)->limit(80),
                'primary_author' => $item->primaryAuthor?->name ?? '—',
                'college' => $item->motherCollege?->code ?? '—',
                'classification' => ucwords(str_replace('_', ' ', $item->research_classification)),
                'approval_stage' => new \Illuminate\Support\HtmlString(
                    \Illuminate\Support\Facades\Blade::render(
                        '<x-badge :status="$status">{{ $label }}</x-badge>',
                        [
                            'status' => $approvalStageBadgeStatus($item->approval_stage),
                            'label' => $stageLabel,
                        ]
                    )
                ),
                'date_submitted' => $item->created_at?->format('M d, Y') ?? '—',
                'actions' => new \Illuminate\Support\HtmlString(
                    '<a href="'.e(route('ovpri.review', $item)).'" '
                    .'class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg '
                    .'bg-[#1E3A8A] text-white no-underline whitespace-nowrap hover:bg-[#1E40AF]">'
                    .e(__('View')).' →</a>'
                ),
            ];
        })->all();
    @endphp

    <x-page-header
        :title="__('All research')"
        :subtitle="__('Institutional research register (OVPRI)')"
        :breadcrumb="[
            ['label' => __('All research')],
        ]"
    />

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif

    <x-card accent="gold" class="mt-6">
        <form method="GET" action="{{ route('ovpri.research') }}">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <div>
                    <span style="font-size:13px;font-weight:600;color:#0F172A;">{{ __('Filter research') }}</span>
                    <span style="font-size:12px;color:#94A3B8;margin-left:8px;">{{ __('Refine the institutional register') }}</span>
                </div>
                <a href="{{ route('ovpri.research') }}" style="font-size:12px;color:#94A3B8;text-decoration:none;">{{ __('Reset filters') }}</a>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:flex-end;">
                <div>
                    <label for="college" style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;">
                        {{ __('College') }}
                    </label>
                    <select id="college" name="college" class="kmsar-select" style="width:100%;" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}" @selected((string) $selectedCollege === (string) $college->id)>
                                {{ $college->code }} — {{ $college->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="stage" style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;">
                        {{ __('Approval stage') }}
                    </label>
                    <select id="stage" name="stage" class="kmsar-select" style="width:100%;" onchange="this.form.submit()">
                        @foreach ($stageOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStage === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;">
                        {{ __('Status') }}
                    </label>
                    <select id="status" name="status" class="kmsar-select" style="width:100%;" onchange="this.form.submit()">
                        @foreach ($stageOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </x-card>

    <x-card accent="gold" class="mt-6" :title="__('Research records')" :count="$research->total()">
        <x-data-table
            :headers="[
                'title' => __('Title'),
                'primary_author' => __('Primary Author'),
                'college' => __('College'),
                'classification' => __('Classification'),
                'approval_stage' => __('Approval Stage'),
                'date_submitted' => __('Date Submitted'),
                'actions' => __('Action'),
            ]"
            :rows="$tableRows"
            :empty="__('No research records found.')"
        />

        @if ($research->hasPages())
            <div class="mt-4 flex justify-end">
                {{ $research->appends(request()->query())->links() }}
            </div>
        @endif
    </x-card>
@endsection
