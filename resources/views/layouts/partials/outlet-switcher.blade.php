@php
    /**
     * Which branch the dashboard is reading.
     *
     * An operator assigned to an outlet sees it as a fixed label — that
     * assignment is the safeguard against acting on the wrong branch, so it
     * is deliberately not switchable from here.
     */
    $me = auth('web')->user();
    $pinned = (bool) $me?->outlet_id;
@endphp

@if ($pinned)
    <span class="outlet-chip outlet-chip--fixed" title="Anda ditugaskan pada outlet ini">
        <x-icon name="store" size="14"/>
        <span class="truncate">{{ $outlet?->name ?? '—' }}</span>
        <x-icon name="lock" size="12" class="subtle"/>
    </span>
@else
    <div class="dropdown">
        <button type="button" class="outlet-chip" data-dropdown>
            <x-icon name="store" size="14"/>
            <span class="truncate">{{ $outlet?->name ?? 'Semua Outlet' }}</span>
            <x-icon name="chevron-down" size="13"/>
        </button>

        <div class="dropdown__menu dropdown__menu--left" style="min-width:250px">
            <div class="dropdown__label">Tampilkan data untuk</div>

            <form method="POST" action="{{ route('admin.outlets.switch') }}">
                @csrf
                <button type="submit" name="outlet_id" value=""
                        class="dropdown__item {{ $outlet ? '' : 'semi' }}">
                    <x-icon name="layers" size="15"/>
                    <span class="grow">Semua Outlet</span>
                    @unless ($outlet) <x-icon name="check" size="14" style="color:var(--ok-600)"/> @endunless
                </button>

                <div class="dropdown__sep"></div>

                @foreach ($outletOptions as $option)
                    <button type="submit" name="outlet_id" value="{{ $option->id }}"
                            class="dropdown__item {{ $outlet?->id === $option->id ? 'semi' : '' }}">
                        <span class="code-chip" style="font-size:10px;padding:1px 5px">{{ $option->code }}</span>
                        <span class="grow truncate">{{ $option->name }}</span>
                        @if ($outlet?->id === $option->id)
                            <x-icon name="check" size="14" style="color:var(--ok-600)"/>
                        @endif
                    </button>
                @endforeach
            </form>

            @allow('outlet.manage')
                <div class="dropdown__sep"></div>
                <a href="{{ route('admin.outlets.index') }}" class="dropdown__item">
                    <x-icon name="settings" size="15"/> Kelola Outlet
                </a>
            @endallow
        </div>
    </div>
@endif
