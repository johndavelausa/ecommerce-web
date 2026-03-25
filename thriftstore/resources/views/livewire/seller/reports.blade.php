<div>
    <style>
        .sel-rep-main { background: #F8FAF9; padding: 1.5rem; border-radius: 1.5rem; }
        .sel-rep-title { font-size: 1.5rem; font-weight: 800; color: #0F3D22; margin-bottom: 0.5rem; }
        .sel-rep-sub { font-size: 0.875rem; color: #666; margin-bottom: 2rem; display: flex; align-items: center; gap: 8px; }
        
        .sel-period-btn { padding: 8px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; cursor: pointer; transition: all 0.15s; }
        .sel-period-btn.active { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #F9C74F; border-color: #2D9F4E; }
        .sel-period-btn:hover:not(.active) { border-color: #2D9F4E; background: #F5FBF7; }

        .sel-card { background: #fff; border-radius: 1.25rem; padding: 1.25rem; border: 1px solid #D4E8DA; box-shadow: 0 4px 12px rgba(15, 61, 34, 0.04); height: 100%; transition: transform 0.2s; }
        .sel-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15, 61, 34, 0.08); }
        .sel-card-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        .sel-card-value { font-size: 1.75rem; font-weight: 900; color: #0F3D22; line-height: 1.1; }
        .sel-card-hint { font-size: 0.75rem; color: #1B7A37; font-weight: 600; margin-top: 0.5rem; display: flex; align-items: center; gap: 4px; }

        .sel-chart { background: #fff; border-radius: 1.5rem; padding: 1.5rem; border: 1.5px solid #D4E8DA; box-shadow: 0 4px 12px rgba(15, 61, 34, 0.04); min-height: 320px; }
        .sel-chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .sel-chart-title { font-size: 1rem; font-weight: 800; color: #0F3D22; }
    </style>

    <div class="sel-rep-main">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h1 class="sel-rep-title">Revenue & Sales Report</h1>
                <p class="sel-rep-sub">
                    <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                    Analyze your platform performance and sales growth.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('seller.reports.export', ['period' => $salesPeriod]) }}" class="sel-period-btn" style="border-color: #4A90E2; color: #4A90E2; display: flex; align-items: center; gap: 8px;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Download PDF
                </a>
                <button wire:click="setPeriod('daily')" class="sel-period-btn {{ $salesPeriod === 'daily' ? 'active' : '' }}">Daily</button>
                <button wire:click="setPeriod('weekly')" class="sel-period-btn {{ $salesPeriod === 'weekly' ? 'active' : '' }}">Weekly</button>
                <button wire:click="setPeriod('monthly')" class="sel-period-btn {{ $salesPeriod === 'monthly' ? 'active' : '' }}">Monthly</button>
                <button wire:click="setPeriod('yearly')" class="sel-period-btn {{ $salesPeriod === 'yearly' ? 'active' : '' }}">Yearly</button>
            </div>
        </div>

        {{-- Metrics Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="sel-card">
                <p class="sel-card-label">Total Lifetime Revenue</p>
                <p class="sel-card-value">&#8369;{{ number_format($totalRevenue, 2) }}</p>
                <p class="sel-card-hint">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    Total platform earnings
                </p>
            </div>
            <div class="sel-card">
                <p class="sel-card-label">Revenue ({{ ucfirst($salesPeriod) }})</p>
                <p class="sel-card-value">&#8369;{{ number_format($periodSales, 2) }}</p>
                <p class="sel-card-hint" style="color: #4A90E2;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
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
                <p class="sel-card-value" style="color: #E74C3C;">{{ number_format($cancelledOrdersCount) }}</p>
                <p class="sel-card-hint" style="color: #E74C3C;">
                    ⚠️ Total unsuccessful
                </p>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 sel-chart">
                <div class="sel-chart-header">
                    <p class="sel-chart-title">Monthly Revenue Trend</p>
                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Last 6 Months</div>
                </div>
                <div style="height: 240px;">
                    <canvas id="sellerRevenueChart"></canvas>
                </div>
            </div>

            <div class="sel-chart">
                <div class="sel-chart-header">
                    <p class="sel-chart-title">Best Sellers</p>
                </div>
                <div class="space-y-4">
                    @forelse($topProducts as $p)
                        <div class="p-3 rounded-xl border border-gray-100 hover:bg-gray-50 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-50 text-green-700 flex items-center justify-center font-bold text-xs">{{ $loop->iteration }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-800 truncate">{{ $p->name }}</p>
                                <div class="w-full bg-gray-100 h-1 rounded-full mt-1.5 overflow-hidden">
                                    <div class="bg-green-500 h-full rounded-full" style="width: {{ min(100, ($p->qty / max(1, $topProducts->first()->qty)) * 100) }}%"></div>
                                </div>
                            </div>
                            <div class="text-xs font-black text-green-600">{{ $p->qty }} <span class="text-[9px] font-normal text-gray-400 uppercase">sold</span></div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-400 italic text-sm">No sales data found yet.</div>
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
                            borderColor: '#2D9F4E',
                            backgroundColor: 'rgba(45, 159, 78, 0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 6,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#2D9F4E',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (c) => ' ₱' + c.parsed.y.toLocaleString()
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#9E9E9E' } },
                            y: { 
                                grid: { color: '#F0F7F2' },
                                ticks: { 
                                    font: { size: 10, weight: 'bold' }, 
                                    color: '#BDBDBD',
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
