;(function(){
if(window.marco)return;
var __ctx={projectId:"",scriptId:"default-macro-looping",configId:"macro-looping-config.json",urlRuleId:""};
var __reqCounter=0;
var __pending={};
function __genId(){return"marco-sdk-"+(++__reqCounter)+"-"+Date.now();}
function sendMsg(m){return new Promise(function(resolve,reject){var rid=__genId();__pending[rid]={resolve:resolve,reject:reject};m.source="marco-controller";m.requestId=rid;try{window.postMessage(m,"*");}catch(e){delete __pending[rid];reject(e);}setTimeout(function(){if(__pending[rid]){delete __pending[rid];reject(new Error("Marco SDK message timeout"));}},10000);});}
window.addEventListener("message",function(evt){if(evt.source!==window)return;var d=evt.data;if(!d||d.source!=="marco-extension"||d.type!=="RESPONSE")return;var rid=d.requestId;if(!rid||!__pending[rid])return;var p=__pending[rid];delete __pending[rid];var payload=d.payload;if(payload&&payload.isOk===false){p.reject(new Error(payload.errorMessage||"SDK message failed"));}else{p.resolve(payload);}});
function logFn(level){return function(message,metadata){sendMsg({type:"USER_SCRIPT_LOG",payload:{level:level,source:"user-script",category:"USER",action:"log",detail:String(message),metadata:metadata?JSON.stringify(metadata):null,projectId:__ctx.projectId,scriptId:__ctx.scriptId,configId:__ctx.configId,urlRuleId:__ctx.urlRuleId,pageUrl:window.location.href,timestamp:new Date().toISOString()}}).catch(function(){});};}
function nsKey(k){return __ctx.projectId+"::"+k;}
function globalKey(k){return "__global__::"+k;}
window.marco={
log:{info:logFn("INFO"),warn:logFn("WARN"),error:logFn("ERROR"),debug:logFn("DEBUG"),write:function(opts){sendMsg({type:"USER_SCRIPT_LOG",payload:{level:opts.level||"INFO",source:"user-script",category:opts.category||"USER",action:opts.action||"log",detail:String(opts.message),metadata:opts.metadata?JSON.stringify(opts.metadata):null,projectId:__ctx.projectId,scriptId:__ctx.scriptId,configId:__ctx.configId,urlRuleId:__ctx.urlRuleId,pageUrl:window.location.href,timestamp:new Date().toISOString()}}).catch(function(){});}},
store:{
set:function(k,v){return sendMsg({type:"USER_SCRIPT_DATA_SET",key:nsKey(k),value:v,projectId:__ctx.projectId,scriptId:__ctx.scriptId});},
get:function(k){return sendMsg({type:"USER_SCRIPT_DATA_GET",key:nsKey(k)}).then(function(r){return r.value;});},
delete:function(k){return sendMsg({type:"USER_SCRIPT_DATA_DELETE",key:nsKey(k)});},
keys:function(){return sendMsg({type:"USER_SCRIPT_DATA_KEYS",prefix:__ctx.projectId+"::"}).then(function(r){return r.keys;});},
getAll:function(){return sendMsg({type:"USER_SCRIPT_DATA_GET_ALL",prefix:__ctx.projectId+"::"}).then(function(r){return r.entries;});},
clear:function(){return sendMsg({type:"USER_SCRIPT_DATA_CLEAR",prefix:__ctx.projectId+"::"});},
setGlobal:function(k,v){return sendMsg({type:"USER_SCRIPT_DATA_SET",key:globalKey(k),value:v,projectId:"__global__",scriptId:__ctx.scriptId});},
getGlobal:function(k){return sendMsg({type:"USER_SCRIPT_DATA_GET",key:globalKey(k)}).then(function(r){return r.value;});},
deleteGlobal:function(k){return sendMsg({type:"USER_SCRIPT_DATA_DELETE",key:globalKey(k)});},
keysGlobal:function(){return sendMsg({type:"USER_SCRIPT_DATA_KEYS",prefix:"__global__::"}).then(function(r){return r.keys;});}
},
context:Object.freeze({projectId:__ctx.projectId,scriptId:__ctx.scriptId,configId:__ctx.configId,urlRuleId:__ctx.urlRuleId})
};
Object.freeze(window.marco.log);
Object.freeze(window.marco.store);
Object.freeze(window.marco);
})();
;(function() {
    window.__MARCO_CONFIG__ = {
  "comboSwitch": {
    "xpaths": {
      "transferButton": "/html/body/div[2]/div/div/div/div/div/div/div[1]/div/div/div[3]/div[6]/div[2]/button",
      "projectName": "/html/body/div[2]/div/div/div/div/div/div/div[1]/div/div/div[2]/div/div[1]/div/p",
      "combo1": "/html/body/div[6]/div[2]/div[1]/div/p",
      "combo2Button": "/html/body/div[6]/div[2]/div[2]/button",
      "optionsContainer": "/html/body/div[7]/div",
      "confirmButton": "/html/body/div[6]/div[3]/button[2]"
    },
    "fallbacks": {
      "transfer": {
        "textMatch": [
          "Transfer",
          "Transfer project"
        ],
        "tag": "button",
        "ariaLabel": "Transfer",
        "headingSearch": "transfer"
      },
      "combo1": {
        "tag": "p",
        "selector": "div[role=\"dialog\"] p.min-w-0.truncate|div[role=\"dialog\"] p.truncate|div[role=\"dialog\"] p"
      },
      "combo2": {
        "tag": "button",
        "selector": "div[role=\"dialog\"] button[role=\"combobox\"]",
        "role": "combobox"
      },
      "options": {
        "selector": "[role=\"listbox\"]|[data-radix-popper-content-wrapper] > div|[cmdk-list]",
        "role": "listbox"
      },
      "confirm": {
        "textMatch": [
          "Confirm",
          "Confirm transfer",
          "Save"
        ],
        "tag": "button",
        "selector": "div[role=\"dialog\"] button:last-child|div[role=\"alertdialog\"] button:last-child"
      }
    },
    "timing": {
      "pollIntervalMs": 300,
      "openMaxAttempts": 20,
      "waitMaxAttempts": 20,
      "retryCount": 2,
      "retryDelayMs": 1000,
      "confirmDelayMs": 500
    },
    "elementIds": {
      "scriptMarker": "ahk-combo-script",
      "buttonContainer": "ahk-combo-btn-container",
      "buttonUp": "ahk-combo-up-btn",
      "buttonDown": "ahk-combo-down-btn",
      "progressStatus": "__combo_progress_status__"
    },
    "shortcuts": {
      "focusTextboxKey": "/",
      "comboUpKey": "ArrowUp",
      "comboDownKey": "ArrowDown",
      "shortcutModifier": "none"
    }
  },
  "macroLoop": {
    "creditBarWidthPx": 160,
    "timing": {
      "loopIntervalMs": 100000,
      "countdownIntervalMs": 1000,
      "firstCycleDelayMs": 500,
      "postComboDelayMs": 4000,
      "pageLoadDelayMs": 2500,
      "dialogWaitMs": 3000,
      "wsCheckIntervalMs": 5000
    },
    "urls": {
      "requiredDomain": "https://lovable.dev/",
      "settingsPath": "/settings?tab=project",
      "defaultView": "?view=codeEditor"
    },
    "xpaths": {
      "projectButton": "/html/body/div[2]/div/div[2]/nav/div/div/div/div[1]/div[1]/button",
      "mainProgress": "/html/body/div[6]/div/div[2]/div[2]/div/div[2]/div/div[1]",
      "progress": "/html/body/div[6]/div/div[2]/div[2]/div/div[2]/div/div[2]",
      "workspace": "/html/body/div[6]/div/div[2]/div[1]/p",
      "workspaceNav": "",
      "controls": "/html/body/div[3]/div/div[2]/main/div/div/div[3]",
      "promptActive": "/html/body/div[2]/div/div[2]/main/div/div/div[1]/div/div[2]/div/form/div[2]",
      "projectName": "/html/body/div[2]/div/div/div/div/div/div/div[1]/div/div/div[2]/div/div[1]/div/p",
      "freeCreditProgress": "/html/body/div[6]/div/div[2]/div[2]/div/div[2]/div/div[2]"
    },
    "elementIds": {
      "scriptMarker": "ahk-loop-script",
      "container": "ahk-loop-container",
      "status": "ahk-loop-status",
      "startBtn": "ahk-loop-start-btn",
      "stopBtn": "ahk-loop-stop-btn",
      "upBtn": "ahk-loop-up-btn",
      "downBtn": "ahk-loop-down-btn",
      "recordIndicator": "ahk-loop-record",
      "jsExecutor": "ahk-loop-js-executor",
      "jsExecuteBtn": "ahk-loop-js-execute-btn"
    },
    "shortcuts": {
      "focusTextboxKey": "/",
      "startKey": "s",
      "stopKey": "x",
      "shortcutModifier": "none"
    }
  },
  "creditStatus": {
    "api": {
      "baseUrl": "https://api.lovable.dev",
      "authMode": "cookieSession"
    },
    "timing": {
      "autoCheckEnabled": true,
      "autoCheckIntervalSeconds": 60,
      "cacheTtlSeconds": 30
    },
    "retry": {
      "maxRetries": 2,
      "retryBackoffMs": 1000
    },
    "xpaths": {
      "plansButton": "/html/body/div[3]/div/div/aside/nav/div[2]/div[2]/button[3]",
      "freeProgressBar": "/html/body/div[3]/div/div/div/div/div/div/div[10]/div/div/div[2]/div/div[2]/div/div[2]/div/div[2]/div/div[4]",
      "totalCredits": "/html/body/div[3]/div/div/div/div/div/div/div[10]/div/div/div[2]/div/div[2]/div/div[1]/p[2]"
    }
  },
  "general": {
    "browserExe": "chrome.exe",
    "debug": true,
    "configWatchIntervalMs": 2000
  }
};
try {
        // ============================================
// MacroLoop Controller — Standalone Version
// Reads config from window.__MARCO_CONFIG__ (injected by Chrome Extension)
// Based on: marco-script-ahk-v7.latest/macro-looping.js
// ============================================

(function() {
  'use strict';

  const FILE_NAME = 'macro-looping.js';
  const VERSION = '7.33';

  // ============================================
  // Config: Read from window.__MARCO_CONFIG__ or use defaults
  // ============================================
  const cfg = window.__MARCO_CONFIG__ || {};
  const loopCfg = cfg.macroLoop || {};
  const loopIds = loopCfg.elementIds || {};
  const loopTiming = loopCfg.timing || {};
  const loopXPaths = loopCfg.xpaths || {};
  const loopUrls = loopCfg.urls || {};
  const creditBarWidthPx = loopCfg.creditBarWidthPx || 160;

  // ============================================
  // Theme: Read from window.__MARCO_THEME__ or use defaults
  // Editable via Chrome Extension config CRUD (Options page)
  // ============================================
  const theme = window.__MARCO_THEME__ || {};
  const TC = theme.colors || {};
  const TP = TC.panel || {};
  const TPri = TC.primary || {};
  const TAcc = TC.accent || {};
  const TSt = TC.status || {};
  const TN = TC.neutral || {};
  const TCb = TC.creditBar || {};
  const TWs = TC.workspace || {};
  const TLog = TC.log || {};
  const TCd = TC.countdownBar || {};
  const TAnim = theme.animations || {};
  const TTrans = theme.transitions || {};
  const TLayout = theme.layout || {};
  const TTypo = theme.typography || {};

  // Panel colors
  const cPanelBg      = TP.background     || '#171b25';
  const cPanelBorder  = TP.border         || '#252a36';
  const cPanelFg      = TP.foreground     || '#e7e9ed';
  const cPanelFgMuted = TP.foregroundMuted || '#c9a8ef';
  const cPanelFgDim   = TP.foregroundDim  || '#94a3b8';
  const cPanelText    = TP.textBody       || '#e2e8f0';

  // Primary colors
  const cPrimary        = TPri.base          || '#7c3aed';
  const cPrimaryLight   = TPri.light         || '#a78bfa';
  const cPrimaryLighter = TPri.lighter       || '#ae7ce8';
  const cPrimaryLightest= TPri.lightest      || '#c9a8ef';
  const cPrimaryDark    = TPri.dark          || '#4c1d95';
  const cPrimaryGlow    = TPri.glow          || 'rgba(139,92,246,0.2)';
  const cPrimaryGlowS   = TPri.glowStrong   || 'rgba(139,92,246,0.35)';
  const cPrimaryGlowSub = TPri.glowSubtle   || 'rgba(139,92,246,0.15)';
  const cPrimaryBorderA = TPri.borderAlpha   || 'rgba(124,58,237,0.4)';
  const cPrimaryBgA     = TPri.bgAlpha       || 'rgba(124,58,237,0.2)';
  const cPrimaryBgAL    = TPri.bgAlphaLight  || 'rgba(167,139,250,0.3)';
  const cPrimaryBgAS    = TPri.bgAlphaSubtle || 'rgba(167,139,250,0.15)';
  const cPrimaryHL      = TPri.highlight     || 'rgba(139,92,246,0.15)';

  // Accent colors
  const cAccPurple      = TAcc.purple      || '#8b5cf6';
  const cAccPurpleLight = TAcc.purpleLight  || '#c4b5fd';
  const cAccPink        = TAcc.pink        || '#ec4899';

  // Status colors
  const cSuccess       = TSt.success       || '#10b981';
  const cSuccessLight  = TSt.successLight  || '#6ee7b7';
  const cSuccessMuted  = TSt.successMuted  || '#34d399';
  const cWarning       = TSt.warning       || '#f59e0b';
  const cWarningLight  = TSt.warningLight  || '#fbbf24';
  const cWarningPale   = TSt.warningPale   || '#fde68a';
  const cError         = TSt.error         || '#ef4444';
  const cErrorLight    = TSt.errorLight    || '#f87171';
  const cInfo          = TSt.info          || '#3b82f6';
  const cInfoLight     = TSt.infoLight     || '#60a5fa';

  // Neutral colors
  const cNeutral400 = TN['400'] || '#94a3b8';
  const cNeutral500 = TN['500'] || '#64748b';
  const cNeutral600 = TN['600'] || '#475569';
  const cNeutral700 = TN['700'] || '#6b7280';
  const cNeutral950 = TN['950'] || '#0e111a';

  // Credit bar gradients
  const cCbBonus    = TCb.bonus    || ['#7c3aed', '#a78bfa'];
  const cCbBilling  = TCb.billing  || ['#22c55e', '#4ade80'];
  const cCbRollover = TCb.rollover || ['#6b7280', '#9ca3af'];
  const cCbDaily    = TCb.daily    || ['#d97706', '#facc15'];
  const cCbAvail    = TCb.available || '#22d3ee';
  const cCbEmpty    = TCb.emptyTrack || 'rgba(239,68,68,0.25)';

  // Log level colors
  const cLogDefault   = TLog['default']  || '#a78bfa';
  const cLogError     = TLog.error       || '#ef4444';
  const cLogInfo      = TLog.info        || '#9ca3af';
  const cLogSuccess   = TLog.success     || '#6ee7b7';
  const cLogDebug     = TLog.debug       || '#c4b5fd';
  const cLogWarn      = TLog.warn        || '#fbbf24';
  const cLogDelegate  = TLog.delegate    || '#60a5fa';
  const cLogCheck     = TLog.check       || '#c4b5fd';
  const cLogSkip      = TLog.skip        || '#9ca3af';
  const cLogTimestamp  = TLog.timestamp   || '#6b7280';

  // Extra colors
  const cOrange     = TC.orange     || '#f97316';
  const cCyan       = TC.cyan       || '#22d3ee';
  const cCyanLight  = TC.cyanLight  || '#67e8f9';
  const cSkyLight   = TC.skyLight   || '#38bdf8';
  const cGreenBright= TC.greenBright|| '#86efac';

  // Layout tokens
  const lPanelRadius  = TLayout.panelBorderRadius  || '12px';
  const lPanelPadding = TLayout.panelPadding       || '12px';
  const lPanelMinW    = TLayout.panelMinWidth       || '420px';
  const lPanelFloatW  = TLayout.panelFloatingWidth  || '480px';
  const lPanelShadow  = TLayout.panelShadow         || '0 4px 16px rgba(0,0,0,0.3),0 0 1px rgba(139,92,246,0.2)';
  const lPanelFloatSh = TLayout.panelFloatShadow    || '0 8px 32px rgba(0,0,0,0.4)';
  const lDropdownRadius= TLayout.dropdownBorderRadius || '4px';
  const lDropdownShadow= TLayout.dropdownShadow      || '0 8px 24px rgba(0,0,0,0.6)';
  const lModalRadius  = TLayout.modalBorderRadius    || '16px';
  const lModalShadow  = TLayout.modalShadow          || '0 25px 60px rgba(0,0,0,0.5),0 0 40px rgba(167,139,250,0.15)';
  const lAboutGradient= TLayout.aboutGradient         || 'linear-gradient(135deg,#171b25 0%,#0e111a 100%)';

  // Typography tokens
  const tFont       = TTypo.fontFamily       || 'monospace';
  const tFontSystem = TTypo.fontFamilySystem || '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif';
  const tFontSize   = TTypo.fontSize         || '12px';
  const tFontSm     = TTypo.fontSizeSmall    || '11px';
  const tFontTiny   = TTypo.fontSizeTiny     || '10px';
  const tFontMicro  = TTypo.fontSizeMicro    || '9px';

  // Transition tokens
  const trFast   = TTrans.fast   || '0.15s';
  const trNormal = TTrans.normal || '0.2s';
  const trSlow   = TTrans.slow   || '0.3s';

  // Auto-Attach config
  const autoAttachCfg = cfg.autoAttach || {};
  const autoAttachTiming = autoAttachCfg.timing || {};
  const autoAttachGroups = autoAttachCfg.groups || [];

  // === Domain Guard: Prevent injection into DevTools or non-page contexts ===
  const currentHostname = window.location.hostname || '(empty)';
  const currentHref = window.location.href || '(empty)';
  const isPageContext = (
    currentHostname.indexOf('lovable.dev') !== -1 ||
    currentHostname.indexOf('lovable.app') !== -1 ||
    currentHostname === 'localhost'
  );
  if (!isPageContext && !window.__comboForceInject) {
    console.warn(
      '[MacroLoop] DOMAIN GUARD ABORT\n' +
      '  hostname: ' + currentHostname + '\n' +
      '  href: ' + currentHref + '\n' +
      '  expected: lovable.dev | lovable.app | localhost\n' +
      '  cause: Script executed in DevTools context instead of page context.\n' +
      '  bypass: Set window.__comboForceInject = true before pasting.\n' +
      '  UI will NOT be injected here.'
    );
    return;
  }

  // ============================================
  // IDs from config JSON (with defaults matching AHK values)
  // ============================================
  const IDS = {
    SCRIPT_MARKER: loopIds.scriptMarker || 'ahk-loop-script',
    CONTAINER: loopIds.container || 'ahk-loop-container',
    STATUS: loopIds.status || 'ahk-loop-status',
    START_BTN: loopIds.startBtn || 'ahk-loop-start-btn',
    STOP_BTN: loopIds.stopBtn || 'ahk-loop-stop-btn',
    UP_BTN: loopIds.upBtn || 'ahk-loop-up-btn',
    DOWN_BTN: loopIds.downBtn || 'ahk-loop-down-btn',
    RECORD_INDICATOR: loopIds.recordIndicator || 'ahk-loop-record',
    JS_EXECUTOR: loopIds.jsExecutor || 'ahk-loop-js-executor',
    JS_EXECUTE_BTN: loopIds.jsExecuteBtn || 'ahk-loop-js-execute-btn'
  };

  // ============================================
  // Timing from config JSON (with defaults)
  // ============================================
  const TIMING = {
    LOOP_INTERVAL: loopTiming.loopIntervalMs || 100000,
    COUNTDOWN_INTERVAL: loopTiming.countdownIntervalMs || 1000,
    FIRST_CYCLE_DELAY: loopTiming.firstCycleDelayMs || 500,
    POST_COMBO_DELAY: loopTiming.postComboDelayMs || 4000,
    PAGE_LOAD_DELAY: loopTiming.pageLoadDelayMs || 2500,
    DIALOG_WAIT: loopTiming.dialogWaitMs || 3000,
    WS_CHECK_INTERVAL: loopTiming.workspaceCheckIntervalMs || 5000
  };

  // ============================================
  // XPaths and URLs from config JSON (can be changed on the fly)
  // ============================================
  const CONFIG = {
    PROJECT_BUTTON_XPATH: loopXPaths.projectButton || '/html/body/div[2]/div/div[2]/nav/div/div/div/div[1]/div[1]/button',
    MAIN_PROGRESS_XPATH: loopXPaths.mainProgress || '/html/body/div[6]/div/div[2]/div[2]/div/div[2]/div/div[1]',
    PROGRESS_XPATH: loopXPaths.progress || '/html/body/div[6]/div/div[2]/div[2]/div/div[2]/div/div[2]',
    WORKSPACE_XPATH: loopXPaths.workspace || '/html/body/div[6]/div/div[2]/div[1]/p',
    WORKSPACE_NAV_XPATH: loopXPaths.workspaceNav || '',
    CONTROLS_XPATH: loopXPaths.controls || '/html/body/div[3]/div/div[2]/main/div/div/div[3]',
    PROMPT_ACTIVE_XPATH: loopXPaths.promptActive || '/html/body/div[2]/div/div[2]/main/div/div/div[1]/div/div[2]/div/form/div[2]',
    PROJECT_NAME_XPATH: loopXPaths.projectName || '/html/body/div[2]/div/div/div/div/div/div/div[1]/div/div/div[2]/div/div[1]/div/p',
    REQUIRED_DOMAIN: loopUrls.requiredDomain || 'https://lovable.dev/',
    SETTINGS_PATH: loopUrls.settingsPath || '/settings?tab=project',
    DEFAULT_VIEW: loopUrls.defaultView || '?view=codeEditor'
  };

  // ============================================
  // INIT: Idempotent — skip if already embedded
  // Flow: AHK checks marker first, injects macro-looping.js only if absent,
  //       then calls __loopStart(direction) separately.
  // ============================================
  // v7.25: Clear destroyed flag on fresh injection
  window.__loopDestroyed = false;

  const existingMarker = document.getElementById(IDS.SCRIPT_MARKER);
  if (existingMarker) {
    const existingVersion = existingMarker.getAttribute('data-version') || '';
    const isVersionMismatch = existingVersion !== VERSION;

    if (isVersionMismatch) {
      // v7.26: Version differs — force teardown and re-inject
      console.warn('[MacroLoop v' + VERSION + '] VERSION MISMATCH: existing=' + existingVersion + ' new=' + VERSION + ' — forcing re-injection');
      // Teardown: stop loops, remove UI, clear globals
      if (typeof window.__loopStop === 'function') {
        try { window.__loopStop(); } catch(e) {}
      }
      existingMarker.remove();
      const staleContainer = document.getElementById(IDS.CONTAINER);
      if (staleContainer) staleContainer.remove();
      // Clear global functions
      const globalsToClear = ['__loopStart','__loopStop','__loopCheck','__loopState','__loopSetInterval',
        '__loopToast','__delegateComplete','__setProjectButtonXPath','__setProgressXPath','__loopDiag',
        '__loopUpdateStartStopBtn','__loopUpdateAuthDiag'];
      for (let gi = 0; gi < globalsToClear.length; gi++) {
        try { delete window[globalsToClear[gi]]; } catch(e) {}
      }
    } else if (typeof window.__loopStart === 'function') {
      // Same version, globals intact — skip
      console.log('%c[MacroLoop v' + VERSION + '] Already embedded (marker=' + IDS.SCRIPT_MARKER + ') — skipping injection, UI and state intact', 'color: #10b981; font-weight: bold;');
      return; // Exit IIFE — no teardown, no re-creation
    } else {
      // Marker exists but globals missing — previous injection crashed. Remove stale marker and re-init.
      console.warn('[MacroLoop v' + VERSION + '] Stale marker found (globals missing) — removing marker and re-initializing');
      existingMarker.remove();
      const staleContainer2 = document.getElementById(IDS.CONTAINER);
      if (staleContainer2) staleContainer2.remove();
    }
  }

  // ============================================
  // Utility: Log with version prefix
  // ============================================
  let activityLogVisible = false;
  const activityLogLines = [];
  const maxActivityLines = 100;

  // ============================================
  // localStorage logging system
  // ============================================
  const LOG_STORAGE_KEY = 'ahk_macroloop_logs';
  const WS_HISTORY_KEY = 'ml_workspace_history';
  const WS_SHARED_KEY = 'ml_known_workspaces';
  const LOG_MAX_ENTRIES = 500;
  const WS_HISTORY_MAX_ENTRIES = 50;

  // ============================================
  // Quota-safe localStorage wrapper
  // On QuotaExceededError: find and purge bloated keys (e.g. console-history), then retry once.
  // ============================================
  const BLOATED_KEY_PATTERNS = ['console-history', 'previously-viewed-files', 'ai-code-completion'];

  function safeSetItem(key, value) {
    try {
      localStorage.setItem(key, value);
      return true;
    } catch (e) {
      const isQuotaError = (
        e instanceof DOMException &&
        (e.code === 22 || e.code === 1014 || e.name === 'QuotaExceededError' || e.name === 'NS_ERROR_DOM_QUOTA_REACHED')
      );
      if (!isQuotaError) return false;

      console.warn('[MacroLoop] localStorage quota exceeded — scanning for bloated keys to purge');
      let purged = 0;
      for (let i = localStorage.length - 1; i >= 0; i--) {
        let k = localStorage.key(i);
        if (!k) continue;
        for (let p = 0; p < BLOATED_KEY_PATTERNS.length; p++) {
          if (k.indexOf(BLOATED_KEY_PATTERNS[p]) !== -1) {
            let size = (localStorage.getItem(k) || '').length;
            console.warn('[MacroLoop] Purging bloated key: "' + k + '" (size=' + size + ')');
            localStorage.removeItem(k);
            purged++;
            break;
          }
        }
      }

      if (purged > 0) {
        try {
          localStorage.setItem(key, value);
          console.log('[MacroLoop] Retry succeeded after purging ' + purged + ' bloated key(s)');
          return true;
        } catch (e2) {
          console.error('[MacroLoop] Retry failed even after purging — clearing all localStorage');
          localStorage.clear();
          try { localStorage.setItem(key, value); return true; } catch (e3) { return false; }
        }
      } else {
        console.error('[MacroLoop] Quota exceeded but no bloated keys found — clearing all localStorage');
        localStorage.clear();
        try { localStorage.setItem(key, value); return true; } catch (e4) { return false; }
      }
    }
  }

  // v7.9.39: Extract project ID from URL for project-scoped storage keys
  function getProjectIdFromUrl() {
    const url = window.location.href;
    const match = url.match(/\/projects\/([a-f0-9-]+)/);
    return match ? match[1] : null;
  }

  function getWsHistoryKey() {
    const projectId = getProjectIdFromUrl();
    return projectId ? WS_HISTORY_KEY + '_' + projectId : WS_HISTORY_KEY;
  }

  // v7.9.39: Get project name from DOM via ProjectNameXPath
  function getProjectNameFromDom() {
    const xp = CONFIG.PROJECT_NAME_XPATH;
    if (!xp || xp.charAt(0) === '_') return null;
    try {
      let el = document.evaluate(xp, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
      if (el) {
        let text = (el.textContent || '').trim();
        if (text) return text;
      }
    } catch (e) { /* XPath error */ }
    return null;
  }

  // v7.9.39: Display project name (DOM XPath > document title > URL ID)
  function getDisplayProjectName() {
    const domName = getProjectNameFromDom();
    if (domName) return domName;
    const titleMatch = (document.title || '').match(/^(.+?)\s*[-–—]\s*Lovable/);
    if (titleMatch) return titleMatch[1].trim();
    const pid = getProjectIdFromUrl();
    return pid ? pid.substring(0, 8) : 'Unknown Project';
  }

  function getLogStorageKey() {
    const url = window.location.href;
    const projectMatch = url.match(/\/projects\/([a-f0-9-]+)/);
    const projectId = projectMatch ? projectMatch[1].substring(0, 8) : 'unknown';
    return LOG_STORAGE_KEY + '_' + projectId;
  }

  function persistLog(level, message) {
    try {
      let key = getLogStorageKey();
      let logs = JSON.parse(localStorage.getItem(key) || '[]');
      const now = new Date();
      const timestamp = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
      logs.push({
        t: timestamp,
        l: level,
        m: message,
        url: window.location.pathname
      });
      if (logs.length > LOG_MAX_ENTRIES) {
        logs = logs.slice(logs.length - LOG_MAX_ENTRIES);
      }
      safeSetItem(key, JSON.stringify(logs));
    } catch (e) { /* storage full or unavailable */ }
  }

  function getAllLogs() {
    try {
      let key = getLogStorageKey();
      return JSON.parse(localStorage.getItem(key) || '[]');
    } catch (e) { return []; }
  }

  function clearAllLogs() {
    try {
      let key = getLogStorageKey();
      localStorage.removeItem(key);
    } catch (e) { /* ignore */ }
  }

  function formatLogsForExport() {
    let logs = getAllLogs();
    const lines = [];
    lines.push('=== MacroLoop Logs ===');
    lines.push('Project URL: ' + window.location.href);
    lines.push('Exported at: ' + new Date().toISOString());
    lines.push('Total entries: ' + logs.length);
    lines.push('---');
    for (let i = 0; i < logs.length; i++) {
      const e = logs[i];
      lines.push('[' + e.t + '] [' + e.l + '] ' + e.m);
    }
    return lines.join('\n');
  }

  function copyLogsToClipboard() {
    let text = formatLogsForExport();
    navigator.clipboard.writeText(text).then(function() {
      log('Copied ' + getAllLogs().length + ' log entries to clipboard', 'success');
    }).catch(function(err) {
      log('Clipboard copy failed: ' + err.message, 'warn');
    });
  }

  function downloadLogs() {
    let text = formatLogsForExport();
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'macroloop-logs-' + new Date().toISOString().replace(/[:.]/g, '-') + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    log('Downloaded logs file', 'success');
  }

  // ============================================
  // CSV Export: Workspace names + credits (ascending sort by name)
  // ============================================
  function exportWorkspacesAsCsv() {
    const workspaces = loopCreditState.perWorkspace;
    if (!workspaces || workspaces.length === 0) {
      log('CSV Export: No workspace data — fetch credits first (💳)', 'warn');
      return;
    }

    // Sort ascending by fullName (case-insensitive)
    const sorted = workspaces.slice().sort(function(a, b) {
      return (a.fullName || '').toLowerCase().localeCompare((b.fullName || '').toLowerCase());
    });

    function csvVal(v) {
      if (v === null || v === undefined) return '';
      const s = String(v);
      if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
        return '"' + s.replace(/"/g, '""') + '"';
      }
      return s;
    }

    const lines = [];
    lines.push([
      'Workspace Name', 'Workspace ID', 'Email', 'Role',
      'Plan', 'Plan Type', 'Subscription Status', 'Subscription Currency', 'Payment Provider',
      'Daily Free', 'Daily Limit', 'Daily Used', 'Daily Used In Billing',
      'Rollover', 'Rollover Limit', 'Rollover Used',
      'Billing Available', 'Billing Limit', 'Billing Used',
      'Granted', 'Granted Remaining', 'Topup Limit', 'Topup Used',
      'Total Credits', 'Total Credits Used', 'Total Used In Billing', 'Available Credits',
      'Backend Used In Billing',
      'Num Projects', 'Referral Count', 'Followers Count',
      'Billing Period Start', 'Billing Period End', 'Next Credit Grant Date',
      'Created At', 'Updated At',
      'Owner ID', 'MCP Enabled'
    ].join(','));

    for (let i = 0; i < sorted.length; i++) {
      const ws = sorted[i];
      const r = ws.raw || {};
      const m = r.membership || {};
      const row = [
        csvVal(ws.fullName),
        csvVal(ws.id),
        csvVal(m.email || ''),
        csvVal(m.role || ws.role || ''),
        csvVal(r.plan || ''),
        csvVal(r.plan_type || ''),
        csvVal(ws.subscriptionStatus || r.subscription_status || ''),
        csvVal(r.subscription_currency || ''),
        csvVal(r.payment_provider || ''),
        ws.dailyFree,
        ws.dailyLimit,
        ws.dailyUsed,
        r.daily_credits_used_in_billing_period != null ? r.daily_credits_used_in_billing_period : '',
        ws.rollover,
        ws.rolloverLimit,
        ws.rolloverUsed,
        ws.billingAvailable,
        ws.limit,
        ws.used,
        ws.freeGranted,
        ws.freeRemaining,
        ws.topupLimit,
        r.topup_credits_used != null ? r.topup_credits_used : '',
        ws.totalCredits,
        ws.totalCreditsUsed != null ? ws.totalCreditsUsed : (r.total_credits_used != null ? r.total_credits_used : ''),
        r.total_credits_used_in_billing_period != null ? r.total_credits_used_in_billing_period : '',
        ws.available,
        r.backend_total_used_in_billing_period != null ? r.backend_total_used_in_billing_period : '',
        r.num_projects != null ? r.num_projects : '',
        r.referral_count != null ? r.referral_count : '',
        r.followers_count != null ? r.followers_count : '',
        csvVal(r.billing_period_start_date || ''),
        csvVal(r.billing_period_end_date || ''),
        csvVal(r.next_monthly_credit_grant_date || ''),
        csvVal(r.created_at || ''),
        csvVal(r.updated_at || ''),
        csvVal(r.owner_id || ''),
        r.mcp_enabled != null ? r.mcp_enabled : ''
      ];
      lines.push(row.join(','));
    }

    const csvText = lines.join('\n');
    const blob = new Blob([csvText], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'workspaces-' + new Date().toISOString().replace(/[:.]/g, '-') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    log('CSV Export: Downloaded ' + sorted.length + ' workspaces (sorted A→Z)', 'success');
  }

  // CSV Export: Only workspaces with available credits > 0
  // ============================================
  function exportAvailableWorkspacesAsCsv() {
    const workspaces = loopCreditState.perWorkspace;
    if (!workspaces || workspaces.length === 0) {
      log('CSV Export (available): No workspace data — fetch credits first (💳)', 'warn');
      return;
    }

    const filtered = workspaces.filter(function(ws) {
      return (ws.available || 0) > 0;
    });

    if (filtered.length === 0) {
      log('CSV Export (available): No workspaces with available credits > 0', 'warn');
      return;
    }

    const sorted = filtered.slice().sort(function(a, b) {
      return (a.fullName || '').toLowerCase().localeCompare((b.fullName || '').toLowerCase());
    });

    function csvVal(v) {
      if (v === null || v === undefined) return '';
      var s = String(v);
      if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
        return '"' + s.replace(/"/g, '""') + '"';
      }
      return s;
    }

    var lines = [];
    lines.push([
      'Workspace Name', 'Workspace ID', 'Email', 'Role',
      'Plan', 'Plan Type', 'Subscription Status', 'Subscription Currency', 'Payment Provider',
      'Daily Free', 'Daily Limit', 'Daily Used', 'Daily Used In Billing',
      'Rollover', 'Rollover Limit', 'Rollover Used',
      'Billing Available', 'Billing Limit', 'Billing Used',
      'Granted', 'Granted Remaining', 'Topup Limit', 'Topup Used',
      'Total Credits', 'Total Credits Used', 'Total Used In Billing', 'Available Credits',
      'Backend Used In Billing',
      'Num Projects', 'Referral Count', 'Followers Count',
      'Billing Period Start', 'Billing Period End', 'Next Credit Grant Date',
      'Created At', 'Updated At',
      'Owner ID', 'MCP Enabled'
    ].join(','));

    for (var i = 0; i < sorted.length; i++) {
      var ws = sorted[i];
      var r = ws.raw || {};
      var m = r.membership || {};
      var row = [
        csvVal(ws.fullName),
        csvVal(ws.id),
        csvVal(m.email || ''),
        csvVal(m.role || ws.role || ''),
        csvVal(r.plan || ''),
        csvVal(r.plan_type || ''),
        csvVal(ws.subscriptionStatus || r.subscription_status || ''),
        csvVal(r.subscription_currency || ''),
        csvVal(r.payment_provider || ''),
        ws.dailyFree,
        ws.dailyLimit,
        ws.dailyUsed,
        r.daily_credits_used_in_billing_period != null ? r.daily_credits_used_in_billing_period : '',
        ws.rollover,
        ws.rolloverLimit,
        ws.rolloverUsed,
        ws.billingAvailable,
        ws.limit,
        ws.used,
        ws.freeGranted,
        ws.freeRemaining,
        ws.topupLimit,
        r.topup_credits_used != null ? r.topup_credits_used : '',
        ws.totalCredits,
        ws.totalCreditsUsed != null ? ws.totalCreditsUsed : (r.total_credits_used != null ? r.total_credits_used : ''),
        r.total_credits_used_in_billing_period != null ? r.total_credits_used_in_billing_period : '',
        ws.available,
        r.backend_total_used_in_billing_period != null ? r.backend_total_used_in_billing_period : '',
        r.num_projects != null ? r.num_projects : '',
        r.referral_count != null ? r.referral_count : '',
        r.followers_count != null ? r.followers_count : '',
        csvVal(r.billing_period_start_date || ''),
        csvVal(r.billing_period_end_date || ''),
        csvVal(r.next_monthly_credit_grant_date || ''),
        csvVal(r.created_at || ''),
        csvVal(r.updated_at || ''),
        csvVal(r.owner_id || ''),
        r.mcp_enabled != null ? r.mcp_enabled : ''
      ];
      lines.push(row.join(','));
    }

    var csvText = lines.join('\n');
    var blob = new Blob([csvText], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'workspaces-available-' + new Date().toISOString().replace(/[:.]/g, '-') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    log('CSV Export (available): Downloaded ' + sorted.length + '/' + workspaces.length + ' workspaces with credits > 0', 'success');
  }

  window.__loopLogs = { copy: copyLogsToClipboard, download: downloadLogs, get: getAllLogs, clear: clearAllLogs, format: formatLogsForExport };
  window.__loopExportCsv = exportWorkspacesAsCsv;
  window.__loopExportCsvAvailable = exportAvailableWorkspacesAsCsv;

  function addActivityLog(time, level, message, indent) {
    const timestamp = time || new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
    const indentLevel = indent || 0;
    const entry = { time: timestamp, level: level, msg: message, indent: indentLevel };

    activityLogLines.push(entry);
    if (activityLogLines.length > maxActivityLines) {
      activityLogLines.shift();
    }

    updateActivityLogUI();
  }

  function updateActivityLogUI() {
    const logContainer = document.getElementById('loop-activity-log-content');
    if (!logContainer) return;

    let html = '';
    for (let i = activityLogLines.length - 1; i >= 0; i--) {
      const entry = activityLogLines[i];
      let color = cLogDefault;
      if (entry.level === 'ERROR' || entry.level === 'error') color = cLogError;
      else if (entry.level === 'INFO') color = cLogInfo;
      else if (entry.level === 'success') color = cLogSuccess;
      else if (entry.level === 'DEBUG') color = cLogDebug;
      else if (entry.level === 'WARN' || entry.level === 'warn') color = cLogWarn;
      else if (entry.level === 'delegate') color = cLogDelegate;
      else if (entry.level === 'check') color = cLogCheck;

      const indentPx = (entry.indent || 0) * 12;
      html += '<div style="font-size:' + tFontSm + ';font-family:' + tFont + ';padding:2px 0;color:' + color + ';margin-left:' + indentPx + 'px;">';
      if (entry.indent && entry.indent > 0) {
        html += '<span style="color:' + cLogTimestamp + ';">' + entry.time + '</span> ';
      } else {
        html += '<span style="color:' + cLogTimestamp + ';">[' + entry.time + ']</span> ';
        html += '<span style="color:' + cLogDefault + ';">[' + entry.level + ']</span> ';
      }
      html += entry.msg;
      html += '</div>';
    }

    logContainer.innerHTML = html || '<div style="color:' + cLogTimestamp + ';font-size:' + tFontSm + ';padding:8px;">No activity logs yet</div>';
  }

  function toggleActivityLog() {
    // T-5: Activity log is now inside a collapsible section; this function is kept for API compatibility
    activityLogVisible = !activityLogVisible;
    const logPanel = document.getElementById('loop-activity-log-panel');
    if (logPanel) {
      logPanel.style.display = activityLogVisible ? 'block' : 'none';
    }
  }

  // Expose globally for AHK to call
  window.__addActivityLog = addActivityLog;

  function log(msg, type) {
    const prefix = '[MacroLoop v' + VERSION + '] ';
    let style = 'color: ' + cLogDefault + ';';
    if (type === 'success') style = 'color: ' + cLogSuccess + ';';
    if (type === 'error') style = 'color: ' + cLogError + '; font-weight: bold;';
    if (type === 'warn') style = 'color: ' + cLogWarn + ';';
    if (type === 'delegate') style = 'color: ' + cLogDelegate + ';';
    if (type === 'check') style = 'color: ' + cLogCheck + ';';
    if (type === 'skip') style = 'color: ' + cLogSkip + '; font-style: italic;';
    console.log('%c' + prefix + msg, style);

    // Add to activity log (indent 0 = main log)
    addActivityLog(null, type || 'INFO', msg, 0);

    // Persist to localStorage
    persistLog(type || 'INFO', msg);
  }

  // ============================================
  // Sub-log with indentation levels (1-4)
  // Level 1: Direct sub-action
  // Level 2: Detail within sub-action
  // Level 3: Nested detail (e.g. XPath result)
  // Level 4: Deep nested (e.g. element attribute)
  // ============================================
  function logSub(msg, indent) {
    let level = indent || 1;
    const pad = '';
    for (let p = 0; p < level; p++) pad += '  ';
    const prefix = '[MacroLoop v' + VERSION + '] ';
    console.log('%c' + prefix + pad + msg, 'color: ' + cLogInfo + ';');

    addActivityLog(null, 'SUB', msg, level);
    persistLog('SUB', pad + msg);
  }

  // ============================================
  // XPathUtils integration: delegate reactClick to shared module
  // XPathUtils.js MUST be injected by AHK before macro-looping.js
  // ============================================
  // ============================================
  // Shared Bearer Token Resolution (cookie + extension session bridge)
  // ============================================
  const SESSION_BRIDGE_KEYS = [
    'marco_bearer_token',
    'lovable-session-id',
    'ahk_bearer_token'
  ];
  let LAST_SESSION_BRIDGE_SOURCE = '';

  function getBearerTokenFromSessionBridge() {
    try {
      for (let i = 0; i < SESSION_BRIDGE_KEYS.length; i++) {
        let key = SESSION_BRIDGE_KEYS[i];
        const token = localStorage.getItem(key) || '';
        if (token && token.length >= 10) {
          if (LAST_SESSION_BRIDGE_SOURCE !== key) {
            LAST_SESSION_BRIDGE_SOURCE = key;
            log('resolveToken: using session token from localStorage[' + key + ']', 'success');
          }
          return token;
        }
      }
    } catch (e) {
      log('resolveToken: localStorage bridge unavailable — ' + e.message, 'warn');
    }
    return '';
  }

  // v7.9.46: Read bearer token from lovable-session-id.id cookie with comprehensive diagnostics
  function getBearerTokenFromCookie() {
    const fn = 'getBearerTokenFromCookie';
    try {
      const rawCookie = document.cookie;
      const cookieCount = rawCookie ? rawCookie.split(';').length : 0;
      const cookieNames = rawCookie ? rawCookie.split(';').map(function(c) { return c.trim().split('=')[0]; }) : [];

      log(fn + ': === COOKIE DIAGNOSTIC START ===', 'info');
      log(fn + ': document.cookie accessible: ' + (typeof document.cookie === 'string' ? 'YES' : 'NO'), 'info');
      log(fn + ': Total cookies visible to JS: ' + cookieCount, 'info');
      log(fn + ': Cookie names visible: [' + cookieNames.join(', ') + ']', 'info');
      log(fn + ': Raw cookie string length: ' + rawCookie.length + ' chars', 'info');

      if (cookieCount === 0 || rawCookie.length === 0) {
        log(fn + ': ⚠ NO cookies visible to JavaScript at all!', 'warn');
        log(fn + ': PROBABLE CAUSE: All cookies are HttpOnly (server-set, JS cannot read them)', 'warn');
        log(fn + ': HttpOnly cookies are visible in DevTools > Application > Cookies but NOT to document.cookie', 'warn');
        log(fn + ': === COOKIE DIAGNOSTIC END (no cookies) ===', 'info');
        return '';
      }

      let hasTarget = false;
      const cookies = rawCookie.split(';');
      for (let i = 0; i < cookies.length; i++) {
        let c = cookies[i].trim();
        if (c.indexOf('lovable-session-id.id=') === 0) {
          hasTarget = true;
          const val = c.substring('lovable-session-id.id='.length);
          log(fn + ': ✅ Found "lovable-session-id.id" cookie', 'info');
          log(fn + ':   Value length: ' + (val ? val.length : 0), 'info');
          log(fn + ':   Preview: ' + (val ? val.substring(0, 12) + '...REDACTED' : '(empty)'), 'info');
          if (val && val.length >= 10) {
            log(fn + ': ✅ Cookie value is valid (len=' + val.length + ')', 'success');
            log(fn + ': === COOKIE DIAGNOSTIC END (success) ===', 'info');
            return val;
          } else {
            log(fn + ': ⚠ Cookie found but value too short (len=' + (val ? val.length : 0) + ', min=10)', 'warn');
            log(fn + ': PROBABLE CAUSE: Cookie was cleared/corrupted or session expired', 'warn');
          }
        }
      }

      if (!hasTarget) {
        log(fn + ': ❌ "lovable-session-id.id" NOT found among ' + cookieCount + ' visible cookies', 'warn');
        log(fn + ': Visible cookie names: [' + cookieNames.join(', ') + ']', 'warn');
        log(fn + ': PROBABLE CAUSE: The session cookie is HttpOnly — JS cannot access it', 'warn');
        log(fn + ': HttpOnly cookies are set with "Set-Cookie: HttpOnly" flag by the server', 'warn');
        log(fn + ': They appear in DevTools > Application > Cookies but document.cookie cannot read them', 'warn');
      }
      log(fn + ': === COOKIE DIAGNOSTIC END (not found) ===', 'info');
    } catch (e) {
      log(fn + ': ❌ EXCEPTION reading cookies: ' + e.message, 'error');
      log(fn + ': This may happen in sandboxed iframes or restricted contexts', 'error');
    }
    return '';
  }

  // v7.22: Track last token source for auth diagnostic UI
  let LAST_TOKEN_SOURCE = 'none';

  // v7.21: Resolve bearer token from extension session bridge first, then JS-visible cookie
  function resolveToken() {
    const sessionToken = getBearerTokenFromSessionBridge();
    if (sessionToken) {
      LAST_TOKEN_SOURCE = 'localStorage[' + LAST_SESSION_BRIDGE_SOURCE + ']';
      return sessionToken;
    }

    const cookieToken = getBearerTokenFromCookie();
    if (cookieToken) {
      LAST_TOKEN_SOURCE = 'cookie[lovable-session-id.id]';
      return cookieToken;
    }

    LAST_TOKEN_SOURCE = 'none';
    return '';
  }

  // Expose for future API integration
  window.__loopGetBearerToken = resolveToken;

  // ============================================
  // Credit API Config — reads from combo.js shared localStorage or uses defaults
  // Uses same API endpoint as combo.js for consistent credit data
  // ============================================
  const CREDIT_API_BASE = 'https://api.lovable.dev';
  const CREDIT_CACHE_TTL_S = 30;

  const loopCreditState = {
    lastCheckedAt: null,
    perWorkspace: [],
    currentWs: null,       // workspace matching current context
    totalDailyFree: 0,
    totalRollover: 0,
    totalAvailable: 0,
    totalBillingAvail: 0,
    source: null
  };

  // ============================================
  // Workspace Rename: Selection state
  // ============================================
  let loopWsCheckedIds = {};       // { [workspaceId]: true }
  let loopWsLastCheckedIdx = -1;   // index of last checkbox click (for Shift range)

  // ============================================
  // Credit API: Parse response (same logic as combo.js)
  // ============================================
  // === Shared credit calculation helpers ===
  function calcTotalCredits(granted, dailyLimit, billingLimit, topupLimit, rolloverLimit) {
    return Math.round((granted || 0) + (dailyLimit || 0) + (billingLimit || 0) + (topupLimit || 0) + (rolloverLimit || 0));
  }
  function calcAvailableCredits(totalCredits, rolloverUsed, dailyUsed, billingUsed, freeUsed) {
    // v7.12.0: Include freeUsed (credits_used against credits_granted) — previously omitted,
    // causing available to be inflated by unspent granted credits
    return Math.max(0, Math.round(totalCredits - (rolloverUsed || 0) - (dailyUsed || 0) - (billingUsed || 0) - (freeUsed || 0)));
  }
  function calcFreeCreditAvailable(dailyLimit, dailyUsed) {
    return Math.max(0, Math.round((dailyLimit || 0) - (dailyUsed || 0)));
  }
  function calcSegmentPercents(totalCredits, freeRemaining, billingAvailable, rollover, dailyFree) {
    const total = Math.max(0, Math.round(totalCredits || 0));
    const free = Math.max(0, Math.round(freeRemaining || 0));
    const billing = Math.max(0, Math.round(billingAvailable || 0));
    const roll = Math.max(0, Math.round(rollover || 0));
    const daily = Math.max(0, Math.round(dailyFree || 0));

    if (total <= 0) {
      return { free: 0, billing: 0, rollover: 0, daily: 0 };
    }

    const freePct = (free / total) * 100;
    const billingPct = (billing / total) * 100;
    const rollPct = (roll / total) * 100;
    const dailyPct = (daily / total) * 100;
    const sum = freePct + billingPct + rollPct + dailyPct;

    if (sum > 100) {
      const scale = 100 / sum;
      freePct *= scale;
      billingPct *= scale;
      rollPct *= scale;
      dailyPct *= scale;
    }

    return {
      free: Number(freePct.toFixed(2)),
      billing: Number(billingPct.toFixed(2)),
      rollover: Number(rollPct.toFixed(2)),
      daily: Number(dailyPct.toFixed(2))
    };
  }

  // Shared credit bar renderer — single source of truth for all rendering sites.
  // Spec 06 §Rendering Sites: identical segment order, colors, formulas everywhere.
  // Mode: compact (14px, ⚡+🎁 labels) vs full (18px, 🎁💰🔄📅⚡ labels).
  function renderCreditBar(opts) {
    const tc = opts.totalCredits || 0;
    const av = opts.available || 0;
    const tu = opts.totalUsed || 0;
    const fr = opts.freeRemaining || 0;
    const ba = opts.billingAvail || 0;
    const ro = opts.rollover || 0;
    const df = opts.dailyFree || 0;
    const compact = opts.compact || false;
    const maxTc = opts.maxTotalCredits || tc; // v7.23: relative scaling across workspaces
    const mt = opts.marginTop ? 'margin-top:' + opts.marginTop + ';' : '';
    const segments = calcSegmentPercents(tc, fr, ba, ro, df);
    const bH = compact ? '14px' : '18px';
    const bR = compact ? '5px' : '7px';
    const bW = creditBarWidthPx + 'px';
    const bBorder = compact ? '1px solid rgba(255,255,255,.10)' : '1px solid rgba(255,255,255,.15)';
    const bShadow = compact ? 'box-shadow:inset 0 1px 2px rgba(0,0,0,0.2);' : 'box-shadow:inset 0 2px 4px rgba(0,0,0,0.3);';
    const wW = compact ? 'width:100%;' : '';
    const bTitle = 'Available: ' + av + ' / Total: ' + tc + ' (Used: ' + tu + ')';
    // v7.23: Scale filled portion relative to maxTotalCredits for cross-workspace comparison
    const fillPct = maxTc > 0 ? Math.min(100, (tc / maxTc) * 100) : 100;
    const h = '<div style="display:flex;align-items:center;gap:8px;' + mt + wW + '">';
    h += '<div title="' + bTitle + '" style="flex:none;height:' + bH + ';width:' + bW + ';min-width:' + bW + ';max-width:' + bW + ';background:' + cCbEmpty + ';border-radius:' + bR + ';overflow:hidden;display:flex;border:' + bBorder + ';' + bShadow + '">';
    h += '<div style="width:' + fillPct.toFixed(2) + '%;height:100%;display:flex;transition:width ' + trSlow + ' ease;">';
    h += '<div title="🎁 Bonus: ' + fr + '" style="width:' + segments.free + '%;height:100%;background:linear-gradient(90deg,' + cCbBonus[0] + ',' + cCbBonus[1] + ');transition:width ' + trSlow + ' ease;"></div>';
    h += '<div title="💰 Monthly: ' + ba + '" style="width:' + segments.billing + '%;height:100%;background:linear-gradient(90deg,' + cCbBilling[0] + ',' + cCbBilling[1] + ');transition:width ' + trSlow + ' ease;"></div>';
    h += '<div title="🔄 Rollover: ' + ro + '" style="width:' + segments.rollover + '%;height:100%;background:linear-gradient(90deg,' + cCbRollover[0] + ',' + cCbRollover[1] + ');transition:width ' + trSlow + ' ease;"></div>';
    h += '<div title="📅 Free: ' + df + '" style="width:' + segments.daily + '%;height:100%;background:linear-gradient(90deg,' + cCbDaily[0] + ',' + cCbDaily[1] + ');transition:width ' + trSlow + ' ease;"></div>';
    h += '</div>';
    h += '</div>';
    const icoStyle = 'display:inline-block;min-width:32px;text-align:right;';
    const icoStyleWide = 'display:inline-block;min-width:52px;text-align:right;font-weight:700;';
    if (compact) {
      h += '<span style="font-size:' + tFontSm + ';font-family:' + tFont + ';white-space:nowrap;">';
      h += '<span style="color:' + cPrimaryLight + ';' + icoStyle + '" title="🎁 Bonus — Promotional one-time credits">🎁' + fr + '</span> ';
      h += '<span style="color:' + cCbBilling[1] + ';' + icoStyle + '" title="💰 Monthly — Credits from subscription plan">💰' + ba + '</span> ';
      h += '<span style="color:' + cLogInfo + ';' + icoStyle + '" title="🔄 Rollover — Unused credits from previous period">🔄' + ro + '</span> ';
      h += '<span style="color:' + cCbDaily[1] + ';' + icoStyle + '" title="📅 Free — Daily free credits">📅' + df + '</span> ';
      h += '<span style="color:' + cCbAvail + ';' + icoStyleWide + '" title="Available / Total credits">⚡' + av + '/' + tc + '</span>';
      h += '</span>';
    } else {
      h += '<span style="font-size:' + tFontSm + ';white-space:nowrap;font-family:' + tFont + ';line-height:1;">';
      h += '<span style="color:' + cPrimaryLight + ';' + icoStyle + '" title="🎁 Bonus — Promotional one-time credits">🎁' + fr + '</span> ';
      h += '<span style="color:' + cCbBilling[1] + ';' + icoStyle + '" title="💰 Monthly — Credits from subscription plan">💰' + ba + '</span> ';
      h += '<span style="color:' + cLogInfo + ';' + icoStyle + '" title="🔄 Rollover — Unused credits carried from previous period">🔄' + ro + '</span> ';
      h += '<span style="color:' + cCbDaily[1] + ';' + icoStyle + '" title="📅 Free — Daily free credits (refreshed daily)">📅' + df + '</span> ';
      h += '<span style="color:' + cCbAvail + ';' + icoStyleWide + '" title="⚡ Available / Total credits">⚡' + av + '/' + tc + '</span>';
      h += '</span>';
    }
    h += '</div>';
    return h;
  }

  function parseLoopApiResponse(data) {
    const workspaces = data.workspaces || data || [];
    if (!Array.isArray(workspaces)) {
      log('parseLoopApiResponse: unexpected response shape', 'warn');
      return false;
    }

    let perWs = [];
    for (let i = 0; i < workspaces.length; i++) {
      const rawWs = workspaces[i];
      const ws = rawWs.workspace || rawWs;
      const bUsed = ws.billing_period_credits_used || 0;
      const bLimit = ws.billing_period_credits_limit || 0;
      const dUsed = ws.daily_credits_used || 0;
      const dLimit = ws.daily_credits_limit || 0;
      const rUsed = ws.rollover_credits_used || 0;
      const rLimit = ws.rollover_credits_limit || 0;
      const freeGranted = ws.credits_granted || 0;
      const freeUsed = ws.credits_used || 0;
      const freeRemaining = Math.max(0, Math.round(freeGranted - freeUsed));

      let dailyFree = Math.max(0, Math.round(dLimit - dUsed));
      let rollover = Math.max(0, Math.round(rLimit - rUsed));
      const billingAvailable = Math.max(0, Math.round(bLimit - bUsed));
      const topupLimit = Math.round(ws.topup_credits_limit || 0);
      const totalCreditsUsed = Math.round(ws.total_credits_used || 0);
      // Total Credits = credits_granted + daily_credits_limit + billing_period_credits_limit + topup_credits_limit + rollover_credits_limit
      const totalCredits = calcTotalCredits(freeGranted, dLimit, bLimit, topupLimit, rLimit);
      // Available Credit = Total Credits - rollover_credits_used - daily_credits_used - billing_period_credits_used - credits_used
      let available = calcAvailableCredits(totalCredits, rUsed, dUsed, bUsed, freeUsed);

      const subStatus = (rawWs.workspace ? rawWs.subscription_status : ws.subscription_status) || 'N/A';
      let role = (rawWs.workspace ? rawWs.role : ws.role) || 'N/A';

      perWs.push({
        id: ws.id || '',
        name: (ws.name || 'WS' + i).substring(0, 12),
        fullName: ws.name || 'WS' + i,
        dailyFree: dailyFree, dailyLimit: Math.round(dLimit),
        dailyUsed: Math.round(dUsed),
        rollover: rollover, rolloverLimit: Math.round(rLimit),
        rolloverUsed: Math.round(rUsed),
        available: available, billingAvailable: billingAvailable,
        used: Math.round(bUsed),
        limit: Math.round(bLimit),
        freeGranted: Math.round(freeGranted), freeRemaining: freeRemaining,
        hasFree: freeGranted > 0 && freeUsed < freeGranted,
        topupLimit: topupLimit,
        totalCreditsUsed: totalCreditsUsed,
        totalCredits: totalCredits,
        subscriptionStatus: subStatus, role: role,
        raw: ws
      });
    }

    loopCreditState.perWorkspace = perWs;
    loopCreditState.lastCheckedAt = Date.now();

    // Aggregate totals
    const tdf = 0, tr = 0, ta = 0, tba = 0;
    for (let j = 0; j < perWs.length; j++) {
      tdf += perWs[j].dailyFree;
      tr += perWs[j].rollover;
      ta += perWs[j].available;
      tba += perWs[j].billingAvailable;
    }
    loopCreditState.totalDailyFree = tdf;
    loopCreditState.totalRollover = tr;
    loopCreditState.totalAvailable = ta;
    loopCreditState.totalBillingAvail = tba;

    // v7.9.19: Don't blindly default to perWs[0] — leave null until workspace is properly detected
    // autoDetectLoopCurrentWorkspace will set currentWs after matching via API or DOM fallback
    if (state.workspaceName && perWs.length > 0) {
      for (let k = 0; k < perWs.length; k++) {
        if (perWs[k].fullName === state.workspaceName || perWs[k].name === state.workspaceName) {
          loopCreditState.currentWs = perWs[k];
          break;
        }
      }
    }

    // v7.9.20: Build wsById dictionary for O(1) lookup by workspace ID
    loopCreditState.wsById = {};
    for (let w = 0; w < perWs.length; w++) {
      if (perWs[w].id) {
        loopCreditState.wsById[perWs[w].id] = perWs[w];
      }
    }

    loopCreditState.source = 'api';
    log('Credit API: parsed ' + perWs.length + ' workspaces — dailyFree=' + tdf + ' rollover=' + tr + ' available=' + ta + ' | wsById keys=' + Object.keys(loopCreditState.wsById).length, 'success');
    return true;
  }

  // ============================================
  // Credit API: Fetch credits from API
  // ============================================
  // v7.25: Invalidate a specific session bridge key so resolveToken() skips it on next call
  function invalidateSessionBridgeKey(token) {
    for (let i = 0; i < SESSION_BRIDGE_KEYS.length; i++) {
      let key = SESSION_BRIDGE_KEYS[i];
      try {
        const stored = localStorage.getItem(key) || '';
        if (stored && stored === token) {
          localStorage.removeItem(key);
          log('Token fallback: invalidated localStorage[' + key + ']', 'warn');
          return key;
        }
      } catch (e) { /* ignore */ }
    }
    return '';
  }

  function fetchLoopCredits(isRetry) {
    const url = CREDIT_API_BASE + '/user/workspaces';
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };

    // v7.9.35: Unified token resolution (localStorage > cookie)
    const token = resolveToken();
    if (token) {
      headers['Authorization'] = 'Bearer ' + token;
    }

    // v7.9.25: Full fetch logging per standard
    log('Credit API: GET ' + url + (isRetry ? ' (RETRY with fallback token)' : ''), 'check');
    logSub('Auth: ' + (token ? 'Bearer ' + token.substring(0, 12) + '...REDACTED' : 'cookies only (no bearer)'), 1);
    logSub('Request headers: ' + JSON.stringify({ Accept: headers['Accept'], 'Content-Type': headers['Content-Type'], Authorization: token ? 'Bearer ' + token.substring(0, 12) + '...REDACTED' : '(none)' }), 1);

    fetch(url, { method: 'GET', headers: headers, credentials: 'include' })
      .then(function(resp) {
        const respContentType = resp.headers.get('content-type') || '(none)';
        const respContentLength = resp.headers.get('content-length') || '(not set)';
        log('Credit API: Response status=' + resp.status + ' statusText="' + resp.statusText + '" content-type="' + respContentType + '" content-length=' + respContentLength, 'check');
        if (!resp.ok) {
          // v7.25: On 401/403, invalidate current token and retry once with next token source
          if ((resp.status === 401 || resp.status === 403) && token && !isRetry) {
            markBearerTokenExpired('loop');
            const invalidatedKey = invalidateSessionBridgeKey(token);
            log('Token fallback: got ' + resp.status + ' — invalidated "' + invalidatedKey + '", retrying with next token source...', 'warn');
            showToast('Auth ' + resp.status + ' — token "' + invalidatedKey + '" expired, retrying with fallback...', 'warn');
            fetchLoopCredits(true);
            return;
          }
          if (resp.status === 401 || resp.status === 403) {
            markBearerTokenExpired('loop');
          }
          return resp.text().then(function(errBody) {
            log('Credit API: HTTP ' + resp.status + ' error body: ' + (errBody || '(empty)').substring(0, 500), 'error');
            throw new Error('HTTP ' + resp.status + ' ' + resp.statusText);
          });
        }
        return resp.text().then(function(bodyText) {
          bodyText = (bodyText || '').trim();
          logSub('Credit API: body length=' + bodyText.length + ' preview="' + (bodyText || '(empty)').substring(0, 200) + '"', 1);
          if (!bodyText) {
            throw new Error('Empty response body from ' + url);
          }
          let data;
          try { data = JSON.parse(bodyText); } catch(e) {
            throw new Error('JSON parse failed: ' + e.message + ' | raw: "' + bodyText.substring(0, 300) + '"');
          }
          return data;
        });
      })
      .then(function(data) {
        if (!data) return; // v7.25: retry branch returns undefined, skip processing
        const ok = parseLoopApiResponse(data);
        if (ok) {
          // v7.9.3: Auto-detect current workspace via project API
          const token = resolveToken();
          window.__loopResolvedToken = token;
          autoDetectLoopCurrentWorkspace(token).then(function() {
            // v7.9.7: Sync state.hasFreeCredit from API data
            syncCreditStateFromApi();
            updateUI();
            log('Credit API: display updated (workspace detected)', 'success');
            if (typeof window.__loopUpdateAuthDiag === 'function') window.__loopUpdateAuthDiag();
          });
        }
      })
      .catch(function(err) {
        log('Credit API failed: ' + err.message + ' | URL=' + url + ' | auth=' + (token ? 'bearer(' + token.substring(0, 12) + '...REDACTED)' : 'cookies'), 'error');
        if (typeof window.__loopUpdateAuthDiag === 'function') window.__loopUpdateAuthDiag();
      });
  }

  window.__loopFetchCredits = fetchLoopCredits;

  // v7.11.3 + v7.25: Promise-returning version with token fallback retry
  function fetchLoopCreditsAsync(isRetry) {
    const url = CREDIT_API_BASE + '/user/workspaces';
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const token = resolveToken();
    if (token) {
      headers['Authorization'] = 'Bearer ' + token;
    }
    log('Credit API (async): GET ' + url + (isRetry ? ' (RETRY with fallback token)' : ''), 'check');
    return fetch(url, { method: 'GET', headers: headers, credentials: 'include' })
      .then(function(resp) {
        if (!resp.ok) {
          // v7.25: On 401/403, invalidate current token and retry once with next source
          if ((resp.status === 401 || resp.status === 403) && token && !isRetry) {
            markBearerTokenExpired('loop');
            const invalidatedKey = invalidateSessionBridgeKey(token);
            log('Token fallback (async): got ' + resp.status + ' — invalidated "' + invalidatedKey + '", retrying...', 'warn');
            showToast('Auth ' + resp.status + ' — token "' + invalidatedKey + '" expired, retrying with fallback...', 'warn');
            return fetchLoopCreditsAsync(true);
          }
          if (resp.status === 401 || resp.status === 403) {
            markBearerTokenExpired('loop');
          }
          throw new Error('HTTP ' + resp.status);
        }
        return resp.text();
      })
      .then(function(bodyText) {
        if (!bodyText) return; // v7.25: retry branch may resolve without body
        bodyText = (bodyText || '').trim();
        if (!bodyText) throw new Error('Empty response body');
        let data = JSON.parse(bodyText);
        parseLoopApiResponse(data);
        log('Credit API (async): parsed ' + (loopCreditState.perWorkspace || []).length + ' workspaces', 'success');
      });
  }

  // v7.19: Auto-detect current workspace.
  // Tier 1: POST /projects/{id}/mark-viewed → workspace_id → wsById lookup (restored per RCA #23)
  // Tier 2: XPath detection via Project Dialog
  // Tier 3: Default to first workspace (last resort)
  // Returns a Promise so Focus Current can await it.
  // ============================================
  function autoDetectLoopCurrentWorkspace(bearerToken) {
    const fn = 'autoDetectLoopWs';
    let perWs = loopCreditState.perWorkspace || [];
    if (perWs.length === 0) {
      log(fn + ': No workspaces loaded', 'warn');
      return Promise.resolve();
    }
    if (perWs.length === 1) {
      state.workspaceName = perWs[0].fullName || perWs[0].name;
      state.workspaceFromApi = true;
      loopCreditState.currentWs = perWs[0];
      log(fn + ': Single workspace: ' + state.workspaceName, 'success');
      return Promise.resolve();
    }

    // v7.9.34: GUARD — If workspace was already set authoritatively (e.g. post-move API success),
    // skip detection entirely. Just match the known name against the workspace list.
    if (state.workspaceFromApi && state.workspaceName) {
      let matched = null;
      for (let g = 0; g < perWs.length; g++) {
        if (perWs[g].fullName === state.workspaceName || perWs[g].name === state.workspaceName) {
          matched = perWs[g];
          break;
        }
      }
      if (matched) {
        loopCreditState.currentWs = matched;
        log(fn + ': ✅ GUARD — workspace already set authoritatively: "' + state.workspaceName + '" (skipping detection)', 'success');
        return Promise.resolve();
      }
      // Name doesn't match any workspace — fall through to detection
      log(fn + ': GUARD — workspaceFromApi=true but "' + state.workspaceName + '" not found in list, falling through to Tier 1', 'warn');
      state.workspaceFromApi = false;
    }

    // ---- Tier 1: POST /projects/{id}/mark-viewed → workspace_id → wsById O(1) lookup ----
    const projectId = extractProjectIdFromUrl();
    const token = bearerToken || resolveToken();
    if (!projectId) {
      log(fn + ': No projectId in URL — skipping Tier 1, falling to Tier 2 (XPath)', 'warn');
      return detectWorkspaceViaProjectDialog(fn, perWs);
    }
    if (!token) {
      log(fn + ': No bearer token — skipping Tier 1, falling to Tier 2 (XPath)', 'warn');
      return detectWorkspaceViaProjectDialog(fn, perWs);
    }

    const markViewedUrl = CREDIT_API_BASE + '/projects/' + projectId + '/mark-viewed';
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token };
    log(fn + ': Tier 1 — POST ' + markViewedUrl, 'check');

    return fetch(markViewedUrl, { method: 'POST', headers: headers, credentials: 'include', body: '{}' })
      .then(function(resp) {
        if (!resp.ok) {
          log(fn + ': Tier 1 FAILED — HTTP ' + resp.status + ' — falling to Tier 2 (XPath)', 'warn');
          if (resp.status === 401 || resp.status === 403) {
            markBearerTokenExpired('loop');
          }
          return detectWorkspaceViaProjectDialog(fn, perWs);
        }
        return resp.text().then(function(bodyText) {
          let data;
          try { data = JSON.parse(bodyText); } catch(e) {
            log(fn + ': Tier 1 — invalid JSON response — falling to Tier 2', 'warn');
            return detectWorkspaceViaProjectDialog(fn, perWs);
          }

          // Extract workspace_id from multiple possible response shapes
          const wsId = data.workspace_id
            || (data.project && data.project.workspace_id)
            || data.workspaceId
            || '';

          logSub('Tier 1 response keys: ' + Object.keys(data).join(', '), 1);
          logSub('Extracted workspace_id: "' + wsId + '"', 1);

          if (!wsId) {
            log(fn + ': Tier 1 — no workspace_id in response — falling to Tier 2 (XPath)', 'warn');
            logSub('Response (first 400 chars): ' + bodyText.substring(0, 400), 1);
            return detectWorkspaceViaProjectDialog(fn, perWs);
          }

          // O(1) lookup via wsById dictionary
          let wsById = loopCreditState.wsById || {};
          const matchedWs = wsById[wsId];
          if (matchedWs) {
            state.workspaceName = matchedWs.fullName || matchedWs.name;
            state.workspaceFromApi = true;
            loopCreditState.currentWs = matchedWs;
            log(fn + ': ✅ Tier 1 MATCHED via wsById: "' + state.workspaceName + '" (id=' + wsId + ')', 'success');
            return; // Success — no need for Tier 2
          }

          // wsById miss — try linear scan (edge case: dictionary key format mismatch)
          log(fn + ': Tier 1 — workspace_id "' + wsId + '" not in wsById (' + Object.keys(wsById).length + ' keys) — trying linear scan', 'warn');
          logSub('wsById keys: ' + Object.keys(wsById).slice(0, 10).join(', '), 1);
          for (let li = 0; li < perWs.length; li++) {
            if (perWs[li].id === wsId) {
              state.workspaceName = perWs[li].fullName || perWs[li].name;
              state.workspaceFromApi = true;
              loopCreditState.currentWs = perWs[li];
              log(fn + ': ✅ Tier 1 MATCHED via linear scan: "' + state.workspaceName + '" (id=' + wsId + ')', 'success');
              return;
            }
          }

          log(fn + ': Tier 1 — workspace_id "' + wsId + '" not found in ' + perWs.length + ' workspaces — falling to Tier 2', 'warn');
          return detectWorkspaceViaProjectDialog(fn, perWs);
        });
      })
      .catch(function(err) {
        log(fn + ': Tier 1 NETWORK ERROR: ' + err.message + ' — falling to Tier 2 (XPath)', 'warn');
        return detectWorkspaceViaProjectDialog(fn, perWs);
      });
  }

  // v7.9.25: Detect workspace by clicking the Project Button → reading WorkspaceNameXPath
  // This is the reliable DOM fallback: the project dialog always shows the workspace name.
  // Flow: click ProjectButtonXPath → wait for dialog → read WorkspaceNameXPath → validate → close dialog
  function detectWorkspaceViaProjectDialog(callerFn, perWs) {
    const fn = callerFn || 'detectWsViaDialog';
    perWs = perWs || []; // v7.27: Guard against undefined/null
    const hasWorkspaces = perWs.length > 0;
    if (!hasWorkspaces) {
      log(fn + ': No workspaces loaded — will still try to read workspace name from dialog XPath directly', 'warn');
    }

    log(fn + ': Tier 2 — Opening project dialog to read workspace name...', 'check');
    logSub('ProjectButtonXPath: ' + CONFIG.PROJECT_BUTTON_XPATH, 1);
    logSub('WorkspaceNameXPath: ' + CONFIG.WORKSPACE_XPATH, 1);

    // v7.12.0: Retry finding the project button up to 3 times with 1s delays
    // On first load, DOM may not be ready when detection fires immediately after API response
    return findProjectButtonWithRetry(fn, 3, 1000).then(function(btn) {
      if (!btn) {
        log(fn + ': Project button NOT found after retries — cannot open dialog. XPath=' + CONFIG.PROJECT_BUTTON_XPATH, 'error');
        // v7.27: Guard against empty perWs
        if (!state.workspaceName && perWs.length > 0) {
          state.workspaceName = perWs[0].fullName || perWs[0].name;
          loopCreditState.currentWs = perWs[0];
          log(fn + ': Defaulted to first workspace: ' + state.workspaceName, 'warn');
        } else {
          log(fn + ': Keeping existing workspace: ' + (state.workspaceName || '(none)'), 'warn');
        }
        return Promise.resolve();
      }
      return openDialogAndPoll(fn, btn, perWs);
    });
  }

  // v7.12.0: Retry finding the project button with delay between attempts
  function findProjectButtonWithRetry(fn, maxRetries, delayMs) {
    return new Promise(function(resolve) {
      let attempt = 0;
      function tryFind() {
        attempt++;
        let btn = getByXPath(CONFIG.PROJECT_BUTTON_XPATH);
        if (!btn) {
          btn = findElement(ML_ELEMENTS.PROJECT_BUTTON);
          if (btn) logSub('Project button found via fallback findElement (attempt ' + attempt + ')', 1);
        }
        if (btn) {
          logSub('Project button found on attempt ' + attempt, 1);
          resolve(btn);
          return;
        }
        if (attempt < maxRetries) {
          logSub('Project button not found (attempt ' + attempt + '/' + maxRetries + ') — retrying in ' + delayMs + 'ms...', 1);
          setTimeout(tryFind, delayMs);
        } else {
          logSub('Project button not found after ' + maxRetries + ' attempts', 1);
          resolve(null);
        }
      }
      tryFind();
    });
  }

  // v7.12.0: Extracted dialog open + poll logic
  function openDialogAndPoll(fn, btn, perWs) {

    // Check if dialog is already open — v7.11.4: force close-then-reopen for clean state
    const isExpanded = btn.getAttribute('aria-expanded') === 'true' || btn.getAttribute('data-state') === 'open';
    if (isExpanded) {
      logSub('Dialog is already open — closing first for clean re-read', 1);
      reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
      // Wait briefly for close animation, then reopen
      return new Promise(function(resolve) {
        setTimeout(function() {
          logSub('Re-opening dialog for fresh workspace read', 1);
          reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
          // Continue with polling logic after reopen
          pollForWorkspaceName(fn, btn, perWs, resolve);
        }, 400);
      });
    } else {
      logSub('Dialog is closed — clicking project button to open', 1);
      reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
    }

    // Step 2: Wait for dialog to render, then read workspace name
    return new Promise(function(resolve) {
      pollForWorkspaceName(fn, btn, perWs, resolve);
    });
  }

  // v7.11.4: Extracted polling logic so it can be called from both normal and close-reopen paths
  function pollForWorkspaceName(fn, btn, perWs, resolve) {
    const dialogWaitMs = 1500;
    const pollInterval = 300;
    const elapsed = 0;
    logSub('Waiting up to ' + dialogWaitMs + 'ms for WorkspaceNameXPath to appear...', 1);

    const pollTimer = setInterval(function() {
      elapsed += pollInterval;

      // v7.10.2: Use getAllByXPath — the XPath may match multiple elements.
      const allNodes = getAllByXPath(CONFIG.WORKSPACE_XPATH);
      if (allNodes.length > 0) {
        clearInterval(pollTimer);
        logSub('WorkspaceNameXPath found ' + allNodes.length + ' node(s) after ' + elapsed + 'ms', 1);

        let matched = null;
        let matchedRawName = '';

        for (let ni = 0; ni < allNodes.length; ni++) {
          let rawName = (allNodes[ni].textContent || '').trim();
          logSub('  Node[' + ni + ']: "' + rawName + '"', 1);
          if (!rawName) continue;

          // Check exact match first, then partial
          for (let wi = 0; wi < perWs.length; wi++) {
            if (perWs[wi].fullName === rawName || perWs[wi].name === rawName) {
              matched = perWs[wi];
              matchedRawName = rawName;
              break;
            }
          }
          if (matched) break;

          // Partial match (case-insensitive)
          for (let wi2 = 0; wi2 < perWs.length; wi2++) {
            if (perWs[wi2].fullName && perWs[wi2].fullName.toLowerCase().indexOf(rawName.toLowerCase()) !== -1) {
              matched = perWs[wi2];
              matchedRawName = rawName;
              break;
            }
            if (rawName.toLowerCase().indexOf(perWs[wi2].name.toLowerCase()) !== -1 && perWs[wi2].name.length >= 4) {
              matched = perWs[wi2];
              matchedRawName = rawName;
              break;
            }
          }
          if (matched) break;
        }

        if (matched) {
          state.workspaceName = matched.fullName || matched.name;
          // v7.14.0: Do NOT set workspaceFromApi here — caller decides whether to set it
          // autoDetectLoopCurrentWorkspace sets it; runCheck does NOT
          loopCreditState.currentWs = matched;
          log(fn + ': ✅ Workspace detected from project dialog: "' + matchedRawName + '" → ' + state.workspaceName + ' (id=' + matched.id + ', node index=' + ni + '/' + allNodes.length + ')', 'success');
        } else {
          const firstRaw = (allNodes[0].textContent || '').trim();
          // v7.27: If no workspace list available, use raw XPath text directly as workspace name
          if (perWs.length === 0 && firstRaw) {
            state.workspaceName = firstRaw;
            log(fn + ': ✅ No workspace list — using raw XPath text as workspace name: "' + firstRaw + '"', 'success');
          } else {
            log(fn + ': XPath returned ' + allNodes.length + ' nodes, none matched known workspaces. First node: "' + firstRaw + '" (checked ' + perWs.length + ' workspaces)', 'warn');
            // v7.11.2: Guard — only default to perWs[0] if no existing workspace name
            if (!state.workspaceName && perWs.length > 0) {
              state.workspaceName = perWs[0].fullName || perWs[0].name;
              loopCreditState.currentWs = perWs[0];
              log(fn + ': Defaulted to first workspace: ' + state.workspaceName, 'warn');
            } else if (!state.workspaceName && firstRaw) {
              state.workspaceName = firstRaw;
              log(fn + ': No list, using raw name: ' + firstRaw, 'warn');
            } else {
              log(fn + ': Keeping existing workspace: ' + state.workspaceName, 'warn');
            }
          }
        }

        // Close dialog after reading
        closeProjectDialogSafe(btn);
        resolve();
        return;
      }

      if (elapsed >= dialogWaitMs) {
        clearInterval(pollTimer);
        log(fn + ': WorkspaceNameXPath not found after ' + dialogWaitMs + 'ms — trying CSS selector fallback (S-012)', 'warn');

        // S-012: CSS selector fallback for workspace name
        const cssFallbackNodes = findWorkspaceNameViaCss(fn, perWs);
        if (cssFallbackNodes.matched) {
          state.workspaceName = cssFallbackNodes.matched.fullName || cssFallbackNodes.matched.name;
          loopCreditState.currentWs = cssFallbackNodes.matched;
          log(fn + ': ⚠️ Workspace detected via CSS fallback: "' + cssFallbackNodes.rawName + '" → ' + state.workspaceName + ' (XPath may be stale — consider updating WorkspaceNameXPath in config.ini)', 'warn');
          closeProjectDialogSafe(btn);
          resolve();
          return;
        }

        log(fn + ': CSS fallback also failed — defaulting', 'warn');
        closeDialogAndDefault(fn, btn, perWs, resolve);
      }
    }, pollInterval);
  }

  // S-012: CSS selector fallback for workspace name detection
  // Tries multiple CSS selectors inside the dialog to find text matching a known workspace.
  function findWorkspaceNameViaCss(fn, perWs) {
    const selectors = ML_ELEMENTS.WORKSPACE_NAME.selector;
    const result = { matched: null, rawName: '' };

    for (let si = 0; si < selectors.length; si++) {
      const sel = selectors[si];
      try {
        const els = document.querySelectorAll(sel);
        logSub('CSS fallback [' + (si + 1) + '/' + selectors.length + ']: "' + sel + '" → ' + els.length + ' element(s)', 2);

        for (let ei = 0; ei < els.length; ei++) {
          let text = (els[ei].textContent || '').trim();
          if (!text || text.length < 3) continue;

          // Match against known workspaces
          for (let wi = 0; wi < perWs.length; wi++) {
            if (perWs[wi].fullName === text || perWs[wi].name === text) {
              logSub('CSS fallback ✅ MATCH: selector="' + sel + '", text="' + text + '" → ' + perWs[wi].fullName, 2);
              result.matched = perWs[wi];
              result.rawName = text;
              return result;
            }
            // Partial match
            if (perWs[wi].fullName && perWs[wi].fullName.toLowerCase().indexOf(text.toLowerCase()) !== -1) {
              logSub('CSS fallback ✅ PARTIAL MATCH: selector="' + sel + '", text="' + text + '" → ' + perWs[wi].fullName, 2);
              result.matched = perWs[wi];
              result.rawName = text;
              return result;
            }
            if (text.toLowerCase().indexOf(perWs[wi].name.toLowerCase()) !== -1 && perWs[wi].name.length >= 4) {
              logSub('CSS fallback ✅ PARTIAL MATCH: selector="' + sel + '", text="' + text + '" → ' + perWs[wi].fullName, 2);
              result.matched = perWs[wi];
              result.rawName = text;
              return result;
            }
          }
        }
      } catch (e) {
        logSub('CSS fallback [' + (si + 1) + '/' + selectors.length + ']: "' + sel + '" → ERROR: ' + e.message, 2);
      }
    }

    logSub('CSS fallback: no selectors matched a known workspace (' + selectors.length + ' selectors tried, ' + perWs.length + ' workspaces)', 2);
    return result;
  }

  function closeDialogAndDefault(fn, btn, perWs, resolve) {
    // v7.11.2: Guard — only default to perWs[0] if no existing workspace name
    if (!state.workspaceName) {
      state.workspaceName = perWs[0].fullName || perWs[0].name;
      loopCreditState.currentWs = perWs[0];
      log(fn + ': Defaulted to first workspace: ' + state.workspaceName, 'warn');
    } else {
      log(fn + ': Keeping existing workspace: ' + state.workspaceName, 'warn');
    }
    closeProjectDialogSafe(btn);
    resolve();
  }

  function closeProjectDialogSafe(btn) {
    try {
      const isExpanded = btn && (btn.getAttribute('aria-expanded') === 'true' || btn.getAttribute('data-state') === 'open');
      if (isExpanded) {
        logSub('Closing project dialog after workspace read', 1);
        reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
      }
    } catch (e) {
      logSub('Error closing dialog: ' + e.message, 1);
    }
  }

  // Legacy alias
  function detectWorkspaceFromDom(callerFn, perWs) {
    detectWorkspaceViaProjectDialog(callerFn, perWs);
  }

  // ============================================
  // Bearer Token Management — session bridge + cookie fallback (v7.21)
  // Token UI removed; auth comes from extension-seeded session token
  // ============================================

  // ============================================
  // Move-to-Workspace API (same as combo.js)
  // PUT /projects/{projectId}/move-to-workspace
  // ============================================
  function extractProjectIdFromUrl() {
    const url = window.location.href;
    const match = url.match(/\/projects\/([a-f0-9-]+)/);
    return match ? match[1] : null;
  }

  const loopMoveStatusEl = null; // set during UI creation

  function moveToWorkspace(targetWorkspaceId, targetWorkspaceName) {
    const projectId = extractProjectIdFromUrl();
    if (!projectId) {
      log('Cannot extract projectId from URL: ' + window.location.href, 'error');
      updateLoopMoveStatus('error', 'No project ID in URL');
      return;
    }

    function doMove(token, isRetry) {
      const url = CREDIT_API_BASE + '/projects/' + projectId + '/move-to-workspace';
      const requestBody = { workspace_id: targetWorkspaceId };
      const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      };
      if (token) {
        headers['Authorization'] = 'Bearer ' + token;
      }

      const label = isRetry ? ' (cookie retry)' : '';
      log('=== MOVE TO WORKSPACE ===' + label, 'delegate');
      log('PUT ' + url, 'delegate');
      logSub('Target: ' + targetWorkspaceName + ' (id=' + targetWorkspaceId + ')', 1);
      logSub('Auth: ' + (token ? 'Bearer ' + token.substring(0, 12) + '...' : 'cookies only'), 1);

      updateLoopMoveStatus('loading', 'Moving to ' + targetWorkspaceName + '...');

      fetch(url, {
        method: 'PUT',
        headers: headers,
        credentials: 'include',
        mode: 'cors',
        body: JSON.stringify(requestBody)
      }).then(function(resp) {
        // v7.25: If 401/403 with bearer token, invalidate current key and retry with next source
        if ((resp.status === 401 || resp.status === 403) && token && !isRetry) {
          const invalidatedKey = invalidateSessionBridgeKey(token);
          log('Move got ' + resp.status + ' — invalidated "' + invalidatedKey + '", retrying with fallback', 'warn');
          showToast('Move auth ' + resp.status + ' — token "' + invalidatedKey + '" expired, retrying...', 'warn');
          const fallbackToken = resolveToken();
          doMove(fallbackToken, true);
          return;
        }
        log('Move response: ' + resp.status + ' ' + resp.statusText + label, resp.ok ? 'success' : 'error');
        if (!resp.ok) {
          return resp.text().then(function(body) {
            log('Move failed: HTTP ' + resp.status + ' | body: ' + body.substring(0, 500), 'error');
            updateLoopMoveStatus('error', 'HTTP ' + resp.status + ': ' + body.substring(0, 80));
            // v7.25: After move failure, verify workspace loading still works
            log('Move failed — verifying workspace session is still valid...', 'warn');
            verifyWorkspaceSessionAfterFailure('move');
          });
        }
        return resp.text().then(function(body) {
          log('✅ MOVE SUCCESS -> ' + targetWorkspaceName + label, 'success');
          updateLoopMoveStatus('success', 'Moved to ' + targetWorkspaceName);
          // v7.9.39: Log workspace change to history before updating state
          const previousWorkspace = state.workspaceName || '(unknown)';
          addWorkspaceChangeEntry(previousWorkspace, targetWorkspaceName);
          // Update current workspace name to the target
          state.workspaceName = targetWorkspaceName;
          state.workspaceFromApi = true;
          log('Updated state.workspaceName to: "' + targetWorkspaceName + '"', 'success');
          // v7.14.1: Immediately update UI so workspace name displays right away
          populateLoopWorkspaceDropdown();
          updateUI();
          // v7.9.32: After move, state is already set authoritatively from API success.
          // Do NOT run XPath detection — the dialog may still show the old workspace.
          // Just refresh credits to get updated data, then sync UI.
          setTimeout(function() {
            fetchLoopCredits();
          }, 2000);
        });
      }).catch(function(err) {
        log('Move error: ' + err.message, 'error');
        updateLoopMoveStatus('error', err.message);
        // v7.25: Network error — verify session
        verifyWorkspaceSessionAfterFailure('move');
      });
    }

    const resolvedToken = resolveToken();
    if (!resolvedToken) {
      log('No bearer token — attempting move with cookies only', 'warn');
    }
    doMove(resolvedToken, false);
  }

  function updateLoopMoveStatus(state, message) {
    let el = document.getElementById('loop-move-status');
    if (!el) return;
    const colors = { loading: '#facc15', success: '#4ade80', error: '#ef4444' };
    el.style.color = colors[state] || '#9ca3af';
    el.textContent = message;
    if (state === 'success') {
      setTimeout(function() { el.textContent = ''; }, 5000);
    }
  }

  // v7.25: After a move/rename failure, probe /user/workspaces to check session health
  function verifyWorkspaceSessionAfterFailure(context) {
    const url = CREDIT_API_BASE + '/user/workspaces';
    const token = resolveToken();
    const h = { 'Accept': 'application/json' };
    if (token) h['Authorization'] = 'Bearer ' + token;
    const authLabel = token ? 'Bearer ' + token.substring(0, 12) + '...' : 'cookies only';

    log('[SessionCheck/' + context + '] Probing GET ' + url + ' (auth: ' + authLabel + ')', 'info');

    fetch(url, { method: 'GET', headers: h, credentials: 'include' })
      .then(function(resp) {
        if (resp.ok) {
          return resp.text().then(function(body) {
            let data;
            try { data = JSON.parse(body); } catch(e) { data = null; }
            const wsCount = Array.isArray(data) ? data.length : (data && data.workspaces ? data.workspaces.length : '?');
            log('[SessionCheck/' + context + '] ✅ Session valid — ' + wsCount + ' workspaces loaded (auth: ' + authLabel + ')', 'success');
            showToast(context + ' failed but session is valid (' + wsCount + ' workspaces)', 'info');
          });
        } else {
          log('[SessionCheck/' + context + '] ❌ Session probe failed: HTTP ' + resp.status + ' (auth: ' + authLabel + ')', 'error');
          showToast(context + ' failed — session also broken (HTTP ' + resp.status + '). Re-auth needed.', 'error');
        }
      })
      .catch(function(err) {
        log('[SessionCheck/' + context + '] ❌ Network error: ' + err.message, 'error');
        showToast(context + ' failed — network error on session check', 'error');
      });
  }

  window.__loopMoveToWorkspace = moveToWorkspace;

  // ============================================
  // Workspace Rename: API + Template + Bulk Logic
  // ============================================

  // Single rename API call — returns Promise
  // v7.31: Configurable delay between bulk rename operations (ms)
  let RENAME_DELAY_MS = 750; // Default 750ms between operations
  window.__loopGetRenameDelay = function() { return RENAME_DELAY_MS; };
  window.__loopSetRenameDelay = function(ms) {
    RENAME_DELAY_MS = Math.max(100, Math.min(10000, parseInt(ms, 10) || 750));
    log('[Rename] Delay set to ' + RENAME_DELAY_MS + 'ms', 'info');
  };

  // v7.31: Cancellation flag for bulk rename
  let RENAME_CANCELLED = false;
  window.__loopCancelRename = function() { RENAME_CANCELLED = true; log('[Rename] Cancellation requested', 'warn'); };

  // Single rename API call — returns Promise
  // v7.31: Try PATCH first, fallback to PUT on 405
  function renameWorkspace(wsId, newName) {
    return new Promise(function(resolve, reject) {
      const url = CREDIT_API_BASE + '/user/workspaces/' + wsId;
      const token = resolveToken();

      function doRename(tkn, isRetry, method) {
        method = method || 'PATCH';
        const h = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
        if (tkn) h['Authorization'] = 'Bearer ' + tkn;
        const label = isRetry ? ' (retry)' : '';
        log('[Rename] ' + method + ' ' + url + ' → "' + newName + '"' + label, 'delegate');
        logSub('Auth: ' + (tkn ? 'Bearer ' + tkn.substring(0, 12) + '...' : 'cookies only'), 1);

        fetch(url, {
          method: method,
          headers: h,
          credentials: 'include',
          mode: 'cors',
          body: JSON.stringify({ name: newName, default_monthly_member_credit_limit: -1 })
        }).then(function(resp) {
          // v7.31: If PATCH returns 405 Method Not Allowed, fallback to PUT
          if (resp.status === 405 && method === 'PATCH' && !isRetry) {
            log('[Rename] PATCH returned 405 — falling back to PUT', 'warn');
            doRename(tkn, false, 'PUT');
            return;
          }
          if ((resp.status === 401 || resp.status === 403) && tkn && !isRetry) {
            const invalidatedKey = invalidateSessionBridgeKey(tkn);
            log('[Rename] Got ' + resp.status + ' — invalidated "' + invalidatedKey + '", retrying with fallback', 'warn');
            showToast('Rename auth ' + resp.status + ' — token "' + invalidatedKey + '" expired, retrying...', 'warn');
            const fallbackToken = resolveToken();
            doRename(fallbackToken, true, method);
            return;
          }
          if (resp.status === 429 && !isRetry) {
            log('[Rename] Rate limited (429) — retrying in 2s', 'warn');
            setTimeout(function() { doRename(tkn, true, method); }, 2000);
            return;
          }
          if (!resp.ok) {
            resp.text().then(function(body) {
              log('[Rename] ❌ HTTP ' + resp.status + ': ' + body.substring(0, 200), 'error');
              verifyWorkspaceSessionAfterFailure('rename');
              reject(new Error('HTTP ' + resp.status));
            });
            return;
          }
          log('[Rename] ✅ renamed to "' + newName + '"', 'success');
          resolve();
        }).catch(function(err) {
          log('[Rename] Network error: ' + err.message, 'error');
          verifyWorkspaceSessionAfterFailure('rename');
          reject(err);
        });
      }

      if (!token) {
        log('[Rename] No bearer token — attempting with cookies only', 'warn');
      }
      doRename(token, false, 'PATCH');
    });
  }

  window.__loopRenameWorkspace = renameWorkspace;

  // v7.31: Template engine: apply prefix + template + suffix with sequential numbering
  // Supports 3 numbering variable types: $ (dollar), # (hash), ** (double-star)
  // Each uses zero-padded sequential numbering based on character count
  // Each variable type has its own independent start number
  function applyRenameTemplate(template, prefix, suffix, startNums, index, originalName) {
    // Backwards compat: if startNums is a number, use it for all variables
    const starts = (typeof startNums === 'object' && startNums !== null)
      ? startNums
      : { dollar: startNums || 1, hash: startNums || 1, star: startNums || 1 };

    // Apply numbering variables to a given string
    // v7.32: Each variable type is replaced independently — supports mixing all 3 in one string
    function applyVars(str) {
      if (!str) return str;
      // $ variable — first contiguous run of $ chars (only first match per field)
      str = str.replace(/(\$+)/, function(m) {
        const num = (starts.dollar || 1) + index;
        let s = String(num);
        while (s.length < m.length) s = '0' + s;
        return s;
      });
      // # variable — first contiguous run of # chars
      // Uses negative lookbehind to avoid matching # inside other patterns
      str = str.replace(/(#+)/, function(m) {
        const num = (starts.hash || 1) + index;
        let s = String(num);
        while (s.length < m.length) s = '0' + s;
        return s;
      });
      // ** variable — first contiguous run of 2+ * chars
      str = str.replace(/(\*{2,})/, function(m) {
        const num = (starts.star || 1) + index;
        let s = String(num);
        while (s.length < m.length) s = '0' + s;
        return s;
      });
      return str;
    }

    let base = '';
    if (template) {
      base = applyVars(template);
    } else {
      base = originalName; // No template — keep original name
    }
    // v7.31: Apply numbering variables to prefix and suffix too
    const resolvedPrefix = applyVars(prefix || '');
    const resolvedSuffix = applyVars(suffix || '');
    return resolvedPrefix + base + resolvedSuffix;
  }

  // Rename history stack for undo/rollback
  let loopRenameHistory = []; // Array of { timestamp, entries: [{ wsId, oldName, newName }] }
  const RENAME_HISTORY_MAX = 20; // Keep last 20 operations

  // v7.32: Sequential bulk rename with configurable delay, cancellation, and rolling-average ETA
  // Tracks actual operation time for accurate ETA predictions
  let RENAME_AVG_OP_MS = 0; // Rolling average of actual operation time (request + delay)
  let RENAME_OP_TIMES = []; // Last N operation durations for rolling average
  const RENAME_OP_WINDOW = 5; // Window size for rolling average

  function bulkRenameWorkspaces(entries, onProgress) {
    log('[Rename] === BULK RENAME START === (' + entries.length + ' workspaces, delay=' + RENAME_DELAY_MS + 'ms)', 'delegate');
    const results = { success: 0, failed: 0, total: entries.length, successEntries: [] };
    RENAME_CANCELLED = false;
    RENAME_OP_TIMES = [];
    RENAME_AVG_OP_MS = 0;

    function doNext(idx) {
      // Check cancellation
      if (RENAME_CANCELLED) {
        log('[Rename] === CANCELLED === at ' + idx + '/' + entries.length + ' (' + results.success + ' success, ' + results.failed + ' failed)', 'warn');
        if (results.successEntries.length > 0) {
          loopRenameHistory.push({ timestamp: Date.now(), entries: results.successEntries });
          if (loopRenameHistory.length > RENAME_HISTORY_MAX) loopRenameHistory.shift();
          updateUndoBtnVisibility();
          try { localStorage.setItem('ml_rename_history', JSON.stringify(loopRenameHistory)); } catch(e) {}
        }
        results.cancelled = true;
        if (onProgress) onProgress(results, true);
        return;
      }

      if (idx >= entries.length) {
        log('[Rename] === BULK RENAME COMPLETE === ' + results.success + '/' + results.total + ' success, ' + results.failed + ' failed', results.failed > 0 ? 'warn' : 'success');

        // Store successful renames in history for undo
        if (results.successEntries.length > 0) {
          loopRenameHistory.push({
            timestamp: Date.now(),
            entries: results.successEntries
          });
          if (loopRenameHistory.length > RENAME_HISTORY_MAX) {
            loopRenameHistory.shift();
          }
          log('[Rename] Saved to undo history (' + results.successEntries.length + ' entries, stack depth=' + loopRenameHistory.length + ')', 'success');
          updateUndoBtnVisibility();
          // Persist to localStorage
          try { localStorage.setItem('ml_rename_history', JSON.stringify(loopRenameHistory)); } catch(e) {}
        }

        // Post-completion: refresh all workspace data
        fetchLoopCredits();
        loopWsCheckedIds = {};
        loopWsLastCheckedIdx = -1;
        if (onProgress) onProgress(results, true);
        return;
      }

      const entry = entries[idx];
      log('[Rename] ' + (idx + 1) + '/' + entries.length + ' — "' + entry.oldName + '" → "' + entry.newName + '"', 'check');

      const opStartTime = Date.now();

      renameWorkspace(entry.wsId, entry.newName).then(function() {
        results.success++;
        results.successEntries.push({ wsId: entry.wsId, oldName: entry.oldName, newName: entry.newName });
        // Update local state
        let perWs = loopCreditState.perWorkspace || [];
        for (let k = 0; k < perWs.length; k++) {
          if (perWs[k].id === entry.wsId) {
            perWs[k].fullName = entry.newName;
            perWs[k].name = entry.newName;
            break;
          }
        }
        log('[Rename] ✅ ' + (idx + 1) + '/' + entries.length + ' renamed: "' + entry.newName + '"', 'success');
        // v7.32: Track actual operation time (request duration, delay added after)
        const requestDuration = Date.now() - opStartTime;
        RENAME_OP_TIMES.push(requestDuration + RENAME_DELAY_MS);
        if (RENAME_OP_TIMES.length > RENAME_OP_WINDOW) RENAME_OP_TIMES.shift();
        RENAME_AVG_OP_MS = Math.round(RENAME_OP_TIMES.reduce(function(a, b) { return a + b; }, 0) / RENAME_OP_TIMES.length);
        if (onProgress) onProgress(results, false);
        setTimeout(function() { doNext(idx + 1); }, RENAME_DELAY_MS);
      }).catch(function(err) {
        results.failed++;
        log('[Rename] ❌ ' + (idx + 1) + '/' + entries.length + ' failed: ' + err.message, 'error');
        const requestDuration = Date.now() - opStartTime;
        RENAME_OP_TIMES.push(requestDuration + RENAME_DELAY_MS);
        if (RENAME_OP_TIMES.length > RENAME_OP_WINDOW) RENAME_OP_TIMES.shift();
        RENAME_AVG_OP_MS = Math.round(RENAME_OP_TIMES.reduce(function(a, b) { return a + b; }, 0) / RENAME_OP_TIMES.length);
        if (onProgress) onProgress(results, false);
        setTimeout(function() { doNext(idx + 1); }, RENAME_DELAY_MS);
      });
    }

    doNext(0);
  }

  // Undo last rename operation
  function undoLastRename(onProgress) {
    if (loopRenameHistory.length === 0) {
      log('[Rename] No rename history to undo', 'warn');
      return;
    }
    const last = loopRenameHistory[loopRenameHistory.length - 1];
    const reverseEntries = [];
    for (let i = 0; i < last.entries.length; i++) {
      reverseEntries.push({
        wsId: last.entries[i].wsId,
        oldName: last.entries[i].newName,
        newName: last.entries[i].oldName
      });
    }

    log('[Rename] === UNDO RENAME === Reverting ' + reverseEntries.length + ' workspaces (from ' + new Date(last.timestamp).toLocaleTimeString() + ')', 'delegate');

    const results = { success: 0, failed: 0, total: reverseEntries.length };

    function doNext(idx) {
      if (idx >= reverseEntries.length) {
        log('[Rename] === UNDO COMPLETE === ' + results.success + '/' + results.total + ' reverted', results.failed > 0 ? 'warn' : 'success');
        if (results.success > 0) {
          // Remove from history stack (don't push undo as new history)
          loopRenameHistory.pop();
          try { localStorage.setItem('ml_rename_history', JSON.stringify(loopRenameHistory)); } catch(e) {}
          updateUndoBtnVisibility();
        }
        fetchLoopCredits();
        if (onProgress) onProgress(results, true);
        return;
      }

      const entry = reverseEntries[idx];
      log('[Rename] Undo ' + (idx + 1) + '/' + reverseEntries.length + ' — "' + entry.oldName + '" → "' + entry.newName + '"', 'check');

      renameWorkspace(entry.wsId, entry.newName).then(function() {
        results.success++;
        let perWs = loopCreditState.perWorkspace || [];
        for (let k = 0; k < perWs.length; k++) {
          if (perWs[k].id === entry.wsId) {
            perWs[k].fullName = entry.newName;
            perWs[k].name = entry.newName;
            break;
          }
        }
        if (onProgress) onProgress(results, false);
        doNext(idx + 1);
      }).catch(function(err) {
        results.failed++;
        log('[Rename] Undo ❌ ' + (idx + 1) + '/' + reverseEntries.length + ' failed: ' + err.message, 'error');
        if (onProgress) onProgress(results, false);
        doNext(idx + 1);
      });
    }

    doNext(0);
  }

  function updateUndoBtnVisibility() {
    const undoBtn = document.getElementById('loop-ws-undo-btn');
    if (undoBtn) {
      undoBtn.style.display = loopRenameHistory.length > 0 ? 'inline-block' : 'none';
      if (loopRenameHistory.length > 0) {
        const last = loopRenameHistory[loopRenameHistory.length - 1];
        undoBtn.title = 'Undo last rename (' + last.entries.length + ' workspaces, ' + new Date(last.timestamp).toLocaleTimeString() + ')';
      }
    }
  }

  // Restore history from localStorage on load
  try {
    const savedHistory = localStorage.getItem('ml_rename_history');
    if (savedHistory) {
      loopRenameHistory = JSON.parse(savedHistory);
      log('[Rename] Restored ' + loopRenameHistory.length + ' undo entries from localStorage', 'success');
    }
  } catch(e) {}

  window.__loopUndoRename = function() { undoLastRename(function(r, done) { if (done) populateLoopWorkspaceDropdown(); }); };
  window.__loopRenameHistory = function() { return loopRenameHistory; };

  // Global API for bulk rename of checked workspaces
  // v7.31: Updated to pass startNums object for multi-variable support
  window.__loopBulkRename = function(template, prefix, suffix, startNum) {
    const checkedIds = Object.keys(loopWsCheckedIds);
    if (checkedIds.length === 0) {
      log('[Rename] No workspaces checked — select some first', 'warn');
      return;
    }
    let perWs = loopCreditState.perWorkspace || [];
    let entries = [];
    let seqIdx = 0;
    const starts = (typeof startNum === 'object' && startNum !== null)
      ? startNum
      : { dollar: startNum || 1, hash: startNum || 1, star: startNum || 1 };
    for (let i = 0; i < perWs.length; i++) {
      if (loopWsCheckedIds[perWs[i].id]) {
        const newName = applyRenameTemplate(template || '', prefix || '', suffix || '', starts, seqIdx, perWs[i].fullName || perWs[i].name);
        entries.push({ wsId: perWs[i].id, oldName: perWs[i].fullName || perWs[i].name, newName: newName });
        seqIdx++;
      }
    }
    bulkRenameWorkspaces(entries, function(results, done) {
      if (done) {
        log('[Rename] Bulk rename finished: ' + results.success + '/' + results.total + ' success', results.failed > 0 ? 'warn' : 'success');
        populateLoopWorkspaceDropdown();
      }
    });
  };

  // Checkbox click handler (with Shift range select)
  function handleWsCheckboxClick(wsId, idx, isShift) {
    if (isShift && loopWsLastCheckedIdx >= 0) {
      let perWs = loopCreditState.perWorkspace || [];
      const lo = Math.min(loopWsLastCheckedIdx, idx);
      const hi = Math.max(loopWsLastCheckedIdx, idx);
      for (let s = lo; s <= hi; s++) {
        if (perWs[s] && perWs[s].id) {
          loopWsCheckedIds[perWs[s].id] = true;
        }
      }
    } else {
      if (loopWsCheckedIds[wsId]) {
        delete loopWsCheckedIds[wsId];
      } else {
        loopWsCheckedIds[wsId] = true;
      }
    }
    loopWsLastCheckedIdx = idx;
    updateWsSelectionUI();
  }

  function updateWsSelectionUI() {
    let count = Object.keys(loopWsCheckedIds).length;
    // Update checkboxes in rendered list
    const listEl = document.getElementById('loop-ws-list');
    if (listEl) {
      const items = listEl.querySelectorAll('.loop-ws-item');
      for (let i = 0; i < items.length; i++) {
        const cb = items[i].querySelector('.loop-ws-checkbox');
        if (cb) {
          const wsId = items[i].getAttribute('data-ws-id');
          cb.textContent = loopWsCheckedIds[wsId] ? '☑' : '☐';
          cb.style.color = loopWsCheckedIds[wsId] ? '#a78bfa' : '#64748b';
        }
      }
    }
    // Update selection count badge
    const badge = document.getElementById('loop-ws-sel-count');
    if (badge) {
      badge.textContent = count > 0 ? count + ' selected' : '';
      badge.style.display = count > 0 ? 'inline' : 'none';
    }
    // Show/hide rename button
    const renameBtn = document.getElementById('loop-ws-rename-btn');
    if (renameBtn) {
      renameBtn.style.display = count > 0 ? 'inline-block' : 'none';
    }
    // Select All toggle
    const allBtn = document.getElementById('loop-ws-select-all-btn');
    if (allBtn) {
      const total = (loopCreditState.perWorkspace || []).length;
      allBtn.textContent = count >= total && total > 0 ? '☐ None' : '☑ All';
    }
  }

  // Right-click context menu for single rename
  function showWsContextMenu(wsId, wsName, x, y) {
    removeWsContextMenu();
    const menu = document.createElement('div');
    menu.id = 'loop-ws-ctx-menu';
    menu.style.cssText = 'position:fixed;left:' + x + 'px;top:' + y + 'px;z-index:100001;background:' + cPanelBg + ';border:1px solid ' + cPrimary + ';border-radius:' + lDropdownRadius + ';padding:2px 0;box-shadow:0 4px 12px rgba(0,0,0,.5);min-width:100px;';

    const renameItem = document.createElement('div');
    renameItem.textContent = '✏️ Rename';
    renameItem.style.cssText = 'padding:5px 12px;font-size:' + tFontTiny + ';color:' + cPanelFg + ';cursor:pointer;';
    renameItem.onmouseover = function() { this.style.background = 'rgba(139,92,246,0.3)'; };
    renameItem.onmouseout = function() { this.style.background = 'transparent'; };
    renameItem.onclick = function() {
      removeWsContextMenu();
      startInlineRename(wsId, wsName);
    };

    menu.appendChild(renameItem);
    document.body.appendChild(menu);

    // Close on click outside
    setTimeout(function() {
      document.addEventListener('click', removeWsContextMenu, { once: true });
    }, 10);
  }

  function removeWsContextMenu() {
    let existing = document.getElementById('loop-ws-ctx-menu');
    if (existing) existing.remove();
  }

  function startInlineRename(wsId, currentName) {
    const listEl = document.getElementById('loop-ws-list');
    if (!listEl) return;
    const items = listEl.querySelectorAll('.loop-ws-item');
    for (let i = 0; i < items.length; i++) {
      if (items[i].getAttribute('data-ws-id') === wsId) {
        const nameDiv = items[i].querySelector('.loop-ws-name');
        if (!nameDiv) break;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentName;
        input.style.cssText = 'width:100%;padding:1px 3px;border:1px solid #a78bfa;border-radius:2px;background:#171b25;color:#e7e9ed;font-size:11px;outline:none;box-sizing:border-box;';
        input.onkeydown = function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            const newName = input.value.trim();
            if (!newName) { log('[Rename] Empty name — cancelled', 'warn'); populateLoopWorkspaceDropdown(); return; }
            if (newName === currentName) { populateLoopWorkspaceDropdown(); return; }
            renameWorkspace(wsId, newName).then(function() {
              // Update local state
              let perWs = loopCreditState.perWorkspace || [];
              for (let k = 0; k < perWs.length; k++) {
                if (perWs[k].id === wsId) { perWs[k].fullName = newName; perWs[k].name = newName; break; }
              }
              populateLoopWorkspaceDropdown();
              fetchLoopCredits(); // Refresh from API
            }).catch(function() { populateLoopWorkspaceDropdown(); });
          } else if (e.key === 'Escape') {
            populateLoopWorkspaceDropdown();
          }
        };
        nameDiv.textContent = '';
        nameDiv.appendChild(input);
        input.focus();
        input.select();
        break;
      }
    }
  }

  // v7.31: Floating Draggable Rename Panel
  // Detects which numbering variables ($, #, **) are used and shows per-variable start# controls
  function renderBulkRenameDialog() {
    removeBulkRenameDialog();
    const checkedIds = Object.keys(loopWsCheckedIds);
    if (checkedIds.length === 0) { log('[Rename] No workspaces selected', 'warn'); return; }

    let perWs = loopCreditState.perWorkspace || [];
    const selected = [];
    for (let i = 0; i < perWs.length; i++) {
      if (loopWsCheckedIds[perWs[i].id]) {
        selected.push(perWs[i]);
      }
    }

    // --- Floating draggable panel ---
    const panel = document.createElement('div');
    panel.id = 'ahk-loop-rename-dialog';
    panel.style.cssText = 'position:fixed;top:80px;right:40px;z-index:100002;background:rgba(23,27,37,0.97);border:1px solid #7c3aed;border-radius:8px;padding:0;min-width:420px;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.6);font-family:monospace;resize:both;overflow:hidden;';

    // --- Title bar (draggable) ---
    const titleBar = document.createElement('div');
    titleBar.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:rgba(124,58,237,0.2);cursor:grab;user-select:none;border-bottom:1px solid rgba(124,58,237,0.3);';
    const titleText = document.createElement('span');
    titleText.style.cssText = 'font-size:11px;color:#ae7ce8;font-weight:700;';
    titleText.textContent = '✏️ Bulk Rename — ' + selected.length + ' workspace' + (selected.length > 1 ? 's' : '');
    const closeBtnTitle = document.createElement('span');
    closeBtnTitle.style.cssText = 'cursor:pointer;color:#94a3b8;font-size:14px;padding:0 4px;';
    closeBtnTitle.textContent = '✕';
    closeBtnTitle.onclick = function() { removeBulkRenameDialog(); };
    titleBar.appendChild(titleText);
    titleBar.appendChild(closeBtnTitle);
    panel.appendChild(titleBar);

    // Drag logic — v7.32: Named handlers for proper cleanup
    let isDragging = false, dragOffX = 0, dragOffY = 0;
    function onDragMouseDown(e) {
      if (e.target === closeBtnTitle) return;
      isDragging = true;
      dragOffX = e.clientX - panel.getBoundingClientRect().left;
      dragOffY = e.clientY - panel.getBoundingClientRect().top;
      titleBar.style.cursor = 'grabbing';
      e.preventDefault();
    }
    function onDragMouseMove(e) {
      if (!isDragging) return;
      panel.style.left = (e.clientX - dragOffX) + 'px';
      panel.style.top = (e.clientY - dragOffY) + 'px';
      panel.style.right = 'auto';
    }
    function onDragMouseUp() {
      isDragging = false;
      titleBar.style.cursor = 'grab';
    }
    titleBar.addEventListener('mousedown', onDragMouseDown);
    document.addEventListener('mousemove', onDragMouseMove);
    document.addEventListener('mouseup', onDragMouseUp);

    // Store cleanup function for removeBulkRenameDialog
    panel.__cleanupDrag = function() {
      document.removeEventListener('mousemove', onDragMouseMove);
      document.removeEventListener('mouseup', onDragMouseUp);
    };

    // --- Body ---
    const body = document.createElement('div');
    body.style.cssText = 'padding:10px;';

    // Prefix row
    const prefixRow = document.createElement('div');
    prefixRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:6px;';
    const prefixCb = document.createElement('input');
    prefixCb.type = 'checkbox'; prefixCb.id = 'rename-prefix-cb';
    prefixCb.style.cssText = 'width:12px;height:12px;accent-color:#a78bfa;';
    const prefixLabel = document.createElement('span');
    prefixLabel.style.cssText = 'font-size:9px;color:#94a3b8;min-width:40px;';
    prefixLabel.textContent = 'Prefix';
    const prefixInput = document.createElement('input');
    prefixInput.type = 'text'; prefixInput.id = 'rename-prefix';
    prefixInput.placeholder = 'e.g. Team-';
    prefixInput.style.cssText = 'flex:1;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-size:10px;outline:none;font-family:monospace;';
    prefixRow.appendChild(prefixCb); prefixRow.appendChild(prefixLabel); prefixRow.appendChild(prefixInput);
    body.appendChild(prefixRow);

    // Template row
    const tmplRow = document.createElement('div');
    tmplRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:6px;';
    const tmplLabel = document.createElement('span');
    tmplLabel.style.cssText = 'font-size:9px;color:#94a3b8;min-width:52px;';
    tmplLabel.textContent = 'Template';
    const tmplInput = document.createElement('input');
    tmplInput.type = 'text'; tmplInput.id = 'rename-template';
    tmplInput.placeholder = 'e.g. Exp $$$$$ D3  or  P## or  Item***';
    tmplInput.style.cssText = 'flex:1;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-size:10px;outline:none;font-family:monospace;';
    tmplRow.appendChild(tmplLabel); tmplRow.appendChild(tmplInput);
    body.appendChild(tmplRow);

    // Suffix row
    const suffixRow = document.createElement('div');
    suffixRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:6px;';
    const suffixCb = document.createElement('input');
    suffixCb.type = 'checkbox'; suffixCb.id = 'rename-suffix-cb';
    suffixCb.style.cssText = 'width:12px;height:12px;accent-color:#a78bfa;';
    const suffixLabel = document.createElement('span');
    suffixLabel.style.cssText = 'font-size:9px;color:#94a3b8;min-width:40px;';
    suffixLabel.textContent = 'Suffix';
    const suffixInput = document.createElement('input');
    suffixInput.type = 'text'; suffixInput.id = 'rename-suffix';
    suffixInput.placeholder = 'e.g.  Dev';
    suffixInput.style.cssText = 'flex:1;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-size:10px;outline:none;font-family:monospace;';
    suffixRow.appendChild(suffixCb); suffixRow.appendChild(suffixLabel); suffixRow.appendChild(suffixInput);
    body.appendChild(suffixRow);

    // v7.31: Variable help hint
    const varHint = document.createElement('div');
    varHint.style.cssText = 'font-size:8px;color:#64748b;margin-bottom:6px;padding:2px 4px;background:rgba(0,0,0,.2);border-radius:2px;';
    varHint.innerHTML = 'Variables: <span style="color:#facc15">$$$</span> <span style="color:#a78bfa">###</span> <span style="color:#34d399">***</span> — zero-padded by count ($$$ → 001). Works in prefix, template, suffix.';
    body.appendChild(varHint);

    // v7.31: Dynamic start number rows — one per detected variable type
    const startNumsContainer = document.createElement('div');
    startNumsContainer.id = 'rename-start-nums';
    startNumsContainer.style.cssText = 'margin-bottom:6px;';
    body.appendChild(startNumsContainer);

    let startDollar = 1, startHash = 1, startStar = 1;

    function detectVarsAndRenderStarts() {
      const allText = tmplInput.value + (prefixCb.checked ? prefixInput.value : '') + (suffixCb.checked ? suffixInput.value : '');
      const hasDollar = /\$+/.test(allText);
      const hasHash = /#+/.test(allText);
      const hasStar = /\*{2,}/.test(allText);

      let html = '';
      if (hasDollar || hasHash || hasStar) {
        html += '<div style="font-size:8px;color:#94a3b8;margin-bottom:3px;">Start Numbers:</div>';
        html += '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
        if (hasDollar) {
          html += '<label style="display:flex;align-items:center;gap:3px;font-size:9px;color:#facc15;">$ <input type="number" id="rename-start-dollar" value="' + startDollar + '" min="0" style="width:50px;padding:2px 4px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#facc15;font-size:9px;font-family:monospace;"></label>';
        }
        if (hasHash) {
          html += '<label style="display:flex;align-items:center;gap:3px;font-size:9px;color:#a78bfa;"># <input type="number" id="rename-start-hash" value="' + startHash + '" min="0" style="width:50px;padding:2px 4px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#a78bfa;font-size:9px;font-family:monospace;"></label>';
        }
        if (hasStar) {
          html += '<label style="display:flex;align-items:center;gap:3px;font-size:9px;color:#34d399;">** <input type="number" id="rename-start-star" value="' + startStar + '" min="0" style="width:50px;padding:2px 4px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#34d399;font-size:9px;font-family:monospace;"></label>';
        }
        html += '</div>';
      }
      startNumsContainer.innerHTML = html;

      // Bind change events
      const dEl = document.getElementById('rename-start-dollar');
      const hEl = document.getElementById('rename-start-hash');
      const sEl = document.getElementById('rename-start-star');
      if (dEl) dEl.oninput = function() { startDollar = parseInt(dEl.value, 10) || 1; updatePreview(); };
      if (hEl) hEl.oninput = function() { startHash = parseInt(hEl.value, 10) || 1; updatePreview(); };
      if (sEl) sEl.oninput = function() { startStar = parseInt(sEl.value, 10) || 1; updatePreview(); };
    }

    // v7.31: Delay controller
    const delayRow = document.createElement('div');
    delayRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:6px;';
    const delayLabel = document.createElement('span');
    delayLabel.style.cssText = 'font-size:9px;color:#94a3b8;min-width:52px;';
    delayLabel.textContent = 'Delay (ms)';
    const delaySlider = document.createElement('input');
    delaySlider.type = 'range'; delaySlider.min = '100'; delaySlider.max = '10000'; delaySlider.step = '100';
    delaySlider.value = String(RENAME_DELAY_MS);
    delaySlider.style.cssText = 'flex:1;accent-color:#a78bfa;height:4px;';
    const delayVal = document.createElement('span');
    delayVal.style.cssText = 'font-size:9px;color:#22d3ee;min-width:42px;text-align:right;';
    delayVal.textContent = RENAME_DELAY_MS + 'ms';
    delaySlider.oninput = function() {
      RENAME_DELAY_MS = parseInt(delaySlider.value, 10);
      delayVal.textContent = RENAME_DELAY_MS + 'ms';
    };
    delayRow.appendChild(delayLabel); delayRow.appendChild(delaySlider); delayRow.appendChild(delayVal);
    body.appendChild(delayRow);

    // v7.31: Token refresh row
    const tokenRow = document.createElement('div');
    tokenRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:8px;';
    const tokenLabel = document.createElement('span');
    tokenLabel.style.cssText = 'font-size:8px;color:#64748b;';
    tokenLabel.textContent = 'Auth: ' + (LAST_TOKEN_SOURCE || 'none');
    tokenLabel.id = 'rename-auth-label';
    const tokenRefreshBtn = document.createElement('button');
    tokenRefreshBtn.textContent = '🔄 Refresh Token';
    tokenRefreshBtn.style.cssText = 'padding:2px 6px;background:rgba(124,58,237,0.2);color:#ae7ce8;border:1px solid rgba(124,58,237,0.4);border-radius:3px;font-size:8px;cursor:pointer;';
    tokenRefreshBtn.onclick = function() {
      // Re-read session cookie into localStorage
      const cookieToken = getBearerTokenFromCookie();
      if (cookieToken) {
        try { localStorage.setItem('marco_bearer_token', cookieToken); } catch(e) {}
        LAST_SESSION_BRIDGE_SOURCE = '';
        log('[Rename] Token refreshed from cookie: ' + cookieToken.substring(0, 12) + '...', 'success');
        showToast('Token refreshed from session cookie', 'success');
      } else {
        log('[Rename] No cookie token available to refresh', 'warn');
        showToast('No session cookie found — login may be required', 'warn');
      }
      // Update display
      const newToken = resolveToken();
      const lbl = document.getElementById('rename-auth-label');
      if (lbl) lbl.textContent = 'Auth: ' + LAST_TOKEN_SOURCE;
    };
    tokenRow.appendChild(tokenLabel); tokenRow.appendChild(tokenRefreshBtn);
    body.appendChild(tokenRow);

    // Preview
    const previewLabel = document.createElement('div');
    previewLabel.style.cssText = 'font-size:9px;color:#94a3b8;margin-bottom:3px;';
    previewLabel.textContent = 'Preview:';
    body.appendChild(previewLabel);

    const previewList = document.createElement('div');
    previewList.id = 'rename-preview-list';
    previewList.style.cssText = 'max-height:150px;overflow-y:auto;border:1px solid rgba(124,58,237,0.3);border-radius:3px;background:rgba(0,0,0,.3);padding:4px;margin-bottom:8px;font-size:9px;';
    body.appendChild(previewList);

    function getStartNums() {
      return { dollar: startDollar, hash: startHash, star: startStar };
    }

    function updatePreview() {
      detectVarsAndRenderStarts();
      const template = tmplInput.value;
      const prefix = prefixCb.checked ? prefixInput.value : '';
      const suffix = suffixCb.checked ? suffixInput.value : '';
      const starts = getStartNums();
      let html = '';
      for (let j = 0; j < selected.length; j++) {
        const origName = selected[j].fullName || selected[j].name;
        const newName = applyRenameTemplate(template, prefix, suffix, starts, j, origName);
        html += '<div style="display:flex;gap:6px;padding:2px 0;border-bottom:1px solid rgba(255,255,255,.05);">'
          + '<span style="color:#94a3b8;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + origName.replace(/"/g, '&quot;') + '">' + origName + '</span>'
          + '<span style="color:#64748b;">→</span>'
          + '<span style="color:#67e8f9;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;" title="' + newName.replace(/"/g, '&quot;') + '">' + newName + '</span>'
          + '</div>';
      }
      previewList.innerHTML = html || '<div style="color:#64748b;">No changes</div>';
    }

    // Live preview on any input change
    tmplInput.oninput = updatePreview;
    prefixInput.oninput = updatePreview;
    suffixInput.oninput = updatePreview;
    prefixCb.onchange = updatePreview;
    suffixCb.onchange = updatePreview;

    updatePreview();

    // v7.32: ETA display — uses rolling average of actual operation time
    const etaRow = document.createElement('div');
    etaRow.id = 'rename-eta-row';
    etaRow.style.cssText = 'font-size:8px;color:#64748b;margin-bottom:6px;display:none;';
    body.appendChild(etaRow);

    function formatEta(ms) {
      if (ms < 1000) return ms + 'ms';
      const secs = Math.ceil(ms / 1000);
      if (secs < 60) return secs + 's';
      const mins = Math.floor(secs / 60);
      const remSecs = secs % 60;
      return mins + 'm ' + (remSecs > 0 ? remSecs + 's' : '');
    }

    function updateEta(completed, total) {
      const remaining = total - completed;
      if (remaining <= 0) {
        etaRow.style.display = 'none';
        return;
      }
      // v7.32: Use rolling average if available, otherwise fall back to delay estimate
      const perOpMs = RENAME_AVG_OP_MS > 0 ? RENAME_AVG_OP_MS : RENAME_DELAY_MS;
      const etaMs = remaining * perOpMs;
      const avgLabel = RENAME_AVG_OP_MS > 0
        ? ' (avg ' + RENAME_AVG_OP_MS + 'ms/op)'
        : ' (est. ' + RENAME_DELAY_MS + 'ms/op)';
      etaRow.style.display = 'block';
      etaRow.innerHTML = '⏱ ETA: <span style="color:#22d3ee;">' + formatEta(etaMs) + '</span> remaining — ' + remaining + ' items' + avgLabel;
    }

    // Show static ETA estimate before starting
    function updateStaticEta() {
      let count = selected.length;
      if (count > 0) {
        const etaMs = count * RENAME_DELAY_MS;
        etaRow.style.display = 'block';
        etaRow.innerHTML = '⏱ Est. total: <span style="color:#94a3b8;">' + formatEta(etaMs) + '</span> for ' + count + ' items @ ' + RENAME_DELAY_MS + 'ms delay';
      }
    }

    // Update static ETA when delay changes
    const origDelayHandler = delaySlider.oninput;
    delaySlider.oninput = function() {
      RENAME_DELAY_MS = parseInt(delaySlider.value, 10);
      delayVal.textContent = RENAME_DELAY_MS + 'ms';
      updateStaticEta();
    };
    updateStaticEta();

    // --- Button row ---
    const btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;gap:6px;justify-content:flex-end;padding:8px 10px;border-top:1px solid rgba(124,58,237,0.2);';

    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = 'Cancel';
    cancelBtn.style.cssText = 'padding:4px 12px;background:rgba(100,116,139,0.3);color:#94a3b8;border:1px solid #475569;border-radius:4px;font-size:10px;cursor:pointer;';
    cancelBtn.onclick = function() { removeBulkRenameDialog(); };

    const stopBtn = document.createElement('button');
    stopBtn.textContent = '⏹ Stop';
    stopBtn.id = 'rename-stop-btn';
    stopBtn.style.cssText = 'display:none;padding:4px 12px;background:rgba(239,68,68,0.3);color:#f87171;border:1px solid rgba(239,68,68,0.4);border-radius:4px;font-size:10px;font-weight:700;cursor:pointer;';
    stopBtn.onclick = function() { RENAME_CANCELLED = true; log('[Rename] Stop requested by user', 'warn'); };

    const applyBtn = document.createElement('button');
    applyBtn.id = 'ahk-loop-rename-apply';
    applyBtn.textContent = '✅ Apply';
    applyBtn.style.cssText = 'padding:4px 12px;background:#059669;color:#fff;border:none;border-radius:4px;font-size:10px;font-weight:700;cursor:pointer;';
    applyBtn.onclick = function() {
      const template = tmplInput.value;
      const prefix = prefixCb.checked ? prefixInput.value : '';
      const suffix = suffixCb.checked ? suffixInput.value : '';
      const starts = getStartNums();

      if (!template && !prefix && !suffix) {
        log('[Rename] Nothing to rename — provide template, prefix, or suffix', 'warn');
        return;
      }

      let entries = [];
      for (let j = 0; j < selected.length; j++) {
        const origName = selected[j].fullName || selected[j].name;
        const newName = applyRenameTemplate(template, prefix, suffix, starts, j, origName);
        if (!newName.trim()) continue;
        entries.push({ wsId: selected[j].id, oldName: origName, newName: newName });
      }

      if (entries.length === 0) { log('[Rename] All names empty — cancelled', 'warn'); return; }

      // Show stop button, disable apply
      applyBtn.disabled = true;
      applyBtn.textContent = 'Renaming... 0/' + entries.length;
      applyBtn.style.background = '#64748b';
      stopBtn.style.display = 'inline-block';
      cancelBtn.style.display = 'none';

      bulkRenameWorkspaces(entries, function(results, done) {
        const completed = results.success + results.failed;
        if (done) {
          let statusText = results.cancelled
            ? '⏹ Stopped: ' + results.success + '/' + results.total
            : '✅ ' + results.success + '/' + results.total + (results.failed > 0 ? ' (' + results.failed + ' failed)' : ' done');
          applyBtn.textContent = statusText;
          applyBtn.style.background = results.cancelled ? '#d97706' : (results.failed > 0 ? '#d97706' : '#059669');
          stopBtn.style.display = 'none';
          etaRow.style.display = 'none';
          setTimeout(function() {
            applyBtn.disabled = false;
            applyBtn.textContent = '✅ Apply';
            applyBtn.style.background = '#059669';
            cancelBtn.style.display = 'inline-block';
            updateStaticEta();
            populateLoopWorkspaceDropdown();
          }, 2000);
        } else {
          applyBtn.textContent = 'Renaming... ' + completed + '/' + results.total
            + (results.success > 0 ? ' ✅' + results.success : '')
            + (results.failed > 0 ? ' ❌' + results.failed : '');
          updateEta(completed, results.total);
        }
      });
    };

    btnRow.appendChild(cancelBtn);
    btnRow.appendChild(stopBtn);
    btnRow.appendChild(applyBtn);

    panel.appendChild(body);
    panel.appendChild(btnRow);
    document.body.appendChild(panel);
  }

  // v7.32: Clean up drag listeners and cancel any in-progress rename
  function removeBulkRenameDialog() {
    RENAME_CANCELLED = true;
    const d = document.getElementById('ahk-loop-rename-dialog');
    if (d) {
      if (typeof d.__cleanupDrag === 'function') d.__cleanupDrag();
      d.remove();
    }
  }

  // Move to adjacent workspace in the loaded list (API-based, used by F-Up/F-Down)
  // v7.9.40: Fetches fresh workspace data before moving, and skips workspaces with dailyFree=0.
  // Walks in the given direction (wrapping around) until it finds one with dailyFree > 0.
  // If none have free credits, falls back to the immediate next workspace.
  function moveToAdjacentWorkspace(direction) {
    log('moveToAdjacentWorkspace(' + direction + '): Fetching fresh workspace data before move...', 'delegate');
    updateLoopMoveStatus('loading', 'Fetching workspaces...');

    const url = CREDIT_API_BASE + '/user/workspaces';
    const token = resolveToken();

    function doFetchWorkspaces(tkn, isRetry) {
      const h = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
      if (tkn) h['Authorization'] = 'Bearer ' + tkn;

    fetch(url, { method: 'GET', headers: h, credentials: 'include' })
      .then(function(resp) {
        // v7.25: Token fallback retry for workspace fetch
        if ((resp.status === 401 || resp.status === 403) && tkn && !isRetry) {
          const invalidatedKey = invalidateSessionBridgeKey(tkn);
          log('moveToAdjacentWorkspace: Auth ' + resp.status + ' — invalidated "' + invalidatedKey + '", retrying with fallback', 'warn');
          showToast('Workspace fetch auth ' + resp.status + ' — token "' + invalidatedKey + '" expired, retrying...', 'warn');
          const fallbackToken = resolveToken();
          doFetchWorkspaces(fallbackToken, true);
          return;
        }
        if (!resp.ok) {
          return resp.text().then(function(errBody) {
            throw new Error('HTTP ' + resp.status + ' ' + resp.statusText + ': ' + (errBody || '').substring(0, 200));
          });
        }
        return resp.text().then(function(bodyText) {
          if (!bodyText) throw new Error('Empty response body');
          let data;
          try { data = JSON.parse(bodyText); } catch(e) { throw new Error('JSON parse: ' + e.message); }
          return data;
        });
      })
      .then(function(data) {
        const ok = parseLoopApiResponse(data);
        if (!ok) {
          log('moveToAdjacentWorkspace: Failed to parse workspace data', 'error');
          updateLoopMoveStatus('error', 'Failed to parse workspaces');
          return;
        }
        const workspaces = loopCreditState.perWorkspace || [];
        if (workspaces.length === 0) {
          log('No workspaces loaded from API', 'error');
          updateLoopMoveStatus('error', 'No workspaces found');
          return;
        }

        log('moveToAdjacentWorkspace: Fresh data loaded — ' + workspaces.length + ' workspaces', 'success');

        // Find current workspace index
        let currentName = state.workspaceName || '';
        let currentIdx = -1;
        for (let i = 0; i < workspaces.length; i++) {
          if (workspaces[i].fullName === currentName || workspaces[i].name === currentName) {
            currentIdx = i;
            break;
          }
        }
        if (currentIdx === -1 && currentName) {
          const lowerName = currentName.toLowerCase();
          for (let pi = 0; pi < workspaces.length; pi++) {
            if ((workspaces[pi].fullName || '').toLowerCase().indexOf(lowerName) !== -1 ||
                lowerName.indexOf((workspaces[pi].fullName || '').toLowerCase()) !== -1) {
              currentIdx = pi;
              log('Workspace partial match: "' + currentName + '" ~ "' + workspaces[pi].fullName + '"', 'warn');
              break;
            }
          }
        }
        if (currentIdx === -1) {
          log('Current workspace "' + currentName + '" not found — using idx 0', 'warn');
          currentIdx = 0;
        }

        // v7.9.40: Walk in direction, find first workspace with dailyFree > 0
        let len = workspaces.length;
        let step = direction === 'up' ? -1 : 1;
        let targetIdx = -1;
        let fallbackIdx = -1; // immediate next, used if none have free credits

        for (let s = 1; s <= len; s++) {
          const candidateIdx = ((currentIdx + step * s) % len + len) % len;
          if (candidateIdx === currentIdx) continue; // wrapped all the way around

          // Track the immediate next as fallback
          if (fallbackIdx === -1) fallbackIdx = candidateIdx;

          const candidate = workspaces[candidateIdx];
          const candidateDailyFree = candidate.dailyFree || 0;
          logSub('Checking ' + direction + ' #' + s + ': "' + candidate.fullName + '" dailyFree=' + candidateDailyFree, 1);

          if (candidateDailyFree > 0) {
            targetIdx = candidateIdx;
            log('Found workspace with free credit: "' + candidate.fullName + '" (dailyFree=' + candidateDailyFree + ', ' + s + ' step(s) ' + direction + ')', 'success');
            break;
          }
        }

        if (targetIdx === -1) {
          log('⚠️ No workspace has dailyFree > 0 — falling back to immediate ' + direction + ' neighbor', 'warn');
          targetIdx = fallbackIdx !== -1 ? fallbackIdx : ((currentIdx + step) % len + len) % len;
        }

        let target = workspaces[targetIdx];
        const targetId = (target.raw && target.raw.id) || target.id || '';
        const skipped = Math.abs(targetIdx - currentIdx);
        if (skipped < 0) skipped += len;
        log('API Move ' + direction.toUpperCase() + ': "' + currentName + '" (#' + currentIdx + ') -> "' + target.fullName + '" (#' + targetIdx + ') dailyFree=' + (target.dailyFree || 0) + (skipped > 1 ? ' (skipped ' + (skipped - 1) + ' depleted)' : ''), 'delegate');
        moveToWorkspace(targetId, target.fullName);

        // Update UI with fresh data
        syncCreditStateFromApi();
        updateUI();
      })
      .catch(function(err) {
        log('moveToAdjacentWorkspace: Fetch failed — ' + err.message + '. Falling back to cached data.', 'error');
        // Fallback: use cached data with old logic
        moveToAdjacentWorkspaceCached(direction);
      });
    } // end doFetchWorkspaces

    doFetchWorkspaces(token, false);
  }

  // Fallback: move using cached workspace data (no fresh fetch)
  function moveToAdjacentWorkspaceCached(direction) {
    const workspaces = loopCreditState.perWorkspace || [];
    if (workspaces.length === 0) {
      log('No cached workspaces — click 💳 first', 'error');
      updateLoopMoveStatus('error', 'Load workspaces first (💳)');
      return;
    }
    let currentName = state.workspaceName || '';
    let currentIdx = -1;
    for (let i = 0; i < workspaces.length; i++) {
      if (workspaces[i].fullName === currentName || workspaces[i].name === currentName) {
        currentIdx = i;
        break;
      }
    }
    if (currentIdx === -1) currentIdx = 0;
    let len = workspaces.length;
    let step = direction === 'up' ? -1 : 1;
    let targetIdx = ((currentIdx + step) % len + len) % len;
    let target = workspaces[targetIdx];
    const targetId = (target.raw && target.raw.id) || target.id || '';
    log('API Move (cached fallback) ' + direction.toUpperCase() + ': -> "' + target.fullName + '"', 'delegate');
    moveToWorkspace(targetId, target.fullName);
  }
  window.__loopMoveAdjacent = moveToAdjacentWorkspace;

  // ============================================
  // Workspace Dropdown State & Rendering (MacroLoop)
  // ============================================
  let loopWsNavIndex = -1;
  let loopWsFreeOnly = false;
  let loopWsCompactMode = (function() { try { const v = localStorage.getItem('ml_compact_mode'); return v === null ? true : v === 'true'; } catch(e) { return true; } })();

  function triggerLoopMoveFromSelection() {
    const selectedEl = document.getElementById('loop-ws-selected');
    const wsId = selectedEl ? selectedEl.getAttribute('data-selected-id') : '';
    const wsName = selectedEl ? selectedEl.getAttribute('data-selected-name') : '';
    if (!wsId) {
      log('No workspace selected for move', 'warn');
      updateLoopMoveStatus('error', 'Select a workspace first');
      return;
    }
    log('Moving project to workspace=' + wsId + ' (' + wsName + ')', 'delegate');
    moveToWorkspace(wsId, wsName);
  }

  function setLoopWsNavIndex(idx) {
    loopWsNavIndex = idx;
    const listEl = document.getElementById('loop-ws-list');
    if (!listEl) return;
    const items = listEl.querySelectorAll('.loop-ws-item');
    for (let i = 0; i < items.length; i++) {
      let isCurrent = items[i].getAttribute('data-ws-current') === 'true';
      if (i === idx) {
        items[i].style.background = 'rgba(139,92,246,0.25)';
        items[i].style.outline = '1px solid #a78bfa';
        items[i].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        const wsId = items[i].getAttribute('data-ws-id');
        const wsName = items[i].getAttribute('data-ws-name');
        const selectedEl = document.getElementById('loop-ws-selected');
        if (selectedEl) {
          selectedEl.setAttribute('data-selected-id', wsId);
          selectedEl.setAttribute('data-selected-name', wsName);
          selectedEl.textContent = '✅ ' + wsName;
          selectedEl.style.color = '#4ade80';
        }
      } else {
        items[i].style.outline = 'none';
        items[i].style.background = isCurrent ? 'rgba(139,92,246,0.15)' : 'transparent';
      }
    }
  }

  function buildLoopTooltipText(ws) {
    const lines = [];
    lines.push('━━━ ' + (ws.fullName || ws.name) + ' ━━━');
    lines.push('');
    lines.push('📊 CALCULATED:');
    lines.push('  🆓 Daily Free: ' + (ws.dailyFree || 0) + ' (' + ws.dailyLimit + ' - ' + ws.dailyUsed + ')');
    lines.push('  🔄 Rollover: ' + (ws.rollover || 0) + ' (' + ws.rolloverLimit + ' - ' + ws.rolloverUsed + ')');
    lines.push('  💰 Available: ' + (ws.available || 0) + ' (total:' + (ws.totalCredits || 0) + ' - rUsed:' + (ws.rolloverUsed || 0) + ' - dUsed:' + (ws.dailyUsed || 0) + ' - bUsed:' + (ws.used || 0) + ')');
    lines.push('  📦 Billing Only: ' + (ws.billingAvailable || 0) + ' (' + ws.limit + ' - ' + ws.used + ')');
    const _tc = ws.totalCredits || calcTotalCredits(ws.freeGranted, ws.dailyLimit, ws.limit, ws.topupLimit, ws.rolloverLimit);
    lines.push('  ⚡ Total Credits: ' + _tc + ' (granted:' + (ws.freeGranted||0) + ' + daily:' + (ws.dailyLimit||0) + ' + billing:' + (ws.limit||0) + ' + topup:' + (ws.topupLimit||0) + ' + rollover:' + (ws.rolloverLimit||0) + ')');
    lines.push('');
    lines.push('📋 RAW DATA:');
    lines.push('  ID: ' + ws.id);
    lines.push('  Billing: ' + ws.used + '/' + ws.limit + ' used');
    lines.push('  Rollover: ' + ws.rolloverUsed + '/' + ws.rolloverLimit + ' used');
    lines.push('  Daily: ' + ws.dailyUsed + '/' + ws.dailyLimit + ' used');
    if (ws.freeGranted > 0) {
      lines.push('  Trial: ' + ws.freeRemaining + '/' + ws.freeGranted + ' remaining');
    }
    lines.push('  Status: ' + (ws.subscriptionStatus || 'N/A'));
    lines.push('  Role: ' + (ws.role || 'N/A'));
    if (ws.raw) {
      let r = ws.raw;
      if (r.last_trial_credit_period) lines.push('  Trial Period: ' + r.last_trial_credit_period);
      if (r.subscription_status) lines.push('  Subscription: ' + r.subscription_status);
    }
    return lines.join('\n');
  }

  function renderLoopWorkspaceList(workspaces, currentName, filter) {
    const listEl = document.getElementById('loop-ws-list');
    if (!listEl) return;
    let html = '';
    let count = 0;
    let currentIdx = -1;
    // v7.23: Pre-compute max totalCredits across all visible workspaces for relative bar scaling
    let maxTotalCredits = 0;
    for (let mi = 0; mi < workspaces.length; mi++) {
      const mws = workspaces[mi];
      const mtc = Math.round(mws.totalCredits || calcTotalCredits(mws.freeGranted, mws.dailyLimit, mws.limit, mws.topupLimit, mws.rolloverLimit));
      if (mtc > maxTotalCredits) maxTotalCredits = mtc;
    }
    for (let i = 0; i < workspaces.length; i++) {
      const ws = workspaces[i];
      let isCurrent = ws.fullName === currentName || ws.name === currentName;
      // Partial match fallback (case-insensitive contains)
      if (!isCurrent && currentName) {
        const lcn = currentName.toLowerCase();
        isCurrent = (ws.fullName || '').toLowerCase().indexOf(lcn) !== -1 ||
                    lcn.indexOf((ws.fullName || '').toLowerCase()) !== -1;
      }
      const matchesFilter = !filter || ws.fullName.toLowerCase().indexOf(filter.toLowerCase()) !== -1 || ws.name.toLowerCase().indexOf(filter.toLowerCase()) !== -1;
      if (!matchesFilter) continue;
      if (loopWsFreeOnly && (ws.dailyFree || 0) <= 0) continue;
      // Advanced filters
      const rolloverFilterEl = document.getElementById('loop-ws-rollover-filter');
      const rolloverOnly = rolloverFilterEl && rolloverFilterEl.getAttribute('data-active') === 'true';
      if (rolloverOnly && (ws.rollover || 0) <= 0) continue;
      const billingFilterEl = document.getElementById('loop-ws-billing-filter');
      const billingOnlyF = billingFilterEl && billingFilterEl.getAttribute('data-active') === 'true';
      if (billingOnlyF && (ws.billingAvailable || 0) <= 0) continue;
      const minCreditsEl = document.getElementById('loop-ws-min-credits');
      const minCreditsVal = minCreditsEl ? parseInt(minCreditsEl.value, 10) || 0 : 0;
      if (minCreditsVal > 0 && (ws.available || 0) < minCreditsVal) continue;
      if (isCurrent) currentIdx = count;
      count++;
      let dailyFree = Math.round(ws.dailyFree || 0);
      let rollover = Math.round(ws.rollover || 0);
      let available = Math.round(ws.available || 0);
      const billingAvail = Math.round(ws.billingAvailable || 0);
      const limitInt = Math.round(ws.limit || 0);
      const emoji = isCurrent ? '📍' : (available <= 0 ? '🔴' : available <= limitInt * 0.2 ? '🟡' : '🟢');
      const nameColor = isCurrent ? '#67e8f9' : '#e2e8f0';
      const nameBold = isCurrent ? 'font-weight:800;' : 'font-weight:500;';
      const bgStyle = isCurrent ? 'background:rgba(139,92,246,0.15);border-left:3px solid #a78bfa;' : 'border-left:3px solid transparent;';
      const dfColor = dailyFree > 0 ? '#4ade80' : '#f87171';
      const roColor = rollover > 0 ? '#c4b5fd' : '#f87171';
      const avColor = available > 0 ? '#67e8f9' : '#f87171';

      const wsId = ws.id || (ws.raw && ws.raw.id) || '';
      const isChecked = !!loopWsCheckedIds[wsId];
      const tooltip = buildLoopTooltipText(ws).replace(/"/g, '&quot;');
      html += '<div class="loop-ws-item" data-ws-id="' + wsId + '" data-ws-name="' + (ws.fullName || ws.name).replace(/"/g, '&quot;') + '" data-ws-current="' + isCurrent + '" data-ws-idx="' + (count - 1) + '" data-ws-raw-idx="' + i + '"'
        + ' title="' + tooltip + '"'
        + ' style="display:flex;align-items:center;gap:4px;padding:5px 6px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05);transition:background 0.15s;font-size:11px;' + bgStyle + '"'
        + ' onmouseover="if(this.getAttribute(\'data-ws-current\')!==\'true\')this.style.background=\'rgba(59,130,246,0.15)\'"'
        + ' onmouseout="if(this.getAttribute(\'data-ws-current\')!==\'true\')this.style.background=\'transparent\'">'
        + '<span class="loop-ws-checkbox" style="font-size:11px;cursor:pointer;color:' + (isChecked ? '#a78bfa' : '#64748b') + ';user-select:none;flex-shrink:0;">' + (isChecked ? '☑' : '☐') + '</span>'
        + '<span style="font-size:12px;">' + emoji + '</span>'
        + '<div style="flex:1;min-width:0;">'
        + '<div class="loop-ws-name" style="color:' + nameColor + ';font-size:11px;' + nameBold + 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (ws.fullName || ws.name) + '</div>'
        + '<div style="display:flex;align-items:center;gap:4px;margin-top:2px;">'
        + (function() {
            const _totalCapacity = Math.round(ws.totalCredits || calcTotalCredits(ws.freeGranted, ws.dailyLimit, ws.limit, ws.topupLimit, ws.rolloverLimit));
          const _fr = Math.round(ws.freeRemaining || 0);
          const _availTotal = Math.round(ws.available || 0);
          return renderCreditBar({
            totalCredits: _totalCapacity, available: _availTotal, totalUsed: ws.totalCreditsUsed || 0,
            freeRemaining: _fr, billingAvail: billingAvail, rollover: rollover, dailyFree: dailyFree,
            compact: loopWsCompactMode, maxTotalCredits: maxTotalCredits
          });
        })()
        + '</div>'
        + '</div>'
        + (isCurrent ? '<span style="font-size:8px;color:#a78bfa;background:rgba(139,92,246,0.3);padding:1px 4px;border-radius:3px;font-weight:700;">NOW</span>' : '')
        + '</div>';
    }
    if (count === 0) {
      html = '<div style="padding:8px;color:#a78bfa;font-size:10px;text-align:center;">🔍 No matches</div>';
    }
    listEl.innerHTML = html;
    loopWsNavIndex = -1;

    // v7.9.52: Update workspace count label
    const countLabel = document.getElementById('loop-ws-count-label');
    if (countLabel) {
      const total = workspaces.length;
      if (filter || loopWsFreeOnly || count !== total) {
        countLabel.textContent = 'Workspaces (' + count + '/' + total + ')';
      } else {
        countLabel.textContent = 'Workspaces (' + total + ')';
      }
    }

    // Bind click, double-click, checkbox, and right-click events
    const items = listEl.querySelectorAll('.loop-ws-item');
    for (let j = 0; j < items.length; j++) {
      // Checkbox click
      (function(item) {
        const cb = item.querySelector('.loop-ws-checkbox');
        if (cb) {
          cb.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            const wsId = item.getAttribute('data-ws-id');
            const rawIdx = parseInt(item.getAttribute('data-ws-raw-idx'), 10);
            handleWsCheckboxClick(wsId, rawIdx, e.shiftKey);
          };
        }
      })(items[j]);

      // Row click (not checkbox)
      items[j].onclick = (function(item) {
        return function(e) {
          // Don't fire if checkbox was clicked
          if (e.target.classList && e.target.classList.contains('loop-ws-checkbox')) return;
          let idx = parseInt(item.getAttribute('data-ws-idx'), 10);
          setLoopWsNavIndex(idx);
          log('Selected workspace: ' + item.getAttribute('data-ws-name'), 'success');
        };
      })(items[j]);

      // Double-click: immediately move to workspace
      items[j].ondblclick = (function(item) {
        return function(e) {
          e.preventDefault();
          e.stopPropagation();
          const wsId = item.getAttribute('data-ws-id');
          const wsName = item.getAttribute('data-ws-name');
          let isCurrent = item.getAttribute('data-ws-current') === 'true';
          if (isCurrent) {
            log('Double-click on current workspace "' + wsName + '" — no move needed', 'warn');
            return;
          }
          log('Double-click move -> ' + wsName + ' (id=' + wsId + ')', 'delegate');
          moveToWorkspace(wsId, wsName);
        };
      })(items[j]);

      // Right-click: context menu for single rename
      items[j].oncontextmenu = (function(item) {
        return function(e) {
          e.preventDefault();
          e.stopPropagation();
          const wsId = item.getAttribute('data-ws-id');
          const wsName = item.getAttribute('data-ws-name');
          showWsContextMenu(wsId, wsName, e.clientX, e.clientY);
        };
      })(items[j]);
    }

    // Auto-scroll to current workspace
    if (currentIdx >= 0 && !filter) {
      setTimeout(function() {
        const currentItem = listEl.querySelector('.loop-ws-item[data-ws-current="true"]');
        if (currentItem) currentItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        // Auto-select current if nothing selected
        const selectedEl = document.getElementById('loop-ws-selected');
        if (selectedEl && !selectedEl.getAttribute('data-selected-id')) {
          setLoopWsNavIndex(currentIdx);
        }
      }, 50);
    }
  }

  function populateLoopWorkspaceDropdown() {
    const listEl = document.getElementById('loop-ws-list');
    if (!listEl) return;
    const workspaces = loopCreditState.perWorkspace || [];
    if (workspaces.length === 0) {
      listEl.innerHTML = '<div style="padding:6px;color:#a78bfa;font-size:10px;">📭 Click 💳 to load workspaces</div>';
      return;
    }
    let currentName = state.workspaceName || '';
    const searchEl = document.getElementById('loop-ws-search');
    let filter = searchEl ? searchEl.value.trim() : '';
    renderLoopWorkspaceList(workspaces, currentName, filter);
    log('Workspace dropdown populated: ' + workspaces.length + ' workspaces', 'success');
  }

  // Override updateUI to also refresh workspace dropdown
  let _origUpdateUI;
  // Will be patched after createUI

  let hasXPathUtils = typeof window.XPathUtils !== 'undefined';
  if (hasXPathUtils) {
    // Route XPathUtils logs into macroloop's localStorage log system
    window.XPathUtils.setLogger(
      function(fn, msg) { log('[XPathUtils.' + fn + '] ' + msg, 'check'); },
      function(fn, msg) { logSub(msg); },
      function(fn, msg) { log('[XPathUtils.' + fn + '] WARN: ' + msg, 'warn'); }
    );
    log('XPathUtils v' + window.XPathUtils.version + ' detected — using shared utilities', 'success');
  } else {
    log('XPathUtils NOT found — using inline fallback', 'warn');
    // Deferred retry
    setTimeout(function() {
      if (typeof window.XPathUtils !== 'undefined' && !hasXPathUtils) {
        hasXPathUtils = true;
        window.XPathUtils.setLogger(
          function(fn, msg) { log('[XPathUtils.' + fn + '] ' + msg, 'check'); },
          function(fn, msg) { logSub(msg); },
          function(fn, msg) { log('[XPathUtils.' + fn + '] WARN: ' + msg, 'warn'); }
        );
        log('XPathUtils detected on deferred retry (500ms)', 'success');
      }
    }, 500);
  }

  // React-compatible click: delegates to XPathUtils if available
  function reactClick(el, callerXpath) {
    if (hasXPathUtils) {
      window.XPathUtils.reactClick(el, callerXpath);
      return;
    }
    // Fallback: inline implementation
    const fn = 'reactClick';
    const tag = '<' + el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + '>';
    log('[' + fn + '] Clicking ' + tag + ' | XPath: ' + (callerXpath || '(no xpath)') + ' [FALLBACK]', 'check');
    let rect = el.getBoundingClientRect();
    let cx = rect.left + rect.width / 2;
    let cy = rect.top + rect.height / 2;
    let opts = { view: window, bubbles: true, cancelable: true, button: 0, buttons: 1, clientX: cx, clientY: cy };
    const pointerOpts = { view: window, bubbles: true, cancelable: true, button: 0, buttons: 1, clientX: cx, clientY: cy, pointerId: 1, pointerType: 'mouse', isPrimary: true };
    el.dispatchEvent(new PointerEvent('pointerdown', pointerOpts));
    el.dispatchEvent(new MouseEvent('mousedown', opts));
    el.dispatchEvent(new PointerEvent('pointerup', pointerOpts));
    el.dispatchEvent(new MouseEvent('mouseup', opts));
    el.dispatchEvent(new MouseEvent('click', opts));
    logSub('All 5 events dispatched [FALLBACK]');
  }

  // v7.20: Mark bearer token as expired — log-only, no UI injection (cookie is auto-resolved)
  function markBearerTokenExpired(controller) {
    log('[' + controller + '] Bearer token expired (401/403) — will re-read from session cookie on next request', 'warn');
  }
  // ============================================
  // Loop State
  // ============================================
  let state = {
    running: false,
    direction: 'down',
    cycleCount: 0,
    countdown: 0,
    isIdle: false,
    isDelegating: false,
    forceDirection: null,  // v6.55: 'up'/'down' when Force button triggered, null otherwise
    delegateStartTime: 0,
    loopIntervalId: null,
    countdownIntervalId: null,
    workspaceName: '',
    hasFreeCredit: false,
    lastStatusCheck: 0,
    statusRefreshId: null,
    workspaceJustChanged: false,
    workspaceChangedTimer: null,
    workspaceObserverActive: false,
    workspaceFromApi: false,  // v7.9.16: true once API has authoritatively set workspace name
    // v7.24: T-1 Auto-retry state
    retryCount: 0,
    maxRetries: (loopCfg.retry && loopCfg.retry.maxRetries) || 3,
    retryBackoffMs: (loopCfg.retry && loopCfg.retry.backoffMs) || 2000,
    lastRetryError: null
  };

  // ============================================
  // v7.24: T-2 Toast Notification System
  // Shows error/warning toasts in the overlay
  // ============================================
  const TOAST_MAX_VISIBLE = 3;
  const TOAST_AUTO_DISMISS_MS = 10000;
  const toastQueue = [];
  const toastContainerId = 'marco-toast-container';

  function showToast(message, level) {
    level = level || 'error'; // 'error' | 'warn' | 'info' | 'success'
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });

    const colors = {
      error:   { bg: '#7f1d1d', border: '#dc2626', icon: '❌', text: '#fca5a5' },
      warn:    { bg: '#78350f', border: '#f59e0b', icon: '⚠️', text: '#fde68a' },
      info:    { bg: '#1e3a5f', border: '#3b82f6', icon: 'ℹ️', text: '#93c5fd' },
      success: { bg: '#14532d', border: '#22c55e', icon: '✅', text: '#86efac' }
    };
    let c = colors[level] || colors.error;

    let container = document.getElementById(toastContainerId);
    if (!container) {
      container = document.createElement('div');
      container.id = toastContainerId;
      container.style.cssText = 'position:fixed;top:12px;right:12px;z-index:99999;display:flex;flex-direction:column;gap:6px;max-width:360px;pointer-events:none;';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.style.cssText = 'display:flex;align-items:flex-start;gap:8px;padding:8px 12px;border-radius:8px;font-family:monospace;font-size:11px;color:' + c.text + ';background:' + c.bg + ';border:1px solid ' + c.border + ';box-shadow:0 4px 12px rgba(0,0,0,0.4);pointer-events:auto;opacity:0;transform:translateX(20px);transition:all 0.3s ease;cursor:pointer;';

    const iconSpan = document.createElement('span');
    iconSpan.style.cssText = 'font-size:14px;flex-shrink:0;line-height:1;';
    iconSpan.textContent = c.icon;

    const bodyDiv = document.createElement('div');
    bodyDiv.style.cssText = 'flex:1;min-width:0;';

    const msgDiv = document.createElement('div');
    msgDiv.style.cssText = 'word-break:break-word;';
    msgDiv.textContent = message;

    const timeDiv = document.createElement('div');
    timeDiv.style.cssText = 'font-size:9px;opacity:0.6;margin-top:2px;';
    timeDiv.textContent = timeStr;

    bodyDiv.appendChild(msgDiv);
    bodyDiv.appendChild(timeDiv);
    toast.appendChild(iconSpan);
    toast.appendChild(bodyDiv);

    // Dismiss on click
    toast.onclick = function() { dismissToast(toast); };

    container.appendChild(toast);
    toastQueue.push(toast);

    // Animate in
    requestAnimationFrame(function() {
      toast.style.opacity = '1';
      toast.style.transform = 'translateX(0)';
    });

    // Enforce max visible
    while (toastQueue.length > TOAST_MAX_VISIBLE) {
      dismissToast(toastQueue[0]);
    }

    // Auto-dismiss
    setTimeout(function() { dismissToast(toast); }, TOAST_AUTO_DISMISS_MS);

    // Also log it
    log('[Toast/' + level + '] ' + message, level === 'error' ? 'error' : (level === 'warn' ? 'warn' : 'check'));
  }

  function dismissToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    setTimeout(function() {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
      let idx = toastQueue.indexOf(toast);
      if (idx !== -1) toastQueue.splice(idx, 1);
    }, 300);
  }

  // v7.9.16: Validate a name against known workspace list
  // Prevents DOM observer from setting project name as workspace name
  function isKnownWorkspaceName(name) {
    if (!name) return false;
    let perWs = loopCreditState.perWorkspace || [];
    if (perWs.length === 0) return false; // v7.9.18: Don't allow unvalidated names — wait for API data
    for (let i = 0; i < perWs.length; i++) {
      const ws = perWs[i];
      if (ws.fullName === name || ws.name === name) return true;
      // Partial match for truncated/formatted names
      if (ws.fullName && ws.fullName.toLowerCase().indexOf(name.toLowerCase()) !== -1) return true;
      if (ws.name && ws.name.toLowerCase().indexOf(name.toLowerCase()) !== -1) return true;
    }
    return false;
  }

  // ============================================
  // Workspace Auto-Check Interval (ms) - independent of loop
  // Opens project dialog every N seconds to check workspace name + credit
  // Configurable via config.ini WorkspaceCheckIntervalMs (default 5000)
  // ============================================

  // ============================================
  // Workspace Name - uses CONFIG.WORKSPACE_XPATH (from config.ini, editable in UI)
  // ============================================

   function fetchWorkspaceName() {
    const wsXpath = CONFIG.WORKSPACE_XPATH;
    if (!wsXpath || wsXpath.indexOf('__') === 0) {
      log('Workspace XPath not configured (placeholder not replaced)', 'warn');
      return;
    }
    try {
      log('Fetching workspace name from XPath: ' + wsXpath, 'check');
      let el = getByXPath(wsXpath);
      if (el) {
        let name = (el.textContent || '').trim();
        if (name) {
          // v7.9.16: Validate against known workspaces to avoid picking up project name
          if (!isKnownWorkspaceName(name)) {
            logSub('Workspace XPath returned "' + name + '" — not a known workspace, skipping', 1);
          } else if (state.workspaceFromApi) {
            logSub('Workspace XPath returned "' + name + '" — ignoring, API already set: ' + state.workspaceName, 1);
          } else if (name !== state.workspaceName) {
            const oldName = state.workspaceName;
            state.workspaceName = name;
            log('Workspace name: ' + name, 'success');
            // Track workspace change if we had a previous name
            if (oldName && oldName !== name) {
              addWorkspaceChangeEntry(oldName, name);
            }
          } else {
            logSub('Workspace unchanged: ' + name, 1);
          }
        } else {
          log('Workspace element found but text is empty', 'warn');
        }
      } else {
        log('Workspace element NOT FOUND at XPath: ' + wsXpath, 'warn');
      }
      updateUI();
    } catch (e) {
      log('fetchWorkspaceName error: ' + e.message, 'error');
    }
  }

  // ============================================
  // v6.55: Fetch workspace name from persistent nav element (NO dialog needed)
  // Uses WorkspaceNavXPath — reads from top-left nav, always visible
  // ============================================
  function fetchWorkspaceNameFromNav() {
    const navXpath = CONFIG.WORKSPACE_NAV_XPATH;
    const hasXpath = navXpath && navXpath.indexOf('__') !== 0 && navXpath !== '';
    try {
      let el = null;
      // Try XPath first
      if (hasXpath) {
        el = getByXPath(navXpath);
      }
      // Fallback: auto-discover
      if (!el) {
        el = autoDiscoverWorkspaceNavElement();
      }
      if (el) {
        let name = (el.textContent || '').trim();
        if (name) {
          // v7.9.16: Validate against known workspaces
          if (!isKnownWorkspaceName(name)) {
            logSub('Nav returned "' + name + '" — not a known workspace, skipping', 1);
            return false;
          }
          if (state.workspaceFromApi) {
            logSub('Nav returned "' + name + '" — ignoring, API already set: ' + state.workspaceName, 1);
            return true;
          }
          if (name !== state.workspaceName) {
            const oldName = state.workspaceName;
            state.workspaceName = name;
            log('Workspace name (from nav): ' + name, 'success');
            if (oldName && oldName !== name) {
              addWorkspaceChangeEntry(oldName, name);
            }
          } else {
            logSub('Workspace unchanged (nav): ' + name, 1);
          }
          updateUI();
          return true;
        }
      }
      logSub('Nav workspace element not found or empty', 1);
      return false;
    } catch (e) {
      log('fetchWorkspaceNameFromNav error: ' + e.message, 'error');
      return false;
    }
  }

  // ============================================
  // v6.56: Workspace MutationObserver — always-on, even when loop is stopped
  // Watches the nav element for text changes and auto-updates workspace name
  // ============================================
  let workspaceObserverInstance = null;
  let workspaceObserverRetryCount = 0;
  const WORKSPACE_OBSERVER_MAX_RETRIES = 10;

  // ============================================
  // v7.1: Auto-discover workspace name element via CSS selectors
  // Fallback when WorkspaceNavXPath is empty or fails
  // Tries common Lovable.dev nav patterns
  // ============================================
  function autoDiscoverWorkspaceNavElement() {
    // Strategy 1: Look for nav button with workspace-like text (not "Projects", not icons)
    const candidates = [];

    // Try: nav area buttons/links that contain team/workspace name
    const navButtons = document.querySelectorAll('nav button, nav a, nav span, [role="navigation"] button');
    for (let i = 0; i < navButtons.length; i++) {
      let el = navButtons[i];
      let text = (el.textContent || '').trim();
      // Skip empty, very short, or known non-workspace texts
      if (!text || text.length < 2 || text.length > 60) continue;
      if (/^(Projects?|Settings|Home|Menu|Sign|Log|Help|Docs|\+|×|☰|⋮)$/i.test(text)) continue;
      // Skip if it's just an icon or single character
      if (text.length <= 2 && /[^a-zA-Z0-9]/.test(text)) continue;
      let rect = el.getBoundingClientRect();
      if (rect.width > 0 && rect.height > 0 && rect.top < 80) {
        candidates.push({ el: el, text: text, y: rect.top, x: rect.left });
      }
    }

    // Strategy 2: Look for the first visible text element in the top-left nav area
    if (candidates.length === 0) {
      const topNavEls = document.querySelectorAll('nav div span, nav div p, nav div a, header span, header a');
      for (let j = 0; j < topNavEls.length; j++) {
        const el2 = topNavEls[j];
        const text2 = (el2.textContent || '').trim();
        if (!text2 || text2.length < 3 || text2.length > 60) continue;
        const rect2 = el2.getBoundingClientRect();
        if (rect2.width > 0 && rect2.height > 0 && rect2.top < 80 && rect2.left < 400) {
          // Check it's a leaf node (no child elements with different text)
          if (el2.children.length === 0 || el2.children.length === 1) {
            candidates.push({ el: el2, text: text2, y: rect2.top, x: rect2.left });
          }
        }
      }
    }

    if (candidates.length > 0) {
      // Pick the first candidate in the top-left area
      candidates.sort(function(a, b) { return a.y - b.y || a.x - b.x; });
      const best = candidates[0];
      log('Auto-discovered workspace nav element: "' + best.text + '" <' + best.el.tagName.toLowerCase() + '> at (' + Math.round(best.x) + ',' + Math.round(best.y) + ')', 'success');
      return best.el;
    }

    return null;
  }

  function startWorkspaceObserver() {
    const navXpath = CONFIG.WORKSPACE_NAV_XPATH;
    const hasXpath = navXpath && navXpath.indexOf('__') !== 0 && navXpath !== '';
    let navEl = null;

    // Try XPath first
    if (hasXpath) {
      navEl = getByXPath(navXpath);
      if (navEl) {
        logSub('Workspace nav element found via XPath', 1);
      }
    }

    // Fallback: auto-discover via CSS selectors
    if (!navEl) {
      if (hasXpath) {
        log('WorkspaceNavXPath configured but element not found — trying auto-discovery', 'warn');
      } else {
        logSub('WorkspaceNavXPath not configured — trying auto-discovery', 1);
      }
      navEl = autoDiscoverWorkspaceNavElement();
    }

    if (!navEl) {
      workspaceObserverRetryCount++;
      if (workspaceObserverRetryCount < WORKSPACE_OBSERVER_MAX_RETRIES) {
        const retryDelay = Math.min(workspaceObserverRetryCount * 3000, 15000);
        log('Workspace observer: element not found — retry ' + workspaceObserverRetryCount + '/' + WORKSPACE_OBSERVER_MAX_RETRIES + ' in ' + (retryDelay/1000) + 's', 'warn');
        setTimeout(startWorkspaceObserver, retryDelay);
      } else {
        log('Workspace observer: gave up after ' + WORKSPACE_OBSERVER_MAX_RETRIES + ' retries. Set WorkspaceNavXPath in config.ini.', 'error');
      }
      return;
    }

    workspaceObserverRetryCount = 0;

    // Disconnect previous observer if any
    if (workspaceObserverInstance) {
      workspaceObserverInstance.disconnect();
      logSub('Previous workspace observer disconnected', 1);
    }

    // Initial read — v7.9.16: validate against known workspaces
    let name = (navEl.textContent || '').trim();
    if (name && name !== state.workspaceName) {
      if (!isKnownWorkspaceName(name)) {
        logSub('Observer init: "' + name + '" not a known workspace — skipping (API will detect)', 1);
      } else if (state.workspaceFromApi) {
        logSub('Observer init: "' + name + '" — ignoring, API already set: ' + state.workspaceName, 1);
      } else {
        const oldName = state.workspaceName;
        state.workspaceName = name;
        log('Workspace name (observer init): ' + name, 'success');
        if (oldName && oldName !== name) {
          addWorkspaceChangeEntry(oldName, name);
        }
        updateUI();
      }
    } else if (name) {
      logSub('Workspace name already set: ' + name, 1);
    }

    // Install MutationObserver — watch the element AND its parent for re-renders
    workspaceObserverInstance = new MutationObserver(function(mutations) {
      // Check if our target element was removed from DOM (SPA re-render)
      if (!document.contains(navEl)) {
        log('Workspace nav element removed from DOM — restarting observer', 'warn');
        workspaceObserverInstance.disconnect();
        state.workspaceObserverActive = false;
        setTimeout(startWorkspaceObserver, 2000);
        return;
      }

      const newName = (navEl.textContent || '').trim();
      // v7.9.16: Validate against known workspaces before accepting
      if (!isKnownWorkspaceName(newName)) {
        logSub('Observer mutation: "' + newName + '" not a known workspace — ignoring', 1);
        return;
      }
      if (state.workspaceFromApi) {
        logSub('Observer mutation: "' + newName + '" — ignoring, API already set: ' + state.workspaceName, 1);
        return;
      }
      if (newName && newName !== state.workspaceName) {
        const oldName = state.workspaceName;
        state.workspaceName = newName;
        log('⚡ Workspace changed (observer): "' + oldName + '" → "' + newName + '"', 'success');
        if (oldName) addWorkspaceChangeEntry(oldName, newName);

        // 2. Show temporary "WS Changed" indicator
        state.workspaceJustChanged = true;
        if (state.workspaceChangedTimer) clearTimeout(state.workspaceChangedTimer);
        state.workspaceChangedTimer = setTimeout(function() {
          state.workspaceJustChanged = false;
          updateUI();
        }, 10000); // Clear after 10 seconds

        // 3. Update UI immediately
        updateUI();

        // 4. Check free credit on workspace change
        triggerCreditCheckOnWorkspaceChange();
      }
    });

    workspaceObserverInstance.observe(navEl, { childList: true, characterData: true, subtree: true });
    state.workspaceObserverActive = true;
    log('✅ Workspace MutationObserver installed on nav element', 'success');
  }

  // ============================================
  // v6.56: On workspace change → check free credit
  // Opens project dialog, checks credit bar, closes dialog, updates UI
  // ============================================
  function triggerCreditCheckOnWorkspaceChange() {
    log('Workspace changed — checking free credit...', 'check');

    // Skip if user is typing in prompt
    if (isUserTypingInPrompt()) {
      log('Skipping credit check — user is typing in prompt', 'skip');
      return;
    }

    const opened = ensureProjectDialogOpen();
    if (!opened) {
      log('Could not open project dialog for credit check', 'warn');
      return;
    }

    pollForDialogReady(function() {
      const hasCredit = checkSystemBusy();
      state.hasFreeCredit = hasCredit;
      state.isIdle = !hasCredit;
      state.lastStatusCheck = Date.now();
      log('Credit check after workspace change: ' + (hasCredit ? 'FREE CREDIT' : 'NO CREDIT'), hasCredit ? 'success' : 'warn');
      closeProjectDialog();
      updateUI();
    });
  }

  // Expose for console usage
  window.__startWorkspaceObserver = startWorkspaceObserver;

  // ============================================
  // Workspace Change History (localStorage)
  // ============================================
  function addWorkspaceChangeEntry(fromName, toName) {
    try {
      let key = getWsHistoryKey();
      let history = JSON.parse(localStorage.getItem(key) || '[]');
      const now = new Date();
      const projectName = getDisplayProjectName();
      const projectId = getProjectIdFromUrl();
      history.push({
        from: fromName,
        to: toName,
        time: now.toISOString(),
        display: now.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }),
        projectName: projectName,
        projectId: projectId
      });
      // Keep max entries
      if (history.length > WS_HISTORY_MAX_ENTRIES) history = history.slice(history.length - WS_HISTORY_MAX_ENTRIES);
      safeSetItem(key, JSON.stringify(history));
      log('Workspace changed: "' + fromName + '" → "' + toName + '" (project=' + projectName + ', key=' + key + ')', 'success');
      // Update project name display in UI
      updateProjectNameDisplay();
    } catch (e) { /* storage error */ }
  }

  function getWorkspaceHistory() {
    try {
      let key = getWsHistoryKey();
      return JSON.parse(localStorage.getItem(key) || '[]');
    } catch (e) { return []; }
  }

  function clearWorkspaceHistory() {
    try {
      let key = getWsHistoryKey();
      localStorage.removeItem(key);
    } catch (e) { /* ignore */ }
  }

  // ============================================
  // Utility Functions
  // ============================================
  function getByXPath(xpath) {
    if (!xpath) {
      log('XPath is empty or undefined', 'error');
      return null;
    }
    try {
      return document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
    } catch (e) {
      log('XPath evaluation error: ' + e.message, 'error');
      log('Problematic XPath: ' + xpath, 'error');
      return null;
    }
  }

  function getAllByXPath(xpath) {
    if (!xpath) {
      log('XPath is empty or undefined', 'error');
      return [];
    }
    try {
      const result = document.evaluate(xpath, document, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
      const nodes = [];
      for (let i = 0; i < result.snapshotLength; i++) {
        nodes.push(result.snapshotItem(i));
      }
      return nodes;
    } catch (e) {
      log('XPath evaluation error: ' + e.message, 'error');
      log('Problematic XPath: ' + xpath, 'error');
      return [];
    }
  }

  // ============================================
  // S-001: Generic findElement() with multi-method fallback
  // descriptor: { name, xpath, textMatch, tag, selector, role, ariaLabel }
  // ============================================
  function findElement(descriptor) {
    let name = descriptor.name || 'unknown';
    log('findElement: Searching for "' + name + '"', 'check');

    // Method 1: Configured XPath
    if (descriptor.xpath) {
      log('  Method 1 (XPath) for ' + name + ': ' + descriptor.xpath, 'check');
      const xpathResult = getByXPath(descriptor.xpath);
      if (xpathResult) {
        log('  ' + name + ' FOUND via XPath: ' + descriptor.xpath, 'success');
        return xpathResult;
      }
      log('  ' + name + ' XPath failed: ' + descriptor.xpath + ' — trying fallbacks', 'warn');
    }

    // Method 2: Text-based scan
    if (descriptor.textMatch) {
      const tag = descriptor.tag || 'button';
      const texts = Array.isArray(descriptor.textMatch) ? descriptor.textMatch : [descriptor.textMatch];
      log('  Method 2 (text scan): looking in <' + tag + '> for ' + JSON.stringify(texts), 'check');
      const allTags = document.querySelectorAll(tag);
      for (let t = 0; t < allTags.length; t++) {
        const elText = (allTags[t].textContent || '').trim();
        for (let m = 0; m < texts.length; m++) {
          if (elText === texts[m] || elText.indexOf(texts[m]) !== -1) {
            log('  ' + name + ' FOUND via text: "' + elText.substring(0, 40) + '"', 'success');
            return allTags[t];
          }
        }
      }
    }

    // Method 3: CSS selector
    if (descriptor.selector) {
      const selectors = Array.isArray(descriptor.selector) ? descriptor.selector : [descriptor.selector];
      log('  Method 3 (CSS selector): trying ' + selectors.length + ' selectors', 'check');
      for (let s = 0; s < selectors.length; s++) {
        try {
          log('    [' + (s+1) + '/' + selectors.length + '] querySelector("' + selectors[s] + '")', 'check');
          const sResult = document.querySelector(selectors[s]);
          if (sResult) {
            log('    ✅ FOUND via selector [' + (s+1) + ']: ' + selectors[s] + ' → <' + sResult.tagName.toLowerCase() + '>', 'success');
            return sResult;
          }
          log('    ❌ Not found', 'warn');
        } catch (e) {
          log('    ❌ Invalid selector: ' + e.message, 'error');
        }
      }
    }

    // Method 4: ARIA/role attributes
    if (descriptor.ariaLabel || descriptor.role) {
      log('  Method 4 (ARIA/role)', 'check');
      if (descriptor.ariaLabel) {
        const ariaLabels = Array.isArray(descriptor.ariaLabel) ? descriptor.ariaLabel : [descriptor.ariaLabel];
        for (let a = 0; a < ariaLabels.length; a++) {
          try {
            const ariaResult = document.querySelector('[aria-label*="' + ariaLabels[a] + '" i], [title*="' + ariaLabels[a] + '" i]');
            if (ariaResult) {
              log('  ' + name + ' FOUND via ARIA: ' + ariaLabels[a], 'success');
              return ariaResult;
            }
          } catch (e) { /* skip */ }
        }
      }
      if (descriptor.role) {
        const roleResult = document.querySelector('[role="' + descriptor.role + '"]');
        if (roleResult) {
          log('  ' + name + ' FOUND via role: ' + descriptor.role, 'success');
          return roleResult;
        }
      }
    }

    log('  All methods failed for "' + name + '"', 'error');
    return null;
  }

  // ============================================
  // S-001: Element descriptors for MacroLoop XPath elements
  // ============================================
  const ML_ELEMENTS = {
    PROJECT_BUTTON: {
      name: 'Project Button',
      xpath: CONFIG.PROJECT_BUTTON_XPATH,
      selector: ['nav button', 'nav div button', '[data-testid="project-button"]'],
      ariaLabel: ['project', 'Project'],
      tag: 'button'
    },
    PROGRESS: {
      name: 'Progress Bar',
      xpath: CONFIG.PROGRESS_XPATH,
      selector: ['[role="progressbar"]', '.progress-bar', '[class*="progress"]'],
      role: 'progressbar'
    },
    // S-012: CSS fallback selectors for workspace name inside project dialog
    // Used when WorkspaceNameXPath fails (DOM structure changed)
    WORKSPACE_NAME: {
      name: 'Workspace Name (in dialog)',
      xpath: CONFIG.WORKSPACE_XPATH,
      selector: [
        '[data-testid="workspace-name"]',
        '[data-testid*="workspace"]',
        '[class*="workspace"] span',
        '[class*="workspace"] p',
        'nav [class*="sidebar"] span',
        '[role="dialog"] h2',
        '[role="dialog"] h3',
        '[role="dialog"] [class*="title"]',
        '[data-state="open"] [class*="workspace"]',
        '[data-radix-popper-content-wrapper] span'
      ],
      tag: 'span'
    }
  };

  function isOnProjectPage() {
    const url = window.location.href;
    return url.indexOf(CONFIG.REQUIRED_DOMAIN) !== -1 &&
           url.indexOf('/projects/') !== -1 &&
           url.indexOf('/settings') === -1;
  }

  // ============================================
  // Check if user is actively typing in the prompt area
  // If so, we should NOT open the project dialog (disrupts typing)
  // ============================================
  function isUserTypingInPrompt() {
    const promptXpath = CONFIG.PROMPT_ACTIVE_XPATH;
    if (!promptXpath || promptXpath.indexOf('__') === 0) return false;
    try {
      const promptEl = getByXPath(promptXpath);
      if (!promptEl) return false;
      // Check if the prompt area or any of its children has focus
      const activeEl = document.activeElement;
      if (!activeEl) return false;
      const isInPrompt = promptEl.contains(activeEl) || promptEl === activeEl;
      if (isInPrompt) {
        logSub('User is typing in prompt area — skipping dialog open', 1);
      }
      return isInPrompt;
    } catch (e) { return false; }
  }

  // ============================================
  // Check if system is busy (progress bar visible)
  // S-001: Now uses findElement with multi-method fallback
  // ============================================
  function checkSystemBusy() {
    const progressEl = findElement(ML_ELEMENTS.PROGRESS);
    if (!progressEl) {
      logSub('Progress bar element NOT found in DOM', 1);
      return false;
    }
    // Validate: element must have actual visible content (not just exist in DOM)
    let rect = progressEl.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const computedStyle = window.getComputedStyle(progressEl);
    let isHidden = computedStyle.display === 'none' || computedStyle.visibility === 'hidden' || computedStyle.opacity === '0';
    let hasContent = (progressEl.textContent || '').trim().length > 0 || progressEl.children.length > 0;
    
    logSub('Progress bar check: visible=' + isVisible + ', hidden=' + isHidden + ', hasContent=' + hasContent + ', rect=' + Math.round(rect.width) + 'x' + Math.round(rect.height), 1);
    
    if (isHidden) {
      logSub('Progress bar exists but is HIDDEN (display/visibility/opacity) — treating as NO credit', 1);
      return false;
    }
    if (!isVisible) {
      logSub('Progress bar exists but has 0 size — treating as NO credit', 1);
      return false;
    }
    
    logSub('Progress bar is VISIBLE and has content — FREE CREDIT detected', 1);
    return true;
  }

  // ============================================
  // Poll for Main Progress Bar (dialog ready signal)
  // Polls every 200ms for up to DialogWaitMs (fallback timeout)
  // Calls back immediately when main bar appears — much faster than fixed wait
  // ============================================
  function pollForDialogReady(callback) {
    const mainXpath = CONFIG.MAIN_PROGRESS_XPATH;
    if (!mainXpath || mainXpath.indexOf('__') === 0) {
      log('MainProgressXPath not configured — falling back to fixed DialogWaitMs wait', 'warn');
      setTimeout(callback, TIMING.DIALOG_WAIT || 2000);
      return;
    }

    const pollInterval = 200; // ms between polls
    const maxWait = TIMING.DIALOG_WAIT || 3000; // fallback timeout
    const elapsed = 0;

    log('Polling for main progress bar (every ' + pollInterval + 'ms, max ' + maxWait + 'ms)...', 'check');

    const pollTimer = setInterval(function() {
      elapsed += pollInterval;
      const mainEl = getByXPath(mainXpath);
      if (mainEl) {
        let rect = mainEl.getBoundingClientRect();
        const isVisible = rect.width > 0 && rect.height > 0;
        if (isVisible) {
          clearInterval(pollTimer);
          log('Main progress bar FOUND after ' + elapsed + 'ms — waiting 500ms for dialog to fully render...', 'success');
          setTimeout(function() {
            log('Dialog settle delay complete — proceeding', 'check');
            callback();
          }, 500);
          return;
        }
      }

      if (elapsed >= maxWait) {
        clearInterval(pollTimer);
        log('Main progress bar NOT found after ' + maxWait + 'ms — proceeding anyway (timeout)', 'warn');
        callback();
      }
    }, pollInterval);
  }

  // ============================================
  // Close project dialog (toggle close if open)
  // ============================================
  function closeProjectDialog() {
    let btn = getByXPath(CONFIG.PROJECT_BUTTON_XPATH);
    if (!btn) {
      const fallbackBtn = findElement(ML_ELEMENTS.PROJECT_BUTTON);
      if (fallbackBtn) btn = fallbackBtn;
    }
    if (btn) {
      const isExpanded = btn.getAttribute('aria-expanded') === 'true' || btn.getAttribute('data-state') === 'open';
      if (isExpanded) {
        logSub('Closing project dialog', 1);
        reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
      }
    }
  }

  // ============================================
  // Click Project Button
  // S-001: Uses getAllByXPath first, then findElement() as fallback
  // ============================================
  // ============================================
  // Ensure project dialog is OPEN (not toggled closed)
  // Returns true if dialog is confirmed open, false if button not found
  // ============================================
  function ensureProjectDialogOpen() {
    log('Ensuring project dialog is OPEN...', 'check');
    log('Using XPath: ' + CONFIG.PROJECT_BUTTON_XPATH, 'check');

    let buttons = getAllByXPath(CONFIG.PROJECT_BUTTON_XPATH);

    if (buttons.length === 0) {
      log('XPath returned 0 matches, trying multi-method fallback...', 'warn');
      const fallbackBtn = findElement(ML_ELEMENTS.PROJECT_BUTTON);
      if (fallbackBtn) {
        buttons = [fallbackBtn];
      } else {
        log('PROJECT BUTTON NOT FOUND via XPath or fallback!', 'error');
        log('Please update the XPath in the panel below or in config.ini', 'warn');
        return false;
      }
    }

    for (let i = 0; i < buttons.length; i++) {
      let btn = buttons[i];
      let rect = btn.getBoundingClientRect();
      const isVisible = rect.width > 0 && rect.height > 0 &&
                      window.getComputedStyle(btn).visibility !== 'hidden' &&
                      window.getComputedStyle(btn).display !== 'none';

      if (isVisible) {
        const btnInfo = 'Button: ' + btn.tagName;
        if (btn.textContent) btnInfo += ', text: "' + btn.textContent.substring(0, 30) + '"';
        log(btnInfo, 'check');

        // CHECK: Is dialog already open? (aria-expanded=true means open)
        const isExpanded = btn.getAttribute('aria-expanded') === 'true' || btn.getAttribute('data-state') === 'open';
        if (isExpanded) {
          log('Dialog is ALREADY OPEN (aria-expanded=true) — skipping click', 'success');
          return true;
        }

        // Dialog is closed — click to open
        log('Dialog is CLOSED — clicking to open', 'check');
        highlightElement(btn, '#6ee7b7');

        try {
          reactClick(btn, CONFIG.PROJECT_BUTTON_XPATH);
          log('Clicked Project Button successfully — dialog should now be opening', 'success');
          return true;
        } catch (e) {
          log('Click failed on button ' + i + ': ' + e.message, 'error');
          continue;
        }
      } else {
        log('Button ' + i + ' is not visible, skipping...', 'skip');
      }
    }

    log('PROJECT BUTTON NOT FOUND! (' + buttons.length + ' matches but none are valid)', 'error');
    return false;
  }

  // Legacy alias
  function clickProjectButton() {
    return ensureProjectDialogOpen();
  }

  // ============================================
  // Highlight element with CSS
  // ============================================
  function highlightElement(el, color) {
    if (!el) return;
    el.style.outline = '3px solid ' + (color || '#ec4899');
    el.style.outlineOffset = '2px';
    el.style.boxShadow = '0 0 10px ' + (color || '#ec4899');
    setTimeout(function() {
      el.style.outline = '';
      el.style.outlineOffset = '';
      el.style.boxShadow = '';
    }, 3000);
  }

  // ============================================
  // Check Button Function - Manual test
  // v7.9.30: Also detects workspace via XPath after progress check
  // v7.14.0: Manual Check — XPath-only workspace detection
  // Does NOT use Tier 1 API (mark-viewed). Directly clicks Project Button → reads XPath → updates workspace.
  // ============================================
  function runCheck() {
    log('=== MANUAL CHECK START ===', 'check');

    const statusEl = document.getElementById(IDS.STATUS);
    if (statusEl) {
      statusEl.innerHTML = '<span style="color:#38bdf8;">🔍</span> Checking...';
    }

    const previousWsName = state.workspaceName || '';
    const previousCurrentWs = loopCreditState.currentWs;
    state.workspaceName = '';  // Clear to force fresh detection

    function restoreOnFailure() {
      if (!state.workspaceName && previousWsName) {
        state.workspaceName = previousWsName;
        loopCreditState.currentWs = previousCurrentWs;
        log('Restored previous workspace (detection failed): ' + previousWsName, 'warn');
      }
    }

    let perWs = loopCreditState.perWorkspace || [];

    function doXPathDetect(wsList) {
      // Step 1: Open Project Dialog
      log('Step 1: Opening Project Dialog...', 'check');
      // detectWorkspaceViaProjectDialog handles: click button → open dialog → poll XPath
      return detectWorkspaceViaProjectDialog('runCheck', wsList).then(function() {
        // Step 2: Check Workspace via XPath (logged inside detectWorkspaceViaProjectDialog)
        // By this point, all XPath nodes were checked and workspace was matched (or not)
        restoreOnFailure();
        if (state.workspaceName) {
          log('Step 2 complete: ✅ Workspace found = "' + state.workspaceName + '"', 'success');
        } else {
          log('Step 2 complete: ❌ No workspace matched from XPath', 'error');
        }
        // v7.14.0: Do NOT set workspaceFromApi — this is a pure DOM operation
        state.workspaceFromApi = false;
      });
    }

    // v7.17: Always attempt XPath detection — workspace list is optional (nice-to-have for matching)
    // If credit API failed (401), we still open the dialog and read workspace name from XPath directly
    let detectPromise;
    if (perWs.length > 0) {
      detectPromise = doXPathDetect(perWs);
    } else {
      log('No workspaces loaded — attempting credit fetch, but will detect via XPath regardless...', 'warn');
      if (statusEl) {
        statusEl.innerHTML = '<span style="color:#38bdf8;">🔍</span> Fetching workspaces...';
      }
      detectPromise = fetchLoopCreditsAsync().then(function() {
        const freshPerWs = loopCreditState.perWorkspace || [];
        return doXPathDetect(freshPerWs);
      }).catch(function(err) {
        log('Credit fetch failed: ' + err.message + ' — detecting via XPath without workspace list', 'warn');
        return doXPathDetect([]);
      });
    }

    return detectPromise.then(function() {
      // Step 3: Check Progress Bar
      return new Promise(function(resolve) {
        setTimeout(function() {
          log('Step 3: Checking Progress Bar...', 'check');
          log('  XPath: ' + CONFIG.PROGRESS_XPATH + ' (+ fallbacks)', 'check');
          const progressEl = findElement(ML_ELEMENTS.PROGRESS);

          if (progressEl) {
            log('  Progress Bar FOUND — System is BUSY', 'warn');
            highlightElement(progressEl, '#fbbf24');
            state.isIdle = false;
          } else {
            log('  Progress Bar NOT FOUND — System is IDLE', 'success');
            state.isIdle = true;
          }

          // Step 4: Update UI
          log('Step 4: Updating UI...', 'check');
          syncCreditStateFromApi();
          updateUI();
          log('=== MANUAL CHECK COMPLETE ===', 'check');
          resolve();
        }, 500);
      });
    });
  }

  // ============================================
  // Update XPath from UI
  // ============================================
  function updateProjectButtonXPath(newXPath) {
    if (newXPath && newXPath.trim()) {
      CONFIG.PROJECT_BUTTON_XPATH = newXPath.trim();
      ML_ELEMENTS.PROJECT_BUTTON.xpath = newXPath.trim();
      log('Project Button XPath updated to: ' + CONFIG.PROJECT_BUTTON_XPATH, 'success');
      return true;
    }
    return false;
  }

  function updateProgressXPath(newXPath) {
    if (newXPath && newXPath.trim()) {
      CONFIG.PROGRESS_XPATH = newXPath.trim();
      ML_ELEMENTS.PROGRESS.xpath = newXPath.trim();
      log('Progress Bar XPath updated to: ' + CONFIG.PROGRESS_XPATH, 'success');
      return true;
    }
    return false;
  }

  function updateWorkspaceXPath(newXPath) {
    if (newXPath && newXPath.trim()) {
      CONFIG.WORKSPACE_XPATH = newXPath.trim();
      log('Workspace XPath updated to: ' + CONFIG.WORKSPACE_XPATH, 'success');
      return true;
    }
    return false;
  }

  // ============================================
  // DEPRECATED (v7.9.6): Signal AHK via Clipboard
  // No longer used — workspace moves are now handled directly via API (moveToAdjacentWorkspace).
  // Kept for reference only. See performDirectMove() for the replacement.
  // ============================================
  function dispatchDelegateSignal(direction) {
    const signal = direction === 'up' ? 'DELEGATE_UP' : 'DELEGATE_DOWN';
    // v6.53: Embed full URL in title signal so AHK can extract project ID
    // without fragile Ctrl+L/Ctrl+C address bar reads
    const currentUrl = window.location.href;
    const titleMarker = '__AHK_' + signal + '__URL:' + currentUrl + '__ENDURL__';
    
    // PRIMARY: Use document.title (always works, no focus requirement)
    const cleanTitle = document.title.replace(/__AHK_DELEGATE_(UP|DOWN)__URL:.*?__ENDURL__/g, '').replace(/__AHK_DELEGATE_(UP|DOWN)__/g, '');
    document.title = titleMarker + cleanTitle;
    log('DEPRECATED: Title signal set: ' + titleMarker, 'delegate');
    
    // SECONDARY: Also try clipboard (works for user-gesture triggers like Force buttons)
    try {
      navigator.clipboard.writeText(signal).catch(function() {
        // Clipboard failed (expected when DevTools focused) - title signal is primary
      });
    } catch (e) { /* ignore */ }
  }

  // ============================================
  // v7.9.6: Direct API Move — replaces AHK delegation entirely.
  // No tab switching, no clipboard signals, no title markers.
  // Just calls moveToAdjacentWorkspace() which does PUT /move-to-workspace.
  // ============================================
  function performDirectMove(direction) {
    log('=== DIRECT API MOVE ' + direction.toUpperCase() + ' ===', 'delegate');
    logSub('v7.9.6: Using moveToAdjacentWorkspace() — no AHK delegation', 1);
    state.isDelegating = true;
    state.forceDirection = direction;
    state.delegateStartTime = Date.now();
    updateUI();

    try {
      moveToAdjacentWorkspace(direction);
      // moveToAdjacentWorkspace is async (fetch) — give it time to complete
      setTimeout(function() {
        state.isDelegating = false;
        state.forceDirection = null;
        state.delegateStartTime = 0;
        state.countdown = Math.floor(TIMING.LOOP_INTERVAL / 1000);
        log('Direct API move complete (' + direction.toUpperCase() + ')', 'success');
        // Refresh credit/workspace data after move
        fetchLoopCredits();
        updateUI();
      }, 3000);
    } catch (err) {
      log('Direct API move FAILED: ' + err.message, 'error');
      state.isDelegating = false;
      state.forceDirection = null;
      state.delegateStartTime = 0;
      updateUI();
    }
  }

  // ============================================
  // UI Update Functions
  // ============================================
  function updateUI() {
    updateStatus();
    updateButtons();
    updateRecordIndicator();
    populateLoopWorkspaceDropdown();
    updateProjectNameDisplay();
  }

  // v7.9.39: Update project name display in title bar
  function updateProjectNameDisplay() {
    let el = document.getElementById('loop-project-name');
    if (el) {
      el.textContent = getDisplayProjectName();
    }
  }

  function updateStatus() {
    let el = document.getElementById(IDS.STATUS);
    if (!el) return;

    // Workspace name fragment (inline, yellow, bold)
    let wsFragment = '';
    if (state.workspaceName) {
      wsFragment = '<span style="color:#fbbf24;font-weight:700;">' + state.workspaceName + '</span>';
      // v6.56: Show temporary "WS Changed" indicator
      if (state.workspaceJustChanged) {
        wsFragment += ' <span style="color:#f97316;font-size:10px;font-weight:bold;">⚡ WS Changed</span>';
      }
      wsFragment += ' | ';
    }

    // Build credit bar section matching workspace item format (if API data available)
    // v7.33: Use same maxTotalCredits scaling as workspace list items
    let creditBarsHtml = '';
    if (loopCreditState.lastCheckedAt) {
      const cws = loopCreditState.currentWs;
      if (cws) {
        const df = Math.round(cws.dailyFree || 0);
        const ro = Math.round(cws.rollover || 0);
        const ba = Math.round(cws.billingAvailable || 0);
        const fr = Math.round(cws.freeRemaining || 0);
        const _totalCapacity = Math.round(cws.totalCredits || calcTotalCredits(cws.freeGranted, cws.dailyLimit, cws.limit, cws.topupLimit, cws.rolloverLimit));
        const _availTotal = Math.round(cws.available || calcAvailableCredits(_totalCapacity, cws.rolloverUsed, cws.dailyUsed, cws.used, (cws.freeGranted || 0) - (cws.freeRemaining || 0)));
        // Compute maxTotalCredits across all workspaces (same as workspace list)
        const _perWs = loopCreditState.perWorkspace || [];
        let _maxTc = 0;
        for (let _mi = 0; _mi < _perWs.length; _mi++) {
          const _mtc = Math.round(_perWs[_mi].totalCredits || calcTotalCredits(_perWs[_mi].freeGranted, _perWs[_mi].dailyLimit, _perWs[_mi].limit, _perWs[_mi].topupLimit, _perWs[_mi].rolloverLimit));
          if (_mtc > _maxTc) _maxTc = _mtc;
        }
        creditBarsHtml = renderCreditBar({
          totalCredits: _totalCapacity, available: _availTotal, totalUsed: cws.totalCreditsUsed || 0,
          freeRemaining: fr, billingAvail: ba, rollover: ro, dailyFree: df,
          compact: false, marginTop: '4px', maxTotalCredits: _maxTc
        });
      }
    }

    if (state.running) {
      let hasFreeCredit = !state.isIdle;
      const creditIcon = hasFreeCredit ? '[Y]' : '[N]';
      const creditColor = hasFreeCredit ? '#10b981' : '#ef4444';
      const creditLabel = hasFreeCredit ? 'Free Credit' : 'No Credit';
      const creditText = '<span style="color:' + creditColor + ';">' + creditIcon + ' ' + creditLabel + '</span>';
      let delegateText = '';
      if (state.isDelegating) {
        if (state.forceDirection) {
          delegateText = ' | <span style="color:#f97316;font-weight:bold;">FORCE ' + state.forceDirection.toUpperCase() + '</span>';
        } else {
          delegateText = ' | <span style="color:#3b82f6;">SWITCHING...</span>';
        }
      }
      const totalSec = Math.floor(TIMING.LOOP_INTERVAL / 1000);
      const pct = totalSec > 0 ? Math.max(0, Math.min(100, ((totalSec - state.countdown) / totalSec) * 100)) : 0;
      const barColor = pct > 80 ? '#ef4444' : pct > 50 ? '#f59e0b' : '#10b981';

      const statusLine = '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap;">'
        + wsFragment
        + '<span style="color:#10b981;">*</span> '
        + state.direction.toUpperCase()
        + ' | #' + state.cycleCount
        + ' | ' + creditText
        + ' | <span style="color:#fbbf24;font-weight:bold;">' + state.countdown + 's</span>'
        + delegateText
        + '</div>';

      const progressBar = '<div style="width:100%;height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;">'
        + '<div style="width:' + pct + '%;height:100%;background:' + barColor + ';border-radius:3px;transition:width 0.8s linear;"></div>'
        + '</div>';

      el.innerHTML = statusLine + progressBar + creditBarsHtml;
    } else {
      let creditInfoStop = '';
      if (state.lastStatusCheck > 0) {
        const creditIconStop = state.hasFreeCredit ? '[Y]' : '[N]';
        const creditColorStop = state.hasFreeCredit ? '#10b981' : '#ef4444';
        const creditLabelStop = state.hasFreeCredit ? 'Free Credit' : 'No Credit';
        creditInfoStop = ' | <span style="color:' + creditColorStop + ';">' + creditIconStop + ' ' + creditLabelStop + '</span>';
      }
      el.innerHTML = wsFragment + '<span style="color:#9ca3af;">[=]</span> Stopped | Cycles: ' + state.cycleCount + creditInfoStop + creditBarsHtml;
    }
  }

  function updateButtons() {
    // v7.28: The start/stop button is now a TOGGLE (single button, id=START_BTN).
    // Do NOT disable it when running — that prevents clicking Stop.
    // Instead, sync its visual state via __loopUpdateStartStopBtn.
    if (typeof window.__loopUpdateStartStopBtn === 'function') {
      window.__loopUpdateStartStopBtn(!!state.running);
    }

    // Legacy: if separate stop button exists (old layout), update it
    const stopBtn = document.getElementById(IDS.STOP_BTN);
    if (stopBtn) {
      stopBtn.disabled = !state.running;
      stopBtn.style.opacity = state.running ? '1' : '0.5';
      stopBtn.style.cursor = state.running ? 'pointer' : 'not-allowed';
    }
  }

  function updateRecordIndicator() {
    let el = document.getElementById(IDS.RECORD_INDICATOR);
    if (!el) return;
    
    if (state.running) {
      el.style.display = 'flex';
      if (state.isDelegating) {
        if (state.forceDirection) {
          // v6.55: Distinct Force indicator (orange)
          el.innerHTML = '<span style="width:10px;height:10px;background:#f97316;border-radius:50%;display:inline-block;"></span> FORCE ' + state.forceDirection.toUpperCase();
          el.style.background = '#c2410c';
        } else {
          el.innerHTML = '<span style="width:10px;height:10px;background:#3b82f6;border-radius:50%;display:inline-block;"></span> SWITCHING';
          el.style.background = '#1d4ed8';
        }
      } else {
        el.innerHTML = '<span style="width:10px;height:10px;background:#fff;border-radius:50%;display:inline-block;"></span> LOOP';
        el.style.background = '#dc2626';
      }
    } else {
      el.style.display = 'none';
    }
  }

  // ============================================
  // Loop Control
  // ============================================
  function startLoop(direction) {
    if (state.running) {
      log('Cannot start - loop is already running', 'warn');
      return false;
    }

    if (!isOnProjectPage()) {
      log('Cannot start - must be on a lovable.dev project page (not settings)', 'error');
      return false;
    }

    state.direction = direction || 'down';
    state.cycleCount = 0;
    state.isIdle = true;
    state.isDelegating = false;

    // v7.27: Set running=true IMMEDIATELY so stop button works right away
    state.running = true;
    state.countdown = Math.floor(TIMING.LOOP_INTERVAL / 1000);
    if (typeof window.__loopUpdateStartStopBtn === 'function') window.__loopUpdateStartStopBtn(true);

    log('=== LOOP STARTING ===', 'success');
    log('Direction: ' + state.direction.toUpperCase(), 'success');
    log('Interval: ' + (TIMING.LOOP_INTERVAL/1000) + 's');
    log('Project Button XPath: ' + CONFIG.PROJECT_BUTTON_XPATH);
    log('Progress XPath: ' + CONFIG.PROGRESS_XPATH);

    // v7.15: Step 0 — Confirm controller is injected at the CONTROLS_XPATH (not just marker)
    log('Step 0: Confirming controller injection at CONTROLS_XPATH...', 'check');
    log('  CONTROLS_XPATH: ' + CONFIG.CONTROLS_XPATH, 'check');

    let marker = document.getElementById(IDS.SCRIPT_MARKER);
    const uiContainer = document.getElementById(IDS.CONTAINER);
    const xpathTarget = getByXPath(CONFIG.CONTROLS_XPATH);

    if (!marker || typeof window.__loopStart !== 'function') {
      log('❌ Controller script NOT injected (marker=' + !!marker + ', __loopStart=' + (typeof window.__loopStart) + ') — aborting', 'error');
      state.running = false;
      if (typeof window.__loopUpdateStartStopBtn === 'function') window.__loopUpdateStartStopBtn(false);
      return false;
    }

    if (!uiContainer) {
      log('❌ Controller UI container NOT found in DOM (id=' + IDS.CONTAINER + ') — aborting', 'error');
      state.running = false;
      if (typeof window.__loopUpdateStartStopBtn === 'function') window.__loopUpdateStartStopBtn(false);
      return false;
    }

    // Verify UI is inside the XPath target, not body fallback
    if (xpathTarget && xpathTarget.contains(uiContainer)) {
      log('Step 0: ✅ Controller confirmed at CONTROLS_XPATH', 'success');
    } else if (xpathTarget) {
      log('Step 0: ⚠️ Controller exists but NOT inside CONTROLS_XPATH (body fallback?) — proceeding with warning', 'warn');
    } else {
      log('Step 0: ⚠️ CONTROLS_XPATH element not found — controller may be in fallback position', 'warn');
    }

    updateUI();

    // v7.15: Step 1 — Controller confirmed. NOW run check to detect workspace.
    log('Step 1: Controller confirmed — running initial workspace check...', 'check');

    // runCheck is async (opens project dialog, reads XPath) — start loop timers AFTER it completes
    let checkPromise;
    try {
      checkPromise = runCheck();
    } catch(e) {
      log('Initial check threw error: ' + e.message + ' — starting loop anyway', 'warn');
    }

    // Start the actual loop timers
    const startTimers = function() {
      if (!state.running) {
        // User already pressed stop before check completed
        log('Loop was stopped during initial check — not starting timers', 'warn');
        return;
      }

      log('=== LOOP STARTED (post-check) ===', 'success');

      // Start countdown timer
      state.countdownIntervalId = setInterval(function() {
        if (state.countdown > 0) state.countdown--;
        updateStatus();
      }, TIMING.COUNTDOWN_INTERVAL);

      // Start main loop
      state.loopIntervalId = setInterval(runCycle, TIMING.LOOP_INTERVAL);

      // Run first cycle after short delay
      setTimeout(runCycle, TIMING.FIRST_CYCLE_DELAY);

      updateUI();
    };

    if (checkPromise && typeof checkPromise.then === 'function') {
      checkPromise.then(function() {
        log('Initial check completed — starting loop timers', 'success');
        startTimers();
      }).catch(function(err) {
        log('Initial check failed: ' + (err && err.message ? err.message : String(err)) + ' — starting loop anyway', 'warn');
        startTimers();
      });
    } else {
      // runCheck didn't return a promise — fallback to delay
      setTimeout(startTimers, 3000);
    }

    return true;
  }

  function stopLoop() {
    if (!state.running) {
      return false;
    }

    state.running = false;
    state.isDelegating = false;
    state.forceDirection = null;  // v6.55

    if (state.loopIntervalId) {
      clearInterval(state.loopIntervalId);
      state.loopIntervalId = null;
    }
    if (state.countdownIntervalId) {
      clearInterval(state.countdownIntervalId);
      state.countdownIntervalId = null;
    }

    log('=== LOOP STOPPED ===', 'success');
    log('Total cycles completed: ' + state.cycleCount);
    if (typeof window.__loopUpdateStartStopBtn === 'function') window.__loopUpdateStartStopBtn(false);
    updateUI();
    return true;
  }

  // ============================================
  // v7.9.7: Sync state.hasFreeCredit from API credit data
  // Called after every fetchLoopCredits() to keep loop state in sync
  // ============================================
  function syncCreditStateFromApi() {
    const cws = loopCreditState.currentWs;
    if (!cws) {
      logSub('syncCreditState: no currentWs — cannot determine credit', 1);
      return;
    }
    // v7.9.37: Use dailyFree (📅) as the sole free-credit indicator and move trigger
    let dailyFree = cws.dailyFree || 0;
    const hasCredit = dailyFree > 0;
    state.hasFreeCredit = hasCredit;
    state.isIdle = !hasCredit;
    state.lastStatusCheck = Date.now();
    log('API Credit Sync: ' + cws.fullName + ' dailyFree=' + dailyFree + ' (available=' + cws.available + ') → ' + (hasCredit ? '[Y] FREE CREDIT' : '[N] NO FREE CREDIT → will move'), hasCredit ? 'success' : 'warn');
  }

  // ============================================
  // Run Cycle - v7.9.7: API-based credit check (no dialog needed)
  // Fetches credit data via API, checks available credits, moves if depleted
  // ============================================
  function runCycle() {
    // Check 1: Is loop running?
    if (!state.running) {
      log('SKIP: Loop not running', 'skip');
      return;
    }
    
    // Check 2: Are we waiting for move to complete? (with 60s timeout)
    if (state.isDelegating) {
      const elapsed = state.delegateStartTime ? (Date.now() - state.delegateStartTime) / 1000 : 0;
      if (elapsed > 60) {
        log('Move timeout after ' + Math.floor(elapsed) + 's - auto-recovering', 'warn');
        state.isDelegating = false;
        state.forceDirection = null;
        state.delegateStartTime = 0;
        updateUI();
      } else {
        log('SKIP: Waiting for API move (' + Math.floor(elapsed) + 's)', 'skip');
        return;
      }
    }

    state.cycleCount++;
    state.countdown = Math.floor(TIMING.LOOP_INTERVAL / 1000);
    log('--- Cycle #' + state.cycleCount + ' ---');

    // Step 0: Check if user is typing in prompt — skip cycle to avoid disruption
    if (isUserTypingInPrompt()) {
      log('SKIP: User is typing in prompt area', 'skip');
      return;
    }

    // Step 1: Fetch fresh credit data via API (v7.9.7 — replaces DOM dialog checking)
    log('Step 1: Fetching credit data via API...', 'check');
    
    const url = CREDIT_API_BASE + '/user/workspaces';
    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    const token = resolveToken();
    if (token) {
      headers['Authorization'] = 'Bearer ' + token;
    }

    // v7.9.24: Comprehensive fetch logging
    log('Cycle API: GET ' + url, 'check');
    logSub('Auth: ' + (token ? 'Bearer ' + token.substring(0, 12) + '...REDACTED' : 'cookies only'), 1);

    fetch(url, { method: 'GET', headers: headers, credentials: 'include' })
      .then(function(resp) {
        const respContentType = resp.headers.get('content-type') || '(none)';
        const respContentLength = resp.headers.get('content-length') || '(not set)';
        log('Cycle API: Response status=' + resp.status + ' content-type="' + respContentType + '" content-length=' + respContentLength, 'check');

        // v7.20: On 401/403, just log — cookie will be re-read on next request
        if ((resp.status === 401 || resp.status === 403) && token) {
          markBearerTokenExpired('loop');
        }

        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.text().then(function(bodyText) {
          log('Cycle API: Body length=' + bodyText.length + ' preview="' + bodyText.substring(0, 200) + '"', 'check');
          return JSON.parse(bodyText);
        });
      })
      .then(function(data) {
        // v7.24: Reset retry count on successful API response
        if (state.retryCount > 0) {
          log('Retry recovery: API succeeded after ' + state.retryCount + ' previous failure(s)', 'success');
          showToast('Recovered after ' + state.retryCount + ' retry(ies)', 'success');
        }
        state.retryCount = 0;
        state.lastRetryError = null;

        if (!state.running || state.isDelegating) {
          log('SKIP: State changed during API fetch', 'skip');
          return;
        }

        const ok = parseLoopApiResponse(data);
        if (!ok) {
          log('Cycle aborted: API response parse failed', 'error');
          return;
        }

        // v7.10.1: Reset workspaceFromApi before cycle detection.
        // The guard should only protect the 2-second post-move refresh from overwriting
        // authoritative state with stale DOM. By the time the next 50s cycle runs,
        // the DOM has updated — we MUST re-detect to catch external workspace changes.
        // Without this reset, the controller shows stale workspace names indefinitely.
        // See Issue #20.
        state.workspaceFromApi = false;

        // Auto-detect current workspace
        return autoDetectLoopCurrentWorkspace(token).then(function() {
          if (!state.running || state.isDelegating) {
            log('SKIP: State changed during workspace detection', 'skip');
            return;
          }

          // Step 2: Check daily free credits from API data (v7.9.37: dailyFree drives move decision)
          syncCreditStateFromApi();
          updateUI();

          const cws = loopCreditState.currentWs;
          let dailyFree = cws ? (cws.dailyFree || 0) : 0;

          if (dailyFree > 0) {
            log('✅ Daily free credits available (' + dailyFree + ') — NO move needed', 'success');
            return;
          }

          // Step 3: No credits — double-confirm with a second API fetch
          log('Step 3: No credits on first check — double-confirming via API...', 'warn');
          
          setTimeout(function() {
            if (!state.running || state.isDelegating) {
              log('SKIP: State changed during double-confirm wait', 'skip');
              return;
            }

            fetch(url, { method: 'GET', headers: headers, credentials: 'include' })
              .then(function(resp2) {
                if (!resp2.ok) throw new Error('HTTP ' + resp2.status);
                return resp2.json();
              })
              .then(function(data2) {
                if (!state.running || state.isDelegating) {
                  log('SKIP: State changed during double-confirm fetch', 'skip');
                  return;
                }

                parseLoopApiResponse(data2);
                state.workspaceFromApi = false; // v7.10.1: force re-detect on double-confirm too
                return autoDetectLoopCurrentWorkspace(token).then(function() {
                  syncCreditStateFromApi();
                  updateUI();

                  const cws2 = loopCreditState.currentWs;
                  const dailyFree2 = cws2 ? (cws2.dailyFree || 0) : 0;

                  if (dailyFree2 > 0) {
                    log('DOUBLE-CONFIRM: Daily free credits found on re-check (' + dailyFree2 + ')! No move needed.', 'success');
                    return;
                  }

                  // Step 4: Confirmed no daily free credits — move via API
                  log('CONFIRMED: No daily free credits after double-check (dailyFree=' + dailyFree2 + ', available=' + (cws2 ? cws2.available : 0) + ') — moving via API', 'delegate');
                  logSub('Direction: ' + state.direction.toUpperCase() + ', Workspace: ' + (cws2 ? cws2.fullName : 'unknown'), 1);
                  performDirectMove(state.direction);
                });
              })
              .catch(function(err) {
                log('Double-confirm API fetch failed: ' + err.message, 'error');
              });
          }, 2000); // 2s gap between first and confirm check
        });
      })
      .catch(function(err) {
        // v7.24: T-1 Auto-retry with exponential backoff
        state.retryCount++;
        const canRetry = state.retryCount <= state.maxRetries;

        if (canRetry) {
          const backoff = state.retryBackoffMs * Math.pow(2, state.retryCount - 1); // 2s, 4s, 8s
          showToast('Cycle failed: ' + err.message + ' — retrying in ' + (backoff / 1000) + 's (attempt ' + state.retryCount + '/' + state.maxRetries + ')', 'warn');
          log('Cycle API fetch failed (attempt ' + state.retryCount + '/' + state.maxRetries + '): ' + err.message + ' — retrying in ' + backoff + 'ms', 'warn');
          setTimeout(function() {
            if (state.running) {
              log('Retry #' + state.retryCount + ' — re-running cycle...', 'check');
              runCycle();
            }
          }, backoff);
        } else {
          // Max retries exhausted
          state.lastRetryError = err.message;
          showToast('Cycle failed after ' + state.maxRetries + ' retries: ' + err.message + '. Loop stopped.', 'error');
          log('Cycle API fetch failed after ' + state.maxRetries + ' retries: ' + err.message + ' — stopping loop', 'error');
          stopLoop();
          // Also try DOM fallback as last resort before full stop
          runCycleDomFallback();
        }
      });
  }

  // ============================================
  // DEPRECATED (v7.9.7): DOM-based cycle fallback
  // Only used when API fetch fails. Opens project dialog to check progress bar.
  // ============================================
  function runCycleDomFallback() {
    log('DOM Fallback: Opening project dialog for progress bar check...', 'warn');
    
    if (isUserTypingInPrompt()) {
      log('SKIP: User is typing — cannot open dialog', 'skip');
      return;
    }

    const clicked = ensureProjectDialogOpen();
    if (!clicked) {
      log('DOM Fallback: project button not found', 'error');
      return;
    }

    pollForDialogReady(function() {
      if (!state.running || state.isDelegating) {
        closeProjectDialog();
        return;
      }
      
      fetchWorkspaceName();
      const hasProgressBar = checkSystemBusy();
      state.isIdle = !hasProgressBar;
      state.hasFreeCredit = hasProgressBar;
      state.lastStatusCheck = Date.now();
      closeProjectDialog();
      
      if (hasProgressBar) {
        log('DOM Fallback: Free credit found — NO move needed', 'success');
        updateUI();
        return;
      }

      log('DOM Fallback: No credit — moving via API', 'delegate');
      performDirectMove(state.direction);
    });
  }

  // ============================================
  // Force Switch - Immediately trigger move without waiting for idle
  // v7.9.6: Now uses direct API move instead of AHK delegation
  // ============================================
  function forceSwitch(direction) {
    if (state.isDelegating) {
      log('BLOCKED: Already moving, ignoring force ' + direction.toUpperCase(), 'warn');
      return;
    }
    log('=== FORCE ' + direction.toUpperCase() + ' ===', 'delegate');
    logSub('v7.9.6: Direct API move — no AHK delegation', 1);
    performDirectMove(direction);
  }

  window.__forceSwitch = forceSwitch;

  // v7.32: Button click animation — scale pulse + color-slide highlight
  function animateBtn(btn) {
    if (!btn) return;
    const origBg = btn.style.background || '';
    const origTransform = btn.style.transform || '';
    btn.style.transition = 'transform 0.1s ease, background 0.15s ease, opacity 0.1s ease';
    btn.style.transform = 'scale(0.88)';
    btn.style.opacity = '0.7';
    setTimeout(function() {
      btn.style.transform = 'scale(1.06)';
      btn.style.opacity = '1';
      // Color slide: briefly flash a lighter highlight
      btn.style.background = 'linear-gradient(90deg, rgba(255,255,255,0.25) 0%, rgba(167,139,250,0.4) 50%, rgba(255,255,255,0.1) 100%)';
      setTimeout(function() {
        btn.style.transform = origTransform || 'scale(1)';
        btn.style.background = origBg;
      }, 180);
    }, 100);
  }

  // v7.21: Consistent hover feedback for injected control buttons
  function attachButtonHoverFx(btn) {
    if (!btn) return;

    btn.onmouseenter = function() {
      if (btn.disabled) return;
      btn.style.filter = 'brightness(1.08) saturate(1.12)';
      btn.style.boxShadow = '0 6px 14px rgba(0,0,0,.28)';
      btn.style.transform = 'translateY(-1px) scale(1.03)';
    };

    btn.onmouseleave = function() {
      btn.style.filter = '';
      btn.style.boxShadow = '';
      btn.style.transform = 'scale(1)';
    };
  }

  // ============================================
  // DEPRECATED (v7.9.6): Delegate Complete - Was called by AHK when done
  // No longer used — performDirectMove() handles its own completion.
  // Kept for backward compatibility if old AHK calls it.
  // ============================================
  function delegateComplete() {
    log('DEPRECATED: delegateComplete called (v7.9.6 uses performDirectMove)', 'warn');
    state.isDelegating = false;
    state.forceDirection = null;
    state.delegateStartTime = 0;
    document.title = document.title.replace(/__AHK_DELEGATE_(UP|DOWN)__URL:.*?__ENDURL__/g, '').replace(/__AHK_DELEGATE_(UP|DOWN)__/g, '');
    state.countdown = Math.floor(TIMING.LOOP_INTERVAL / 1000);
    updateUI();
  }

  // ============================================
  // Workspace Auto-Check - runs every WS_CHECK_INTERVAL ms
  // v6.55: Tries nav-based workspace name fetch FIRST (no dialog needed)
  // Only opens project dialog for credit status check
  // ============================================
  function refreshStatus() {
    // Skip if loop is actively running (runCycle handles its own checks)
    if (state.running) {
      logSub('Workspace auto-check skipped — loop is running (runCycle handles checks)', 1);
      return;
    }
    
    // Skip if user is typing in prompt area
    if (isUserTypingInPrompt()) {
      log('Workspace auto-check: user is typing in prompt — skipping', 'skip');
      return;
    }

    // v6.55: Try lightweight nav-based workspace name fetch first (no dialog disruption)
    const gotNavName = fetchWorkspaceNameFromNav();
    if (gotNavName) {
      logSub('Workspace name updated from nav — skipping dialog open for name', 1);
    }

    // Still need to open dialog for credit status check
    logSub('Workspace auto-check: opening dialog for credit check...', 1);
    const opened = ensureProjectDialogOpen();
    if (!opened) {
      logSub('Workspace auto-check: could not open project dialog', 1);
      updateUI();
      return;
    }

    // Poll for main progress bar instead of fixed wait
    pollForDialogReady(function() {
      // If nav fetch didn't work, try dialog-based fetch as fallback
      if (!gotNavName) {
        const oldName = state.workspaceName;
        fetchWorkspaceName();
        const nameChanged = oldName && state.workspaceName && oldName !== state.workspaceName;
        if (nameChanged) {
          log('Workspace changed during auto-check: "' + oldName + '" -> "' + state.workspaceName + '"', 'success');
        }
      }

      // Check credit while dialog is open
      logSub('Checking credit status (dialog already open)', 1);
      const hasCredit = checkSystemBusy();
      state.hasFreeCredit = hasCredit;
      state.isIdle = !hasCredit;
      state.lastStatusCheck = Date.now();

      // Close the dialog after checking
      closeProjectDialog();
      
      updateUI();
    });
  }

  function startStatusRefresh() {
    if (state.statusRefreshId) return; // already running
    const intervalMs = TIMING.WS_CHECK_INTERVAL || 5000;
    log('Starting workspace auto-check (every ' + (intervalMs/1000) + 's)', 'success');
    state.statusRefreshId = setInterval(refreshStatus, intervalMs);
    // Run immediately on start
    setTimeout(refreshStatus, 1000);
  }

  function stopStatusRefresh() {
    if (state.statusRefreshId) {
      clearInterval(state.statusRefreshId);
      state.statusRefreshId = null;
      log('Workspace auto-check stopped', 'warn');
    }
  }

  // Expose globally
  window.__refreshStatus = refreshStatus;
  window.__startStatusRefresh = startStatusRefresh;
  window.__stopStatusRefresh = stopStatusRefresh;

  // ============================================
  // Set Interval dynamically (called from AHK)
  // ============================================
  function setLoopInterval(newIntervalMs) {
    const oldInterval = TIMING.LOOP_INTERVAL;
    TIMING.LOOP_INTERVAL = newIntervalMs;
    log('Interval changed: ' + oldInterval + 'ms -> ' + newIntervalMs + 'ms', 'success');
    
    state.countdown = Math.floor(newIntervalMs / 1000);
    
    if (state.running && state.loopIntervalId) {
      clearInterval(state.loopIntervalId);
      state.loopIntervalId = setInterval(runCycle, newIntervalMs);
      log('Loop timer restarted with new interval');
    }
    
    updateUI();
    return true;
  }

  // ============================================
  // JS Executor History (ported from combo.js)
  // ============================================
  const loopJsHistory = [];
  let loopJsHistoryIndex = -1;
  const LOOP_JS_HISTORY_MAX = 20;

  function addLoopJsHistoryEntry(code, success, resultText) {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const entry = { time: timeStr, code: code, success: success, result: resultText };
    // Avoid consecutive duplicates
    const isDuplicate = loopJsHistory.length > 0 && loopJsHistory[0].code === code;
    if (!isDuplicate) {
      loopJsHistory.unshift(entry);
      if (loopJsHistory.length > LOOP_JS_HISTORY_MAX) loopJsHistory.pop();
      logSub('JS history updated: ' + loopJsHistory.length + ' entries');
    }
    loopJsHistoryIndex = -1;
    renderLoopJsHistory();
  }

  function renderLoopJsHistory() {
    let el = document.getElementById('loop-js-history');
    if (!el) return;
    if (loopJsHistory.length === 0) {
      el.innerHTML = '<span style="color:#64748b;font-size:10px;">No commands yet</span>';
      return;
    }
    let html = '';
    for (let i = 0; i < loopJsHistory.length; i++) {
      const e = loopJsHistory[i];
      const statusColor = e.success ? '#4ade80' : '#ef4444';
      const statusIcon = e.success ? '✓' : '✗';
      html += '<div class="loop-js-hist-item" data-hist-idx="' + i + '" style="display:flex;gap:4px;align-items:flex-start;padding:3px 4px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05);font-size:10px;font-family:monospace;"'
        + ' onmouseover="this.style.background=\'rgba(139,92,246,0.15)\'"'
        + ' onmouseout="this.style.background=\'transparent\'">'
        + '<span style="color:' + statusColor + ';font-size:10px;">' + statusIcon + '</span>'
        + '<span style="color:#6b7280;font-size:9px;min-width:40px;">' + e.time + '</span>'
        + '<span style="color:#e7e9ed;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + e.code.substring(0, 60) + '</span>'
        + '</div>';
    }
    el.innerHTML = html;
    // Bind click events for recall
    const items = el.querySelectorAll('.loop-js-hist-item');
    for (let j = 0; j < items.length; j++) {
      items[j].onclick = (function(idx) {
        return function() {
          let ta = document.getElementById(IDS.JS_EXECUTOR);
          if (ta && loopJsHistory[idx]) {
            ta.value = loopJsHistory[idx].code;
            ta.focus();
            log('Recalled JS command #' + idx, 'success');
          }
        };
      })(j);
    }
  }

  function navigateLoopJsHistory(direction) {
    let ta = document.getElementById(IDS.JS_EXECUTOR);
    if (!ta || loopJsHistory.length === 0) return;
    if (direction === 'up') {
      if (loopJsHistoryIndex < loopJsHistory.length - 1) {
        loopJsHistoryIndex++;
        ta.value = loopJsHistory[loopJsHistoryIndex].code;
      }
    } else {
      if (loopJsHistoryIndex > 0) {
        loopJsHistoryIndex--;
        ta.value = loopJsHistory[loopJsHistoryIndex].code;
      } else {
        loopJsHistoryIndex = -1;
        ta.value = '';
      }
    }
  }

  // ============================================
  // JS Executor
  // ============================================
  function executeJs() {
    const textbox = document.getElementById(IDS.JS_EXECUTOR);
    if (!textbox) {
      log('JS textbox element not found', 'error');
      return;
    }
    const code = textbox.value.trim();
    if (!code) {
      log('No code to execute', 'warn');
      return;
    }

    log('Executing custom JS code...');
    try {
      const result = new Function(code)();
      const resultStr = result !== undefined ? String(result) : '(undefined)';
      if (result !== undefined) {
        console.log('[MacroLoop v' + VERSION + '] Result:', result);
      }
      log('JS execution completed successfully', 'success');
      addLoopJsHistoryEntry(code, true, resultStr.substring(0, 100));
    } catch (e) {
      log('JS execution error: ' + e.message, 'error');
      addLoopJsHistoryEntry(code, false, e.message);
    }
  }

  // ============================================
  // Create UI
  // ============================================
  let createUIRetryCount = 0;
  const CREATE_UI_MAX_RETRIES = 5;

  // ============================================
  // About Modal — shows author info when version badge is clicked
  // ============================================
  function showAboutModal() {
    // Remove existing if open
    let existing = document.getElementById('macroloop-about-modal');
    if (existing) { existing.remove(); return; }

    const overlay = document.createElement('div');
    overlay.id = 'macroloop-about-modal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:2147483647;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);';
    overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };

    const modal = document.createElement('div');
    modal.style.cssText = 'background:' + lAboutGradient + ';border:1px solid rgba(167,139,250,0.3);border-radius:' + lModalRadius + ';padding:32px;max-width:420px;width:90%;color:' + cPanelText + ';font-family:' + tFontSystem + ';box-shadow:' + lModalShadow + ';';
    modal.className = 'marco-enter';

    // Header
    const header = document.createElement('div');
    header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;';
    const titleEl = document.createElement('div');
    titleEl.style.cssText = 'font-size:18px;font-weight:700;color:#ae7ce8;letter-spacing:-0.3px;';
    titleEl.textContent = '⚡ MacroLoop Controller';
    const closeBtn = document.createElement('span');
    closeBtn.style.cssText = 'font-size:18px;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:6px;transition:all 0.15s;';
    closeBtn.textContent = '✕';
    closeBtn.className = 'marco-transition';
    closeBtn.onmouseenter = function() { closeBtn.style.color = '#e2e8f0'; closeBtn.style.background = 'rgba(255,255,255,0.1)'; };
    closeBtn.onmouseleave = function() { closeBtn.style.color = '#64748b'; closeBtn.style.background = 'none'; };
    closeBtn.onclick = function() { overlay.remove(); };
    header.appendChild(titleEl);
    header.appendChild(closeBtn);
    modal.appendChild(header);

    // Version badge
    const vBadge = document.createElement('div');
    vBadge.style.cssText = 'display:inline-block;background:rgba(167,139,250,0.15);border:1px solid rgba(167,139,250,0.3);border-radius:6px;padding:3px 10px;font-size:11px;color:#a78bfa;font-weight:600;margin-bottom:16px;font-family:monospace;';
    vBadge.textContent = 'v' + VERSION;
    modal.appendChild(vBadge);

    // Description
    const desc = document.createElement('p');
    desc.style.cssText = 'font-size:13px;color:#94a3b8;line-height:1.6;margin:0 0 20px 0;';
    desc.textContent = 'Browser automation & credit management tool for Lovable workspaces. Automatically monitors credits, rotates projects across workspaces, and provides real-time diagnostics.';
    modal.appendChild(desc);

    // Divider
    const divider = document.createElement('div');
    divider.style.cssText = 'height:1px;background:linear-gradient(90deg,transparent,rgba(167,139,250,0.3),transparent);margin:16px 0;';
    modal.appendChild(divider);

    // Author section
    const authorLabel = document.createElement('div');
    authorLabel.style.cssText = 'font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;font-weight:600;margin-bottom:10px;';
    authorLabel.textContent = 'Created by';
    modal.appendChild(authorLabel);

    const authorRow = document.createElement('div');
    authorRow.style.cssText = 'display:flex;align-items:center;gap:12px;margin-bottom:12px;';
    const avatar = document.createElement('div');
    avatar.style.cssText = 'width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#8b5cf6,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:white;flex-shrink:0;';
    avatar.textContent = 'AK';
    const authorInfo = document.createElement('div');
    const authorName = document.createElement('div');
    authorName.style.cssText = 'font-size:15px;font-weight:700;color:#e2e8f0;';
    authorName.textContent = 'Md. Alim Ul Karim';
    const authorTitle = document.createElement('div');
    authorTitle.style.cssText = 'font-size:11px;color:#a78bfa;font-weight:500;margin-top:2px;';
    authorTitle.textContent = 'Chief Software Engineer — Riseup Asia';
    authorInfo.appendChild(authorName);
    authorInfo.appendChild(authorTitle);
    authorRow.appendChild(avatar);
    authorRow.appendChild(authorInfo);
    modal.appendChild(authorRow);

    const authorDesc = document.createElement('p');
    authorDesc.style.cssText = 'font-size:12px;color:#94a3b8;line-height:1.5;margin:0 0 16px 0;';
    authorDesc.textContent = '20+ years of software engineering experience. Former Software Architect at Crossover.com (Top 1% Developer worldwide). Known for inventing an automatic unit test generation tool in 2018 — before AI — capable of writing code and unit tests automatically. Built this tool to help developers work more effectively with automated credit management and workspace orchestration.';
    modal.appendChild(authorDesc);

    // Links
    const linksRow = document.createElement('div');
    linksRow.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;';

    const links = [
      { label: '🔗 alimkarim.com', url: 'https://alimkarim.com' },
      { label: '🚀 Riseup Asia', url: 'https://riseup-asia.com' },
      { label: '💼 LinkedIn', url: 'https://linkedin.com/in/alimkarim' },
    ];

    links.forEach(function(link) {
      let a = document.createElement('a');
      a.href = link.url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.style.cssText = 'font-size:11px;color:#a78bfa;text-decoration:none;padding:5px 12px;border:1px solid rgba(167,139,250,0.25);border-radius:8px;transition:all 0.15s;display:inline-block;';
      a.textContent = link.label;
      a.onmouseenter = function() { a.style.background = 'rgba(167,139,250,0.1)'; a.style.borderColor = 'rgba(167,139,250,0.5)'; };
      a.onmouseleave = function() { a.style.background = 'none'; a.style.borderColor = 'rgba(167,139,250,0.25)'; };
      linksRow.appendChild(a);
    });

    modal.appendChild(linksRow);

    // Footer
    const footer = document.createElement('div');
    footer.style.cssText = 'margin-top:20px;padding-top:12px;border-top:1px solid rgba(100,116,139,0.2);font-size:10px;color:#475569;text-align:center;';
    footer.textContent = '© ' + new Date().getFullYear() + ' Md. Alim Ul Karim — Made with ♥ for the Lovable community';
    modal.appendChild(footer);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    log('About modal opened', 'info');
  }

  function destroyPanel() {
    log('MacroLoop panel DESTROYED by user — remove marker + globals for clean re-inject', 'warn');
    window.__loopDestroyed = true;

    // Stop any active loop
    if (typeof window.__loopStop === 'function') {
      try { window.__loopStop(); } catch (e) { /* ignore */ }
    }

    // Remove DOM elements
    let marker = document.getElementById(IDS.SCRIPT_MARKER);
    if (marker) marker.remove();
    let container = document.getElementById(IDS.CONTAINER);
    if (container) container.remove();

    // Clean up global functions so idempotent guard allows re-injection
    const globals = [
      '__loopStart', '__loopStop', '__loopCheck', '__loopDiag',
      '__loopFetchCredits', '__loopGetBearerToken', '__loopResolvedToken',
      '__loopExportCsv', '__loopExportCsvAvailable', '__loopLogs', '__loopUpdateAuthDiag',
      '__loopShowPanel', '__setProjectButtonXPath', '__setProgressXPath'
    ];
    for (let i = 0; i < globals.length; i++) {
      try { delete window[globals[i]]; } catch (e) { /* ignore */ }
    }

    log('Teardown complete — re-inject script to restore controller', 'success');
  }

  window.__loopDestroy = destroyPanel;

  function createUI() {
    let container = getByXPath(CONFIG.CONTROLS_XPATH);
    if (!container) {
      createUIRetryCount++;
      log('UI container not found at XPath: ' + CONFIG.CONTROLS_XPATH + ' (attempt ' + createUIRetryCount + '/' + CREATE_UI_MAX_RETRIES + ')', 'warn');
      if (createUIRetryCount < CREATE_UI_MAX_RETRIES) {
        log('Retrying in 2 seconds...', 'warn');
        setTimeout(createUI, 2000);
        return;
      }
      // Fallback: attach as fixed floating panel to body
      log('XPath container not found after ' + CREATE_UI_MAX_RETRIES + ' retries — using BODY fallback (floating panel)', 'warn');
      container = document.body;
    }

    if (document.getElementById(IDS.CONTAINER)) {
      log('UI already exists in DOM');
      return;
    }

    let style = document.createElement('style');
    style.textContent = ''
      + '@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}.loop-pulse{animation:pulse 1s infinite}'
      + '@keyframes marcoFadeIn{0%{opacity:0;transform:translateY(10px)}100%{opacity:1;transform:translateY(0)}}'
      + '@keyframes marcoScaleIn{0%{transform:scale(0.95);opacity:0}100%{transform:scale(1);opacity:1}}'
      + '@keyframes marcoSlideIn{0%{transform:translateX(100%)}100%{transform:translateX(0)}}'
      + '@keyframes marcoGlow{0%,100%{box-shadow:0 0 8px ' + cPrimaryGlowSub + '}50%{box-shadow:0 0 18px ' + cPrimaryGlowS + '}}'
      + '.marco-fade-in{animation:marcoFadeIn 0.3s ease-out}'
      + '.marco-scale-in{animation:marcoScaleIn 0.2s ease-out}'
      + '.marco-enter{animation:marcoFadeIn 0.3s ease-out,marcoScaleIn 0.2s ease-out}'
      + '.marco-glow{animation:marcoGlow 2s cubic-bezier(0.4,0,0.6,1) infinite}'
      + '.marco-hover-scale{transition:transform ' + trNormal + '}' 
      + '.marco-hover-scale:hover{transform:scale(1.05)}'
      + '.marco-transition{transition:color ' + trFast + ',background-color ' + trFast + ',border-color ' + trFast + ',box-shadow ' + trFast + '}';
    document.head.appendChild(style);

    let panelState = 'expanded';
    let isDragging = false;
    let dragOffsetX = 0;
    let dragOffsetY = 0;
    let isFloating = false;
    const dragStartPos = { x: 0, y: 0 };
    let dragPointerId = null;

    // Main UI container element
    const ui = document.createElement('div');
    ui.id = IDS.CONTAINER;
    ui.style.cssText = 'background:' + cPanelBg + ';border:1px solid ' + cPanelBorder + ';border-radius:' + lPanelRadius + ';padding:' + lPanelPadding + ';margin:8px 0;font-family:' + tFont + ';font-size:' + tFontSize + ';color:' + cPanelFg + ';min-width:' + lPanelMinW + ';box-shadow:' + lPanelShadow + ';';
    ui.className = 'marco-enter';

    function enableFloating() {
      if (isFloating) return;
      log('Switching MacroLoop panel to floating mode');
      isFloating = true;
      ui.style.position = 'fixed';
      ui.style.zIndex = '99997';
      ui.style.width = lPanelFloatW;
      ui.style.top = '80px';
      ui.style.left = '20px';
      ui.style.margin = '0';
      ui.style.boxShadow = lPanelFloatSh;
    }

    // v7.9.42: Position controller to a screen corner
    function positionLoopController(position) {
      enableFloating();
      let margin = 20;
      if (position === 'bottom-left') {
        ui.style.left = margin + 'px';
        ui.style.right = 'auto';
        ui.style.top = 'auto';
        ui.style.bottom = margin + 'px';
      } else if (position === 'bottom-right') {
        ui.style.left = 'auto';
        ui.style.right = margin + 'px';
        ui.style.top = 'auto';
        ui.style.bottom = margin + 'px';
      }
      log('Moved MacroLoop to ' + position);
    }

    function startDragHandler(e) {
      isDragging = true;
      dragPointerId = e.pointerId;
      let rect = ui.getBoundingClientRect();
      dragOffsetX = e.clientX - rect.left;
      dragOffsetY = e.clientY - rect.top;
      dragStartPos.x = e.clientX;
      dragStartPos.y = e.clientY;
      enableFloating();
      // v7.9.42: Capture pointer to prevent desync when cursor leaves window
      if (e.target.setPointerCapture && dragPointerId != null) {
        e.target.setPointerCapture(dragPointerId);
      }
      e.preventDefault();
    }

    document.addEventListener('pointermove', function(e) {
      if (!isDragging) return;
      ui.style.left = (e.clientX - dragOffsetX) + 'px';
      ui.style.top = (e.clientY - dragOffsetY) + 'px';
      ui.style.right = 'auto';
      ui.style.bottom = 'auto';
      e.preventDefault();
    });

    document.addEventListener('pointerup', function(e) {
      if (!isDragging) return;
      isDragging = false;
      if (e.target.releasePointerCapture && dragPointerId != null) {
        try { e.target.releasePointerCapture(dragPointerId); } catch(ex) {}
      }
      dragPointerId = null;
    });

    // v7.31: Resize handles — bottom-right corner + bottom edge
    // Fixes:
    //   1) When panel is bottom-anchored, resizing now always grows downward (not upward)
    //   2) Key scrollable sections expand with panel height instead of staying fixed
    let isResizing = false;
    let resizeType = ''; // 'corner' or 'bottom'
    let resizeStartX = 0;
    let resizeStartY = 0;
    let resizeStartW = 0;
    let resizeStartH = 0;
    let resizePointerId = null;

    function applyResizeResponsiveLayout(panelHeight) {
      const extra = Math.max(0, panelHeight - resizeStartH);
      const wsListEl = document.getElementById('loop-ws-list');
      if (wsListEl) wsListEl.style.maxHeight = (160 + Math.floor(extra * 0.75)) + 'px';

      const activityPanelEl = document.getElementById('loop-activity-log-panel');
      if (activityPanelEl) activityPanelEl.style.maxHeight = (120 + Math.floor(extra * 0.35)) + 'px';

      const wsHistoryPanelEl = document.getElementById('loop-ws-history-panel');
      if (wsHistoryPanelEl) wsHistoryPanelEl.style.maxHeight = (120 + Math.floor(extra * 0.35)) + 'px';

      const jsHistoryEl = document.getElementById('loop-js-history');
      if (jsHistoryEl) jsHistoryEl.style.maxHeight = (80 + Math.floor(extra * 0.25)) + 'px';
    }

    function createResizeHandle(type) {
      const handle = document.createElement('div');
      if (type === 'corner') {
        handle.style.cssText = 'position:absolute;right:0;bottom:0;width:18px;height:18px;cursor:nwse-resize;z-index:99999;display:flex;align-items:center;justify-content:center;';
        const grip = document.createElement('div');
        grip.style.cssText = 'width:10px;height:10px;opacity:0.4;transition:opacity .2s;';
        grip.innerHTML = '<svg viewBox="0 0 10 10" width="10" height="10"><circle cx="7" cy="3" r="1" fill="#ae7ce8"/><circle cx="3" cy="7" r="1" fill="#ae7ce8"/><circle cx="7" cy="7" r="1" fill="#ae7ce8"/></svg>';
        handle.appendChild(grip);
        handle.onmouseenter = function() { grip.style.opacity = '0.9'; };
        handle.onmouseleave = function() { grip.style.opacity = '0.4'; };
      } else {
        handle.style.cssText = 'position:absolute;left:12px;right:12px;bottom:0;height:6px;cursor:ns-resize;z-index:99998;';
        const bar = document.createElement('div');
        bar.style.cssText = 'width:40px;height:3px;background:#7c3aed;border-radius:2px;margin:2px auto 0;opacity:0.3;transition:opacity .2s;';
        handle.appendChild(bar);
        handle.onmouseenter = function() { bar.style.opacity = '0.8'; };
        handle.onmouseleave = function() { bar.style.opacity = '0.3'; };
      }

      handle.addEventListener('pointerdown', function(e) {
        e.stopPropagation();
        e.preventDefault();
        isResizing = true;
        resizeType = type;
        resizePointerId = e.pointerId;

        let rect = ui.getBoundingClientRect();
        resizeStartX = e.clientX;
        resizeStartY = e.clientY;
        resizeStartW = rect.width;
        resizeStartH = rect.height;

        enableFloating();

        // Critical: lock current rect as explicit top/left anchor so growth goes downward
        ui.style.left = rect.left + 'px';
        ui.style.top = rect.top + 'px';
        ui.style.right = 'auto';
        ui.style.bottom = 'auto';
        ui.style.width = rect.width + 'px';
        ui.style.height = rect.height + 'px';

        if (handle.setPointerCapture && resizePointerId != null) {
          handle.setPointerCapture(resizePointerId);
        }
      });

      return handle;
    }

    document.addEventListener('pointermove', function(e) {
      if (!isResizing) return;
      e.preventDefault();

      const dx = e.clientX - resizeStartX;
      const dy = e.clientY - resizeStartY;

      if (resizeType === 'corner') {
        const newW = Math.max(420, resizeStartW + dx);
        const newH = Math.max(200, resizeStartH + dy);
        ui.style.width = newW + 'px';
        ui.style.height = newH + 'px';
        ui.style.overflow = 'hidden';
        applyResizeResponsiveLayout(newH);
      } else {
        const newH2 = Math.max(200, resizeStartH + dy);
        ui.style.height = newH2 + 'px';
        ui.style.overflow = 'hidden';
        applyResizeResponsiveLayout(newH2);
      }
    });

    document.addEventListener('pointerup', function(e) {
      if (!isResizing) return;
      isResizing = false;
      if (e.target.releasePointerCapture && resizePointerId != null) {
        try { e.target.releasePointerCapture(resizePointerId); } catch(ex) {}
      }
      resizePointerId = null;
    });

    ui.style.position = ui.style.position || 'relative';
    const cornerHandle = createResizeHandle('corner');
    const bottomHandle = createResizeHandle('bottom');
    ui.appendChild(cornerHandle);
    ui.appendChild(bottomHandle);

    let bodyElements = [];

    function toggleMinimize() {
      const isExpanded = panelState === 'expanded';
      if (isExpanded) {
        log('Minimizing MacroLoop panel');
        for (let i = 0; i < bodyElements.length; i++) {
          bodyElements[i].style.display = 'none';
        }
        panelToggleSpan.textContent = '[ + ]';
        panelState = 'minimized';
      } else {
        log('Expanding MacroLoop panel');
        for (let i = 0; i < bodyElements.length; i++) {
          bodyElements[i].style.display = '';
        }
        panelToggleSpan.textContent = '[ - ]';
        panelState = 'expanded';
      }
    }

    function restorePanel() {
      log('Restoring hidden MacroLoop panel');
      ui.style.display = '';
      for (let i = 0; i < bodyElements.length; i++) {
        bodyElements[i].style.display = '';
      }
      panelToggleSpan.textContent = '[ - ]';
      panelState = 'expanded';
    }

    const titleRow = document.createElement('div');
    titleRow.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:grab;user-select:none;padding:2px 0;';
    titleRow.title = 'Drag to move, click to minimize/expand';

    let title = document.createElement('div');
    title.style.cssText = 'font-weight:bold;color:' + cPanelFgMuted + ';font-size:14px;flex:1;';
    title.textContent = 'MacroLoop Controller';

    // v7.9.39: Project name display
    const projectNameEl = document.createElement('div');
    projectNameEl.id = 'loop-project-name';
    projectNameEl.style.cssText = 'font-size:' + tFontTiny + ';color:' + cWarningLight + ';font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;';
    projectNameEl.title = 'Project name (from DOM XPath)';
    projectNameEl.textContent = getDisplayProjectName();

    const versionSpan = document.createElement('span');
    versionSpan.style.cssText = 'font-size:' + tFontTiny + ';color:' + cPrimaryLight + ';margin-right:8px;cursor:pointer;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;';
    versionSpan.textContent = 'v' + VERSION;
    versionSpan.title = 'Click to see About info';
    versionSpan.onclick = function(e) {
      e.stopPropagation();
      showAboutModal();
    };

    const panelToggleSpan = document.createElement('span');
    panelToggleSpan.style.cssText = 'font-size:' + tFontTiny + ';color:' + cNeutral500 + ';cursor:pointer;margin-right:4px;';
    panelToggleSpan.textContent = '[ - ]';

    const hideBtn = document.createElement('span');
    hideBtn.style.cssText = 'font-size:' + tFontTiny + ';color:' + cNeutral500 + ';cursor:pointer;';
    hideBtn.textContent = '[ x ]';
    hideBtn.title = 'Close and fully remove controller (re-inject to restore)';
    hideBtn.onclick = function(e) {
      e.stopPropagation();
      destroyPanel();
    };

    titleRow.onpointerdown = function(e) {
      const isHide = e.target === hideBtn;
      if (isHide) return;
      startDragHandler(e);
    };

    titleRow.onpointerup = function(e) {
      const isHide = e.target === hideBtn;
      if (isHide) return;
      const dx = Math.abs(e.clientX - dragStartPos.x);
      const dy = Math.abs(e.clientY - dragStartPos.y);
      const isClick = dx < 5 && dy < 5;
      if (isClick) {
        toggleMinimize();
      }
    };

    titleRow.appendChild(title);
    titleRow.appendChild(projectNameEl);
    titleRow.appendChild(versionSpan);
    titleRow.appendChild(panelToggleSpan);
    titleRow.appendChild(hideBtn);

    let status = document.createElement('div');
    status.id = IDS.STATUS;
    status.style.cssText = 'font-family:' + tFont + ';font-size:' + tFontSm + ';padding:4px 6px;background:rgba(0,0,0,.4);border-radius:4px;color:' + cNeutral400 + ';';
    status.innerHTML = '<span style="color:' + cWarningLight + ';">⟳</span> Initializing... checking workspace &amp; credit status';

    const infoRow = document.createElement('div');
    infoRow.style.cssText = 'font-size:' + tFontMicro + ';color:' + cPrimaryLighter + ';padding:2px 6px;background:rgba(0,0,0,.2);border-radius:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
    infoRow.textContent = '1. Open Dialog -> 2. Check Credit -> 3. Double-Confirm -> 4. Delegate | Ctrl+Alt+Up/Down | Ctrl+Up/Down (Move) | Ctrl+Alt+H to hide';

    const btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center;padding:8px 4px;';

    const btnStyle = 'padding:5px 10px;border:none;border-radius:4px;font-weight:600;font-size:' + tFontSm + ';cursor:pointer;transition:all ' + trNormal + ';line-height:1;height:28px;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;';
    const menuBtnStyle = 'display:flex;align-items:center;gap:4px;width:100%;padding:5px 10px;border:none;background:transparent;color:' + cPanelFgMuted + ';font-size:' + tFontSm + ';cursor:pointer;text-align:left;border-radius:3px;transition:background ' + trFast + ';';

    // === Check button (always visible) ===
    const checkBtn = document.createElement('button');
    checkBtn.textContent = '☑ Check';
    checkBtn.title = 'One-shot credit check';
    checkBtn.style.cssText = btnStyle + 'background:' + cAccPink + ';color:#fff;';
    let checkInFlight = false;
    let checkInFlightTimer = null;

    function resetCheckButtonState() {
      if (checkInFlightTimer) {
        clearTimeout(checkInFlightTimer);
        checkInFlightTimer = null;
      }
      checkInFlight = false;
      checkBtn.textContent = '☑ Check';
      checkBtn.style.opacity = '1';
      checkBtn.style.pointerEvents = 'auto';
    }

    checkBtn.onclick = function() {
      if (checkInFlight) {
        log('Check cooldown: already in flight', 'warn');
        return;
      }
      if (state.isDelegating) {
        log('Check blocked: move/delegation in progress', 'warn');
        checkBtn.style.opacity = '0.5';
        setTimeout(function() { checkBtn.style.opacity = '1'; }, 500);
        return;
      }

      checkInFlight = true;
      checkBtn.textContent = '⏳ Checking…';
      checkBtn.style.opacity = '0.6';
      checkBtn.style.pointerEvents = 'none';

      // Failsafe: never leave Check button permanently locked
      checkInFlightTimer = setTimeout(function() {
        if (checkInFlight) {
          log('Manual Check timeout (15s) — auto-resetting button state', 'warn');
          resetCheckButtonState();
        }
      }, 15000);

      let checkPromise;
      try {
        checkPromise = runCheck();
      } catch(syncErr) {
        log('Manual Check sync error: ' + syncErr.message, 'error');
        resetCheckButtonState();
        return;
      }

      if (checkPromise && typeof checkPromise.then === 'function') {
        checkPromise.then(function() {
          log('Manual Check completed successfully', 'success');
        }).catch(function(err) {
          log('Manual Check failed: ' + (err && err.message ? err.message : String(err)), 'error');
        }).then(function() {
          // finally equivalent
          resetCheckButtonState();
        });
      } else {
        resetCheckButtonState();
      }
    };

    // === Start/Stop toggle (always visible, ▶/⏹ icons) ===
    const startStopBtn = document.createElement('button');
    startStopBtn.id = IDS.START_BTN;
    startStopBtn.textContent = '▶';
    startStopBtn.title = 'Start loop';
    startStopBtn.style.cssText = btnStyle + 'background:' + cSuccess + ';color:#fff;font-size:14px;padding:5px 12px;';
    let loopIsRunning = false;
    startStopBtn.onclick = function() {
      // Use source-of-truth state to avoid stale local toggle state
      if (state.running) {
        stopLoop();
      } else {
        startLoop(state.direction);
      }
    };

    function updateStartStopBtn(running) {
      const isRunning = (typeof running === 'boolean') ? running : !!state.running;
      loopIsRunning = isRunning;
      if (isRunning) {
        startStopBtn.textContent = '⏹';
        startStopBtn.title = 'Stop loop';
        startStopBtn.style.background = cError;
      } else {
        startStopBtn.textContent = '▶';
        startStopBtn.title = 'Start loop';
        startStopBtn.style.background = cSuccess;
      }
    }

    // Expose so startLoop/stopLoop can call it
    window.__loopUpdateStartStopBtn = updateStartStopBtn;
    // Sync initial visual state in case loop was started before button wiring completed
    updateStartStopBtn(!!state.running);

    // === Credits button (always visible) ===
    const creditBtn = document.createElement('button');
    creditBtn.textContent = '💰 Credits';
    creditBtn.title = 'Fetch credit status via API and refresh workspace bars';
    creditBtn.style.cssText = btnStyle + 'background:' + cPrimaryDark + ';color:' + cAccPurpleLight + ';font-size:' + tFontTiny + ';padding:5px 8px;';
    creditBtn.onclick = function() { fetchLoopCredits(); };

    // === Prompts dropdown (always visible, placeholder — T-3 will populate) ===
    const promptsContainer = document.createElement('div');
    promptsContainer.style.cssText = 'position:relative;display:inline-block;';
    const promptsBtn = document.createElement('button');
    promptsBtn.textContent = '📋 Prompts';
    promptsBtn.title = 'Select a prompt to paste or copy';
    promptsBtn.style.cssText = btnStyle + 'background:#1e3a5f;color:#93c5fd;font-size:' + tFontTiny + ';padding:5px 8px;';
    const promptsDropdown = document.createElement('div');
    promptsDropdown.style.cssText = 'display:none;position:absolute;top:100%;left:0;min-width:220px;max-width:340px;max-height:280px;overflow-y:auto;background:' + cPanelBg + ';border:1px solid ' + cPrimary + ';border-radius:' + lDropdownRadius + ';z-index:100001;box-shadow:' + lDropdownShadow + ';margin-top:2px;';

    // T-3: Hardcoded SHORT fallback prompts — canonical full text lives in macro-prompts.json
    // These are only used if JSON config is not available (standalone AHK without extension)
    const DEFAULT_PROMPTS = [
      { name: 'Start Prompt', text: 'Write a readme.txt text file with 3 words with no context at all "let\'s start now {date:dd-MMM-YYYY} {time:12 hr clock format exact time now for malaysia}"' },
      { name: 'Start Prompt v2', text: 'Write a readme.txt text file with 3 words with no context at all "let\'s start now {date:dd-MMM-YYYY} {time:12 hr clock format exact time now for malaysia}"\n\nUh, try to write a file to the Git system. I\'m not sure what you are doing. I\'m asking you to write the text file in the read.txt file, readme.txt file with the date and time, and you are not doing it. You are not doing a Git, uh, update. Are you stupid?' },
      { name: 'Rejog the Memory v1', text: 'Read and synthesize existing repository context from the Lovable memory folder and the full specification set, then produce a reliability risk report before any implementation work begins. Do not implement anything. Only produce a report and specification-side artifacts for memory, suggestions, and planning.' },
      { name: 'Unified AI Prompt v4', text: 'Read and synthesize existing repository context from the Lovable memory folder and the full specification set. Follow the Required Execution Order: scan repo, read memory, read specs, reconstruct context, produce reliability report, propose corrections, update memory, update plan, ask user which task to implement next.' },
      { name: 'Issues Tracking', text: 'Do not implement any code changes. Update specifications and documentation only. Enforce a strict workflow so the same mistakes do not repeat, and ensure every fix is recorded in a standardized issue write-up file and reflected in memory.' },
      { name: 'Unit Test Failing', text: 'Fix failing tests: 1) Check code, 2) Check actual method implementation, 3) Check logical implementation of the test, 4) Check test case, 5) Fix logically either the implementation or the test. Document at /spec/05-failing-tests/{seq}-failing-test-name.md with root cause and solution.' },
      { name: 'Audit Spec v1', text: 'Perform a comprehensive audit of every specification file. Score each spec on Completeness, Consistency, Implementation Alignment, Clarity, Maintainability, and Test Coverage (1-10 scale). Produce a scorecard, detailed findings for specs below 8.0, cross-spec dependency map, and priority fix list. Write report to .lovable/memory/audit/spec-audit-report.md.' },
      { name: 'Minor Bump', text: 'Bump all Minor versions for all', category: 'versioning' },
      { name: 'Major Bump', text: 'Bump all Major versions for all', category: 'versioning' },
      { name: 'Patch Bump', text: 'Bump all Patch versions for all', category: 'versioning' }
    ];
    const DEFAULT_PASTE_XPATH = '/html/body/div[3]/div/div[2]/main/div/div/div[1]/div/div[2]/div/form/div[3]/div/div/div/div';

    // v7.27: Cached prompts from JSON file (loaded async on first use)
    let _loadedJsonPrompts = null;
    let _jsonPromptsLoading = false;

    // v7.29: Load full prompts with multi-source fallback
    // Priority: __MARCO_PROMPTS__ (explicit inject) → bundled macro-prompts.json → extension message
    function loadPromptsFromJson(callback) {
      function finish(prompts, source) {
        _jsonPromptsLoading = false;
        if (prompts && prompts.length > 0) {
          _loadedJsonPrompts = prompts;
          log('Loaded ' + prompts.length + ' prompts from ' + source, 'success');
          callback(_loadedJsonPrompts);
          return;
        }
        callback(null);
      }

      function normalizePromptEntries(entries) {
        if (!Array.isArray(entries)) return [];
        const out = [];
        for (let i = 0; i < entries.length; i++) {
          let p = entries[i] || {};
          let name = typeof p.name === 'string' ? p.name : '';
          let text = typeof p.text === 'string' ? p.text : '';
          if (name && text) out.push({ name: name, text: text });
        }
        return out;
      }

      function parseWithRecovery(content) {
        try {
          return JSON.parse(content);
        } catch (e) {
          const trimmed = String(content || '').trim();
          const lastBrace = trimmed.lastIndexOf('}');
          if (lastBrace > 0) {
            const repaired = trimmed.substring(0, lastBrace + 1);
            // Recover truncated arrays: [{...}, {...
            if (trimmed.charAt(0) === '[') {
              repaired += ']';
            }
            try {
              return JSON.parse(repaired);
            } catch (_) {}
          }
          throw e;
        }
      }

      function tryLoadFromJsonUrl(url, onDone) {
        if (!url || typeof fetch !== 'function') {
          onDone(null);
          return;
        }
        fetch(url).then(function(resp) {
          if (!resp || !resp.ok) {
            onDone(null);
            return;
          }
          return resp.text().then(function(raw) {
            try {
              const parsed = parseWithRecovery(raw);
              const prompts = normalizePromptEntries((parsed && parsed.prompts) || parsed);
              onDone(prompts.length > 0 ? prompts : null);
            } catch (err) {
              log('Prompt JSON parse failed at ' + url + ': ' + (err && err.message ? err.message : String(err)), 'warn');
              onDone(null);
            }
          });
        }).catch(function() {
          onDone(null);
        });
      }

      function tryLoadByMessage(type, onDone) {
        if (!(typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.sendMessage)) {
          onDone(null);
          return;
        }
        try {
          chrome.runtime.sendMessage({ type: type }, function(response) {
            const runtimeErr = chrome.runtime && chrome.runtime.lastError;
            if (runtimeErr) {
              onDone(null);
              return;
            }
            const prompts = normalizePromptEntries(response && response.prompts);
            onDone(prompts.length > 0 ? prompts : null);
          });
        } catch (_) {
          onDone(null);
        }
      }

      if (_loadedJsonPrompts) { callback(_loadedJsonPrompts); return; }
      if (_jsonPromptsLoading) { callback(null); return; }
      _jsonPromptsLoading = true;

      // Method 1: injected runtime global
      if (window.__MARCO_PROMPTS__ && Array.isArray(window.__MARCO_PROMPTS__) && window.__MARCO_PROMPTS__.length > 0) {
        finish(normalizePromptEntries(window.__MARCO_PROMPTS__), '__MARCO_PROMPTS__');
        return;
      }

      // Method 2: bundled JSON in extension dist (full canonical prompts)
      if (typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.getURL) {
        tryLoadFromJsonUrl(chrome.runtime.getURL('config/macro-prompts.json'), function(promptsFromJson) {
          // T-4.3: Always also load user-saved prompts via GET_PROMPTS and merge
          function mergeUserPrompts(basePrompts) {
            tryLoadByMessage('GET_PROMPTS', function(userPrompts) {
              if (userPrompts && userPrompts.length > 0) {
                // Merge: user prompts override base by name
                const merged = (basePrompts || []).slice();
                const seen = {};
                for (let m = 0; m < merged.length; m++) {
                  seen[(merged[m].name || '').toLowerCase()] = true;
                }
                for (let u = 0; u < userPrompts.length; u++) {
                  const uName = (userPrompts[u].name || '').toLowerCase();
                  if (uName && !seen[uName]) {
                    merged.push(userPrompts[u]);
                    seen[uName] = true;
                  }
                }
                finish(merged, 'extension JSON + user prompts');
              } else if (basePrompts && basePrompts.length > 0) {
                finish(basePrompts, 'extension config/macro-prompts.json');
              } else {
                finish(null, 'fallback');
              }
            });
          }

          if (promptsFromJson) {
            mergeUserPrompts(promptsFromJson);
            return;
          }

          // No JSON file — try GET_PROMPTS alone, then legacy fallback
          tryLoadByMessage('GET_PROMPTS', function(promptsFromMsg) {
            if (promptsFromMsg) {
              finish(promptsFromMsg, 'extension message GET_PROMPTS');
              return;
            }
            tryLoadByMessage('GET_PROMPTS_CONFIG', function(legacyPrompts) {
              if (legacyPrompts) {
                finish(legacyPrompts, 'extension message GET_PROMPTS_CONFIG');
                return;
              }
              finish(null, 'fallback');
            });
          });
        });
        return;
      }

      // Method 4: web path fallback (non-extension preview environments)
      tryLoadFromJsonUrl('/standalone-scripts/macro-controller/macro-prompts.json', function(promptsFromWebJson) {
        finish(promptsFromWebJson, 'web /standalone-scripts/macro-controller/macro-prompts.json');
      });
    }

    // T-3: Resolve prompts config from multiple sources
    function getPromptsConfig() {
      const promptsCfg = cfg.prompts || (window.__MARCO_CONFIG__ || {}).prompts || {};
      let entries = promptsCfg.entries || promptsCfg.prompts || [];
      if (!Array.isArray(entries) && typeof entries === 'object') {
        entries = entries.entries || [];
      }

      // v7.31: Prefer loaded full JSON prompts over config defaults.
      // Keep config-only custom entries by appending names not present in JSON.
      if (_loadedJsonPrompts && Array.isArray(_loadedJsonPrompts) && _loadedJsonPrompts.length > 0) {
        const merged = _loadedJsonPrompts.slice();
        const seen = {};
        for (let i = 0; i < merged.length; i++) {
          seen[(merged[i].name || '').toLowerCase()] = true;
        }
        if (Array.isArray(entries)) {
          for (let j = 0; j < entries.length; j++) {
            let p = entries[j] || {};
            const n = typeof p.name === 'string' ? p.name : '';
            let t = typeof p.text === 'string' ? p.text : '';
            let key = n.toLowerCase();
            if (n && t && !seen[key]) {
              merged.push({ name: n, text: t });
              seen[key] = true;
            }
          }
        }
        entries = merged;
      }

      // Fallback chain
      if (!Array.isArray(entries) || entries.length === 0) {
        entries = DEFAULT_PROMPTS;
      }

      return {
        entries: entries,
        pasteTargetXPath: promptsCfg.pasteTargetXPath || promptsCfg.pasteTarget && promptsCfg.pasteTarget.xpath || DEFAULT_PASTE_XPATH,
        pasteTargetSelector: promptsCfg.pasteTargetSelector || promptsCfg.pasteTarget && promptsCfg.pasteTarget.selector || ''
      };
    }

    // T-3: Find the editor text area to paste into
    function findPasteTarget(promptsCfg) {
      let el = null;
      if (promptsCfg.pasteTargetXPath) {
        el = getByXPath(promptsCfg.pasteTargetXPath);
        if (el) return el;
      }
      if (promptsCfg.pasteTargetSelector) {
        el = document.querySelector(promptsCfg.pasteTargetSelector);
        if (el) return el;
      }
      // Auto-discovery fallback: common Lovable editor selectors
      const selectors = [
        'form textarea[placeholder]',
        'div[contenteditable="true"]',
        'textarea.ProseMirror',
        '[data-testid="prompt-input"]'
      ];
      for (let s = 0; s < selectors.length; s++) {
        el = document.querySelector(selectors[s]);
        if (el) return el;
      }
      return null;
    }

    // T-3: Paste text into the editor element
    // v7.32: Full-text insertion with chunked fallback and post-paste verification.
    // Strategies (in order): synthetic ClipboardEvent, clipboard API + real paste,
    // chunked execCommand insertText, direct DOM set.
    function showPasteToast(message, isError) {
      const toast = document.createElement('div');
      toast.textContent = message;
      toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);' +
        'padding:10px 20px;border-radius:8px;font-size:13px;z-index:1000000;' +
        'color:#fff;font-family:system-ui,sans-serif;pointer-events:none;' +
        'transition:opacity .3s;opacity:0;' +
        (isError ? 'background:#dc2626;' : 'background:#16a34a;');
      document.body.appendChild(toast);
      requestAnimationFrame(function() { toast.style.opacity = '1'; });
      setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() { toast.remove(); }, 300);
      }, isError ? 4000 : 2500);
    }

    function pasteIntoEditor(text, promptsCfg) {
      let target = findPasteTarget(promptsCfg);
      if (!target) {
        log('Prompt paste: No editor target found — copying to clipboard instead', 'warn');
        navigator.clipboard.writeText(text).then(function() {
          log('Prompt copied to clipboard (no paste target)', 'success');
          showPasteToast('📋 Copied to clipboard — paste manually with Ctrl+V', false);
        }).catch(function() {
          showPasteToast('❌ Could not paste or copy — editor target not found', true);
        });
        return false;
      }
      target.focus();
      log('Prompt paste: target found (' + target.tagName + ', contentEditable=' + target.contentEditable + '), text length=' + text.length, 'info');

      // Clear existing content first (select all within target, then replace)
      clearTargetContent(target);

      // Strategy 1: Synthetic paste event with DataTransfer (best for ProseMirror/Tiptap)
      let pasted = false;
      try {
        const dt = new DataTransfer();
        dt.setData('text/plain', text);
        const pasteEvent = new ClipboardEvent('paste', {
          bubbles: true,
          cancelable: true,
          clipboardData: dt
        });
        pasted = !target.dispatchEvent(pasteEvent); // returns false if preventDefault was called
        if (pasted) {
          log('Prompt pasted via synthetic ClipboardEvent (' + text.length + ' chars)', 'success');
          // Verify the paste was not truncated
          const actualLen = getTargetTextLength(target);
          if (actualLen > 0 && actualLen < text.length * 0.9) {
            log('Prompt paste: ClipboardEvent appears truncated (' + actualLen + '/' + text.length + ' chars), retrying with direct set', 'warn');
            pasted = false;
          }
        } else {
          log('Prompt paste: ClipboardEvent dispatched but not consumed by editor, trying fallbacks', 'warn');
        }
      } catch (e) {
        log('Prompt paste: synthetic ClipboardEvent failed: ' + (e.message || e), 'warn');
      }

      // Strategy 2: Write to real clipboard and trigger a real paste via execCommand
      if (!pasted) {
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            // Use synchronous execCommand('paste') after writing to clipboard
            // Note: This is async — schedule fallback after a short delay
            let clipWritten = false;
            navigator.clipboard.writeText(text).then(function() {
              clipWritten = true;
              target.focus();
              // Try execCommand paste (works in some contexts with clipboard permission)
              try {
                const pasteOk = document.execCommand('paste');
                if (pasteOk) {
                  log('Prompt pasted via clipboard.writeText + execCommand paste (' + text.length + ' chars)', 'success');
                  return;
                }
              } catch (ep) { /* ignore */ }
              // If execCommand paste didn't work, the text is still on clipboard
              log('Prompt paste: text written to clipboard, execCommand paste failed — user can Ctrl+V', 'info');
            }).catch(function(err) {
              log('Prompt paste: clipboard.writeText failed: ' + err, 'warn');
            });
            // Don't return true here — continue to synchronous fallbacks
          }
        } catch (e2) {
          log('Prompt paste: clipboard strategy failed: ' + (e2.message || e2), 'warn');
        }
      }

      // Strategy 3: Chunked insertText execCommand (handles large texts that get truncated)
      if (!pasted) {
        try {
          const sel = window.getSelection();
          const range = document.createRange();
          range.selectNodeContents(target);
          sel.removeAllRanges();
          sel.addRange(range);

          // Chunk the text to avoid browser insertText limits (~32KB in some browsers)
          const CHUNK_SIZE = 16384; // 16KB chunks
          if (text.length <= CHUNK_SIZE) {
            const inserted = document.execCommand('insertText', false, text);
            if (inserted) {
              pasted = true;
              log('Prompt pasted via execCommand insertText (' + text.length + ' chars)', 'success');
            }
          } else {
            // Clear selection after first chunk — subsequent chunks append
            const firstChunk = text.substring(0, CHUNK_SIZE);
            const ok = document.execCommand('insertText', false, firstChunk);
            if (ok) {
              pasted = true;
              for (const ci = CHUNK_SIZE; ci < text.length; ci += CHUNK_SIZE) {
                const chunk = text.substring(ci, Math.min(ci + CHUNK_SIZE, text.length));
                // Move cursor to end
                const endSel = window.getSelection();
                if (endSel.rangeCount > 0) {
                  const endRange = endSel.getRangeAt(0);
                  endRange.collapse(false); // collapse to end
                }
                document.execCommand('insertText', false, chunk);
              }
              log('Prompt pasted via chunked execCommand insertText (' + text.length + ' chars, ' + Math.ceil(text.length / CHUNK_SIZE) + ' chunks)', 'success');
            }
          }

          // Verify no truncation
          if (pasted) {
            const verifyLen = getTargetTextLength(target);
            if (verifyLen > 0 && verifyLen < text.length * 0.9) {
              log('Prompt paste: execCommand result truncated (' + verifyLen + '/' + text.length + '), falling back to direct set', 'warn');
              pasted = false;
            }
          }
        } catch (e3) {
          log('Prompt paste: execCommand fallback failed: ' + (e3.message || e3), 'warn');
        }
      }

      // Strategy 4: Direct DOM set (last resort — always delivers full text)
      if (!pasted) {
        directSet(target, text);
        // Verify direct set worked
        const directLen = getTargetTextLength(target);
        if (directLen < text.length * 0.5) {
          log('Prompt paste: All strategies failed — content may not be visible', 'error');
          navigator.clipboard.writeText(text).catch(function() {});
          showPasteToast('⚠️ Paste may have failed — copied to clipboard, try Ctrl+V', true);
          return false;
        }
      }

      log('Prompt paste complete: "' + text.substring(0, 80) + '..." (' + text.length + ' total chars)', 'success');
      showPasteToast('✓ Prompt pasted (' + text.length + ' chars)', false);
      return true;
    }

    function clearTargetContent(target) {
      try {
        if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
          const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value') ||
                             Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
          if (nativeSetter && nativeSetter.set) {
            nativeSetter.set.call(target, '');
          } else {
            target.value = '';
          }
        } else {
          // For contenteditable: select all within target and delete
          const sel = window.getSelection();
          const range = document.createRange();
          range.selectNodeContents(target);
          sel.removeAllRanges();
          sel.addRange(range);
          document.execCommand('delete', false, null);
        }
      } catch (e) {
        // Non-critical — insertion will replace content anyway
      }
    }

    function getTargetTextLength(target) {
      try {
        if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
          return (target.value || '').length;
        }
        return (target.textContent || '').length;
      } catch (e) {
        return -1; // Unknown — skip verification
      }
    }

    function directSet(target, text) {
      if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
        // For native inputs, use nativeInputValueSetter to bypass React's synthetic events
        const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value') ||
                           Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        if (nativeSetter && nativeSetter.set) {
          nativeSetter.set.call(target, text);
        } else {
          target.value = text;
        }
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
      } else {
        target.textContent = text;
        target.dispatchEvent(new Event('input', { bubbles: true }));
      }
      log('Prompt pasted via direct DOM set (' + text.length + ' chars)', 'warn');
    }

    let _promptCategoryFilter = null; // null = show all

    function renderPromptsDropdown() {
      const promptsCfg = getPromptsConfig();
      let entries = promptsCfg.entries;
      if (!entries.length) entries = [{ name: 'No prompts configured', text: '' }];
      promptsDropdown.innerHTML = '';

      // Collect unique categories
      const categories = [];
      const catSeen = {};
      for (let c = 0; c < entries.length; c++) {
        const cat = (entries[c].category || '').trim();
        if (cat && !catSeen[cat.toLowerCase()]) {
          categories.push(cat);
          catSeen[cat.toLowerCase()] = true;
        }
      }

      // Header
      const header = document.createElement('div');
      header.style.cssText = 'padding:4px 8px;font-size:9px;color:#a78bfa;border-bottom:1px solid #7c3aed;';
      header.textContent = '📋 Click to paste · 📋 icon to copy';
      promptsDropdown.appendChild(header);

      // Category filter bar (only if categories exist)
      if (categories.length > 0) {
        const filterBar = document.createElement('div');
        filterBar.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;padding:6px 8px;border-bottom:1px solid rgba(124,58,237,0.2);';

        function makeFilterChip(label, value) {
          const chip = document.createElement('span');
          chip.textContent = label;
          const isActive = _promptCategoryFilter === value;
          chip.style.cssText = 'padding:2px 8px;border-radius:10px;font-size:9px;cursor:pointer;transition:all .15s;' +
            (isActive ? 'background:#7c3aed;color:#fff;' : 'background:rgba(124,58,237,0.15);color:#a78bfa;');
          chip.onclick = function(e) {
            e.stopPropagation();
            _promptCategoryFilter = isActive ? null : value;
            renderPromptsDropdown();
          };
          return chip;
        }

        filterBar.appendChild(makeFilterChip('All', null));
        for (let f = 0; f < categories.length; f++) {
          filterBar.appendChild(makeFilterChip(categories[f], categories[f].toLowerCase()));
        }
        promptsDropdown.appendChild(filterBar);
      }

      // Filter entries by category
      let filtered = entries;
      if (_promptCategoryFilter) {
        filtered = [];
        for (let fi = 0; fi < entries.length; fi++) {
          if ((entries[fi].category || '').trim().toLowerCase() === _promptCategoryFilter) {
            filtered.push(entries[fi]);
          }
        }
        if (filtered.length === 0) {
          const empty = document.createElement('div');
          empty.style.cssText = 'padding:12px 8px;text-align:center;color:#6b7280;font-size:11px;';
          empty.textContent = 'No prompts in this category';
          promptsDropdown.appendChild(empty);
        }
      }
      for (let i = 0; i < filtered.length; i++) {
        (function(p, idx) {
          const item = document.createElement('div');
          item.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 8px;cursor:pointer;font-size:10px;color:#c9a8ef;border-bottom:1px solid rgba(124,58,237,0.15);';
          item.onmouseover = function() { this.style.background = 'rgba(139,92,246,0.2)'; };
          item.onmouseout = function() { this.style.background = 'transparent'; };
          // Number badge
          const badge = document.createElement('span');
          badge.textContent = (idx + 1);
          badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:3px;background:#7c3aed;color:#e7e9ed;font-size:8px;font-weight:700;margin-right:6px;flex-shrink:0;';
          item.appendChild(badge);
          const nameSpan = document.createElement('span');
          nameSpan.textContent = p.name;
          nameSpan.style.cssText = 'flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
          nameSpan.title = p.text || '';
          item.appendChild(nameSpan);

          // ── T-4.2: Action buttons container (edit, delete, copy) ──
          const actions = document.createElement('span');
          actions.style.cssText = 'display:flex;align-items:center;gap:2px;margin-left:4px;flex-shrink:0;';

          if (p.text) {
            // Edit button
            const editIcon = document.createElement('span');
            editIcon.textContent = '✏️';
            editIcon.title = 'Edit prompt';
            editIcon.style.cssText = 'cursor:pointer;font-size:10px;opacity:0.6;';
            editIcon.onmouseover = function() { this.style.opacity = '1'; };
            editIcon.onmouseout = function() { this.style.opacity = '0.6'; };
            editIcon.onclick = function(e) {
              e.stopPropagation();
              promptsDropdown.style.display = 'none';
              openPromptCreationModal({ id: p.id, name: p.name, text: p.text, category: p.category, isDefault: p.isDefault });
            };
            actions.appendChild(editIcon);

            // Delete button (only for non-default / user prompts, or all if they have an id)
            if (!p.isDefault) {
              const delIcon = document.createElement('span');
              delIcon.textContent = '🗑️';
              delIcon.title = 'Delete prompt';
              delIcon.style.cssText = 'cursor:pointer;font-size:10px;opacity:0.6;';
              delIcon.onmouseover = function() { this.style.opacity = '1'; };
              delIcon.onmouseout = function() { this.style.opacity = '0.6'; };
              delIcon.onclick = function(e) {
                e.stopPropagation();
                if (!confirm('Delete prompt "' + p.name + '"?')) return;
                sendToExtension('DELETE_PROMPT', { promptId: p.id }, function(resp) {
                  if (resp && resp.isOk) {
                    log('Deleted prompt: ' + p.name, 'success');
                    _loadedJsonPrompts = null; // invalidate cache
                    // Refresh dropdown
                    loadPromptsFromJson(function() { renderPromptsDropdown(); });
                  } else {
                    log('Failed to delete prompt: ' + p.name, 'error');
                  }
                });
              };
              actions.appendChild(delIcon);
            }

            // Copy button
            const copyIcon = document.createElement('span');
            copyIcon.textContent = '📋';
            copyIcon.title = 'Copy to clipboard';
            copyIcon.style.cssText = 'cursor:pointer;font-size:11px;opacity:0.7;';
            copyIcon.onmouseover = function() { this.style.opacity = '1'; };
            copyIcon.onmouseout = function() { this.style.opacity = '0.7'; };
            copyIcon.onclick = function(e) {
              e.stopPropagation();
              navigator.clipboard.writeText(p.text).then(function() {
                log('Prompt copied: ' + p.name, 'success');
                copyIcon.textContent = '✅';
                setTimeout(function() { copyIcon.textContent = '📋'; }, 1500);
              });
            };
            actions.appendChild(copyIcon);

            item.onclick = function() {
              pasteIntoEditor(p.text, promptsCfg);
              promptsDropdown.style.display = 'none';
            };
          }
          item.appendChild(actions);
          promptsDropdown.appendChild(item);
        })(filtered[i], i);
      }

      // ── T-3.1: ➕ Add New Prompt button ──
      const addBtn = document.createElement('div');
      addBtn.style.cssText = 'display:flex;align-items:center;justify-content:center;padding:8px;cursor:pointer;font-size:11px;color:#a78bfa;border-top:1px solid rgba(124,58,237,0.3);';
      addBtn.textContent = '➕ Add New Prompt';
      addBtn.onmouseover = function() { this.style.background = 'rgba(139,92,246,0.2)'; };
      addBtn.onmouseout = function() { this.style.background = 'transparent'; };
      addBtn.onclick = function(e) {
        e.stopPropagation();
        promptsDropdown.style.display = 'none';
        openPromptCreationModal();
      };
      promptsDropdown.appendChild(addBtn);
    }

    // ── T-3.2–T-3.5: Prompt Creation Modal ──
    function sendToExtension(type, payload, callback) {
      // Try chrome.runtime.sendMessage first, fall back to window.postMessage relay
      if (typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.sendMessage) {
        try {
          const msg = Object.assign({ type: type }, payload);
          chrome.runtime.sendMessage(msg, function(resp) {
            if (chrome.runtime.lastError) {
              log('Extension message error: ' + (chrome.runtime.lastError.message || ''), 'warn');
              if (callback) callback(null);
              return;
            }
            if (callback) callback(resp);
          });
          return;
        } catch (e) { /* fall through to relay */ }
      }
      // Relay via window.postMessage (content script bridge)
      const requestId = 'pr-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
      function onResponse(event) {
        if (event.data && event.data.source === 'marco-extension' && event.data.requestId === requestId) {
          window.removeEventListener('message', onResponse);
          if (callback) callback(event.data.payload);
        }
      }
      window.addEventListener('message', onResponse);
      window.postMessage({ source: 'marco-controller', type: type, requestId: requestId, prompt: payload.prompt || undefined, promptId: payload.promptId || undefined }, '*');
      // Timeout after 5s
      setTimeout(function() { window.removeEventListener('message', onResponse); }, 5000);
    }

    function openPromptCreationModal(editPrompt) {
      // Remove existing modal if any
      let existing = document.getElementById('marco-prompt-modal');
      if (existing) existing.remove();

      const isEdit = !!editPrompt;
      const overlay = document.createElement('div');
      overlay.id = 'marco-prompt-modal';
      overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:1000010;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,sans-serif;';

      const modal = document.createElement('div');
      modal.style.cssText = 'background:#1e2233;border:1px solid #7c3aed;border-radius:12px;width:520px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.8);';

      // Header
      const headerEl = document.createElement('div');
      headerEl.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid rgba(124,58,237,0.3);';
      const titleEl = document.createElement('span');
      titleEl.textContent = isEdit ? '✏️ Edit Prompt' : '➕ New Prompt';
      titleEl.style.cssText = 'font-size:15px;font-weight:600;color:#e7e9ed;';
      const closeBtn = document.createElement('button');
      closeBtn.textContent = '✕';
      closeBtn.style.cssText = 'background:none;border:none;color:#9ca3af;font-size:18px;cursor:pointer;padding:0 4px;';
      closeBtn.onclick = function() { overlay.remove(); };
      headerEl.appendChild(titleEl);
      headerEl.appendChild(closeBtn);
      modal.appendChild(headerEl);

      // Body (scrollable)
      const body = document.createElement('div');
      body.style.cssText = 'padding:16px 20px;overflow-y:auto;flex:1;';

      // Title input
      const titleLabel = document.createElement('label');
      titleLabel.textContent = 'Prompt Title';
      titleLabel.style.cssText = 'display:block;font-size:11px;color:#a78bfa;margin-bottom:4px;font-weight:600;';
      body.appendChild(titleLabel);
      const titleInput = document.createElement('input');
      titleInput.type = 'text';
      titleInput.placeholder = 'e.g. Code Review Prompt';
      titleInput.value = isEdit ? (editPrompt.name || '') : '';
      titleInput.style.cssText = 'width:100%;padding:8px 12px;background:#171b25;border:1px solid rgba(124,58,237,0.4);border-radius:6px;color:#e7e9ed;font-size:13px;margin-bottom:12px;outline:none;box-sizing:border-box;';
      titleInput.onfocus = function() { this.style.borderColor = '#7c3aed'; };
      titleInput.onblur = function() { this.style.borderColor = 'rgba(124,58,237,0.4)'; };
      body.appendChild(titleInput);

      // Content textarea
      const contentLabel = document.createElement('label');
      contentLabel.textContent = 'Prompt Content (Markdown supported)';
      contentLabel.style.cssText = 'display:block;font-size:11px;color:#a78bfa;margin-bottom:4px;font-weight:600;';
      body.appendChild(contentLabel);
      const contentArea = document.createElement('textarea');
      contentArea.placeholder = 'Enter your prompt text here…\n\nSupports {{date}}, {{time}} variables.';
      contentArea.value = isEdit ? (editPrompt.text || '') : '';
      contentArea.style.cssText = 'width:100%;height:200px;padding:10px 12px;background:#171b25;border:1px solid rgba(124,58,237,0.4);border-radius:6px;color:#e7e9ed;font-size:12px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;resize:vertical;outline:none;box-sizing:border-box;line-height:1.5;';
      contentArea.onfocus = function() { this.style.borderColor = '#7c3aed'; };
      contentArea.onblur = function() { this.style.borderColor = 'rgba(124,58,237,0.4)'; };
      body.appendChild(contentArea);

      // Character count
      const charCount = document.createElement('div');
      charCount.style.cssText = 'text-align:right;font-size:10px;color:#6b7280;margin-top:2px;margin-bottom:8px;';
      charCount.textContent = '0 chars';
      contentArea.oninput = function() { charCount.textContent = contentArea.value.length + ' chars'; };
      if (isEdit) charCount.textContent = contentArea.value.length + ' chars';
      body.appendChild(charCount);

      // ── Category/Tag field ──
      const catLabel = document.createElement('label');
      catLabel.textContent = 'Category (optional)';
      catLabel.style.cssText = 'display:block;font-size:11px;color:#a78bfa;margin-bottom:4px;font-weight:600;';
      body.appendChild(catLabel);
      const catInput = document.createElement('input');
      catInput.type = 'text';
      catInput.placeholder = 'e.g. review, testing, deploy';
      catInput.value = isEdit ? (editPrompt.category || '') : '';
      catInput.style.cssText = 'width:100%;padding:8px 12px;background:#171b25;border:1px solid rgba(124,58,237,0.4);border-radius:6px;color:#e7e9ed;font-size:13px;margin-bottom:12px;outline:none;box-sizing:border-box;';
      catInput.onfocus = function() { this.style.borderColor = '#7c3aed'; };
      catInput.onblur = function() { this.style.borderColor = 'rgba(124,58,237,0.4)'; };
      body.appendChild(catInput);

      // ── T-3.3: File Drop Zone ──
      const dropZone = document.createElement('div');
      dropZone.style.cssText = 'border:2px dashed rgba(124,58,237,0.3);border-radius:8px;padding:16px;text-align:center;color:#6b7280;font-size:11px;margin-bottom:12px;transition:all .2s;cursor:pointer;';
      dropZone.innerHTML = '📁 Drop <b>.md</b>, <b>.txt</b>, or <b>.prompt</b> file here<br><span style="font-size:10px;color:#4b5563;">or click to browse</span>';
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.accept = '.md,.txt,.prompt';
      fileInput.style.display = 'none';
      dropZone.onclick = function() { fileInput.click(); };

      function handleFile(file) {
        if (!file) return;
        const ext = (file.name || '').split('.').pop().toLowerCase();
        if (!['md', 'txt', 'prompt'].includes(ext)) {
          showPasteToast('❌ Unsupported file type: .' + ext, true);
          return;
        }
        if (file.size > 50 * 1024) {
          showPasteToast('❌ File too large (max 50KB)', true);
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          const content = e.target.result;
          contentArea.value = content;
          charCount.textContent = content.length + ' chars';
          // Auto-fill title from filename if empty
          if (!titleInput.value.trim()) {
            titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
          }
          dropZone.style.borderColor = '#16a34a';
          dropZone.innerHTML = '✅ Loaded: <b>' + file.name + '</b> (' + content.length + ' chars)';
          setTimeout(function() {
            dropZone.style.borderColor = 'rgba(124,58,237,0.3)';
          }, 2000);
          log('File loaded into prompt editor: ' + file.name, 'success');
        };
        reader.readAsText(file);
      }

      fileInput.onchange = function() { handleFile(fileInput.files[0]); };
      dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '#7c3aed';
        this.style.background = 'rgba(124,58,237,0.1)';
      });
      dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = 'rgba(124,58,237,0.3)';
        this.style.background = 'transparent';
      });
      dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = 'rgba(124,58,237,0.3)';
        this.style.background = 'transparent';
        if (e.dataTransfer && e.dataTransfer.files.length > 0) {
          handleFile(e.dataTransfer.files[0]);
        }
      });
      body.appendChild(dropZone);
      body.appendChild(fileInput);

      // ── Variable reference (collapsible) ──
      const varToggle = document.createElement('div');
      varToggle.style.cssText = 'cursor:pointer;font-size:11px;color:#a78bfa;margin-bottom:4px;user-select:none;';
      varToggle.textContent = '▸ Template Variables';
      const varList = document.createElement('div');
      varList.style.cssText = 'display:none;padding:6px 10px;background:rgba(124,58,237,0.08);border-radius:6px;font-size:10px;color:#9ca3af;margin-bottom:12px;line-height:1.8;';
      varList.innerHTML = '<code style="color:#c4b5fd;">{{date}}</code> — current date<br>' +
        '<code style="color:#c4b5fd;">{{time}}</code> — current time<br>' +
        '<code style="color:#c4b5fd;">{{date:FORMAT}}</code> — e.g. dd-MMM-YYYY<br>' +
        '<code style="color:#c4b5fd;">{{time:FORMAT}}</code> — e.g. 12 hr clock';
      varToggle.onclick = function() {
        const isOpen = varList.style.display !== 'none';
        varList.style.display = isOpen ? 'none' : 'block';
        varToggle.textContent = (isOpen ? '▸' : '▾') + ' Template Variables';
      };
      body.appendChild(varToggle);
      body.appendChild(varList);

      modal.appendChild(body);

      // ── Footer with actions ──
      const footer = document.createElement('div');
      footer.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;padding:12px 20px;border-top:1px solid rgba(124,58,237,0.3);';

      // T-3.4: Paste Test button
      const testBtn = document.createElement('button');
      testBtn.textContent = '📋 Paste Test';
      testBtn.style.cssText = 'padding:8px 14px;background:#252a36;border:1px solid rgba(124,58,237,0.4);border-radius:6px;color:#c4b5fd;font-size:12px;cursor:pointer;';
      testBtn.onmouseover = function() { this.style.background = '#2d3348'; };
      testBtn.onmouseout = function() { this.style.background = '#252a36'; };
      testBtn.onclick = function() {
        let text = contentArea.value.trim();
        if (!text) {
          showPasteToast('❌ No content to paste', true);
          return;
        }
        // Resolve simple template variables
        const now = new Date();
        text = text.replace(/\{\{date\}\}/gi, now.toLocaleDateString());
        text = text.replace(/\{\{time\}\}/gi, now.toLocaleTimeString());
        const promptsCfg = getPromptsConfig();
        const result = pasteIntoEditor(text, promptsCfg);
        if (!result) {
          // pasteIntoEditor already shows toast on failure
        }
      };
      footer.appendChild(testBtn);

      // T-3.5: Save button
      const saveBtn = document.createElement('button');
      saveBtn.textContent = isEdit ? '💾 Update' : '💾 Save';
      saveBtn.style.cssText = 'padding:8px 18px;background:#7c3aed;border:none;border-radius:6px;color:#fff;font-size:12px;font-weight:600;cursor:pointer;';
      saveBtn.onmouseover = function() { this.style.background = '#6d28d9'; };
      saveBtn.onmouseout = function() { this.style.background = '#7c3aed'; };
      saveBtn.onclick = function() {
        let name = titleInput.value.trim();
        let text = contentArea.value.trim();
        if (!name) { showPasteToast('❌ Title is required', true); titleInput.focus(); return; }
        if (!text) { showPasteToast('❌ Content is required', true); contentArea.focus(); return; }
        if (text.length > 50 * 1024) { showPasteToast('❌ Content exceeds 50KB limit', true); return; }

        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ Saving…';

        let category = catInput.value.trim();
        const promptPayload = { name: name, text: text, source: 'user' };
        if (category) promptPayload.category = category;
        if (isEdit && editPrompt.id) promptPayload.id = editPrompt.id;

        sendToExtension('SAVE_PROMPT', { prompt: promptPayload }, function(resp) {
          saveBtn.disabled = false;
          saveBtn.textContent = isEdit ? '💾 Update' : '💾 Save';
          if (resp && resp.isOk) {
            showPasteToast('✓ Prompt saved: ' + name, false);
            log('Prompt saved: ' + name, 'success');
            // Invalidate cache so next dropdown open fetches fresh data
            _loadedJsonPrompts = null;
            overlay.remove();
          } else {
            const errMsg = (resp && resp.errorMessage) || 'Save failed — extension may not be connected';
            showPasteToast('❌ ' + errMsg, true);
            log('Prompt save failed: ' + errMsg, 'error');
          }
        });
      };
      footer.appendChild(saveBtn);

      modal.appendChild(footer);
      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      // Close on overlay click (not modal)
      overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
      // Close on Escape
      function onEsc(e) { if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', onEsc); } }
      document.addEventListener('keydown', onEsc);

      titleInput.focus();
    }

      e.stopPropagation();
      const isOpen = promptsDropdown.style.display !== 'none';
      promptsDropdown.style.display = isOpen ? 'none' : 'block';
      if (!isOpen) {
        // v7.33: Show loading state while prompts load asynchronously
        promptsDropdown.innerHTML = '';
        const loadingItem = document.createElement('div');
        loadingItem.style.cssText = 'padding:10px 16px;color:#9ca3af;font-size:13px;text-align:center;';
        loadingItem.textContent = '⏳ Loading prompts…';
        promptsDropdown.appendChild(loadingItem);
        loadPromptsFromJson(function(loaded) {
          renderPromptsDropdown();
        });
      }
    };
    // Close on outside click
    document.addEventListener('click', function() { promptsDropdown.style.display = 'none'; });
    promptsContainer.appendChild(promptsBtn);
    promptsContainer.appendChild(promptsDropdown);

    // === ☰ Menu button (secondary actions) ===
    const menuContainer = document.createElement('div');
    menuContainer.style.cssText = 'position:relative;display:inline-block;';
    const menuBtn = document.createElement('button');
    menuBtn.textContent = '☰';
    menuBtn.title = 'More actions';
    menuBtn.style.cssText = btnStyle + 'background:#252a36;color:#ae7ce8;font-size:14px;padding:5px 10px;';
    const menuDropdown = document.createElement('div');
    menuDropdown.style.cssText = 'display:none;position:absolute;top:100%;right:0;min-width:180px;background:#171b25;border:1px solid #7c3aed;border-radius:4px;z-index:100001;box-shadow:0 8px 24px rgba(0,0,0,.6);margin-top:2px;padding:4px 0;';

    function createMenuItem(icon, label, title, onclick) {
      const item = document.createElement('button');
      item.style.cssText = menuBtnStyle;
      item.title = title || label;
      item.innerHTML = '<span style="font-size:12px;width:18px;text-align:center;">' + icon + '</span><span>' + label + '</span>';
      item.onmouseover = function() { this.style.background = 'rgba(139,92,246,0.2)'; };
      item.onmouseout = function() { this.style.background = 'transparent'; };
      item.onclick = function(e) {
        e.stopPropagation();
        menuDropdown.style.display = 'none';
        onclick();
      };
      return item;
    }
    function createMenuSep() {
      const sep = document.createElement('div');
      sep.style.cssText = 'height:1px;background:#7c3aed;margin:3px 8px;opacity:0.4;';
      return sep;
    }

    // Build hidden button references for force move (need state for cooldown)
    const forceUpBtn = { el: null };
    const forceDownBtn = { el: null };
    let forceMoveInFlight = false;

    function setForceMoveInFlight() {
      forceMoveInFlight = true;
      setTimeout(function() { forceMoveInFlight = false; }, 8000);
    }

    // Menu items
    menuDropdown.appendChild(createMenuItem('▲', 'Loop Up', 'Start loop in UP direction', function() {
      state.direction = 'up';
      log('Direction set to: UP');
      startLoop('up');
    }));
    menuDropdown.appendChild(createMenuItem('▼', 'Loop Down', 'Start loop in DOWN direction', function() {
      state.direction = 'down';
      log('Direction set to: DOWN');
      startLoop('down');
    }));
    menuDropdown.appendChild(createMenuSep());
    menuDropdown.appendChild(createMenuItem('⏫', 'Force Move Up', 'Force move project to previous workspace via API (Ctrl+Up)', function() {
      if (forceMoveInFlight) { log('Force move: cooldown active', 'warn'); return; }
      setForceMoveInFlight();
      moveToAdjacentWorkspace('up');
    }));
    menuDropdown.appendChild(createMenuItem('⏬', 'Force Move Down', 'Force move project to next workspace via API (Ctrl+Down)', function() {
      if (forceMoveInFlight) { log('Force move: cooldown active', 'warn'); return; }
      setForceMoveInFlight();
      moveToAdjacentWorkspace('down');
    }));
    menuDropdown.appendChild(createMenuSep());
    menuDropdown.appendChild(createMenuItem('📋', 'Export CSV', 'Export all workspaces + credits as CSV', function() {
      exportWorkspacesAsCsv();
    }));
    menuDropdown.appendChild(createMenuItem('📥', 'Download Bundle', 'Download bundle (xpath-utils + macro-looping) as .js file', function() {
      const bundle = window.__exportBundle;
      if (!bundle || bundle.length < 100) {
        log('Export: No bundle available — re-inject via AHK to generate', 'error');
        return;
      }
      const now = new Date();
      const timestamp = now.toISOString().replace('T', ' ').substring(0, 19);
      const header = '// ============================================\n';
      header += '// MACROLOOP BUNDLE EXPORT (self-contained)\n';
      header += '// Generated: ' + timestamp + '\n';
      header += '// Version:   v' + VERSION + '\n';
      header += '// Contents:  xpath-utils.js + macro-looping.js\n';
      header += '// Length:    ' + bundle.length + ' chars\n';
      header += '// ============================================\n';
      header += '// All __PLACEHOLDER__ tokens have been resolved.\n';
      header += '// Paste this entire script into any browser DevTools Console.\n';
      header += '// TIP: If Domain Guard blocks, run: window.__comboForceInject = true  first.\n';
      header += '// ============================================\n\n';
      const fullExport = header + bundle;
      const blob = new Blob([fullExport], { type: 'application/javascript' });
      const url = URL.createObjectURL(blob);
      let a = document.createElement('a');
      a.href = url;
      a.download = 'automator-bundle-v' + VERSION + '-' + now.toISOString().replace(/[:.]/g, '-').substring(0, 19) + '.js';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      log('Export: Downloaded bundle (' + fullExport.length + ' chars)', 'success');
    }));
    menuDropdown.appendChild(createMenuItem('📋', 'Copy JS Bundle', 'Copy bundle to clipboard', function() {
      const bundle = window.__exportBundle;
      if (!bundle || bundle.length < 100) {
        log('Copy JS: No bundle available — re-inject via AHK to generate', 'error');
        return;
      }
      navigator.clipboard.writeText(bundle).then(function() {
        log('Copy JS: Copied to clipboard (' + bundle.length + ' chars)', 'success');
      }).catch(function(err) {
        log('Copy JS: Clipboard failed: ' + err.message, 'warn');
      });
    }));
    menuDropdown.appendChild(createMenuSep());
    menuDropdown.appendChild(createMenuItem('🔧', 'Diagnostic Dump', 'Run diagnostic dump', function() {
      if (typeof window.__loopDiag === 'function') window.__loopDiag();
      else log('Diagnostic dump not available', 'warn');
    }));

    // Auth panel toggle (menu item with live indicator)
    const authMenuItem = createMenuItem('🔴', 'Auth Panel', 'Reopen the Macro Auth panel', function() {
      if (window.__MARCO__ && typeof window.__MARCO__.showAuthPanel === 'function') {
        window.__MARCO__.showAuthPanel();
        log('Auth panel reopened via menu', 'success');
      } else {
        log('Auth panel not available — __MARCO__.showAuthPanel not found', 'warn');
      }
    });
    menuDropdown.appendChild(authMenuItem);
    // Update auth indicator periodically
    setInterval(function() {
      const isOpen = !!document.getElementById('marco-auth-panel');
      const iconSpan = authMenuItem.querySelector('span');
      if (iconSpan) iconSpan.textContent = isOpen ? '🟢' : '🔴';
    }, 2000);

    menuDropdown.appendChild(createMenuSep());

    // Read Session Cookie — menu item
    menuDropdown.appendChild(createMenuItem('🍪', 'Read Session Cookie', 'Read session token from cookie and save to localStorage for API auth', function() {
      const cookieToken = getBearerTokenFromCookie();
      if (cookieToken) {
        try {
          localStorage.setItem('marco_bearer_token', cookieToken);
          log('Session cookie read & saved to localStorage[marco_bearer_token] (' + cookieToken.substring(0, 12) + '...)', 'success');
          showToast('Session token refreshed from cookie ✓', 'success');
        } catch (e) {
          log('Failed to save session token: ' + e.message, 'error');
          showToast('Failed to save session token', 'error');
        }
      } else {
        log('No session cookie found (lovable-session-id.id) — user may need to log in', 'warn');
        showToast('No session cookie found — please log in first', 'warn');
      }
    }));

    // ============================================
    // Auto-Attach Files — workflow automation
    // ============================================
    let autoAttachRunning = false;

    function resolveAutoAttachConfig() {
      // Re-read from live config in case it was updated
      const rawCfg = (window.__MARCO_CONFIG__ || {}).autoAttach;
      const liveCfg = (rawCfg && typeof rawCfg === 'object')
        ? rawCfg
        : ((autoAttachCfg && typeof autoAttachCfg === 'object') ? autoAttachCfg : {});

      const timing = (liveCfg.timing && typeof liveCfg.timing === 'object') ? liveCfg.timing : {};
      const groups = Array.isArray(liveCfg.groups) ? liveCfg.groups : [];

      return {
        plusXPath: liveCfg.plusButtonXPath || '',
        attachXPath: liveCfg.attachButtonXPath || '',
        chatBoxXPath: liveCfg.chatBoxXPath || '',
        timing: timing,
        groups: groups
      };
    }

    function autoAttachDelay(ms) {
      return new Promise(function(resolve) { setTimeout(resolve, ms); });
    }

    function clickByXPath(xpath, label) {
      if (!xpath) { log('Auto-Attach: No XPath for ' + label, 'warn'); return false; }
      let el = document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
      if (!el) { log('Auto-Attach: Element not found for ' + label + ': ' + xpath, 'warn'); return false; }
      el.click();
      log('Auto-Attach: Clicked ' + label, 'info');
      return true;
    }

    function insertTextIntoElement(xpath, text, label) {
      if (!xpath || !text) return false;
      let el = document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
      if (!el) { log('Auto-Attach: Element not found for ' + label + ': ' + xpath, 'warn'); return false; }
      // Focus and set text via input simulation
      el.focus();
      // Try native input value setter for React-controlled inputs
      const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value') ||
                                    Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
      if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
        if (nativeInputValueSetter && nativeInputValueSetter.set) {
          nativeInputValueSetter.set.call(el, text);
        } else {
          el.value = text;
        }
      } else {
        // contenteditable div
        el.textContent = text;
      }
      // Dispatch events to trigger React state updates
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      log('Auto-Attach: Inserted text into ' + label + ' (' + text.length + ' chars)', 'success');
      return true;
    }

    async function runAutoAttachGroup(group) {
      const aaCfg = resolveAutoAttachConfig();
      const stepDelay = aaCfg.timing.stepDelayMs || 200;
      const preDialogDelay = aaCfg.timing.preDialogDelayMs || 800;
      const preFileDialogDelay = aaCfg.timing.preFileDialogDelayMs || 1000;
      const files = group.files || [];

      if (autoAttachRunning) {
        showToast('Auto-Attach already running', 'warn');
        return;
      }

      autoAttachRunning = true;
      log('Auto-Attach: Starting group "' + group.name + '" with ' + files.length + ' file(s)', 'info');

      // Step 1: Insert prompt into chat box
      if (group.prompt && aaCfg.chatBoxXPath) {
        insertTextIntoElement(aaCfg.chatBoxXPath, group.prompt, 'chatBox');
        await autoAttachDelay(stepDelay);
      }

      // Step 2: For each file, run the Plus → Attach → File dialog sequence
      for (let i = 0; i < files.length; i++) {
        const filePath = files[i];
        log('Auto-Attach: Attaching file ' + (i + 1) + '/' + files.length + ': ' + filePath, 'info');
        showToast('Attaching file ' + (i + 1) + '/' + files.length + '...', 'info');

        // Click Plus button
        if (!clickByXPath(aaCfg.plusXPath, 'Plus button')) {
          log('Auto-Attach: Failed to click Plus button — aborting', 'error');
          break;
        }
        await autoAttachDelay(preDialogDelay);

        // Click Attach button
        if (!clickByXPath(aaCfg.attachXPath, 'Attach button')) {
          log('Auto-Attach: Failed to click Attach button — aborting', 'error');
          break;
        }
        await autoAttachDelay(preFileDialogDelay);

        // Write file path to clipboard for AHK to pick up
        // Signal format: AUTO_ATTACH_FILE:<path>
        try {
          await navigator.clipboard.writeText('AUTO_ATTACH_FILE:' + filePath);
          log('Auto-Attach: File path written to clipboard for AHK: ' + filePath, 'info');
        } catch (e) {
          log('Auto-Attach: Clipboard write failed: ' + e.message, 'warn');
        }

        // Wait for AHK to handle the file dialog
        await autoAttachDelay(preFileDialogDelay + 500);
        await autoAttachDelay(stepDelay);
      }

      autoAttachRunning = false;
      log('Auto-Attach: Group "' + group.name + '" complete (' + files.length + ' files)', 'success');
      showToast('Auto-Attach complete: ' + group.name + ' (' + files.length + ' files)', 'success');
    }

    // Expose for external use
    window.__autoAttachRunGroup = runAutoAttachGroup;

    // === Auto Attach menu section ===
    menuDropdown.appendChild(createMenuSep());

    // Build submenu-like section header
    const aaHeader = document.createElement('div');
    aaHeader.style.cssText = 'padding:4px 10px;font-size:9px;color:#a78bfa;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;';
    aaHeader.textContent = '📎 Auto Attach Files';
    menuDropdown.appendChild(aaHeader);

    // Add group items
    const aaGroups = resolveAutoAttachConfig().groups;
    if (aaGroups.length === 0) {
      const noGroups = document.createElement('div');
      noGroups.style.cssText = 'padding:4px 10px;font-size:10px;color:#6b7280;font-style:italic;';
      noGroups.textContent = 'No groups configured';
      menuDropdown.appendChild(noGroups);
    } else {
      for (let gi = 0; gi < aaGroups.length; gi++) {
        (function(group) {
          const fileCount = (group.files || []).length;
          const label = group.name + ' (' + fileCount + ' file' + (fileCount !== 1 ? 's' : '') + ')';
          menuDropdown.appendChild(createMenuItem('📁', label, 'Attach files from: ' + group.name + (group.prompt ? '\nPrompt: ' + group.prompt.substring(0, 60) + '...' : ''), function() {
            runAutoAttachGroup(group);
          }));
        })(aaGroups[gi]);
      }
    }

    menuDropdown.appendChild(createMenuSep());

    // About — menu item
    menuDropdown.appendChild(createMenuItem('ℹ️', 'About', 'About MacroLoop Controller', function() {
      showAboutModal();
    }));

    menuBtn.onclick = function(e) {
      e.stopPropagation();
      const isOpen = menuDropdown.style.display !== 'none';
      menuDropdown.style.display = isOpen ? 'none' : 'block';
    };
    document.addEventListener('click', function() { menuDropdown.style.display = 'none'; });
    menuContainer.appendChild(menuBtn);
    menuContainer.appendChild(menuDropdown);

    // Assemble primary button row: Check | ▶/⏹ | Credits | Prompts | ☰
    btnRow.appendChild(checkBtn);
    btnRow.appendChild(startStopBtn);
    btnRow.appendChild(creditBtn);
    btnRow.appendChild(promptsContainer);
    btnRow.appendChild(menuContainer);

    // Legacy button references for keyboard shortcuts and external API
    const startBtn = startStopBtn;
    const stopBtn = startStopBtn;
    const upBtn = { onclick: function() { state.direction = 'up'; startLoop('up'); } };
    const downBtn = { onclick: function() { state.direction = 'down'; startLoop('down'); } };

    [checkBtn, startStopBtn, creditBtn, promptsBtn, menuBtn].forEach(attachButtonHoverFx);

    // === T-5: Collapsible section helper with localStorage persistence ===
    function createCollapsibleSection(title, storageKey, opts) {
      opts = opts || {};
      const section = document.createElement('div');
      section.style.cssText = 'padding:4px 6px;background:rgba(0,0,0,.3);border-radius:4px;';
      const header = document.createElement('div');
      header.style.cssText = 'display:flex;align-items:center;cursor:pointer;user-select:none;';
      const toggle = document.createElement('span');
      toggle.style.cssText = 'font-size:10px;color:#a78bfa;margin-right:4px;';
      const titleEl = document.createElement('span');
      titleEl.style.cssText = 'font-size:10px;color:#ae7ce8;font-weight:bold;';
      titleEl.textContent = title;
      header.appendChild(toggle);
      header.appendChild(titleEl);
      const body = document.createElement('div');
      body.style.cssText = 'margin-top:4px;';
      // Read saved state; default collapsed
      let savedState = null;
      try { savedState = localStorage.getItem(storageKey); } catch(e) {}
      const isCollapsed = savedState !== null ? savedState === 'collapsed' : true;
      body.style.display = isCollapsed ? 'none' : '';
      toggle.textContent = isCollapsed ? '[+]' : '[-]';
      header.onclick = function() {
        let hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        toggle.textContent = hidden ? '[-]' : '[+]';
        try { localStorage.setItem(storageKey, hidden ? 'expanded' : 'collapsed'); } catch(e) {}
      };
      section.appendChild(header);
      section.appendChild(body);
      return { section: section, header: header, toggle: toggle, titleEl: titleEl, body: body };
    }

    const xpathCol = createCollapsibleSection('XPath Configuration (editable)', 'ml_collapse_xpath');
    const xpathSection = xpathCol.section;
    const xpathBody = xpathCol.body;

    const projLabel = document.createElement('div');
    projLabel.style.cssText = 'font-size:9px;color:#a78bfa;margin-bottom:1px;';
    projLabel.textContent = 'Project Button XPath:';

    const projInput = document.createElement('input');
    projInput.type = 'text';
    projInput.id = 'xpath-project-btn';
    projInput.value = CONFIG.PROJECT_BUTTON_XPATH;
    projInput.style.cssText = 'width:100%;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-family:monospace;font-size:9px;margin-bottom:4px;box-sizing:border-box;';
    projInput.onchange = function() {
      updateProjectButtonXPath(this.value);
    };

    const progLabel = document.createElement('div');
    progLabel.style.cssText = 'font-size:9px;color:#a78bfa;margin-bottom:1px;';
    progLabel.textContent = 'Progress Bar XPath:';

    const progInput = document.createElement('input');
    progInput.type = 'text';
    progInput.id = 'xpath-progress-bar';
    progInput.value = CONFIG.PROGRESS_XPATH;
    progInput.style.cssText = 'width:100%;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-family:monospace;font-size:9px;box-sizing:border-box;';
    progInput.onchange = function() {
      updateProgressXPath(this.value);
    };

    const wsLabel = document.createElement('div');
    wsLabel.style.cssText = 'font-size:9px;color:#a78bfa;margin-bottom:1px;margin-top:4px;';
    wsLabel.textContent = 'Workspace Name XPath:';

    const wsInput = document.createElement('input');
    wsInput.type = 'text';
    wsInput.id = 'xpath-workspace-name';
    wsInput.value = CONFIG.WORKSPACE_XPATH;
    wsInput.style.cssText = 'width:100%;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-family:monospace;font-size:9px;box-sizing:border-box;';
    wsInput.onchange = function() {
      updateWorkspaceXPath(this.value);
    };

    xpathBody.appendChild(projLabel);
    xpathBody.appendChild(projInput);
    xpathBody.appendChild(progLabel);
    xpathBody.appendChild(progInput);
    xpathBody.appendChild(wsLabel);
    xpathBody.appendChild(wsInput);

    // JS Executor - collapsible with localStorage persistence
    const jsCol = createCollapsibleSection('JS Executor (Ctrl+Enter to run)', 'ml_collapse_jsexec');
    const jsSection = jsCol.section;
    const jsBody = jsCol.body;

    const jsRow = document.createElement('div');
    jsRow.style.cssText = 'display:flex;gap:4px;';

    const jsTextbox = document.createElement('textarea');
    jsTextbox.id = IDS.JS_EXECUTOR;
    jsTextbox.placeholder = 'JavaScript code...';
    jsTextbox.style.cssText = 'flex:1;min-height:30px;padding:4px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-family:monospace;font-size:10px;resize:vertical;';
    jsTextbox.spellcheck = false;
    jsTextbox.onkeydown = function(e) {
      const isCtrlEnter = e.ctrlKey && e.key === 'Enter';
      if (isCtrlEnter) {
        e.preventDefault();
        executeJs();
        return;
      }
      // ArrowUp/Down for JS history recall (only on single-line content)
      const isSingleLine = (jsTextbox.value || '').indexOf('\n') === -1;
      if (e.key === 'ArrowUp' && isSingleLine) {
        e.preventDefault();
        navigateLoopJsHistory('up');
        return;
      }
      if (e.key === 'ArrowDown' && isSingleLine) {
        e.preventDefault();
        navigateLoopJsHistory('down');
        return;
      }
    };

    const jsBtn = document.createElement('button');
    jsBtn.id = IDS.JS_EXECUTE_BTN;
    jsBtn.textContent = 'Run';
    jsBtn.style.cssText = btnStyle + 'background:#8b5cf6;color:#fff;align-self:flex-end;';
    jsBtn.onclick = executeJs;

    jsRow.appendChild(jsTextbox);
    jsRow.appendChild(jsBtn);
    jsBody.appendChild(jsRow);

    // JS Command History panel
    const jsHistLabel = document.createElement('div');
    jsHistLabel.style.cssText = 'font-size:9px;color:#a78bfa;margin-top:4px;';
    jsHistLabel.textContent = 'JS History (click to recall, Up/Down arrows in textbox)';
    jsBody.appendChild(jsHistLabel);

    const jsHistBox = document.createElement('div');
    jsHistBox.id = 'loop-js-history';
    jsHistBox.style.cssText = 'max-height:80px;overflow-y:auto;background:rgba(0,0,0,.3);border:1px solid #7c3aed;border-radius:3px;margin-top:2px;';
    jsHistBox.innerHTML = '<span style="color:#64748b;font-size:10px;padding:4px;">No commands yet</span>';
    jsBody.appendChild(jsHistBox);

  // Auto-Attach config
  autoAttachCfg = cfg.autoAttach || {};
  autoAttachTiming = autoAttachCfg.timing || {};
  autoAttachGroups = autoAttachCfg.groups || [];

    // XPath Tester removed (v7.9.1) — use combo.js XPath Tester instead

    // Activity log - collapsible with localStorage persistence
    const activityCol = createCollapsibleSection('Activity Log', 'ml_collapse_activity');
    const activitySection = activityCol.section;

    const activityPanel = document.createElement('div');
    activityPanel.id = 'loop-activity-log-panel';
    activityPanel.style.cssText = 'padding:4px;background:rgba(0,0,0,.5);border:1px solid #7c3aed;border-radius:3px;max-height:120px;overflow-y:auto;';

    const activityContent = document.createElement('div');
    activityContent.id = 'loop-activity-log-content';
    activityContent.innerHTML = '<div style="color:#6b7280;font-size:10px;padding:4px;">No activity logs yet</div>';

    activityPanel.appendChild(activityContent);
    activityCol.body.appendChild(activityPanel);

    // JS Logs - collapsible with localStorage persistence
    const logCol = createCollapsibleSection('JS Logs (' + getAllLogs().length + ' entries)', 'ml_collapse_jslogs');
    const logSection = logCol.section;

    const logExportRow = document.createElement('div');
    logExportRow.style.cssText = 'display:flex;gap:4px;align-items:center;';

    const logLabel = document.createElement('span');
    logLabel.style.cssText = 'font-size:9px;color:#a78bfa;flex:1;';
    logLabel.textContent = 'JS Logs (' + getAllLogs().length + ' entries)';
    logLabel.id = 'loop-log-count';

    const copyLogBtn = document.createElement('button');
    copyLogBtn.textContent = 'Copy';
    copyLogBtn.style.cssText = 'padding:2px 6px;background:#252a36;color:#c9a8ef;border:1px solid #7c3aed;border-radius:2px;font-size:9px;cursor:pointer;';
    copyLogBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      copyLogsToClipboard();
      const countEl = document.getElementById('loop-log-count');
      if (countEl) countEl.textContent = 'Copied! (' + getAllLogs().length + ' entries)';
      setTimeout(function() {
        if (countEl) countEl.textContent = 'JS Logs (' + getAllLogs().length + ' entries)';
      }, 2000);
    };

    const downloadLogBtn = document.createElement('button');
    downloadLogBtn.textContent = 'DL';
    downloadLogBtn.title = 'Download logs';
    downloadLogBtn.style.cssText = 'padding:2px 6px;background:#252a36;color:#c9a8ef;border:1px solid #7c3aed;border-radius:2px;font-size:9px;cursor:pointer;';
    downloadLogBtn.onclick = function(e) { e.preventDefault(); e.stopPropagation(); downloadLogs(); };

    const clearLogBtn = document.createElement('button');
    clearLogBtn.textContent = 'Clr';
    clearLogBtn.title = 'Clear all logs';
    clearLogBtn.style.cssText = 'padding:2px 6px;background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b;border-radius:2px;font-size:9px;cursor:pointer;';
    clearLogBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      clearAllLogs();
      const countEl = document.getElementById('loop-log-count');
      if (countEl) countEl.textContent = 'JS Logs (0 entries)';
    };

    logExportRow.appendChild(logLabel);
    logExportRow.appendChild(copyLogBtn);
    logExportRow.appendChild(downloadLogBtn);
    logExportRow.appendChild(clearLogBtn);
    logCol.body.appendChild(logExportRow);

    // Workspace History - collapsible with localStorage persistence
    const wsHistoryCol = createCollapsibleSection('Workspace History', 'ml_collapse_wshistory');
    const wsHistorySection = wsHistoryCol.section;

    const wsHistoryPanel = document.createElement('div');
    wsHistoryPanel.id = 'loop-ws-history-panel';
    wsHistoryPanel.style.cssText = 'padding:4px;background:rgba(0,0,0,.5);border:1px solid #b45309;border-radius:3px;max-height:120px;overflow-y:auto;';

    function renderWsHistory() {
      let history = getWorkspaceHistory();
      const projectName = getDisplayProjectName();
      const historyKey = getWsHistoryKey();
      if (history.length === 0) {
        wsHistoryPanel.innerHTML = '<div style="color:#6b7280;font-size:10px;padding:4px;">No workspace changes recorded for project "' + projectName + '"</div>';
        return;
      }
      let html = '<div style="font-size:9px;color:#a78bfa;padding:2px 0;margin-bottom:2px;">📁 Project: ' + projectName + ' (' + history.length + ' entries)</div>';
      for (let i = history.length - 1; i >= 0; i--) {
        const e = history[i];
        html += '<div style="font-size:10px;font-family:monospace;padding:2px 0;color:#fbbf24;">';
        html += '<span style="color:#6b7280;">[' + e.display + ']</span> ';
        html += '<span style="color:#ef4444;">' + e.from + '</span>';
        html += ' <span style="color:#9ca3af;">→</span> ';
        html += '<span style="color:#10b981;">' + e.to + '</span>';
        html += '</div>';
      }
      html += '<div style="margin-top:4px;text-align:right;"><button onclick="(function(){try{localStorage.removeItem(\'' + historyKey + '\');document.getElementById(\'loop-ws-history-panel\').innerHTML=\'<div style=\\\'color:#6b7280;font-size:10px;padding:4px;\\\'>History cleared</div>\';}catch(e){}})();" style="padding:2px 6px;background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b;border-radius:2px;font-size:9px;cursor:pointer;">Clear History</button></div>';
      wsHistoryPanel.innerHTML = html;
    }

    wsHistoryCol.body.appendChild(wsHistoryPanel);
    // Render history when section expands
    const origWsHistoryClick = wsHistoryCol.header.onclick;
    wsHistoryCol.header.onclick = function() {
      origWsHistoryClick();
      if (wsHistoryCol.body.style.display !== 'none') renderWsHistory();
    };
    // Also render on initial load if expanded
    if (wsHistoryCol.body.style.display !== 'none') renderWsHistory();

    // === v7.22: Auth Diagnostic Row ===
    const authDiagRow = document.createElement('div');
    authDiagRow.id = 'loop-auth-diag';
    authDiagRow.style.cssText = 'display:flex;align-items:center;gap:6px;padding:3px 6px;background:rgba(0,0,0,.25);border:1px solid #252a36;border-radius:4px;font-size:10px;font-family:monospace;';

    const authDiagIcon = document.createElement('span');
    authDiagIcon.style.cssText = 'font-size:11px;';

    const authDiagLabel = document.createElement('span');
    authDiagLabel.style.cssText = 'color:#a78bfa;white-space:nowrap;';
    authDiagLabel.textContent = 'Auth:';

    const authDiagValue = document.createElement('span');
    authDiagValue.id = 'loop-auth-diag-value';
    authDiagValue.style.cssText = 'color:#ae7ce8;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';

    function updateAuthDiagRow() {
      let source = LAST_TOKEN_SOURCE || 'none';
      const isNone = source === 'none';
      authDiagIcon.textContent = isNone ? '🔴' : '🟢';
      authDiagValue.textContent = isNone ? 'No token resolved' : source;
      authDiagValue.title = isNone ? 'No bearer token found in localStorage or cookies' : 'Last Credits call used: ' + source;
    }

    updateAuthDiagRow();
    window.__loopUpdateAuthDiag = updateAuthDiagRow;

    authDiagRow.appendChild(authDiagIcon);
    authDiagRow.appendChild(authDiagLabel);
    authDiagRow.appendChild(authDiagValue);

    // === Workspace Dropdown Section ===
    const wsDropSection = document.createElement('div');
    wsDropSection.style.cssText = 'padding:4px 6px;background:rgba(0,0,0,.3);border:1px solid #7c3aed;border-radius:4px;';

    const wsDropHeader = document.createElement('div');
    wsDropHeader.style.cssText = 'display:flex;align-items:center;gap:4px;margin-bottom:4px;flex-wrap:wrap;';
    wsDropHeader.innerHTML = '<span style="font-size:11px;">🏢</span><span id="loop-ws-count-label" style="font-size:10px;color:#ae7ce8;font-weight:bold;">Workspaces</span>'
      + '<span id="loop-ws-sel-count" style="font-size:8px;color:#facc15;display:none;"></span>';

    // Select All / Deselect All button
    const wsSelectAllBtn = document.createElement('button');
    wsSelectAllBtn.id = 'loop-ws-select-all-btn';
    wsSelectAllBtn.textContent = '☑ All';
    wsSelectAllBtn.title = 'Select all / deselect all workspaces';
    wsSelectAllBtn.style.cssText = 'padding:1px 5px;background:rgba(139,92,246,0.15);color:#ae7ce8;border:1px solid rgba(139,92,246,0.4);border-radius:3px;font-size:8px;cursor:pointer;';
    wsSelectAllBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      let perWs = loopCreditState.perWorkspace || [];
      const allChecked = Object.keys(loopWsCheckedIds).length >= perWs.length && perWs.length > 0;
      if (allChecked) {
        loopWsCheckedIds = {};
      } else {
        loopWsCheckedIds = {};
        for (let i = 0; i < perWs.length; i++) {
          if (perWs[i].id) loopWsCheckedIds[perWs[i].id] = true;
        }
      }
      loopWsLastCheckedIdx = -1;
      updateWsSelectionUI();
    };
    wsDropHeader.appendChild(wsSelectAllBtn);

    // Rename button (visible when selection > 0)
    const wsRenameBtn = document.createElement('button');
    wsRenameBtn.id = 'loop-ws-rename-btn';
    wsRenameBtn.textContent = '✏️ Rename';
    wsRenameBtn.title = 'Bulk rename selected workspaces';
    wsRenameBtn.style.cssText = 'display:none;padding:1px 6px;background:rgba(234,179,8,0.2);color:#facc15;border:1px solid rgba(234,179,8,0.4);border-radius:3px;font-size:8px;cursor:pointer;font-weight:700;';
    wsRenameBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      renderBulkRenameDialog();
    };
    wsDropHeader.appendChild(wsRenameBtn);

    // Undo last rename button
    const wsUndoBtn = document.createElement('button');
    wsUndoBtn.id = 'loop-ws-undo-btn';
    wsUndoBtn.textContent = '↩️ Undo';
    wsUndoBtn.title = 'Undo last bulk rename';
    wsUndoBtn.style.cssText = 'display:none;padding:1px 6px;background:rgba(239,68,68,0.2);color:#f87171;border:1px solid rgba(239,68,68,0.4);border-radius:3px;font-size:8px;cursor:pointer;font-weight:700;';
    wsUndoBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      if (loopRenameHistory.length === 0) { log('[Rename] Nothing to undo', 'warn'); return; }
      const last = loopRenameHistory[loopRenameHistory.length - 1];
      let count = last.entries.length;
      wsUndoBtn.disabled = true;
      wsUndoBtn.textContent = '↩️ Undoing... 0/' + count;
      wsUndoBtn.style.background = 'rgba(100,116,139,0.3)';
      undoLastRename(function(results, done) {
        if (done) {
          wsUndoBtn.disabled = false;
          wsUndoBtn.textContent = '↩️ Undo';
          wsUndoBtn.style.background = 'rgba(239,68,68,0.2)';
          populateLoopWorkspaceDropdown();
          log('[Rename] Undo complete: ' + results.success + '/' + results.total + ' reverted' + (results.failed > 0 ? ' (' + results.failed + ' failed)' : ''), results.failed > 0 ? 'warn' : 'success');
        } else {
          wsUndoBtn.textContent = '↩️ ' + (results.success + results.failed) + '/' + count;
        }
      });
    };
    wsDropHeader.appendChild(wsUndoBtn);

    // Show undo button if history exists on load
    setTimeout(function() { updateUndoBtnVisibility(); }, 100);

    const wsFocusBtn = document.createElement('button');
    wsFocusBtn.textContent = '📍 Focus Current';
    wsFocusBtn.title = 'Scroll to and highlight the current workspace in the list';
    wsFocusBtn.style.cssText = 'margin-left:auto;padding:2px 7px;background:rgba(139,92,246,0.2);color:#ae7ce8;border:1px solid rgba(139,92,246,0.4);border-radius:3px;font-size:9px;cursor:pointer;';
    wsFocusBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      let currentName = state.workspaceName || '';

      // If no name yet, try reading from Transfer dialog DOM (XPath: /html/body/div[7]/div[2]/div[1]/div/p)
      if (!currentName) {
        try {
          const selectors = [
            'div[role="dialog"] p.min-w-0.truncate',
            'div[role="dialog"] p.truncate'
          ];
          for (let s = 0; s < selectors.length; s++) {
            const domEl = document.querySelector(selectors[s]);
            if (domEl) {
              const domText = (domEl.textContent || '').trim();
              if (domText) {
                currentName = domText;
                state.workspaceName = domText;
                log('Focus Current: read workspace from Transfer dialog DOM: "' + domText + '"', 'success');
                break;
              }
            }
          }
        } catch (ex) { /* ignore */ }
      }

      log('Focus Current: looking for "' + currentName + '"', 'check');

      // If we already know the current workspace, just find & scroll — no API needed
      if (currentName && (loopCreditState.perWorkspace || []).length > 0) {
        populateLoopWorkspaceDropdown();
        const listEl = document.getElementById('loop-ws-list');
        if (listEl) {
          const currentItem = listEl.querySelector('.loop-ws-item[data-ws-current="true"]');
          if (currentItem) {
            currentItem.scrollIntoView({ block: 'center', behavior: 'smooth' });
            let idx = parseInt(currentItem.getAttribute('data-ws-idx'), 10);
            if (!isNaN(idx)) setLoopWsNavIndex(idx);
            log('✅ Focused & selected: ' + currentName, 'success');
          } else {
            log('Focus Current: name "' + currentName + '" not found in rendered list', 'warn');
          }
        }
        return;
      }

      // Fallback: no name known — fetch credits (which auto-detects workspace)
      if ((loopCreditState.perWorkspace || []).length === 0) {
        log('Focus Current: no workspaces loaded, fetching...', 'check');
        fetchLoopCredits();
        return;
      }

      // Have workspaces but no name — detect via API
      const token = window.__loopResolvedToken || resolveToken();
      autoDetectLoopCurrentWorkspace(token).then(function() {
        populateLoopWorkspaceDropdown();
        const listEl = document.getElementById('loop-ws-list');
        if (!listEl) return;
        const currentItem = listEl.querySelector('.loop-ws-item[data-ws-current="true"]');
        if (currentItem) {
          currentItem.scrollIntoView({ block: 'center', behavior: 'smooth' });
          let idx = parseInt(currentItem.getAttribute('data-ws-idx'), 10);
          if (!isNaN(idx)) setLoopWsNavIndex(idx);
          log('✅ Focused & selected: ' + state.workspaceName, 'success');
        } else {
          log('Focus Current: no item marked as current after detection', 'warn');
        }
      });
    };
    wsDropHeader.appendChild(wsFocusBtn);

    // Free Only filter
    const wsFreeBtn = document.createElement('button');
    wsFreeBtn.textContent = '🆓';
    wsFreeBtn.title = 'Toggle free-only filter';
    wsFreeBtn.style.cssText = 'padding:1px 5px;background:rgba(250,204,21,0.15);color:#facc15;border:1px solid rgba(250,204,21,0.4);border-radius:3px;font-size:9px;cursor:pointer;';
    wsFreeBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      loopWsFreeOnly = !loopWsFreeOnly;
      this.style.background = loopWsFreeOnly ? 'rgba(250,204,21,0.4)' : 'rgba(250,204,21,0.15)';
      this.style.fontWeight = loopWsFreeOnly ? '700' : 'normal';
      populateLoopWorkspaceDropdown();
    };
    wsDropHeader.appendChild(wsFreeBtn);

    // Rollover filter
    const wsRolloverBtn = document.createElement('button');
    wsRolloverBtn.id = 'loop-ws-rollover-filter';
    wsRolloverBtn.textContent = '🔄';
    wsRolloverBtn.title = 'Show only workspaces with rollover credits';
    wsRolloverBtn.style.cssText = 'padding:1px 5px;background:rgba(167,139,250,0.15);color:#c4b5fd;border:1px solid rgba(167,139,250,0.4);border-radius:3px;font-size:9px;cursor:pointer;';
    wsRolloverBtn.setAttribute('data-active', 'false');
    wsRolloverBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      const isActive = this.getAttribute('data-active') === 'true';
      this.setAttribute('data-active', isActive ? 'false' : 'true');
      this.style.background = !isActive ? 'rgba(167,139,250,0.4)' : 'rgba(167,139,250,0.15)';
      this.style.fontWeight = !isActive ? '700' : 'normal';
      populateLoopWorkspaceDropdown();
    };
    wsDropHeader.appendChild(wsRolloverBtn);

    // Billing filter
    const wsBillingBtn = document.createElement('button');
    wsBillingBtn.id = 'loop-ws-billing-filter';
    wsBillingBtn.textContent = '💰';
    wsBillingBtn.title = 'Show only workspaces with billing credits';
    wsBillingBtn.style.cssText = 'padding:1px 5px;background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.4);border-radius:3px;font-size:9px;cursor:pointer;';
    wsBillingBtn.setAttribute('data-active', 'false');
    wsBillingBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      const isActive = this.getAttribute('data-active') === 'true';
      this.setAttribute('data-active', isActive ? 'false' : 'true');
      this.style.background = !isActive ? 'rgba(34,197,94,0.4)' : 'rgba(34,197,94,0.15)';
      this.style.fontWeight = !isActive ? '700' : 'normal';
      populateLoopWorkspaceDropdown();
    };
    wsDropHeader.appendChild(wsBillingBtn);

    // Compact mode toggle
    const wsCompactBtn = document.createElement('button');
    wsCompactBtn.id = 'loop-ws-compact-toggle';
    wsCompactBtn.textContent = '⚡';
    wsCompactBtn.title = 'Compact view: show only ⚡available/total';
    wsCompactBtn.style.cssText = 'padding:1px 5px;background:rgba(34,211,238,0.4);color:#22d3ee;border:1px solid rgba(34,211,238,0.4);border-radius:3px;font-size:9px;cursor:pointer;font-weight:700;';
    wsCompactBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      loopWsCompactMode = !loopWsCompactMode;
      try { localStorage.setItem('ml_compact_mode', loopWsCompactMode ? 'true' : 'false'); } catch(e) {}
      this.style.background = loopWsCompactMode ? 'rgba(34,211,238,0.4)' : 'rgba(34,211,238,0.15)';
      this.style.fontWeight = loopWsCompactMode ? '700' : 'normal';
      populateLoopWorkspaceDropdown();
    };
    wsDropHeader.appendChild(wsCompactBtn);

    // Min credits filter
    const wsMinRow = document.createElement('div');
    wsMinRow.style.cssText = 'display:flex;align-items:center;gap:3px;';
    const wsMinLabel = document.createElement('span');
    wsMinLabel.style.cssText = 'font-size:8px;color:#94a3b8;';
    wsMinLabel.textContent = 'Min⚡';
    const wsMinInput = document.createElement('input');
    wsMinInput.type = 'number';
    wsMinInput.id = 'loop-ws-min-credits';
    wsMinInput.placeholder = '0';
    wsMinInput.min = '0';
    wsMinInput.style.cssText = 'width:35px;padding:1px 3px;border:1px solid #7c3aed;border-radius:2px;background:#171b25;color:#22d3ee;font-size:8px;outline:none;font-family:monospace;';
    wsMinInput.oninput = function() { populateLoopWorkspaceDropdown(); };
    wsMinRow.appendChild(wsMinLabel);
    wsMinRow.appendChild(wsMinInput);
    wsDropHeader.appendChild(wsMinRow);

    // Icon legend
    const wsLegend = document.createElement('div');
    wsLegend.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;padding:2px 0;border-top:1px solid rgba(255,255,255,.1);margin-top:2px;';
    wsLegend.innerHTML = '<span style="font-size:7px;color:#4ade80;" title="Billing credits from subscription">💰Billing</span>'
      + '<span style="font-size:7px;color:#c4b5fd;" title="Rollover from previous period">🔄Rollover</span>'
      + '<span style="font-size:7px;color:#facc15;" title="Daily free credits">📅Daily</span>'
      + '<span style="font-size:7px;color:#22d3ee;" title="Total available credits">⚡Total</span>'
      + '<span style="font-size:7px;color:#4ade80;" title="Trial credits">🎁Trial</span>'
      + '<span style="font-size:7px;color:#94a3b8;" title="📍=Current 🟢=OK 🟡=Low 🔴=Empty">📍🟢🟡🔴</span>';
    wsDropHeader.appendChild(wsLegend);

    // Search input
    const wsSearchInput = document.createElement('input');
    wsSearchInput.type = 'text';
    wsSearchInput.id = 'loop-ws-search';
    wsSearchInput.placeholder = '🔍 Search...';
    wsSearchInput.style.cssText = 'width:100%;padding:3px 5px;border:1px solid #7c3aed;border-radius:3px;background:#171b25;color:#e7e9ed;font-size:9px;outline:none;box-sizing:border-box;margin-bottom:4px;';
    wsSearchInput.onfocus = function() { this.style.borderColor = '#a78bfa'; };
    wsSearchInput.onblur = function() { this.style.borderColor = '#7c3aed'; };
    wsSearchInput.oninput = function() { populateLoopWorkspaceDropdown(); };
    wsSearchInput.onkeydown = function(e) {
      const listEl = document.getElementById('loop-ws-list');
      if (!listEl) return;
      const items = listEl.querySelectorAll('.loop-ws-item');
      if (items.length === 0) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setLoopWsNavIndex(loopWsNavIndex < items.length - 1 ? loopWsNavIndex + 1 : 0);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setLoopWsNavIndex(loopWsNavIndex > 0 ? loopWsNavIndex - 1 : items.length - 1);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        triggerLoopMoveFromSelection();
      }
    };

    // Workspace list
    const wsList = document.createElement('div');
    wsList.id = 'loop-ws-list';
    wsList.style.cssText = 'max-height:160px;overflow-y:auto;border:1px solid rgba(124,58,237,0.3);border-radius:3px;background:rgba(0,0,0,.3);';
    wsList.innerHTML = '<div style="padding:6px;color:#a78bfa;font-size:10px;">📭 Click 💳 to load workspaces</div>';

    // Selected indicator
    const wsSelected = document.createElement('div');
    wsSelected.id = 'loop-ws-selected';
    wsSelected.style.cssText = 'font-size:9px;color:#9ca3af;margin-top:3px;min-height:12px;';
    wsSelected.textContent = 'No workspace selected';

    // Move button row
    const wsMoveRow = document.createElement('div');
    wsMoveRow.style.cssText = 'display:flex;gap:4px;align-items:center;margin-top:3px;';

    const moveBtn = document.createElement('button');
    moveBtn.textContent = '🚀 Move';
    moveBtn.title = 'Move project to selected workspace';
    moveBtn.style.cssText = 'flex:1;padding:4px 8px;background:#059669;color:#fff;border:none;border-radius:4px;font-size:10px;font-weight:700;cursor:pointer;transition:all 0.15s;';
    moveBtn.onmouseover = function() { this.style.background = '#047857'; };
    moveBtn.onmouseout = function() { this.style.background = '#059669'; };
    moveBtn.onclick = function(e) {
      e.preventDefault(); e.stopPropagation();
      triggerLoopMoveFromSelection();
    };

    const moveStatus = document.createElement('div');
    moveStatus.id = 'loop-move-status';
    moveStatus.style.cssText = 'font-size:9px;min-height:12px;color:#9ca3af;';

    wsMoveRow.appendChild(moveBtn);
    wsMoveRow.appendChild(moveStatus);

    wsDropSection.appendChild(wsDropHeader);
    wsDropSection.appendChild(wsSearchInput);
    wsDropSection.appendChild(wsList);
    wsDropSection.appendChild(wsSelected);
    wsDropSection.appendChild(wsMoveRow);

    // === Master collapsible: wrap XPath, JS Executor, Activity, JS Logs, WS History ===
    const toolsCol = createCollapsibleSection('🔧 Tools & Logs', 'ml_collapse_tools_master');
    const toolsMasterBody = toolsCol.body;
    toolsMasterBody.style.cssText = 'margin-top:4px;display:flex;flex-direction:column;gap:4px;';
    // Default collapsed
    toolsMasterBody.style.display = 'none';
    toolsCol.toggle.textContent = '[+]';

    toolsMasterBody.appendChild(wsHistorySection);
    toolsMasterBody.appendChild(xpathSection);
    toolsMasterBody.appendChild(activitySection);
    toolsMasterBody.appendChild(logSection);
    toolsMasterBody.appendChild(jsSection);

    // Assembly order: status, info, buttons, auth diag, workspaces, master tools section
    bodyElements = [status, infoRow, btnRow, authDiagRow, wsDropSection, toolsCol.section];

    ui.appendChild(titleRow);
    ui.appendChild(status);
    ui.appendChild(infoRow);
    ui.appendChild(btnRow);
    ui.appendChild(authDiagRow);
    ui.appendChild(wsDropSection);
    ui.appendChild(toolsCol.section);

    container.appendChild(ui);

    // If using body fallback, auto-enable floating mode
    if (container === document.body) {
      enableFloating();
    }

    const record = document.createElement('div');
    record.id = IDS.RECORD_INDICATOR;
    record.className = 'loop-pulse';
    record.style.cssText = 'display:none;position:fixed;top:15px;right:15px;padding:8px 12px;background:#dc2626;border-radius:20px;color:#fff;font-size:12px;font-weight:bold;z-index:99999;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(220,38,38,.4);';
    record.innerHTML = '<span style="width:10px;height:10px;background:#fff;border-radius:50%;display:inline-block;"></span> LOOP';
    document.body.appendChild(record);

    // S-003: Page-awareness check - only handle Ctrl+Alt+Up/Down on project pages (not settings)
    function isOnProjectPageForShortcut() {
      const url = window.location.href;
      const isProject = url.indexOf('/projects/') !== -1;
      const isSettings = url.indexOf('/settings') !== -1;
      const isProjectNotSettings = isProject && !isSettings;
      return isProjectNotSettings;
    }

    document.addEventListener('keydown', function(e) {
      // Ctrl+/ to toggle JS Executor
      const isCtrlSlash = e.ctrlKey && !e.altKey && !e.shiftKey && (e.key === '/' || e.code === 'Slash');
      if (isCtrlSlash) {
        e.preventDefault();
        let hidden = jsBody.style.display === 'none';
        jsBody.style.display = hidden ? '' : 'none';
        jsToggle.textContent = hidden ? '[-]' : '[+]';
        if (hidden) {
          let ta = document.getElementById(IDS.JS_EXECUTOR);
          if (ta) ta.focus();
        }
        return;
      }

      const isCtrlAlt = e.ctrlKey && e.altKey;
      if (!isCtrlAlt) {
        // v7.9.33: Ctrl+Up/Down (no Alt) for force move — must check BEFORE returning
        const isCtrlOnly = e.ctrlKey && !e.altKey && !e.shiftKey;

        // v7.9.42: Ctrl+1 → bottom-left, Ctrl+3 → bottom-right
        if (isCtrlOnly && e.key === '1') {
          e.preventDefault();
          positionLoopController('bottom-left');
          return;
        }
        if (isCtrlOnly && e.key === '3') {
          e.preventDefault();
          positionLoopController('bottom-right');
          return;
        }

        if (isCtrlOnly && e.key === 'ArrowUp') {
          e.preventDefault();
          log('Ctrl+Up → Force Move UP via API');
          forceSwitch('up');
          return;
        }
        if (isCtrlOnly && e.key === 'ArrowDown') {
          e.preventDefault();
          log('Ctrl+Down → Force Move DOWN via API');
          forceSwitch('down');
          return;
        }
        return;
      }

      let key = e.key.toLowerCase();

      const isToggleHide = key === 'h';
      if (isToggleHide) {
        e.preventDefault();
        let isHidden = ui.style.display === 'none';
        log('Ctrl+Alt+H pressed on MacroLoop, isHidden=' + isHidden);
        if (isHidden) {
          restorePanel();
        }
        return;
      }

      // S-003: Only process Up/Down on project pages to avoid conflict with ComboSwitch
      const isProjectContext = isOnProjectPageForShortcut();
      if (!isProjectContext) {
        log('Not on project page, skipping MacroLoop shortcut (letting ComboSwitch handle it)', 'skip');
        return;
      }

      const isUpArrow = e.key === 'ArrowUp';
      if (isUpArrow) {
        e.preventDefault();
        log('Ctrl+Alt+Up pressed on project page -> MacroLoop toggle');
        const isRunning = state.running;
        if (isRunning) {
          log('Loop is running, stopping via Ctrl+Alt+Up');
          stopLoop();
        } else {
          log('Starting loop UP via Ctrl+Alt+Up');
          startLoop('up');
        }
        return;
      }

      const isDownArrow = e.key === 'ArrowDown';
      if (isDownArrow) {
        e.preventDefault();
        log('Ctrl+Alt+Down pressed on project page -> MacroLoop toggle');
        const isRunning = state.running;
        if (isRunning) {
          log('Loop is running, stopping via Ctrl+Alt+Down');
          stopLoop();
        } else {
          log('Starting loop DOWN via Ctrl+Alt+Down');
          startLoop('down');
        }
        return;
      }
    });

    log('UI created successfully with drag, hide/minimize, and keyboard shortcuts', 'success');
  }

  // ============================================
  // Initialize
  // ============================================

  let marker = document.createElement('div');
  marker.id = IDS.SCRIPT_MARKER;
  marker.style.display = 'none';
  marker.setAttribute('data-version', VERSION);
  document.body.appendChild(marker);

  window.__loopStart = startLoop;
  window.__loopStop = stopLoop;
  window.__loopCheck = runCheck;
  window.__loopState = function() { return state; };
  window.__loopSetInterval = setLoopInterval;
  window.__loopToast = showToast;  // v7.24: Expose toast system
  window.__delegateComplete = delegateComplete;
  window.__setProjectButtonXPath = updateProjectButtonXPath;
  window.__setProgressXPath = updateProgressXPath;

  createUI();

  // v6.56: Start workspace MutationObserver (always-on, replaces v6.51 disabled auto-check)
  // No longer opens project dialog constantly — just watches the nav element for text changes
  log('Starting workspace MutationObserver (v6.56) — workspace name always visible', 'success');
  startWorkspaceObserver();

  // v7.20: Auto-load workspaces immediately on injection (500ms for DOM settle)
  setTimeout(function() {
    log('Auto-loading workspaces on injection (v7.20)...', 'check');
    fetchLoopCredits();
  }, 500);

  // ============================================
  // S-002: MutationObserver to persist UI across SPA navigation
  // Watches for removal of marker/container and re-injects
  // ============================================
  (function setupPersistence() {
    let reinjectDebounce = null;
    const REINJECT_DELAY_MS = 500;

    function tryReinject() {
      // v7.25: If panel was intentionally destroyed, do NOT re-create
      if (window.__loopDestroyed) {
        log('Panel was destroyed by user — skipping re-injection', 'info');
        return;
      }
      const hasMarker = !!document.getElementById(IDS.SCRIPT_MARKER);
      const hasContainer = !!document.getElementById(IDS.CONTAINER);

      if (!hasMarker) {
        log('Marker removed by SPA navigation, re-placing', 'warn');
        const newMarker = document.createElement('div');
        newMarker.id = IDS.SCRIPT_MARKER;
        newMarker.style.display = 'none';
        newMarker.setAttribute('data-version', VERSION);
        document.body.appendChild(newMarker);
      }

      if (!hasContainer) {
        log('UI container removed by SPA navigation, re-creating', 'warn');
        createUI();
      }
    }

    const observer = new MutationObserver(function(mutations) {
      let hasRemovals = false;
      for (let i = 0; i < mutations.length; i++) {
        if (mutations[i].removedNodes.length > 0) {
          hasRemovals = true;
          break;
        }
      }
      if (!hasRemovals) return;

      const markerGone = !document.getElementById(IDS.SCRIPT_MARKER);
      const containerGone = !document.getElementById(IDS.CONTAINER);

      if (markerGone || containerGone) {
        if (reinjectDebounce) clearTimeout(reinjectDebounce);
        reinjectDebounce = setTimeout(function() {
          log('SPA navigation detected - checking UI state', 'check');
          tryReinject();
        }, REINJECT_DELAY_MS);
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    log('MutationObserver installed for UI persistence', 'success');
  })();

  log('Initialization complete', 'success');

  // XPathUtils integration — no individual globals exposed (use XPathUtils.* in console)
  if (hasXPathUtils) {
    log('XPathUtils v' + window.XPathUtils.version + ' available — use XPathUtils.findByXPath(), XPathUtils.clickByXPath(), etc.', 'success');
  } else {
    log('XPathUtils NOT found — XPath console helpers unavailable. Inject xpath-utils.js first.', 'warn');
  }

  // v7.9.22: Diagnostic function — call window.__loopDiag() in JS Executor
  window.__loopDiag = function() {
    const diag = {
      version: VERSION,
      workspaceName: state.workspaceName,
      workspaceFromApi: state.workspaceFromApi,
      currentWsName: loopCreditState.currentWs ? (loopCreditState.currentWs.fullName || loopCreditState.currentWs.name) : '(null)',
      currentWsId: loopCreditState.currentWs ? loopCreditState.currentWs.id : '(null)',
      wsCount: (loopCreditState.perWorkspace || []).length,
      wsByIdKeys: Object.keys(loopCreditState.wsById || {}),
      projectId: extractProjectIdFromUrl(),
      lastCheckedAt: loopCreditState.lastCheckedAt ? new Date(loopCreditState.lastCheckedAt).toLocaleTimeString() : '(never)',
      source: loopCreditState.source
    };
    log('=== DIAGNOSTIC DUMP ===', 'warn');
    for (let k in diag) {
      const val = Array.isArray(diag[k]) ? '[' + diag[k].join(', ') + ']' : String(diag[k]);
      log('  ' + k + ': ' + val, 'check');
    }
    // Also list all workspace names with their IDs
    let perWs = loopCreditState.perWorkspace || [];
    for (let i = 0; i < perWs.length; i++) {
      log('  ws[' + i + ']: id=' + perWs[i].id + ' name="' + perWs[i].fullName + '"', 'check');
    }
    return diag;
  };

  log('Global functions: __loopStart("up"|"down"), __loopStop(), __loopCheck(), __loopDiag()');
  log('XPath functions: __setProjectButtonXPath(xpath), __setProgressXPath(xpath)');
  log('XPath: use XPathUtils.findByXPath(x), XPathUtils.clickByXPath(x), XPathUtils.fireAll(x)');
  log('Keyboard: Ctrl+Alt+Up/Down to toggle loop, Ctrl+Up/Down to force move, Ctrl+Alt+H to show/hide');
})();

    } catch (__marcoErr) {
        var __errMsg = __marcoErr.message || String(__marcoErr);
        var __errStack = __marcoErr.stack || "";
        console.error("[Marco] Script " + "default-macro-looping" + " error:", __errMsg, "\nStack:", __errStack);
        try {
            var __ctx = window.marco && window.marco.context ? window.marco.context : null;

            window.postMessage({
                source: "marco-controller",
                type: "USER_SCRIPT_ERROR",
                scriptId: "default-macro-looping",
                message: __errMsg,
                stack: __errStack,
                scriptCode: "// ============================================\r\n// MacroLoop Controller — Standalone Version\r\n// Reads config from window.__MARCO_CONFIG__ (injected by Chrome Extension)\r\n// Based on: marco-script-ahk-v7.latest/macro-looping.js\r\n// ============================================\r\n\r\n(function() {\r\n  'use strict';\r\n\r\n  const FILE_NAME = 'macro-looping.js';\r\n  const VERSION = '7.33';\r\n\r\n  // ============================================\r\n  // Config: Read from window.__MARCO_CONFIG__ or use defaults\r\n",
                projectId: __ctx && __ctx.projectId ? __ctx.projectId : null
            }, "*");

            window.postMessage({
                source: "marco-controller",
                type: "USER_SCRIPT_LOG",
                payload: {
                    level: "ERROR",
                    source: "user-script",
                    category: "INJECTION",
                    action: "runtime_error",
                    detail: "Script " + "default-macro-looping" + " error: " + __errMsg,
                    metadata: JSON.stringify({ stack: __errStack }),
                    projectId: __ctx && __ctx.projectId ? __ctx.projectId : null,
                    scriptId: "default-macro-looping",
                    configId: __ctx && __ctx.configId ? __ctx.configId : null,
                    urlRuleId: __ctx && __ctx.urlRuleId ? __ctx.urlRuleId : null,
                    pageUrl: window.location.href,
                    timestamp: new Date().toISOString()
                }
            }, "*");
        } catch (__relayErr) {
            console.error("[Marco] Failed to relay error:", __relayErr);
        }
    }
})();