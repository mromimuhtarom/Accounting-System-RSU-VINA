@if ($paginator->hasPages())
    <div class="vina-pagination">

        {{-- Informasi Data --}}
        <div class="vina-pagination-info">
            Menampilkan
            <strong>{{ $paginator->firstItem() }}</strong>
            sampai
            <strong>{{ $paginator->lastItem() }}</strong>
            dari
            <strong>{{ $paginator->total() }}</strong>
            data
        </div>

        <div class="vina-pagination-nav">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="vina-page vina-page-nav disabled">
                    ‹ Sebelumnya
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="vina-page vina-page-nav"
                    rel="prev"
                >
                    ‹ Sebelumnya
                </a>
            @endif


            {{-- Halaman Pertama --}}
            @if ($paginator->currentPage() > 3)
                <a
                    href="{{ $paginator->url(1) }}"
                    class="vina-page"
                >
                    1
                </a>

                @if ($paginator->currentPage() > 4)
                    <span class="vina-page-dots">•••</span>
                @endif
            @endif


            {{-- Halaman sekitar current page --}}
            @php
                $start = max(
                    1,
                    $paginator->currentPage() - 1
                );

                $end = min(
                    $paginator->lastPage(),
                    $paginator->currentPage() + 1
                );

                /*
                 * Saat berada di awal, tampilkan:
                 * 1 2 3
                 */
                if ($paginator->currentPage() <= 2) {
                    $end = min(
                        3,
                        $paginator->lastPage()
                    );
                }

                /*
                 * Saat berada di akhir, tampilkan
                 * 3 halaman terakhir.
                 */
                if (
                    $paginator->currentPage()
                    >= $paginator->lastPage() - 1
                ) {
                    $start = max(
                        1,
                        $paginator->lastPage() - 2
                    );
                }
            @endphp

            @for ($page = $start; $page <= $end; $page++)
                @if ($page == $paginator->currentPage())
                    <span
                        class="vina-page active"
                        aria-current="page"
                    >
                        {{ $page }}
                    </span>
                @else
                    <a
                        href="{{ $paginator->url($page) }}"
                        class="vina-page"
                    >
                        {{ $page }}
                    </a>
                @endif
            @endfor


            {{-- Halaman Terakhir --}}
            @if ($end < $paginator->lastPage())
                @if ($end < $paginator->lastPage() - 1)
                    <span class="vina-page-dots">•••</span>
                @endif

                <a
                    href="{{ $paginator->url($paginator->lastPage()) }}"
                    class="vina-page"
                >
                    {{ $paginator->lastPage() }}
                </a>
            @endif


            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="vina-page vina-page-nav"
                    rel="next"
                >
                    Berikutnya ›
                </a>
            @else
                <span class="vina-page vina-page-nav disabled">
                    Berikutnya ›
                </span>
            @endif

        </div>
    </div>
@endif


<style>
/* =========================================================
   VINA ESTETICA - COMPACT PAGINATION
   Hanya berlaku untuk pagination ini.
   ========================================================= */

.vina-pagination {
    margin-top: 24px;
    padding: 20px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;
    flex-wrap: wrap;

    border: 1px solid rgba(18, 65, 54, .09);
    border-radius: 22px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.98),
            rgba(246,250,248,.96)
        );

    box-shadow:
        0 16px 38px rgba(13, 50, 40, .07);

    position: relative;
    z-index: 1;
}


/* INFO */
.vina-pagination-info {
    font-size: 14px;
    font-weight: 600;
    color: #718398;

    white-space: nowrap;
}

.vina-pagination-info strong {
    color: #16334a;
    font-weight: 850;
}


/* NAV */
.vina-pagination-nav {
    display: flex;
    align-items: center;

    gap: 8px;

    flex-wrap: nowrap;
}


/* BUTTON */
.vina-page {
    min-width: 46px;
    height: 46px;

    padding: 0 14px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 14px;

    border:
        1px solid
        rgba(20, 67, 54, .11);

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #f5f9f7
        );

    color: #17374d;

    font-size: 14px;
    font-weight: 800;

    text-decoration: none;

    box-shadow:
        0 7px 16px
        rgba(14, 48, 40, .06);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease,
        background .2s ease,
        color .2s ease;

    position: relative;

    transform-style: preserve-3d;
}


/* 3D HOVER */
a.vina-page:hover {
    color: #008c4b;

    border-color:
        rgba(0, 155, 82, .30);

    transform:
        translateY(-3px)
        perspective(500px)
        rotateX(5deg);

    box-shadow:
        0 14px 25px
        rgba(0, 155, 82, .13);
}


/* ACTIVE */
.vina-page.active {
    color: #fff;

    border-color: transparent;

    background:
        linear-gradient(
            135deg,
            #00a65a,
            #00884a
        );

    box-shadow:
        0 13px 25px
        rgba(0, 155, 82, .25);

    transform: translateY(-2px);
}


/* PREVIOUS / NEXT */
.vina-page-nav {
    min-width: auto;

    padding-left: 18px;
    padding-right: 18px;

    white-space: nowrap;
}


/* DISABLED */
.vina-page.disabled {
    color: #a0adb7;

    background:
        linear-gradient(
            145deg,
            #f5f7f7,
            #f0f3f2
        );

    box-shadow: none;

    cursor: default;
}


/* DOTS */
.vina-page-dots {
    min-width: 32px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    color: #8797a4;

    font-weight: 900;
    letter-spacing: 2px;
}


/* =========================================================
   TABLET
   ========================================================= */

@media (max-width: 1000px) {

    .vina-pagination {
        align-items: flex-start;
    }

    .vina-pagination-nav {
        overflow-x: auto;

        max-width: 100%;

        padding:
            4px 2px
            8px;

        scrollbar-width: thin;
    }

}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 700px) {

    .vina-pagination {
        padding: 16px;

        border-radius: 18px;

        display: block;
    }

    .vina-pagination-info {
        margin-bottom: 14px;

        white-space: normal;

        font-size: 13px;
    }

    .vina-pagination-nav {
        display: flex;

        width: 100%;

        overflow-x: auto;

        padding-bottom: 7px;

        gap: 7px;
    }

    .vina-page {
        flex: 0 0 auto;

        min-width: 42px;
        height: 42px;

        padding: 0 12px;

        border-radius: 12px;

        font-size: 13px;
    }

    .vina-page-nav {
        padding-left: 14px;
        padding-right: 14px;
    }

}


/* Accessibility */
@media (prefers-reduced-motion: reduce) {

    .vina-page {
        transition: none;
    }

    a.vina-page:hover {
        transform: none;
    }

}
</style>