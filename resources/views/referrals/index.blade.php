<x-app-layout>

<div style="margin-left: var(--sidebar-width); background: var(--bg-base); min-height: 100vh; padding: 28px 30px;">

    {{-- Header --}}
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">Referral Management</h1>
        <p style="font-size: 14px; color: var(--text-muted);">Track and manage patient referrals to hospitals and specialists</p>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div style="margin-bottom: 20px; padding: 14px 16px; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 11px; color: #065f46; font-size: 14px;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
        {{-- Total --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Total Referrals</p>
            <p style="font-size: 28px; font-weight: 700; color: var(--blue-accent);">{{ $total }}</p>
        </div>

        {{-- Pending --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Pending</p>
            <p style="font-size: 28px; font-weight: 700; color: #f59e0b;">{{ $pending }}</p>
        </div>

        {{-- Completed --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Completed</p>
            <p style="font-size: 28px; font-weight: 700; color: #10b981;">{{ $completed }}</p>
        </div>
    </div>

    {{-- Table Card --}}
    <div style="background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(30,70,140,0.06); overflow: hidden;">

        {{-- Search & Filter Bar --}}
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 1fr 140px 120px; gap: 12px; align-items: center;">
            {{-- Search --}}
            <div style="position: relative;">
                <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <form method="GET" action="{{ route('referrals.index') }}" style="display: contents;">
                    <input type="text" name="search" placeholder="Search by patient name..." value="{{ request('search') }}"
                        style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; background: var(--surface);">
                </form>
            </div>

            {{-- Status Filter --}}
            <form method="GET" action="{{ route('referrals.index') }}" style="display: contents;">
                <select name="status" onchange="this.form.submit()"
                    style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; background: white;">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                    <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </form>

            {{-- Clear Filters --}}
            @if(request('search') || request('status'))
                <a href="{{ route('referrals.index') }}"
                    style="padding: 10px 12px; border: 1px solid #ef4444; border-radius: 9px; font-size: 13px; color: #ef4444; text-decoration: none; text-align: center;">
                    Clear Filters
                </a>
            @endif
        </div>

        {{-- Table --}}
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--bg-base); border-bottom: 1px solid var(--border);">
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Patient Name</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Referred To</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Reason</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Date</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Status</th>
                        <th style="padding: 14px 16px; text-align: right; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($referrals as $referral)
                        <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s;">
                            {{-- Patient --}}
                            <td style="padding: 14px 16px; color: var(--text-primary); font-weight: 500;">
                                {{ $referral->patient->first_name }} {{ $referral->patient->last_name }}
                            </td>

                            {{-- Referred To --}}
                            <td style="padding: 14px 16px; color: var(--text-primary);">
                                {{ $referral->referred_to }}
                            </td>

                            {{-- Reason --}}
                            <td style="padding: 14px 16px; color: var(--text-muted); font-size: 12px;">
                                {{ \Str::limit($referral->reason, 50) }}
                            </td>

                            {{-- Date --}}
                            <td style="padding: 14px 16px; color: var(--text-primary);">
                                {{ $referral->referral_date->format('M d, Y') }}
                            </td>

                            {{-- Status --}}
                            <td style="padding: 14px 16px;">
                                @if($referral->status === 'Pending')
                                    <span style="display: inline-block; padding: 6px 12px; background: #fef3c7; color: #92400e; border-radius: 8px; font-size: 11px; font-weight: 600;">Pending</span>
                                @elseif($referral->status === 'Completed')
                                    <span style="display: inline-block; padding: 6px 12px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 11px; font-weight: 600;">Completed</span>
                                @else
                                    <span style="display: inline-block; padding: 6px 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; font-size: 11px; font-weight: 600;">Cancelled</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td style="padding: 14px 16px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    {{-- Print --}}
                                    <a href="{{ route('referrals.print', $referral->id) }}"
                                        style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: var(--blue-accent); color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; transition: background 0.15s;">
                                        Print
                                    </a>

                                    {{-- Complete --}}
                                    @if($referral->status === 'Pending')
                                        <form method="POST" action="{{ route('referrals.complete', $referral->id) }}" style="display: contents;">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Mark this referral as completed?')"
                                                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #10b981; color: white; border-radius: 8px; border: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s;">
                                                Complete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px 16px; text-align: center; color: var(--text-muted);">
                                No referrals found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($referrals->hasPages())
        <div style="padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-base);">
            {{ $referrals->links() }}
        </div>
        @endif

    </div>

</div>

</x-app-layout>
