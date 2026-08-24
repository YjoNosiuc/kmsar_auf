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
        <x-alert type="success" :message="session('success')" class="mb-5" />
    @endif

    @if (session('warning'))
        <x-alert type="warning" :message="session('warning')" class="mb-5" />
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-5">
            <ul class="authors-error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @include('faculty.research.partials.registration-stepper', [
        'currentStep' => 2,
        'research' => $research,
        'step1Complete' => $step1Complete,
        'step2Complete' => $step2Complete,
    ])

    <form
        method="POST"
        action="{{ route('research.wizard.authors.save', $research) }}"
        x-data="kmsarAuthorSelector(
            @js($meData),
            @js($primaryData),
            @js($coAuthorsData),
            @js(route('api.users.search'))
        )"
        class="authors-form"
    >
        @csrf

        <x-card :title="__('Primary Author')" accent="primary">
            <p class="kmsar-body mb-4">
                {{ __('Choose one active KMSAR user as the primary author.') }}
            </p>

            <input
                type="hidden"
                name="primary_author_user_id"
                :value="primaryAuthor?.id ?? ''"
            >

            <div x-show="!primaryAuthor" class="author-stack">
                <div>
                    <button
                        type="button"
                        class="kmsar-btn kmsar-btn--secondary"
                        @click="setMeAsPrimary()"
                    >
                        {{ __('This is me') }}
                    </button>
                </div>

                <div class="author-field" @click.outside="closePrimary()">
                    <label for="primary-author-search" class="kmsar-form-label">
                        {{ __('Or search for another KMSAR user') }}
                    </label>

                    <input
                        id="primary-author-search"
                        type="search"
                        class="kmsar-input w-full author-search-input"
                        x-model="primarySearch"
                        @input.debounce.300ms="searchPrimary()"
                        @focus="if (primarySearch.trim()) primaryOpen = true"
                        @keydown.escape="closePrimary()"
                        placeholder="{{ __('Search by name, email, or ID number') }}"
                        autocomplete="off"
                        aria-label="{{ __('Search primary author') }}"
                    >

                    {{-- Primary author search results dropdown --}}
                    <div class="author-results" x-show="primaryOpen" x-cloak>
                        <div class="author-results__status" x-show="primaryLoading">
                            {{ __('Searching…') }}
                        </div>

                        <template x-for="user in primaryResults" :key="user.id">
                            <button type="button" class="author-result author-result-item" @click="selectPrimary(user)">
                                <span class="author-result__name" x-text="user.name"></span>
                                <span class="author-result__meta" x-text="resultMeta(user)"></span>
                                <span class="author-result__email" x-text="user.email"></span>
                            </button>
                        </template>

                        <div
                            class="author-results__status"
                            x-show="!primaryLoading && primaryResults.length === 0"
                        >
                            {{ __('No users found matching') }} “<span x-text="primarySearch"></span>”
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="primaryAuthor" x-cloak class="author-selected-card author-selected-card--primary">
                <div class="author-grow">
                    <div class="author-inline">
                        <h3 class="author-name" x-text="primaryAuthor?.name"></h3>
                        <x-badge status="info">{{ __('Primary Author') }}</x-badge>
                        <span class="author-tag-me" x-show="iAmPrimary">{{ __('This is me') }}</span>
                    </div>
                    <dl class="author-details-grid">
                        <div><dt>{{ __('ID Number') }}</dt><dd x-text="primaryAuthor?.employee_number || '—'"></dd></div>
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
            <p class="kmsar-body mb-4">
                {{ __('Add any number of KMSAR users. A user cannot be both primary author and co-author.') }}
            </p>

            <div class="author-field mb-5" @click.outside="closeCoAuthors()">
                <label for="coauthor-search" class="kmsar-form-label">{{ __('Add Co-Author') }}</label>

                <input
                    id="coauthor-search"
                    type="search"
                    class="kmsar-input w-full author-search-input"
                    x-model="coAuthorSearch"
                    @input.debounce.300ms="searchCoAuthors()"
                    @focus="if (primaryAuthor && coAuthorSearch.trim()) coAuthorOpen = true"
                    @keydown.escape="closeCoAuthors()"
                    :disabled="!primaryAuthor"
                    placeholder="{{ __('Search by name, email, or ID number') }}"
                    autocomplete="off"
                    aria-label="{{ __('Search co-author') }}"
                >

                <p x-show="!primaryAuthor" class="kmsar-form-hint">
                    {{ __('Select a primary author before adding co-authors.') }}
                </p>

                {{-- Co-author search results dropdown --}}
                <div class="author-results" x-show="coAuthorOpen" x-cloak>
                    <div class="author-results__status" x-show="coAuthorLoading">
                        {{ __('Searching…') }}
                    </div>

                    <template x-for="user in coAuthorResults" :key="user.id">
                        <button type="button" class="author-result author-result-item" @click="addCoAuthor(user)">
                            <span class="author-result__name" x-text="user.name"></span>
                            <span class="author-result__meta" x-text="resultMeta(user)"></span>
                            <span class="author-result__email" x-text="user.email"></span>
                        </button>
                    </template>

                    <div
                        class="author-results__status"
                        x-show="!coAuthorLoading && coAuthorResults.length === 0"
                    >
                        {{ __('No users found matching') }} “<span x-text="coAuthorSearch"></span>”
                    </div>
                </div>
            </div>

            <div x-show="coAuthors.length === 0" class="author-empty">
                {{ __('No co-authors selected.') }}
            </div>

            <div class="author-list">
                <template x-for="(author, index) in coAuthors" :key="author.user_id">
                    <div class="author-selected-card">
                        <input type="hidden" :name="`coauthors[${index}][user_id]`" :value="author.user_id">
                        <input type="hidden" :name="`coauthors[${index}][can_edit]`" value="0">

                        <div class="author-grow">
                            <div class="author-inline">
                                <h3 class="author-name" x-text="author.name"></h3>
                                <span class="author-result__email" x-text="author.email"></span>
                            </div>
                            <div class="author-meta-line" x-text="userDetails(author)"></div>
                            <div class="mt-3">
                                <label class="author-checkbox">
                                    <input
                                        type="checkbox"
                                        :name="`coauthors[${index}][can_edit]`"
                                        value="1"
                                        x-model="author.can_edit"
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

        <div class="authors-actions">
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
@endsection

