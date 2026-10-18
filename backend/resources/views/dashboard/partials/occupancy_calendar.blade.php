{{-- Kalender Okupansi & Ketersediaan Kamar --}}
<div class="card mb-4 border-0 shadow-sm" id="occupancyCalendarCard" style="border-radius: 14px; overflow: hidden;">
    {{-- Card Header --}}
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 rounded-3 text-white" style="background: linear-gradient(135deg, #0f766e, #115e59);">
                    <i class="bi bi-calendar3-range fs-5"></i>
                </span>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Kalender Okupansi & Ketersediaan Kamar</h5>
                    <span class="small text-muted">
                        Pantau slot kamar harian: ketersediaan slot, status terisi penuh (full), serta jadwal check-out & check-in penyewa.
                    </span>
                </div>
            </div>
        </div>

        {{-- Month Navigation & Actions --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group shadow-sm" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="calBtnPrev" title="Bulan Sebelumnya">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="calBtnToday" title="Ke Hari Ini">
                    <i class="bi bi-calendar-event me-1"></i> Hari Ini
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="calBtnNext" title="Bulan Berikutnya">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <div class="d-inline-flex align-items-center gap-2">
                <span class="badge fs-6 px-3 py-2 text-white shadow-sm" id="calMonthLabel" style="background-color: #0f766e; font-weight: 600;">
                    <i class="bi bi-calendar-month me-1"></i> {{ $calendarData['month_label'] }}
                </span>
                <input type="month" id="calMonthInput" class="form-control form-control-sm shadow-sm" 
                       value="{{ $calendarData['current_month'] }}" 
                       style="width: 140px; cursor: pointer;" title="Pilih Bulan & Tahun">
            </div>
        </div>
    </div>

    {{-- Monthly KPI Summary Bar --}}
    <div class="bg-light px-4 py-3 border-bottom">
        <div class="row g-2 align-items-center">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Rata-rata Okupansi</span>
                    <strong class="fs-6 text-primary-teal" id="calKpiAverage">{{ $calendarData['kpi']['average_occupancy'] }}%</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Total Kamar Terdaftar</span>
                    <strong class="fs-6 text-dark" id="calKpiTotalRooms">{{ $calendarData['total_rooms'] }} Kamar</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Hari Ada Slot</span>
                    <strong class="fs-6 text-success" id="calKpiDaysAvailable">{{ $calendarData['kpi']['days_available_count'] }} Hari</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Hari Penuh (Full)</span>
                    <strong class="fs-6 text-danger" id="calKpiDaysFull">{{ $calendarData['kpi']['days_full_count'] }} Hari</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Akan Checkout Bulan Ini</span>
                    <strong class="fs-6 text-warning" id="calKpiCheckouts">{{ $calendarData['kpi']['month_checkouts_count'] }} Kamar</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="p-2 bg-white rounded-3 border text-center shadow-2xs">
                    <span class="text-muted d-block small" style="font-size: 11px;">Check-in Bulan Ini</span>
                    <strong class="fs-6 text-info" id="calKpiCheckins">{{ $calendarData['kpi']['month_checkins_count'] }} Kamar</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Legend Bar --}}
    <div class="px-4 py-2 bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 12px;">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted fw-semibold me-1"><i class="bi bi-info-circle me-1"></i>Petunjuk:</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="bi bi-check-circle-fill me-1"></i> Masih Ada Slot
            </span>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                <i class="bi bi-slash-circle-fill me-1"></i> Sudah Full (Penuh)
            </span>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                <i class="bi bi-box-arrow-right me-1"></i> Akan Checkout
            </span>
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                <i class="bi bi-box-arrow-in-right me-1"></i> Jadwal Check-in
            </span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                <i class="bi bi-star-fill me-1"></i> Hari Ini
            </span>
        </div>
        <div class="text-muted fst-italic">
            <i class="bi bi-cursor-fill text-primary-teal me-1"></i>Klik tanggal mana pun untuk melihat daftar & detail kamar
        </div>
    </div>

    {{-- Calendar Grid Container --}}
    <div class="card-body p-3 p-md-4 position-relative" style="background-color: #f8fafc;">
        {{-- Loading Spinner Overlay --}}
        <div id="calLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center" 
             style="background: rgba(255, 255, 255, 0.75); z-index: 20; backdrop-filter: blur(2px);">
            <div class="text-center p-4 bg-white rounded-4 shadow-sm border">
                <div class="spinner-border text-primary-teal mb-2" role="status"></div>
                <div class="fw-semibold text-dark">Memuat Data Okupansi...</div>
            </div>
        </div>

        {{-- Weekday Headers (Senin - Minggu) --}}
        <div class="row g-2 mb-2 text-center text-uppercase fw-bold text-muted" style="font-size: 12px; letter-spacing: 0.5px;">
            <div class="col cal-col-header">Senin</div>
            <div class="col cal-col-header">Selasa</div>
            <div class="col cal-col-header">Rabu</div>
            <div class="col cal-col-header">Kamis</div>
            <div class="col cal-col-header">Jumat</div>
            <div class="col cal-col-header text-primary">Sabtu</div>
            <div class="col cal-col-header text-danger">Minggu</div>
        </div>

        {{-- Calendar Days Grid --}}
        <div class="calendar-grid-container" id="calendarDaysGrid">
            @php
                $leadingBlanks = $calendarData['leading_blank_days'];
                $trailingBlanks = $calendarData['trailing_blank_days'];
                $days = $calendarData['days'];
            @endphp

            <div class="row g-2">
                {{-- Leading Blank Cells --}}
                @for($b = 0; $b < $leadingBlanks; $b++)
                    <div class="col-cal-day">
                        <div class="cal-day-cell cal-day-blank"></div>
                    </div>
                @endfor

                {{-- Actual Month Days --}}
                @foreach($days as $dateStr => $day)
                    @php
                        $isToday = $day['is_today'];
                        $isFull = ($day['status'] === 'full');
                        $hasCheckouts = ($day['checkouts_count'] > 0);
                        $hasCheckins = ($day['checkins_count'] > 0);
                        $avail = $day['available_count'];
                        $total = $day['total_rooms'];
                        $occRate = $day['occupancy_rate'];
                    @endphp

                    <div class="col-cal-day">
                        <div class="cal-day-cell {{ $isToday ? 'cal-day-today' : '' }} {{ $isFull ? 'cal-cell-full' : 'cal-cell-available' }}" 
                             data-date="{{ $dateStr }}"
                             onclick="showDayDetail('{{ $dateStr }}')">
                            
                            {{-- Day Header --}}
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="cal-day-num {{ $isToday ? 'cal-num-today' : '' }}">
                                    {{ $day['day_number'] }}
                                </span>
                                @if($isToday)
                                    <span class="badge bg-primary rounded-pill cal-today-badge">Hari Ini</span>
                                @else
                                    <span class="cal-day-name text-muted d-md-none">{{ substr($day['day_name'], 0, 3) }}</span>
                                @endif
                            </div>

                            {{-- Occupancy Progress Mini Bar --}}
                            <div class="cal-occ-bar-wrap mb-1" title="Okupansi {{ $occRate }}% ({{ $day['occupied_count'] }}/{{ $total }} Kamar)">
                                <div class="progress" style="height: 4px; background-color: #e2e8f0; border-radius: 2px;">
                                    <div class="progress-bar {{ $isFull ? 'bg-danger' : ($occRate > 75 ? 'bg-warning' : 'bg-success') }}" 
                                         role="progressbar" 
                                         style="width: {{ $occRate }}%;" 
                                         aria-valuenow="{{ $occRate }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>

                            {{-- Status Badge: Masih Ada Slot vs Sudah Full --}}
                            <div class="cal-status-badge-wrap mb-1">
                                @if($total === 0)
                                    <span class="cal-pill bg-light text-muted border">0 Kamar</span>
                                @elseif($isFull)
                                    <span class="cal-pill bg-danger text-white">
                                        <i class="bi bi-slash-circle me-1"></i>FULL
                                    </span>
                                @else
                                    <span class="cal-pill bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-door-open me-1"></i>{{ $avail }} Slot
                                    </span>
                                @endif
                            </div>

                            {{-- Event Badges: Akan Checkout & Checkin --}}
                            <div class="cal-events-wrap d-flex flex-column gap-1">
                                @if($hasCheckouts)
                                    <span class="cal-event-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle" 
                                          title="{{ $day['checkouts_count'] }} kamar dijadwalkan checkout">
                                        <i class="bi bi-box-arrow-right"></i> {{ $day['checkouts_count'] }} Checkout
                                    </span>
                                @endif

                                @if($hasCheckins)
                                    <span class="cal-event-pill bg-info-subtle text-info-emphasis border border-info-subtle" 
                                          title="{{ $day['checkins_count'] }} kamar dijadwalkan check-in">
                                        <i class="bi bi-box-arrow-in-right"></i> {{ $day['checkins_count'] }} Check-in
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Trailing Blank Cells --}}
                @for($t = 0; $t < $trailingBlanks; $t++)
                    <div class="col-cal-day">
                        <div class="cal-day-cell cal-day-blank"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</div>

