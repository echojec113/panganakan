{{--
    Shared styling for the compact, collapsible FWA and FHR assessment cards.
    Plain CSS only (scoped under [data-assessment]) so no build step is needed
    and no existing Tailwind utility usage changes.
--}}
<style>
    [data-assessment] {
        margin-top: 0.75rem;
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        overflow: hidden;
    }

    [data-assessment] > summary {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        padding: 0.625rem 0.75rem;
        cursor: pointer;
        list-style: none;
        border-radius: 0.5rem;
        transition: background-color 0.15s ease;
    }

    [data-assessment] > summary::-webkit-details-marker {
        display: none;
    }

    [data-assessment] > summary::marker {
        content: '';
    }

    [data-assessment] > summary:hover {
        background-color: rgba(255, 255, 255, 0.5);
    }

    [data-assessment] > summary:focus-visible {
        outline: 2px solid #55b85a;
        outline-offset: -2px;
    }

    [data-assessment] .fa-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.375rem 0.5rem;
    }

    [data-assessment] .fa-summary-text {
        display: block;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: #374151;
    }

    [data-assessment] .fa-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        align-self: flex-start;
        font-size: 0.75rem;
        font-weight: 600;
        color: #047857;
    }

    [data-assessment] .fa-chevron {
        transition: transform 0.15s ease;
    }

    [data-assessment][open] .fa-chevron {
        transform: rotate(180deg);
    }

    [data-assessment]:not([open]) .fa-label-hide,
    [data-assessment][open] .fa-label-view {
        display: none;
    }

    [data-assessment] .fa-body {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem 0.75rem;
        border-top: 1px solid #d1fae5;
    }

    [data-assessment][open] .fa-body {
        animation: fa-body-in 0.15s ease-out;
    }

    [data-assessment] .fa-rows {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    [data-assessment] .fa-hide-btn {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #047857;
        background-color: #ffffff;
        border: 1px solid #bbf7d0;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    [data-assessment] .fa-hide-btn:hover {
        background-color: #f0fdf4;
    }

    [data-assessment] .fa-hide-btn:focus-visible {
        outline: 2px solid #55b85a;
        outline-offset: 2px;
    }

    @keyframes fa-body-in {
        from {
            opacity: 0;
            transform: translateY(-2px);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        [data-assessment][open] .fa-body {
            animation: none;
        }

        [data-assessment] .fa-chevron,
        [data-assessment] > summary,
        [data-assessment] .fa-hide-btn {
            transition: none;
        }
    }
</style>
