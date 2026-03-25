<div>
    <style>
        .sel-rep-main { background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%); padding: 2rem; border-radius: 2rem; }
        .sel-rep-title { font-size: 1.75rem; font-weight: 900; color: #fff; margin-bottom: 0.5rem; letter-spacing: -0.02em; }
        .sel-rep-sub { font-size: 0.875rem; color: rgba(255,255,255,0.7); margin-bottom: 2.5rem; display: flex; align-items: center; gap: 8px; }
        
        .sel-period-btn { padding: 9px 18px; border-radius: 50px; font-size: 0.8125rem; font-weight: 700; border: 1.5px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(8px); }
        .sel-period-btn.active { background: #F9C74F; color: #0F3D22; border-color: #F9C74F; }
        .sel-period-btn:hover:not(.active) { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.3); }

        .sel-card { background: rgba(255,255,255,0.06); border-radius: 1.5rem; padding: 1.5rem; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(12px); box-shadow: 0 8px 32px rgba(0,0,0,0.1); height: 100%; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .sel-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.09); border-color: rgba(255,255,255,0.2); }
        .sel-card-label { font-size: 0.6875rem; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.75rem; }
        .sel-card-value { font-size: 2rem; font-weight: 950; color: #fff; line-height: 1; letter-spacing: -0.01em; }
        .sel-card-hint { font-size: 0.75rem; color: #F9C74F; font-weight: 600; margin-top: 0.75rem; display: flex; align-items: center; gap: 5px; opacity: 0.9; }

        .sel-chart { background: rgba(255,255,255,0.04); border-radius: 2rem; padding: 1.75rem; border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(16px); min-height: 380px; }
        .sel-chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .sel-chart-title { font-size: 1.125rem; font-weight: 800; color: #fff; }
        
        .sel-product-row { padding: 0.875rem 1rem; border-radius: 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s; }
        .sel-product-row:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.1); }
    </style>

    <div class="sel-rep-main">
        <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6 mb-8">
            <div>
                <h1 class="sel-rep-title">Revenue & Sales Report</h1>
                <p class="sel-rep-sub">
                    <span class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></span>
                    Analyze your platform performance and sales growth.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('seller.reports.export', ['period' => $salesPeriod]) }}" class="sel-period-btn" style="border-color: #F9C74F; color: #F9C74F; display: flex; align-items: center; gap: 8px;">
                    📄 Export Report (PDF)
                </a>
                <div class="flex bg-black/20 p-1 rounded-full backdrop-blur-md">
                    <button wire:click="setPeriod('daily')" class="sel-period-btn !border-none {{ $salesPeriod === 'daily' ? 'active' : '' }}">Daily</button>
                    <button wire:click="setPeriod('weekly')" class="sel-period-btn !border-none {{ $salesPeriod === 'weekly' ? 'active' : '' }}">Weekly</button>
                    <button wire:click="setPeriod('monthly')" class="sel-period-btn !border-none {{ $salesPeriod === 'monthly' ? 'active' : '' }}">Monthly</button>
                    <button wire:click="setPeriod('yearly')" class="sel-period-btn !border-none {{ $salesPeriod === 'yearly' ? 'active' : '' }}">Yearly</button>
                </div>
            </div>
        </div>

        {{-- Metrics Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="sel-card">
                <p class="sel-card-label">Total Lifetime Revenue</p>
                <p class="sel-card-value">₱{{ number_format($totalRevenue, 0) }}</p>
                <p class="sel-card-hint">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    Total platform earnings
                </p>
            </div>
            <div class="sel-card">
                <p class="sel-card-label">Revenue ({{ ucfirst($salesPeriod) }})</p>
                <p class="sel-card-value">₱{{ number_format($periodSales, 0) }}</p>
                <p class="sel-card-hint" style="color: #4A90E2;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Current selection
                </p>
            </div>
            <div class="sel-card">
                <p class="sel-card-label">Completed Orders</p>
                <p class="sel-card-value">{{ number_format($completedOrdersCount) }}</p>
                <p class="sel-card-hint" style="color: #9B59B6;">
                    ✅ Delivered & Received
                </p>
            </div>
            <div class="sel-card">
                <p class="sel-card-label">Cancelled Orders</p>
                <p class="sel-card-value" style="color: #FF7675;">{{ number_format($cancelledOrdersCount) }}</p>
                <p class="sel-card-hint" style="color: #FF7675;">
                    ⚠️ Total unsuccessful
                </p>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 sel-chart">
                <div class="sel-chart-header">
                    <p class="sel-chart-title">Revenue Trend Analysis</p>
                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider bg-white/5 px-2 py-1 rounded-md">Last 6 Months</div>
                </div>
                <div style="height: 280px;">
                    <canvas id="sellerRevenueChart"></canvas>
                </div>
            </div>

            <div class="sel-chart">
                <div class="sel-chart-header">
                    <p class="sel-chart-title">Best Sellers</p>
                </div>
                <div class="space-y-4">
                    @forelse($topProducts as $p)
                        <div class="sel-product-row flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 flex items-center justify-center font-black text-xs">{{ $loop->iteration }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-black text-white/90 truncate">{{ $p->name }}</p>
                                <div class="w-full bg-white/10 h-1 rounded-full mt-2 overflow-hidden">
                                    <div class="bg-green-500 h-full rounded-full shadow-[0_0_8px_rgba(34,197,94,0.6)]" style="width: {{ min(100, ($p->qty / max(1, $topProducts->first()->qty)) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="text-xs font-black text-green-400">{{ $p->qty }} <span class="text-[8px] font-normal text-white/30 uppercase ml-0.5">sold</span></div>
                        </div>
                    @empty
                        <div class="text-center py-20 text-white/30 italic text-sm">No sales data found yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            let chartInstance = null;

            function initChart() {
                const ctx = document.getElementById('sellerRevenueChart');
                if (!ctx) return;
                
                if (chartInstance) chartInstance.destroy();

                const data = @json($monthlyRevenue->map(fn($r) => (float)$r->total)->values());
                const labels = @json($monthlyRevenue->map(fn($r) => \Carbon\Carbon::createFromFormat('Y-m', $r->ym)->format('M Y'))->values());

                chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            borderColor: '#F9C74F',
                            backgroundColor: 'rgba(249, 199, 79, 0.1)',
                            fill: true,
                            tension: 0.45,
                            pointRadius: 6,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#F9C74F',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                callbacks: {
                                    label: (c) => ' ₱' + c.parsed.y.toLocaleString()
                                }
                            }
                        },
                        scales: {
                            x: { 
                                grid: { display: false }, 
                                ticks: { font: { size: 10, weight: 'bold' }, color: 'rgba(255,255,255,0.4)' } 
                            },
                            y: { 
                                grid: { color: 'rgba(255,255,255,0.05)' },
                                ticks: { 
                                    font: { size: 10, weight: 'bold' }, 
                                    color: 'rgba(255,255,255,0.4)',
                                    callback: (v) => '₱' + v.toLocaleString()
                                }
                            }
                        }
                    }
                });
            }

            initChart();
            Livewire.on('refresh-charts', initChart);
        });
    </script>
</div>