{{-- Modal Detail Okupansi Hari Terpilih --}}
<div class="modal fade" id="dayDetailModal" tabindex="-1" aria-labelledby="dayDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            {{-- Modal Header --}}
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0f766e, #134e4a);">
                <div>
                    <h5 class="modal-title fw-bold" id="dayDetailModalLabel">
                        <i class="bi bi-calendar-check me-2"></i>Detail Okupansi Kamar
                    </h5>
                    <span class="small text-white-50" id="dayDetailSubLabel">-</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4 bg-light">
                {{-- KPI Row inside modal --}}
                <div class="row g-2 mb-3 text-center">
                    <div class="col-3">
                        <div class="p-2 bg-white rounded-3 border">
                            <span class="text-muted d-block small" style="font-size: 11px;">Total Kamar</span>
                            <strong class="fs-6 text-dark" id="modalTotalRooms">0</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-white rounded-3 border">
                            <span class="text-muted d-block small" style="font-size: 11px;">Terisi (Occupied)</span>
                            <strong class="fs-6 text-primary" id="modalOccupiedRooms">0</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-white rounded-3 border">
                            <span class="text-muted d-block small" style="font-size: 11px;">Slot Kosong</span>
                            <strong class="fs-6 text-success" id="modalAvailableRooms">0</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-white rounded-3 border">
                            <span class="text-muted d-block small" style="font-size: 11px;">Akan Checkout</span>
                            <strong class="fs-6 text-warning" id="modalCheckoutsCount">0</strong>
                        </div>
                    </div>
                </div>

                {{-- Status Banner --}}
                <div id="modalStatusBanner" class="alert mb-3 py-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <strong id="modalStatusTitle">Status</strong>
                        <div class="small" id="modalStatusDesc">Keterangan ketersediaan kamar</div>
                    </div>
                    <span id="modalStatusBadge" class="badge">Status</span>
                </div>

                {{-- Rooms List Table --}}
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark small text-uppercase">
                            <i class="bi bi-door-closed me-1"></i> Daftar Status Kamar Hari Ini
                        </span>
                        <span class="badge bg-secondary" id="modalRoomsCountBadge">0 Kamar</span>
                    </div>
                    <div class="table-responsive" style="max-height: 340px;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light text-muted small text-uppercase sticky-top">
                                <tr>
                                    <th>Kamar</th>
                                    <th>Tipe & Lantai</th>
                                    <th>Status Hari Ini</th>
                                    <th>Penghuni / Booking</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="modalRoomsTableBody">
                                {{-- Injected via Javascript --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between">
                <span class="small text-muted" id="modalFooterNote">
                    <i class="bi bi-shield-check text-success me-1"></i>Data realtime tersinkronisasi dengan booking aktif
                </span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- CSS Custom Styling untuk Kalender Okupansi --}}
