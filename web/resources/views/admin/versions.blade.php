@extends('layouts.app')

@section('title', 'Quản lý Phiên bản & Pipeline Retrain')

@section('content')
<div class="space-y-8">

    <!-- Version Lifecycle Header -->
    <div class="rounded-3xl p-8 glass-panel border border-cyan-500/40 shadow-2xl">
        <div class="flex items-center gap-3 text-cyan-400 text-xs font-bold uppercase tracking-wider mb-2">
            <i data-lucide="git-branch" class="w-4 h-4"></i> Data Lifecycle & Model Retrain Pipeline
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-white">Quản lý Phiên bản Mô hình AI & Snapshot</h1>
                <p class="text-slate-300 text-sm mt-1">
                    Chốt mốc Cutoff Timestamp để tạo Snapshot từ MongoDB, đưa vào luồng huấn luyện lại trên Spark qua Alluxio và chuyển đổi phiên bản an toàn.
                </p>
            </div>
            <!-- Create Snapshot Button -->
            <form method="POST" action="{{ route('admin.create-snapshot') }}">
                @csrf
                <button type="submit" class="px-5 py-3 rounded-2xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-lg shadow-cyan-500/30 transition flex items-center gap-2 shrink-0">
                    <i data-lucide="camera" class="w-4 h-4"></i> Tạo Snapshot Mới (Chốt Cutoff)
                </button>
            </form>
        </div>
    </div>

    <!-- Active Model Switcher & Status Cards -->
    <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4 shadow-xl">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i data-lucide="cpu" class="w-5 h-5 text-amber-400"></i> Danh sách Phiên bản Mô hình Spark ALS (Model Versions)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($models as $m)
            <div class="p-5 rounded-2xl glass-card relative space-y-4 border {{ $m['isActive'] ? 'border-emerald-500/60 bg-emerald-950/20' : 'border-slate-800' }}">
                @if($m['isActive'])
                <div class="absolute top-4 right-4 px-2.5 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 text-[11px] font-bold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Đang hoạt động (Active)
                </div>
                @endif

                <div>
                    <h3 class="font-extrabold text-white text-base">{{ $m['version'] }}</h3>
                    <div class="text-xs text-slate-400 mt-1">Thời gian huấn luyện: {{ $m['trainDate'] }}</div>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] text-slate-400">Snapshot</div>
                        <div class="font-mono text-slate-200 mt-0.5 text-[11px]">{{ $m['datasetSnapshot'] }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] text-slate-400">Siêu tham số</div>
                        <div class="font-mono text-slate-200 mt-0.5 text-[11px]">r={{ $m['rank'] }}, reg={{ $m['regParam'] }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="text-[10px] text-slate-400">RMSE Test</div>
                        <div class="font-bold text-emerald-400 mt-0.5 text-[11px]">{{ $m['rmse'] }}</div>
                    </div>
                </div>

                @if(!$m['isActive'])
                <form method="POST" action="{{ route('admin.switch-version') }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $m['version'] }}">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Kích hoạt phiên bản này (Chuyển đổi / Rollback)
                    </button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    <!-- Snapshots List & Cutoff Timestamps -->
    <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i data-lucide="archive" class="w-5 h-5 text-cyan-400"></i> Lịch sử Snapshots Dữ liệu Đánh giá từ MongoDB
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider bg-slate-900/50">
                        <th class="p-3">Mã Snapshot</th>
                        <th class="p-3">Mốc Cutoff Timestamp</th>
                        <th class="p-3">Số Rating Mới Tích Lũy</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3">Manifest Checksum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @foreach($snapshots as $snap)
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="p-3 font-mono font-bold text-cyan-400">{{ $snap['id'] }}</td>
                        <td class="p-3 font-mono text-slate-200">{{ $snap['cutoff'] }}</td>
                        <td class="p-3 font-extrabold text-amber-400">+{{ number_format($snap['newRatingsCount']) }} ratings</td>
                        <td class="p-3">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                {{ $snap['status'] }}
                            </span>
                        </td>
                        <td class="p-3 font-mono text-slate-400">{{ $snap['checksum'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