@push('styles')
    <style>
        [x-cloak] { display: none !important; }

        .authors-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* The dropdown must escape the card box, which is overflow:hidden by default. */
        .authors-form .kmsar-card {
            overflow: visible;
        }

        .authors-error-list {
            margin: 0;
            padding-left: 1.25rem;
            list-style: disc;
        }

        .authors-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .author-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .author-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        /* Positioning context for the absolute results panel. */
        .author-field {
            position: relative;
        }

        .author-results {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            z-index: 50;
            max-height: 20rem;
            overflow-y: auto;
            margin-top: 0.25rem;
            border: 1px solid #CBD5E1;
            border-radius: 0.5rem;
            background: #FFFFFF;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
        }

        .author-results__status {
            padding: 0.75rem 1rem;
            font-size: 0.8125rem;
            color: #94A3B8;
        }

        .author-result {
            display: flex;
            width: 100%;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.75rem 1rem;
            border: 0;
            border-bottom: 1px solid #F1F5F9;
            background: #FFFFFF;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
        }

        .author-result:last-child {
            border-bottom: 0;
        }

        .author-result:hover,
        .author-result:focus-visible {
            background: #F8FAFC;
            outline: none;
        }

        .author-result__name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0F172A;
        }

        .author-result__meta {
            font-size: 0.75rem;
            color: #64748B;
        }

        .author-result__email {
            font-size: 0.6875rem;
            color: #94A3B8;
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

        .author-grow {
            flex: 1;
            min-width: 0;
        }

        .author-inline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .author-name {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #0F172A;
            margin: 0;
        }

        .author-tag-me {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1E40AF;
        }

        .author-meta-line {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            color: #475569;
        }

        .author-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #334155;
            cursor: pointer;
        }

        .author-empty {
            padding: 1.25rem;
            border: 1px dashed #CBD5E1;
            border-radius: 0.5rem;
            text-align: center;
            font-size: 0.8125rem;
            color: #64748B;
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
            margin: 0.15rem 0 0;
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
@endpush

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('kmsarAuthorSelector', (me, initialPrimary, initialCoAuthors, searchUrl) => ({
                primaryAuthor: initialPrimary,
                primarySearch: '',
                primaryResults: [],
                primaryLoading: false,
                primaryOpen: false,
                iAmPrimary: Number(initialPrimary?.id) === Number(me.id),

                coAuthors: (initialCoAuthors || []).map(author => ({
                    ...author,
                    user_id: Number(author.user_id || author.id),
                    can_edit: Boolean(author.can_edit),
                })),
                coAuthorSearch: '',
                coAuthorResults: [],
                coAuthorLoading: false,
                coAuthorOpen: false,

                primaryRequest: 0,
                coAuthorRequest: 0,

                roleLabel(role) {
                    if (!role || role === '—') return '—';
                    return String(role).replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
                },

                resultMeta(user) {
                    return [
                        user?.employee_number,
                        user?.college_code || user?.college,
                        user?.program,
                    ].filter(value => value && value !== '—').join(' · ') || '—';
                },

                userDetails(user) {
                    return [
                        user?.employee_number,
                        user?.college_code || user?.college,
                        user?.program,
                        this.roleLabel(user?.role),
                    ].filter(value => value && value !== '—').join(' · ') || '—';
                },

                closePrimary() {
                    this.primaryOpen = false;
                    this.primaryResults = [];
                },

                closeCoAuthors() {
                    this.coAuthorOpen = false;
                    this.coAuthorResults = [];
                },

                selectPrimary(user) {
                    this.coAuthors = this.coAuthors.filter(author => Number(author.user_id) !== Number(user.id));
                    this.primaryAuthor = user;
                    this.iAmPrimary = Number(user.id) === Number(me.id);
                    this.primarySearch = '';
                    this.coAuthorSearch = '';
                    this.closePrimary();
                    this.closeCoAuthors();
                },

                clearPrimary() {
                    this.primaryAuthor = null;
                    this.iAmPrimary = false;
                    this.primarySearch = '';
                    this.coAuthorSearch = '';
                    this.closePrimary();
                    this.closeCoAuthors();
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
                    this.closeCoAuthors();
                },

                removeCoAuthor(index) {
                    this.coAuthors.splice(index, 1);
                    this.closeCoAuthors();
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
                            credentials: 'same-origin',
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
                        this.primaryLoading = false;
                        this.closePrimary();
                        return;
                    }

                    this.primaryOpen = true;
                    this.primaryLoading = true;
                    const coAuthorIds = this.coAuthors.map(author => author.user_id);
                    this.fetchUsers(search, coAuthorIds, requestNumber, 'primary');
                },

                searchCoAuthors() {
                    const search = this.coAuthorSearch.trim();
                    const requestNumber = ++this.coAuthorRequest;
                    if (!this.primaryAuthor || !search) {
                        this.coAuthorLoading = false;
                        this.closeCoAuthors();
                        return;
                    }

                    this.coAuthorOpen = true;
                    this.coAuthorLoading = true;
                    this.fetchUsers(search, this.excludedIds(), requestNumber, 'coauthor');
                },
            }));
        });
    </script>
@endpush
