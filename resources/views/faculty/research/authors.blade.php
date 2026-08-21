@extends('layouts.app')

@section('title', __('Authors — Step 2'))

@section('navbar-context')
    {{ __('Faculty · Research registration') }}
@endsection

@section('content')
    <x-page-header
        :title="__('Authors')"
        :subtitle="__('Step 2 of 3 · Select the primary author and co-authors from KMSAR users')"
        :breadcrumb="[
            ['label' => __('My Research'), 'route' => 'research.index'],
            ['label' => $research->reference_number, 'route' => 'research.show', 'parameters' => [$research]],
            ['label' => __('Authors')],
        ]"
    />

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @include('faculty.research.partials.registration-stepper', ['currentStep' => 2, 'research' => $research])

    <form
        method="POST"
        action="{{ route('research.wizard.authors.save', $research) }}"
        x-data="kmsarAuthorSelector(
            @js($meData),
            @js($primaryData),
            @js($coAuthorsData),
            @js(route('api.users.search'))
        )"
        class="space-y-6"
    >
        @csrf

        <x-card :title="__('Primary Author')" accent="primary">
            <p class="kmsar-body mb-4 text-slate-500">
                {{ __('Choose one active KMSAR user as the primary author.') }}
            </p>

            <input
                type="hidden"
                name="primary_author_user_id"
                :value="primaryAuthor?.id ?? ''"
            >

            <div x-show="!primaryAuthor" class="space-y-4">
                <button
                    type="button"
                    class="kmsar-btn kmsar-btn--secondary"
                    @click="setMeAsPrimary()"
                >
                    {{ __('This is me') }}
                </button>

                <div class="relative">
                    <label for="primary-author-search" class="kmsar-form-label">
                        {{ __('Or search for another KMSAR user') }}
                    </label>
                    <div class="relative mt-2">
                        <input
                            id="primary-author-search"
                            type="search"
                            class="kmsar-input w-full"
                            x-model="primarySearch"
                            @input.debounce.300ms="searchPrimary()"
                            @keydown.escape="primaryResults = []"
                            placeholder="{{ __('Search by name, email, or employee number') }}"
                            autocomplete="off"
                        >
                        <span
                            x-show="primaryLoading"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
                        >{{ __('Searching…') }}</span>
                    </div>

                    <div
                        x-show="primaryResults.length > 0"
                        x-cloak
                        class="author-search-results"
                    >
                        <template x-for="user in primaryResults" :key="user.id">
                            <button type="button" class="author-search-result" @click="selectPrimary(user)">
                                <span class="author-search-result__name" x-text="user.name"></span>
                                <span class="author-search-result__meta" x-text="user.email"></span>
                                <span class="author-search-result__meta" x-text="userDetails(user)"></span>
                            </button>
                        </template>
                    </div>

                    <p
                        x-show="primarySearch.trim().length > 0 && !primaryLoading && primaryResults.length === 0"
                        x-cloak
                        class="kmsar-form-hint mt-2"
                    >{{ __('No matching active users found.') }}</p>
                </div>
            </div>

            <div x-show="primaryAuthor" x-cloak class="author-selected-card author-selected-card--primary">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-slate-900" x-text="primaryAuthor?.name"></h3>
                        <x-badge status="info">{{ __('Primary Author') }}</x-badge>
                        <span
                            x-show="iAmPrimary"
                            class="text-xs font-semibold text-blue-700"
                        >{{ __('This is me') }}</span>
                    </div>
                    <dl class="author-details-grid">
                        <div><dt>{{ __('Employee Number') }}</dt><dd x-text="primaryAuthor?.employee_number || '—'"></dd></div>
                        <div><dt>{{ __('College / Office') }}</dt><dd x-text="primaryAuthor?.college || '—'"></dd></div>
                        <div><dt>{{ __('Program / Dept') }}</dt><dd x-text="primaryAuthor?.program || '—'"></dd></div>
                        <div><dt>{{ __('Role') }}</dt><dd x-text="roleLabel(primaryAuthor?.role)"></dd></div>
                    </dl>
                </div>
                <button
                    type="button"
                    class="kmsar-btn kmsar-btn--outline kmsar-btn--sm"
                    @click="clearPrimary()"
                >{{ __('Clear') }}</button>
            </div>
        </x-card>

        <x-card :title="__('Co-Authors')" accent="primary">
            <p class="kmsar-body mb-4 text-slate-500">
                {{ __('Add any number of KMSAR users. A user cannot be both primary author and co-author.') }}
            </p>

            <div class="relative mb-5">
                <label for="coauthor-search" class="kmsar-form-label">{{ __('Add Co-Author') }}</label>
                <div class="relative mt-2">
                    <input
                        id="coauthor-search"
                        type="search"
                        class="kmsar-input w-full"
                        x-model="coAuthorSearch"
                        @input.debounce.300ms="searchCoAuthors()"
                        @keydown.escape="coAuthorResults = []"
                        :disabled="!primaryAuthor"
                        placeholder="{{ __('Search by name, email, or employee number') }}"
                        autocomplete="off"
                    >
                    <span
                        x-show="coAuthorLoading"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
                    >{{ __('Searching…') }}</span>
                </div>

                <p x-show="!primaryAuthor" class="kmsar-form-hint mt-2">
                    {{ __('Select a primary author before adding co-authors.') }}
                </p>

                <div
                    x-show="coAuthorResults.length > 0"
                    x-cloak
                    class="author-search-results"
                >
                    <template x-for="user in coAuthorResults" :key="user.id">
                        <button type="button" class="author-search-result" @click="addCoAuthor(user)">
                            <span class="author-search-result__name" x-text="user.name"></span>
                            <span class="author-search-result__meta" x-text="user.email"></span>
                            <span class="author-search-result__meta" x-text="userDetails(user)"></span>
                        </button>
                    </template>
                </div>

                <p
                    x-show="primaryAuthor && coAuthorSearch.trim().length > 0 && !coAuthorLoading && coAuthorResults.length === 0"
                    x-cloak
                    class="kmsar-form-hint mt-2"
                >{{ __('No matching users available to add.') }}</p>
            </div>

            <div x-show="coAuthors.length === 0" class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                {{ __('No co-authors selected.') }}
            </div>

            <div class="space-y-3">
                <template x-for="(author, index) in coAuthors" :key="author.user_id">
                    <div class="author-selected-card">
                        <input type="hidden" :name="`coauthors[${index}][user_id]`" :value="author.user_id">
                        <input type="hidden" :name="`coauthors[${index}][can_edit]`" value="0">

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900" x-text="author.name"></h3>
                                <span class="text-xs text-slate-500" x-text="author.email"></span>
                            </div>
                            <div class="mt-1 text-sm text-slate-600" x-text="userDetails(author)"></div>
                            <div class="mt-3">
                                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        :name="`coauthors[${index}][can_edit]`"
                                        value="1"
                                        x-model="author.can_edit"
                                        class="rounded border-slate-300 text-blue-800 focus:ring-blue-800"
                                    >
                                    <span>{{ __('Can edit this research') }}</span>
                                </label>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="kmsar-btn kmsar-btn--danger-outline kmsar-btn--sm"
                            @click="removeCoAuthor(index)"
                            :aria-label="'{{ __('Remove') }} ' + author.name"
                        >{{ __('Remove') }}</button>
                    </div>
                </template>
            </div>
        </x-card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('research.wizard.details', $research) }}" class="kmsar-btn kmsar-btn--secondary">
                {{ __('Back') }}
            </a>
            <button
                type="submit"
                class="kmsar-btn kmsar-btn--primary"
                :disabled="!primaryAuthor"
            >{{ __('Continue to documents') }}</button>
        </div>
    </form>

    <style>
        .author-search-results {
            position: absolute;
            z-index: 30;
            top: 100%;
            right: 0;
            left: 0;
            max-height: 22rem;
            overflow-y: auto;
            margin-top: 0.25rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.5rem;
            background: #FFFFFF;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
        }

        .author-search-result {
            display: flex;
            width: 100%;
            flex-direction: column;
            gap: 0.15rem;
            padding: 0.75rem 1rem;
            border: 0;
            border-bottom: 1px solid #E2E8F0;
            background: #FFFFFF;
            text-align: left;
            cursor: pointer;
        }

        .author-search-result:last-child {
            border-bottom: 0;
        }

        .author-search-result:hover,
        .author-search-result:focus-visible {
            background: #F8FAFC;
            outline: none;
        }

        .author-search-result__name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0F172A;
        }

        .author-search-result__meta {
            font-size: 0.75rem;
            color: #64748B;
        }

        .author-selected-card {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.625rem;
            background: #FFFFFF;
        }

        .author-selected-card--primary {
            border-color: #BFDBFE;
            background: #EFF6FF;
        }

        .author-details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem 1.5rem;
            margin-top: 1rem;
        }

        .author-details-grid dt {
            font-size: 0.6875rem;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .author-details-grid dd {
            margin-top: 0.15rem;
            font-size: 0.8125rem;
            color: #334155;
        }

        @media (max-width: 640px) {
            .author-selected-card {
                flex-direction: column;
            }

            .author-details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('kmsarAuthorSelector', (me, initialPrimary, initialCoAuthors, searchUrl) => ({
                primaryAuthor: initialPrimary,
                primarySearch: '',
                primaryResults: [],
                primaryLoading: false,
                iAmPrimary: Number(initialPrimary?.id) === Number(me.id),

                coAuthors: (initialCoAuthors || []).map(author => ({
                    ...author,
                    user_id: Number(author.user_id || author.id),
                    can_edit: Boolean(author.can_edit),
                })),
                coAuthorSearch: '',
                coAuthorResults: [],
                coAuthorLoading: false,

                primaryRequest: 0,
                coAuthorRequest: 0,

                roleLabel(role) {
                    if (!role || role === '—') return '—';
                    return String(role).replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
                },

                userDetails(user) {
                    return [
                        user?.employee_number,
                        user?.college_code || user?.college,
                        user?.program,
                        this.roleLabel(user?.role),
                    ].filter(value => value && value !== '—').join(' · ') || '—';
                },

                selectPrimary(user) {
                    this.coAuthors = this.coAuthors.filter(author => Number(author.user_id) !== Number(user.id));
                    this.primaryAuthor = user;
                    this.iAmPrimary = Number(user.id) === Number(me.id);
                    this.primarySearch = '';
                    this.primaryResults = [];
                    this.coAuthorSearch = '';
                    this.coAuthorResults = [];
                },

                clearPrimary() {
                    this.primaryAuthor = null;
                    this.iAmPrimary = false;
                    this.primarySearch = '';
                    this.primaryResults = [];
                    this.coAuthorSearch = '';
                    this.coAuthorResults = [];
                },

                setMeAsPrimary() {
                    this.selectPrimary(me);
                },

                addCoAuthor(user) {
                    const id = Number(user.id);
                    if (!this.primaryAuthor || Number(this.primaryAuthor.id) === id) return;
                    if (this.coAuthors.some(author => Number(author.user_id) === id)) return;

                    this.coAuthors.push({
                        ...user,
                        user_id: id,
                        can_edit: true,
                    });
                    this.coAuthorSearch = '';
                    this.coAuthorResults = [];
                },

                removeCoAuthor(index) {
                    this.coAuthors.splice(index, 1);
                    this.coAuthorResults = [];
                },

                excludedIds() {
                    return [
                        this.primaryAuthor?.id,
                        ...this.coAuthors.map(author => author.user_id),
                    ].filter(Boolean).map(Number);
                },

                async fetchUsers(search, excludeIds, requestNumber, requestType) {
                    const params = new URLSearchParams({ q: search });
                    excludeIds.forEach(id => params.append('exclude[]', id));

                    try {
                        const response = await fetch(`${searchUrl}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        if (!response.ok) throw new Error('Search failed');

                        const users = await response.json();
                        if (requestType === 'primary' && requestNumber === this.primaryRequest) {
                            this.primaryResults = users;
                        }
                        if (requestType === 'coauthor' && requestNumber === this.coAuthorRequest) {
                            this.coAuthorResults = users;
                        }
                    } catch (error) {
                        if (requestType === 'primary') this.primaryResults = [];
                        if (requestType === 'coauthor') this.coAuthorResults = [];
                    } finally {
                        if (requestType === 'primary' && requestNumber === this.primaryRequest) {
                            this.primaryLoading = false;
                        }
                        if (requestType === 'coauthor' && requestNumber === this.coAuthorRequest) {
                            this.coAuthorLoading = false;
                        }
                    }
                },

                searchPrimary() {
                    const search = this.primarySearch.trim();
                    const requestNumber = ++this.primaryRequest;
                    if (!search) {
                        this.primaryResults = [];
                        this.primaryLoading = false;
                        return;
                    }

                    this.primaryLoading = true;
                    const coAuthorIds = this.coAuthors.map(author => author.user_id);
                    this.fetchUsers(search, coAuthorIds, requestNumber, 'primary');
                },

                searchCoAuthors() {
                    const search = this.coAuthorSearch.trim();
                    const requestNumber = ++this.coAuthorRequest;
                    if (!this.primaryAuthor || !search) {
                        this.coAuthorResults = [];
                        this.coAuthorLoading = false;
                        return;
                    }

                    this.coAuthorLoading = true;
                    this.fetchUsers(search, this.excludedIds(), requestNumber, 'coauthor');
                },
            }));
        });
    </script>
@endpush