<style>
    /* Grid 7 Kolom Responsif */
    .col-cal-day {
        flex: 0 0 calc(100% / 7);
        max-width: calc(100% / 7);
    }

    @media (max-width: 768px) {
        .col-cal-day {
            flex: 0 0 calc(100% / 7);
            max-width: calc(100% / 7);
            padding: 2px !important;
        }
        .cal-day-cell {
            min-height: 85px !important;
            padding: 4px !important;
        }
        .cal-day-num {
            font-size: 12px !important;
        }
        .cal-pill {
            font-size: 9px !important;
            padding: 2px 4px !important;
        }
        .cal-event-pill {
            font-size: 8px !important;
            padding: 1px 3px !important;
        }
    }

    /* Day Cell Styling */
    .cal-day-cell {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        min-height: 110px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .cal-day-cell:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px -2px rgba(15, 23, 42, 0.1);
        border-color: #0f766e;
        z-index: 5;
    }

    .cal-day-blank {
        background-color: transparent !important;
        border: 1px dashed #e2e8f0 !important;
        opacity: 0.35;
        cursor: default !important;
        pointer-events: none;
    }

    /* Today Highlight */
    .cal-day-today {
        border: 2px solid #0f766e !important;
        background-color: #f0fdfa !important;
        box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15);
    }

    .cal-day-num {
        font-weight: 700;
        font-size: 14px;
        color: #1e293b;
    }

    .cal-num-today {
        color: #0f766e;
        font-weight: 800;
    }

    .cal-today-badge {
        font-size: 9px;
        padding: 2px 6px;
        font-weight: 600;
        background-color: #0f766e !important;
    }

    /* Badges & Pills */
    .cal-pill {
        display: inline-block;
        width: 100%;
        text-align: center;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-event-pill {
        display: inline-block;
        width: 100%;
        text-align: center;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-cell-full {
        background-color: #fffafb;
    }

    .cal-cell-full:hover {
        border-color: #ef4444;
    }

    .cal-col-header {
        padding: 8px 4px;
        background: #f1f5f9;
        border-radius: 6px;
    }
</style>

{{-- Client-side Script untuk Kalender Interaktif --}}
@push('scripts')
<script>
    // Data lokal kalender bulan saat ini (di-inject dari Blade)
    let currentCalendarData = @json($calendarData);
    const calendarDataApiUrl = "{{ route('dashboard.calendar-data') }}";
    const currentPropertyFilter = "{{ $propertyFilter ?? '' }}";

    document.addEventListener('DOMContentLoaded', function () {
        const btnPrev = document.getElementById('calBtnPrev');
        const btnNext = document.getElementById('calBtnNext');
        const btnToday = document.getElementById('calBtnToday');
        const monthInput = document.getElementById('calMonthInput');

        if (btnPrev) {
            btnPrev.addEventListener('click', function () {
                if (currentCalendarData && currentCalendarData.prev_month) {
                    loadCalendarMonth(currentCalendarData.prev_month);
                }
            });
        }

        if (btnNext) {
            btnNext.addEventListener('click', function () {
                if (currentCalendarData && currentCalendarData.next_month) {
                    loadCalendarMonth(currentCalendarData.next_month);
                }
            });
        }

        if (btnToday) {
            btnToday.addEventListener('click', function () {
                const todayMonth = new Date().toISOString().slice(0, 7);
                loadCalendarMonth(todayMonth);
            });
        }

        if (monthInput) {
            monthInput.addEventListener('change', function () {
                if (this.value) {
                    loadCalendarMonth(this.value);
                }
            });
        }
    });

    /**
     * Memuat data kalender untuk bulan tertentu via AJAX
     */
    function loadCalendarMonth(targetMonth) {
        const overlay = document.getElementById('calLoadingOverlay');
        if (overlay) overlay.classList.remove('d-none');
        if (overlay) overlay.classList.add('d-flex');

        let url = `${calendarDataApiUrl}?month=${encodeURIComponent(targetMonth)}`;
        if (currentPropertyFilter) {
            url += `&property_id=${encodeURIComponent(currentPropertyFilter)}`;
        }

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Gagal mengambil data kalender.');
            return response.json();
        })
        .then(res => {
            if (res.success && res.data) {
                currentCalendarData = res.data;
                renderCalendarUI(res.data);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan saat memuat kalender: ' + err.message);
        })
        .finally(() => {
            if (overlay) overlay.classList.add('d-none');
            if (overlay) overlay.classList.remove('d-flex');
        });
    }

    /**
     * Render ulang seluruh tampilan Kalender dari JSON response
     */
    function renderCalendarUI(data) {
        // 1. Update Month Label & Input
        const monthLabelEl = document.getElementById('calMonthLabel');
        const monthInputEl = document.getElementById('calMonthInput');
        if (monthLabelEl) monthLabelEl.innerHTML = `<i class="bi bi-calendar-month me-1"></i> ${data.month_label}`;
        if (monthInputEl) monthInputEl.value = data.current_month;

        // 2. Update KPI metrics
        const kpiAvg = document.getElementById('calKpiAverage');
        const kpiTotal = document.getElementById('calKpiTotalRooms');
        const kpiAvail = document.getElementById('calKpiDaysAvailable');
        const kpiFull = document.getElementById('calKpiDaysFull');
        const kpiCo = document.getElementById('calKpiCheckouts');
        const kpiCi = document.getElementById('calKpiCheckins');

        if (kpiAvg) kpiAvg.textContent = `${data.kpi.average_occupancy}%`;
        if (kpiTotal) kpiTotal.textContent = `${data.total_rooms} Kamar`;
        if (kpiAvail) kpiAvail.textContent = `${data.kpi.days_available_count} Hari`;
        if (kpiFull) kpiFull.textContent = `${data.kpi.days_full_count} Hari`;
        if (kpiCo) kpiCo.textContent = `${data.kpi.month_checkouts_count} Kamar`;
        if (kpiCi) kpiCi.textContent = `${data.kpi.month_checkins_count} Kamar`;

        // 3. Render Grid Cells
        const gridContainer = document.getElementById('calendarDaysGrid');
        if (!gridContainer) return;

        let html = '<div class="row g-2">';

        // Leading blanks
        for (let b = 0; b < data.leading_blank_days; b++) {
            html += `
                <div class="col-cal-day">
                    <div class="cal-day-cell cal-day-blank"></div>
                </div>`;
        }

        // Days
        for (const [dateStr, day] of Object.entries(data.days)) {
            const isToday = day.is_today;
            const isFull = (day.status === 'full');
            const hasCheckouts = (day.checkouts_count > 0);
            const hasCheckins = (day.checkins_count > 0);
            const avail = day.available_count;
            const total = day.total_rooms;
            const occRate = day.occupancy_rate;

            const progressBarClass = isFull ? 'bg-danger' : (occRate > 75 ? 'bg-warning' : 'bg-success');

            let statusBadgeHtml = '';
            if (total === 0) {
                statusBadgeHtml = `<span class="cal-pill bg-light text-muted border">0 Kamar</span>`;
            } else if (isFull) {
                statusBadgeHtml = `<span class="cal-pill bg-danger text-white"><i class="bi bi-slash-circle me-1"></i>FULL</span>`;
            } else {
                statusBadgeHtml = `<span class="cal-pill bg-success-subtle text-success border border-success-subtle"><i class="bi bi-door-open me-1"></i>${avail} Slot</span>`;
            }

            let eventsHtml = '';
            if (hasCheckouts) {
                eventsHtml += `
                    <span class="cal-event-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="${day.checkouts_count} kamar dijadwalkan checkout">
                        <i class="bi bi-box-arrow-right"></i> ${day.checkouts_count} Checkout
                    </span>`;
            }
            if (hasCheckins) {
                eventsHtml += `
                    <span class="cal-event-pill bg-info-subtle text-info-emphasis border border-info-subtle" title="${day.checkins_count} kamar dijadwalkan check-in">
                        <i class="bi bi-box-arrow-in-right"></i> ${day.checkins_count} Check-in
                    </span>`;
            }

            html += `
                <div class="col-cal-day">
                    <div class="cal-day-cell ${isToday ? 'cal-day-today' : ''} ${isFull ? 'cal-cell-full' : 'cal-cell-available'}" 
                         data-date="${dateStr}"
                         onclick="showDayDetail('${dateStr}')">
                        
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="cal-day-num ${isToday ? 'cal-num-today' : ''}">
                                ${day.day_number}
                            </span>
                            ${isToday ? '<span class="badge bg-primary rounded-pill cal-today-badge">Hari Ini</span>' : `<span class="cal-day-name text-muted d-md-none">${day.day_name.substring(0, 3)}</span>`}
                        </div>

                        <div class="cal-occ-bar-wrap mb-1" title="Okupansi ${occRate}% (${day.occupied_count}/${total} Kamar)">
                            <div class="progress" style="height: 4px; background-color: #e2e8f0; border-radius: 2px;">
                                <div class="progress-bar ${progressBarClass}" role="progressbar" style="width: ${occRate}%;"></div>
                            </div>
                        </div>

                        <div class="cal-status-badge-wrap mb-1">
                            ${statusBadgeHtml}
                        </div>

                        <div class="cal-events-wrap d-flex flex-column gap-1">
                            ${eventsHtml}
                        </div>
                    </div>
                </div>`;
        }

        // Trailing blanks
        for (let t = 0; t < data.trailing_blank_days; t++) {
            html += `
                <div class="col-cal-day">
                    <div class="cal-day-cell cal-day-blank"></div>
                </div>`;
        }

        html += '</div>';
        gridContainer.innerHTML = html;
    }

    /**
     * Buka Modal Detail Okupansi untuk tanggal tertentu
     */
    function showDayDetail(dateStr) {
        if (!currentCalendarData || !currentCalendarData.days || !currentCalendarData.days[dateStr]) {
            console.warn('Data tanggal tidak ditemukan:', dateStr);
            return;
        }

        const dayData = currentCalendarData.days[dateStr];

        // Format tanggal panjang Indonesia
        const parts = dateStr.split('-');
        const y = parts[0], m = parts[1], d = parts[2];
        const dateFormatted = `${dayData.day_name}, ${parseInt(d, 10)} ${currentCalendarData.month_label}`;

        // Header labels
        document.getElementById('dayDetailModalLabel').innerHTML = `<i class="bi bi-calendar-check me-2"></i>Detail Okupansi Kamar`;
        document.getElementById('dayDetailSubLabel').textContent = dateFormatted;

        // KPI Counts
        document.getElementById('modalTotalRooms').textContent = `${dayData.total_rooms} Kamar`;
        document.getElementById('modalOccupiedRooms').textContent = `${dayData.occupied_count} (${dayData.occupancy_rate}%)`;
        document.getElementById('modalAvailableRooms').textContent = `${dayData.available_count} Kamar`;
        document.getElementById('modalCheckoutsCount').textContent = `${dayData.checkouts_count} Kamar`;

        // Banner Status
        const banner = document.getElementById('modalStatusBanner');
        const bTitle = document.getElementById('modalStatusTitle');
        const bDesc = document.getElementById('modalStatusDesc');
        const bBadge = document.getElementById('modalStatusBadge');

        banner.className = 'alert mb-3 py-2 px-3 d-flex align-items-center justify-content-between';

        if (dayData.total_rooms === 0) {
            banner.classList.add('alert-secondary');
            bTitle.textContent = 'Belum Ada Kamar Terdaftar';
            bDesc.textContent = 'Silakan tambahkan unit kamar pada master data properti.';
            bBadge.className = 'badge bg-secondary';
            bBadge.textContent = 'Kosong';
        } else if (dayData.status === 'full') {
            banner.classList.add('alert-danger');
            bTitle.textContent = 'Kamar Terisi Penuh (Sudah Full)';
            bDesc.textContent = `Seluruh ${dayData.total_rooms} kamar telah terisi atau dipesan pada tanggal ini (0 slot tersisa).`;
            bBadge.className = 'badge bg-danger';
            bBadge.textContent = 'Sudah Full';
        } else {
            banner.classList.add('alert-success');
            bTitle.textContent = 'Masih Ada Slot Tersedia';
            bDesc.textContent = `Tersedia ${dayData.available_count} dari total ${dayData.total_rooms} kamar siap huni atau dipesan.`;
            bBadge.className = 'badge bg-success';
            bBadge.textContent = `${dayData.available_count} Slot Tersedia`;
        }

        // Table Rows
        const tbody = document.getElementById('modalRoomsTableBody');
        tbody.innerHTML = '';

        document.getElementById('modalRoomsCountBadge').textContent = `${dayData.rooms.length} Kamar`;

        if (dayData.rooms.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data kamar terdaftar.</td></tr>`;
        } else {
            dayData.rooms.forEach(r => {
                let statusBadge = `<span class="badge ${r.status_badge}">${r.status_label}</span>`;
                
                let guestInfo = '-';
                if (r.is_occupied) {
                    guestInfo = `
                        <div>
                            <strong class="text-dark">${escapeHtml(r.guest_name)}</strong>
                            <div class="text-muted small" style="font-size: 11px;">
                                <i class="bi bi-ticket-perforated me-1"></i>${escapeHtml(r.booking_code || '-')}
                                ${r.guest_phone ? ` • <i class="bi bi-whatsapp text-success me-1"></i>${escapeHtml(r.guest_phone)}` : ''}
                            </div>
                            <div class="text-muted small" style="font-size: 11px;">
                                Periode: ${r.start_date} s/d ${r.end_date}
                            </div>
                        </div>`;
                } else {
                    guestInfo = `<span class="badge bg-light text-success border"><i class="bi bi-check-circle me-1"></i>Siap Dipesan</span>`;
                }

                let actionBtn = '';
                if (r.booking_id) {
                    actionBtn = `
                        <a href="/dashboard/bookings/${r.booking_id}" class="btn btn-xs btn-outline-primary btn-sm py-1 px-2" title="Lihat Detail Booking">
                            <i class="bi bi-eye"></i> Booking
                        </a>`;
                } else {
                    actionBtn = `
                        <span class="text-muted small fst-italic">Tersedia</span>`;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <strong class="text-dark fs-6">${escapeHtml(r.room_number)}</strong>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">${escapeHtml(r.room_type)}</span><br>
                        <span class="text-muted small">Lantai ${r.floor} • ${escapeHtml(r.property_name)}</span>
                    </td>
                    <td>${statusBadge}</td>
                    <td>${guestInfo}</td>
                    <td class="text-end">${actionBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Show modal using Bootstrap 5 API
        const modalEl = document.getElementById('dayDetailModal');
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>
@endpush
