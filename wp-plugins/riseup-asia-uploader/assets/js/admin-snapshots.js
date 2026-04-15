/**
 * Snapshot Dashboard — Main Orchestrator
 *
 * Initializes all modules: loads snapshots, settings, providers,
 * and wires the MutationObserver for analytics/calendar refresh.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
jQuery(document).ready(function($) {
    'use strict';

    var App = window.RiseupSnapshotsApp;

    // Wire MutationObserver to refresh analytics + calendar after list render
    var snapshotObserver = new MutationObserver(function() {
        App.buildAnalytics(App.allSnapshots);
        App.buildCalendar(App.allSnapshots);
    });
    snapshotObserver.observe(document.getElementById('snapshots_tbody'), { childList: true });

    // Initial load
    App.loadSnapshots(1);
    App.loadSettings();
    App.loadProviders();
});
