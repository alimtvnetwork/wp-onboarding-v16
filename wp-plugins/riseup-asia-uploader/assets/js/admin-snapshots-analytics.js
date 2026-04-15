/**
 * Snapshot Dashboard — Analytics & Calendar
 *
 * Storage analytics chart, monthly calendar with snapshot dots,
 * and scheduled-date projection.
 * Depends on admin-snapshots-utils.js and admin-snapshots-settings.js.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};
    var SNAP_MODE = C.mode;
    var SNAP_FREQ = C.frequency;
    var SNAP_LABELS = C.i18n;
    var MONTH_NAMES = C.monthNames;

    var calYear, calMonth;
    (function() {
        var now = new Date();
        calYear = now.getFullYear();
        calMonth = now.getMonth();
    })();

    App.buildAnalytics = function(snapshots) {
        if (!snapshots || snapshots.length === 0) {
            $('#analytics_loading').hide();
            $('#analytics_empty').show();
            return;
        }

        var totalSize = 0, largest = 0;
        snapshots.forEach(function(s) {
            var sz = parseInt(s.file_size) || 0;
            totalSize += sz;
            if (sz > largest) largest = sz;
        });
        var avgSize = Math.round(totalSize / snapshots.length);

        $('#stat_total_size').text(App.formatBytes(totalSize));
        $('#stat_total_count').text(snapshots.length);
        $('#stat_avg_size').text(App.formatBytes(avgSize));
        $('#stat_largest').text(App.formatBytes(largest));

        var byDay = {};
        snapshots.forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDay[day]) byDay[day] = { full: 0, incr: 0 };
            var sz = parseInt(s.file_size) || 0;
            var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
            if (isIncr) { byDay[day].incr += sz; } else { byDay[day].full += sz; }
        });

        var days = Object.keys(byDay).sort().slice(-30);
        if (days.length === 0) { $('#analytics_loading').hide(); $('#analytics_empty').show(); return; }

        var maxVal = 0;
        days.forEach(function(d) { var t = byDay[d].full + byDay[d].incr; if (t > maxVal) maxVal = t; });
        if (maxVal === 0) maxVal = 1;

        var yHtml = '';
        for (var i = 4; i >= 0; i--) { yHtml += '<span>' + App.formatBytes(Math.round(maxVal * i / 4)) + '</span>'; }
        $('#chart_y_axis').html(yHtml);

        var barsHtml = '';
        days.forEach(function(d) {
            var fullPct = Math.round((byDay[d].full / maxVal) * 100);
            var incrPct = Math.round((byDay[d].incr / maxVal) * 100);
            var label = d.substring(5);
            barsHtml += '<div class="riseup-bar-group" title="' + d + ': ' + App.formatBytes(byDay[d].full + byDay[d].incr) + '">';
            barsHtml += '<div class="riseup-bar-stack">';
            if (incrPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-incr" style="height:' + incrPct + '%;"></div>';
            if (fullPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-full" style="height:' + fullPct + '%;"></div>';
            barsHtml += '</div>';
            barsHtml += '<span class="riseup-bar-label">' + label + '</span>';
            barsHtml += '</div>';
        });
        $('#chart_bars').html(barsHtml);
        $('#analytics_loading').hide();
        $('#analytics_content').show();
    };

    function getScheduledDates(year, month, frequency) {
        var dates = {};
        if (!frequency || frequency === SNAP_FREQ.manual) return dates;
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        for (var d = 1; d <= daysInMonth; d++) {
            var dt = new Date(year, month, d);
            if (dt < today) continue;
            var shouldInclude = false;
            if (frequency === SNAP_FREQ.daily) shouldInclude = true;
            else if (frequency === SNAP_FREQ.weekly) shouldInclude = (dt.getDay() === 0);
            else if (frequency === SNAP_FREQ.monthly) shouldInclude = (d === 1);
            if (shouldInclude) {
                var key = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                dates[key] = true;
            }
        }
        return dates;
    }

    App.buildCalendar = function(snapshots) {
        $('#cal_month_label').text(MONTH_NAMES[calMonth] + ' ' + calYear);

        var byDate = {};
        (snapshots || []).forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDate[day]) byDate[day] = [];
            byDate[day].push(s);
        });

        var scheduledDates = getScheduledDates(calYear, calMonth, App.cachedSchedule);
        var firstDay = new Date(calYear, calMonth, 1).getDay();
        var daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        var today = new Date();
        var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        var html = '';
        var day = 1;
        for (var row = 0; row < 6; row++) {
            if (day > daysInMonth) break;
            html += '<tr>';
            for (var col = 0; col < 7; col++) {
                if (row === 0 && col < firstDay) { html += '<td class="riseup-cal-empty"></td>'; }
                else if (day > daysInMonth) { html += '<td class="riseup-cal-empty"></td>'; }
                else {
                    var dateStr = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    var isToday = (dateStr === todayStr);
                    var cellClass = isToday ? 'riseup-cal-today' : '';
                    var entries = byDate[dateStr] || [];
                    var isScheduled = !!scheduledDates[dateStr];

                    html += '<td class="riseup-cal-day ' + cellClass + '">';
                    html += '<span class="riseup-cal-num">' + day + '</span>';
                    if (entries.length > 0 || isScheduled) {
                        var hasFull = false, hasIncr = false;
                        entries.forEach(function(e) {
                            if (e.snapshot_type === SNAP_MODE.incremental || e.scope === SNAP_MODE.incremental) hasIncr = true;
                            else hasFull = true;
                        });
                        html += '<div class="riseup-cal-dots">';
                        if (hasFull) html += '<span class="riseup-cal-dot riseup-cal-dot-full" title="' + SNAP_LABELS.fullBackup + '"></span>';
                        if (hasIncr) html += '<span class="riseup-cal-dot riseup-cal-dot-incr" title="' + SNAP_LABELS.incrementalBackup + '"></span>';
                        if (isScheduled) html += '<span class="riseup-cal-dot riseup-cal-dot-scheduled" title="' + SNAP_LABELS.scheduledBackup + '"></span>';
                        html += '</div>';
                        if (entries.length > 0) html += '<span class="riseup-cal-count">' + entries.length + '</span>';
                    }
                    html += '</td>';
                    day++;
                }
            }
            html += '</tr>';
        }
        $('#cal_body').html(html);
    };

    $(document).ready(function() {
        $('#cal_prev').on('click', function() {
            calMonth--;
            if (calMonth < 0) { calMonth = 11; calYear--; }
            App.buildCalendar(App.allSnapshots);
        });
        $('#cal_next').on('click', function() {
            calMonth++;
            if (calMonth > 11) { calMonth = 0; calYear++; }
            App.buildCalendar(App.allSnapshots);
        });
    });

})(jQuery);
