{{-- Shared shell for the focused "coach console" pages (Run Training, Students, …).
     Owns the design system (the .rt tokens + components), focus mode (hides the panel chrome),
     the outer .rt column, and the bottom tab bar. Each page drops its own markup into the slot.

     Props:
       active : which tab is current — 'training' | 'students' (highlights the tab)
       tabs   : render the bottom tab bar (false on drill-down/edit screens that own the bottom)
       fill   : stretch the column to viewport height so a sticky bottom bar pins to the edge --}}
@props([
    'active' => null,
    'tabs' => true,
    'fill' => true,
])

<style>
    /* ---------- focus mode ---------- */
    /* Intentionally NOT scoped to .rt: hides the panel's sidebar + topbar so coaches see only the
       coach console. This <style> ships with the shell, so it only applies while a shell page is
       mounted — navigating to a normal panel page removes it and restores the chrome.
       To limit this to coaches, render the shell only for that role; to keep the sidebar and drop
       only the topbar, delete `.fi-sidebar` from the selector. */
    .fi-sidebar, .fi-topbar{ display:none !important; }

    /* width:100% is load-bearing, not redundant with max-width. Filament's .fi-page-content is a
       CSS grid, and `margin:0 auto` makes the auto margins override the grid's default stretch —
       which turns .rt into a shrink-to-fit box that sizes to its content. Without an explicit
       width the column then renders NARROWER on content-light pages (e.g. Students) than on
       content-heavy ones (Run Training). width:100% restores a definite size so every coach page
       is the same 34rem column regardless of what's inside it. */
    .rt{ --b:#e8edf3; --bs:#e2e8f0; --bg:#fff; --soft:#f6f8fb; --mut:#94a3b8; --sub:#64748b; --ink:#0f172a;
         --ac:#2563eb; --ac2:#1d4ed8; --acs:#eff4fe; --ok:#15803d; --oks:#ecfdf3; --wa:#b45309; --was:#fef6e7;
         --da:#b91c1c; --das:#fef2f2; --vi:#6d28d9; --vis:#f5f1fe;
         color:var(--ink); width:100%; max-width:34rem; margin:0 auto; display:flex; flex-direction:column; gap:.75rem; padding-bottom:1.5rem; }
    /* Fill the visible page height so a sticky bottom bar (Save bar / tab bar) has a bottom to pin
       to even when content is short. With focus mode hiding the topbar, the offset is just the page
       padding (~4rem); tweak if it over/undershoots. */
    .rt-fill{ min-height:calc(100dvh - 4rem); }
    /* Reserve room so the last card never hides behind the fixed bottom nav (see .rt-tabbar). */
    .rt-tabbed{ padding-bottom:calc(5.5rem + env(safe-area-inset-bottom)); }
    .dark .rt{ --b:#262d38; --bs:#333c49; --bg:#161b22; --soft:#0f141b; --mut:#6b7688; --sub:#9aa4b5; --ink:#e6e9ef;
         --ac:#5b8bff; --ac2:#3b6cf0; --acs:#182036; --ok:#3fbf98; --oks:#10281f; --wa:#e2ab5b; --was:#2a2114;
         --da:#f08a8a; --das:#301616; --vi:#b394f5; --vis:#241a38; }
    .rt input,.rt select,.rt textarea{ border:1.5px solid var(--bs); border-radius:.75rem; padding:.5rem .7rem; background:var(--bg); color:inherit; font-size:.9rem; width:100%; }
    .rt input:focus,.rt select:focus,.rt textarea:focus{ outline:none; border-color:var(--ac); box-shadow:0 0 0 3px color-mix(in srgb,var(--ac) 18%,transparent); }
    .rt select:disabled,.rt input:disabled{ opacity:.55; }

    /* ---------- app bar ---------- */
    .rt-bar{ position:sticky; top:0; z-index:20; display:flex; align-items:center; gap:.6rem; padding:.7rem .25rem; background:linear-gradient(to bottom,var(--soft) 78%,transparent); }
    .rt-bar h1{ font-size:1.15rem; font-weight:800; letter-spacing:-.02em; margin:0; flex:1; min-width:0; }
    .rt-bar .rt-crumb{ font-size:.72rem; font-weight:700; color:var(--mut); }
    .rt-iconbtn{ display:grid; place-items:center; width:2.5rem; height:2.5rem; border-radius:.8rem; border:1px solid var(--b); background:var(--bg); color:var(--sub); cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.05); flex:none; }
    .rt-iconbtn:disabled{ opacity:.4; cursor:not-allowed; }
    .rt-iconbtn svg{ width:1.15rem; height:1.15rem; stroke:currentColor; fill:none; stroke-width:2.2; }
    .rt-textbtn{ border:0; background:var(--acs); color:var(--ac2); font-weight:800; font-size:.78rem; padding:.5rem .8rem; border-radius:.7rem; cursor:pointer; flex:none; }
    .rt-cal{ position:relative; overflow:hidden; }
    .rt-cal input{ position:absolute; inset:0; opacity:0; cursor:pointer; padding:0; border:0; }

    /* ---------- week strip ---------- */
    .rt-week{ display:flex; align-items:center; gap:.35rem; }
    .rt-days{ display:flex; gap:.4rem; flex:1; overflow-x:auto; scrollbar-width:none; padding:.15rem 0; }
    .rt-days::-webkit-scrollbar{ display:none; }
    .rt-day{ flex:1 0 auto; min-width:2.9rem; display:flex; flex-direction:column; align-items:center; gap:.15rem; padding:.55rem .3rem; border-radius:.9rem; border:1px solid var(--b); background:var(--bg); cursor:pointer; }
    .rt-day .wd{ font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--mut); }
    .rt-day .dn{ font-size:1.05rem; font-weight:800; font-variant-numeric:tabular-nums; }
    .rt-day .dot{ width:.3rem; height:.3rem; border-radius:50%; background:var(--ac); }
    .rt-day.on{ border-color:transparent; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); box-shadow:0 8px 18px -8px color-mix(in srgb,var(--ac) 60%,transparent); }
    .rt-day.on .wd,.rt-day.on .dn{ color:#fff; }
    .rt-day.on .dot{ background:#fff; }

    /* ---------- list cards ---------- */
    .rt-list{ display:flex; flex-direction:column; gap:.6rem; }
    .rt-listlabel{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; padding:.25rem .25rem 0; }
    .rt-scard{ display:flex; align-items:center; gap:.85rem; width:100%; text-align:left; padding:.8rem; border:1px solid var(--b); border-radius:1.1rem; background:var(--bg); cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.04),0 10px 26px -22px rgba(15,23,42,.3); }
    .rt-scard:active{ background:var(--soft); }
    .rt-scard.locked{ opacity:.5; pointer-events:none; }
    .rt-scard.new{ border-style:dashed; border-color:var(--bs); background:transparent; box-shadow:none; }
    .rt-daychip{ display:grid; width:3.5rem; flex:none; border-radius:.85rem; overflow:hidden; border:1px solid var(--b); text-align:center; }
    .rt-daychip .d{ background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-size:.56rem; font-weight:800; text-transform:uppercase; letter-spacing:.1em; padding:.16rem 0; }
    .rt-daychip .t{ font-size:.82rem; font-weight:800; padding:.28rem 0; font-variant-numeric:tabular-nums; background:var(--bg); }
    .rt-scard-body{ flex:1; min-width:0; }
    .rt-scard-title{ font-weight:800; letter-spacing:-.01em; font-size:.95rem; }
    .rt-scard-meta{ font-size:.76rem; color:var(--sub); margin-top:.15rem; display:flex; align-items:center; gap:.4rem; font-weight:600; flex-wrap:wrap; }
    .rt-plus{ display:grid; place-items:center; width:3.5rem; height:3.1rem; flex:none; border-radius:.85rem; border:1.5px dashed var(--bs); color:var(--ac); font-size:1.5rem; font-weight:800; }
    .rt-avatar{ display:inline-grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:999px; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-size:.62rem; font-weight:800; flex:none; }
    .rt-status{ display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; font-weight:800; padding:.24rem .6rem; border-radius:999px; white-space:nowrap; flex:none; }
    .rt-status .led{ width:.42rem; height:.42rem; border-radius:50%; }
    .rt-status.saved{ background:var(--oks); color:var(--ok); } .rt-status.saved .led{ background:var(--ok); }
    .rt-status.pending{ background:var(--was); color:var(--wa); } .rt-status.pending .led{ background:var(--wa); }
    .rt-status.off{ background:var(--soft); color:var(--mut); } .rt-status.off .led{ background:var(--mut); }
    .rt-callout{ border:1.5px dashed var(--bs); border-radius:1.1rem; padding:2rem 1rem; text-align:center; color:var(--mut); font-size:.85rem; background:var(--bg); }
    .rt-callout .ball{ display:block; font-size:1.8rem; margin-bottom:.4rem; }

    /* ---------- recorder / detail body ---------- */
    .rt-panel{ display:flex; flex-direction:column; gap:.85rem; }
    .rt-fieldlabel{ font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
    .rt-newsession{ display:grid; grid-template-columns:1fr; gap:.55rem; padding:.9rem; border:1px solid var(--b); border-radius:1rem; background:var(--bg); }
    .rt-newsession .row2{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
    .rt-coachstrip{ display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; font-size:.8rem; color:var(--sub); background:var(--bg); border:1px solid var(--b); border-radius:1rem; padding:.7rem .85rem; }
    .rt-coachstrip .lbl{ font-weight:800; color:inherit; }
    .rt-coachstrip select{ width:auto; flex:1; min-width:7rem; }
    .rt-linkbtn{ border:0; background:transparent; color:var(--ac2); font-weight:800; font-size:.8rem; cursor:pointer; padding:0; }
    .rt-linkbtn.muted{ color:var(--mut); }
    .rt-warn{ font-size:.78rem; color:var(--wa); background:var(--was); border:1px solid color-mix(in srgb,var(--wa) 30%,transparent); border-radius:.7rem; padding:.5rem .65rem; font-weight:600; }

    .rt-rosterhead{ display:flex; align-items:center; justify-content:space-between; padding:0 .25rem; }
    .rt-rosterhead .t{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
    .rt-players{ display:flex; flex-direction:column; gap:.5rem; }
    .rt-prow{ display:flex; align-items:center; gap:.7rem; padding:.65rem .75rem; border:1px solid var(--b); border-radius:1rem; background:var(--bg); }
    .rt-prow.done{ border-left:4px solid var(--ok); }
    .rt-prow.overlimit{ border-left:4px solid var(--da); }
    .rt-prow.excused{ opacity:.62; }
    .rt-prow-main{ flex:1; min-width:0; display:flex; align-items:center; gap:.65rem; cursor:pointer; background:none; border:0; text-align:left; color:inherit; padding:0; }
    .rt-pav{ display:grid; place-items:center; width:2.25rem; height:2.25rem; border-radius:.7rem; background:var(--soft); border:1px solid var(--b); font-weight:800; font-size:.85rem; color:var(--sub); flex:none; }
    .rt-pname{ font-weight:700; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .rt-psub{ font-size:.7rem; color:var(--mut); margin-top:.1rem; display:flex; gap:.3rem; flex-wrap:wrap; align-items:center; }
    .rt-scored{ font-size:.66rem; font-weight:800; color:var(--ac2); background:var(--acs); padding:.08rem .4rem; border-radius:999px; }
    .rt-scored.full{ color:var(--ok); background:var(--oks); }
    .rt-cyc{ display:inline-flex; align-items:center; gap:.35rem; border:1.5px solid; border-radius:.7rem; padding:.4rem .7rem; font-size:.76rem; font-weight:800; cursor:pointer; background:var(--bg); flex:none; }
    .rt-cyc .d{ width:.45rem; height:.45rem; border-radius:50%; }
    .rt-cyc.present{ border-color:color-mix(in srgb,var(--ok) 45%,transparent); color:var(--ok); background:var(--oks); } .rt-cyc.present .d{ background:var(--ok); }
    .rt-cyc.late{ border-color:color-mix(in srgb,var(--wa) 45%,transparent); color:var(--wa); background:var(--was); } .rt-cyc.late .d{ background:var(--wa); }
    .rt-cyc.absent{ border-color:color-mix(in srgb,var(--da) 45%,transparent); color:var(--da); background:var(--das); } .rt-cyc.absent .d{ background:var(--da); }
    .rt-cyc.excused{ border-color:var(--bs); color:var(--mut); background:var(--soft); } .rt-cyc.excused .d{ background:var(--mut); }
    .rt-remove{ border:0; background:transparent; color:var(--mut); cursor:pointer; font-size:1rem; padding:.25rem; flex:none; }

    .rt-badge{ font-size:.66rem; padding:.15rem .5rem; border-radius:999px; font-weight:800; white-space:nowrap; }
    .rt-badge.pay-active{ background:var(--oks); color:var(--ok); } .rt-badge.pay-pending{ background:var(--was); color:var(--wa); } .rt-badge.pay-overdue{ background:var(--das); color:var(--da); }
    .rt-badge.credits{ background:var(--soft); color:var(--sub); border:1px solid var(--b); } .rt-badge.credits.over{ background:var(--das); color:var(--da); } .rt-badge.credits.full{ background:var(--was); color:var(--wa); }
    .rt-badge.extra{ background:var(--acs); color:var(--ac2); } .rt-badge.walkin{ background:var(--vis); color:var(--vi); } .rt-badge.carry{ background:var(--was); color:var(--wa); }
    .rt-badge.off{ background:var(--soft); color:var(--mut); border:1px solid var(--b); }

    /* ---------- sections & cards (shared layout vocabulary) ---------- */
    /* A labelled block: a .rt-rosterhead header + a body (.rt-card / .rt-players / .rt-stats). */
    .rt-section{ display:flex; flex-direction:column; gap:.5rem; }
    /* The standard bordered content box — same border/radius/background as the other surfaces so
       every panel on every coach page shares one inset rhythm. */
    .rt-card{ border:1px solid var(--b); border-radius:1rem; background:var(--bg); padding:.5rem .85rem; }
    .rt-card.pad{ padding:.85rem; }

    /* ---------- stat tiles (detail) ---------- */
    .rt-stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; }
    .rt-stat{ border:1px solid var(--b); border-radius:1rem; background:var(--bg); padding:.7rem .6rem; text-align:center; }
    .rt-stat .v{ font-size:1.25rem; font-weight:800; font-variant-numeric:tabular-nums; letter-spacing:-.02em; }
    .rt-stat .k{ font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:var(--mut); font-weight:800; margin-top:.1rem; }
    .rt-stat.warn .v{ color:var(--wa); } .rt-stat.bad .v{ color:var(--da); } .rt-stat.good .v{ color:var(--ok); }
    .rt-defrow{ display:flex; align-items:baseline; justify-content:space-between; gap:.6rem; padding:.4rem 0; border-bottom:1px dashed var(--bs); font-size:.86rem; }
    .rt-defrow:last-child{ border-bottom:0; }
    .rt-defrow .k{ color:var(--mut); font-weight:700; }
    .rt-defrow .v{ font-weight:700; text-align:right; }
    .rt-histrow{ display:flex; flex-direction:column; gap:.2rem; padding:.55rem .7rem; border:1px solid var(--b); border-radius:.8rem; background:var(--bg); }
    .rt-histrow .top{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .rt-histrow .dt{ font-weight:800; font-size:.84rem; }
    .rt-histrow .sl{ font-size:.72rem; color:var(--mut); }
    /* expandable session row: a session-average mini bar + a chevron that reveals per-skill bars */
    .rt-histrow .hh-right{ display:flex; align-items:center; gap:.5rem; }
    .rt-avg{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; font-weight:800; color:var(--sub); font-variant-numeric:tabular-nums; }
    .rt-avg .bar{ width:2.6rem; height:.4rem; background:var(--soft); border:1px solid var(--b); border-radius:999px; overflow:hidden; }
    .rt-avg .fill{ height:100%; border-radius:999px; }
    .rt-avg .fill.hi{ background:var(--ok); } .rt-avg .fill.mid{ background-image:linear-gradient(90deg,var(--ac),var(--ac2)); } .rt-avg .fill.lo{ background:var(--wa); }
    .rt-chev{ border:0; background:transparent; color:var(--mut); cursor:pointer; padding:.1rem; display:grid; place-items:center; flex:none; }
    .rt-chev svg{ width:1rem; height:1rem; stroke:currentColor; fill:none; stroke-width:2.4; transition:transform .18s ease; }
    .rt-chev.on svg{ transform:rotate(180deg); }
    .rt-hist-det{ margin-top:.5rem; }

    /* skill level meters — a labelled 0–5 bar per skill, coloured by level */
    .rt-meter{ display:flex; flex-direction:column; gap:.6rem; }
    .rt-meter .m{ display:flex; flex-direction:column; gap:.3rem; }
    .rt-meter .head{ display:flex; align-items:baseline; justify-content:space-between; gap:.6rem; }
    .rt-meter .nm{ font-size:.84rem; font-weight:600; min-width:0; }
    .rt-meter .val{ font-size:.82rem; font-weight:800; font-variant-numeric:tabular-nums; flex:none; }
    .rt-meter .track{ height:.5rem; background:var(--soft); border:1px solid var(--b); border-radius:999px; overflow:hidden; }
    .rt-meter .fill{ height:100%; border-radius:999px; min-width:.35rem; }
    .rt-meter .fill.hi{ background:var(--ok); }
    .rt-meter .fill.mid{ background-image:linear-gradient(90deg,var(--ac),var(--ac2)); }
    .rt-meter .fill.lo{ background:var(--wa); }

    /* mini bar chart (reports) — CSS-only columns, no chart lib */
    .rt-trend{ display:flex; align-items:flex-end; gap:.5rem; height:7.5rem; }
    .rt-trend .bar{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:.25rem; height:100%; min-width:0; }
    .rt-trend .val{ font-size:.62rem; font-weight:800; color:var(--sub); font-variant-numeric:tabular-nums; }
    .rt-trend .col{ width:100%; flex:1; display:flex; align-items:flex-end; background:var(--soft); border-radius:.5rem .5rem 0 0; overflow:hidden; }
    .rt-trend .fill{ width:100%; background-image:linear-gradient(to top,var(--ac),var(--ac2)); border-radius:.5rem .5rem 0 0; min-height:2px; }
    .rt-trend .lbl{ font-size:.56rem; color:var(--mut); font-weight:800; text-transform:uppercase; letter-spacing:.04em; }

    /* ---------- add / floating ---------- */
    .rt-addbtn{ display:flex; align-items:center; justify-content:center; gap:.5rem; width:100%; padding:.8rem; border:1.5px dashed var(--bs); border-radius:1rem; background:var(--bg); color:var(--ac2); font-weight:800; font-size:.85rem; cursor:pointer; }

    /* ---------- bottom sheet ---------- */
    .rt-backdrop{ position:fixed; inset:0; z-index:60; background:rgba(15,23,42,.5); }
    .rt-sheet{ position:fixed; left:50%; transform:translateX(-50%); bottom:0; z-index:61; width:100%; max-width:34rem; max-height:88vh; overflow-y:auto; background:var(--bg); border-radius:1.4rem 1.4rem 0 0; box-shadow:0 -18px 40px -12px rgba(15,23,42,.35); padding:.5rem 1.1rem 1.4rem; display:flex; flex-direction:column; gap:1rem; }
    .rt-handle{ width:2.5rem; height:.28rem; border-radius:999px; background:var(--bs); margin:.35rem auto .2rem; }
    .rt-sheet-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
    .rt-sheet-head .nm{ font-size:1.15rem; font-weight:800; letter-spacing:-.02em; }
    .rt-sheet-head .badges{ display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.35rem; }
    .rt-sheet-close{ border:0; background:var(--soft); border-radius:.7rem; color:var(--sub); font-size:1rem; padding:.4rem .55rem; cursor:pointer; flex:none; }
    .rt-sheet-sec{ display:flex; flex-direction:column; gap:.55rem; }
    .rt-sheet-sec > .h{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
    .rt-att{ display:grid; grid-template-columns:repeat(4,1fr); gap:.45rem; }
    .rt-att button{ border:1.5px solid var(--bs); border-radius:.8rem; padding:.65rem .3rem; background:var(--bg); cursor:pointer; font-size:.78rem; font-weight:700; color:var(--sub); }
    .rt-att button.sel{ color:#fff; border-color:transparent; }
    .rt-att button.sel.present{ background:var(--ok); } .rt-att button.sel.late{ background:var(--wa); } .rt-att button.sel.absent{ background:var(--da); } .rt-att button.sel.excused{ background:var(--mut); }
    .rt-skill{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.15rem 0; }
    .rt-skill .n{ font-size:.86rem; font-weight:600; }
    .rt-pills{ display:inline-flex; gap:.3rem; }
    .rt-pill{ width:2.4rem; height:2.4rem; border:1.5px solid var(--bs); border-radius:.7rem; background:var(--bg); cursor:pointer; font-size:.9rem; font-weight:700; color:var(--sub); }
    .rt-pill.sel{ border-color:transparent; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-weight:800; box-shadow:inset 0 1px 0 rgba(255,255,255,.22); }
    .rt-pill:disabled{ opacity:.35; cursor:not-allowed; }
    .rt-sheet-done{ background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; border:0; border-radius:.9rem; padding:.8rem; font-weight:800; font-size:.95rem; cursor:pointer; box-shadow:inset 0 1px 0 rgba(255,255,255,.22); }

    .rt-result{ display:flex; align-items:center; gap:.6rem; padding:.6rem .7rem; border:1px solid var(--b); border-radius:.8rem; background:var(--bg); font-size:.82rem; flex-wrap:wrap; }
    .rt-result .meta{ flex:1; min-width:0; }
    .rt-tag{ font-size:.66rem; padding:.15rem .5rem; border-radius:999px; background:var(--acs); color:var(--ac2); font-weight:800; }
    .rt-results{ display:flex; flex-direction:column; gap:.4rem; }
    .rt-row2{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
    .rt-muted{ color:var(--mut); font-size:.8rem; }

    /* ---------- sticky action bar ---------- */
    /* Sticky (not viewport-fixed) so it tracks the centered .rt column instead of the whole
       window — otherwise Filament's sidebar offsets it left of the content on desktop. */
    .rt-actionbar{ position:sticky; bottom:0; z-index:40; display:flex; align-items:center; gap:.6rem; padding:.9rem 1rem calc(.9rem + env(safe-area-inset-bottom)); background:linear-gradient(to top,var(--bg) 72%,transparent); }
    .rt-save{ flex:1; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; border:0; border-radius:.95rem; padding:.85rem; font-weight:800; font-size:1rem; cursor:pointer; box-shadow:inset 0 1px 0 rgba(255,255,255,.22),0 12px 24px -10px color-mix(in srgb,var(--ac) 65%,transparent); }
    .rt-save:disabled{ opacity:.55; cursor:not-allowed; box-shadow:none; }
    .rt-discard,.rt-delete{ border:1px solid var(--bs); background:var(--bg); color:var(--sub); border-radius:.95rem; padding:.85rem 1rem; font-weight:800; cursor:pointer; font-size:.85rem; flex:none; }
    .rt-delete{ border-color:color-mix(in srgb,var(--da) 35%,transparent); color:var(--da); }

    /* ---------- bottom tab bar ---------- */
    /* Fixed to the viewport bottom like a native app tab bar — always visible, content scrolls
       behind it (the .rt-tabbed reserve keeps the last card clear). Centered at the column width;
       this lines up with the content because focus mode hides the sidebar. Shown only on top-level
       screens; drill-down screens own the bottom edge with their own action bar. */
    .rt-tabbar{ position:fixed; left:50%; transform:translateX(-50%); bottom:0; z-index:40; width:100%; max-width:34rem; display:grid; grid-auto-columns:1fr; grid-auto-flow:column; gap:.4rem; padding:.45rem .4rem calc(.45rem + env(safe-area-inset-bottom)); background:var(--bg); border-top:1px solid var(--b); box-shadow:0 -8px 24px -18px rgba(15,23,42,.4); }
    .rt-tab{ display:flex; flex-direction:column; align-items:center; gap:.2rem; padding:.5rem; border:1px solid transparent; border-radius:.95rem; background:transparent; color:var(--mut); font-weight:800; font-size:.66rem; text-transform:uppercase; letter-spacing:.06em; cursor:pointer; text-decoration:none; }
    .rt-tab svg{ width:1.4rem; height:1.4rem; stroke:currentColor; fill:none; stroke-width:2; }
    .rt-tab.on{ color:var(--ac2); background:var(--acs); }

    /* ---------- guided spotlight tour + inline hint ---------- */
    /* A dark cut-out overlay: four masks darken everything except the highlighted element's gap
       (which stays clickable), a ring outlines it, and a popover explains it. No JS library. */
    .rt-tour{ position:fixed; inset:0; z-index:90; }
    .rt-tour-mask{ position:fixed; background:rgba(15,23,42,.6); }
    .dark .rt-tour-mask{ background:rgba(0,0,0,.66); }
    .rt-tour-ring{ position:fixed; border-radius:.8rem; box-shadow:0 0 0 3px var(--ac),0 0 0 6px color-mix(in srgb,var(--ac) 30%,transparent); pointer-events:none; transition:top .18s ease,left .18s ease,width .18s ease,height .18s ease; }
    .rt-tour-pop{ position:fixed; left:50%; transform:translateX(-50%); width:calc(100% - 2rem); max-width:30rem; z-index:91; background:var(--bg); border-radius:1.1rem; box-shadow:0 18px 44px -12px rgba(15,23,42,.55); padding:1rem 1.1rem; display:flex; flex-direction:column; gap:.4rem; }
    .rt-tour-pop .ttl{ font-size:1rem; font-weight:800; letter-spacing:-.01em; }
    .rt-tour-pop .txt{ font-size:.86rem; color:var(--sub); line-height:1.45; }
    .rt-tour-foot{ display:flex; align-items:center; gap:.5rem; margin-top:.45rem; }
    .rt-tour-count{ font-size:.72rem; font-weight:800; color:var(--mut); font-variant-numeric:tabular-nums; }
    .rt-tour-foot .grow{ flex:1; }
    .rt-tour-foot .back{ border:1px solid var(--bs); background:var(--bg); color:var(--sub); border-radius:.8rem; padding:.55rem .9rem; font-weight:800; cursor:pointer; font-size:.82rem; }
    .rt-tour-foot .rt-sheet-done{ padding:.55rem 1.15rem; border-radius:.8rem; font-size:.9rem; }
    .rt-tour-skip{ border:0; background:transparent; color:var(--mut); font-weight:700; font-size:.8rem; cursor:pointer; }

    .rt-hint{ display:flex; align-items:center; gap:.5rem; font-size:.78rem; color:var(--sub); background:var(--acs); border:1px solid color-mix(in srgb,var(--ac) 22%,transparent); border-radius:.7rem; padding:.5rem .65rem; }
    .rt-hint button{ margin-left:auto; border:0; background:transparent; color:var(--mut); cursor:pointer; font-size:.9rem; flex:none; padding:0 .2rem; }

    /* ---------- help modal ---------- */
    [x-cloak]{ display:none; }
    .rt-help-overlay{ position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:flex-end; justify-content:center; z-index:100; }
    .rt-help-card{ background:var(--bg); border-radius:1.4rem 1.4rem 0 0; width:100%; max-width:34rem; max-height:85vh; overflow-y:auto; padding:1.3rem 1.4rem 1.6rem; position:relative; }
    .rt-help-close{ position:absolute; top:.9rem; right:.9rem; border:0; background:var(--soft); border-radius:.6rem; color:var(--sub); font-size:1rem; padding:.35rem .5rem; cursor:pointer; }
    .rt-help-title{ font-size:1.15rem; font-weight:800; margin:0 0 .75rem; padding-right:2rem; }
    .rt-help-section{ margin-top:1.1rem; } .rt-help-section h4{ font-size:.68rem; text-transform:uppercase; letter-spacing:.09em; color:var(--mut); font-weight:800; margin:0 0 .5rem; }
    .rt-help-rules,.rt-help-steps{ margin:0; padding-left:1.1rem; display:flex; flex-direction:column; gap:.35rem; font-size:.83rem; color:var(--sub); }
    .rt-help-badges{ display:flex; flex-direction:column; gap:.5rem; } .rt-help-badgerow{ display:flex; align-items:center; gap:.6rem; font-size:.8rem; color:var(--sub); } .rt-help-badgerow .rt-badge{ flex:none; }
    .rt-help-foot{ margin-top:1.1rem; font-size:.8rem; color:var(--mut); border-top:1px dashed var(--bs); padding-top:.75rem; }
</style>

<div class="rt {{ $fill ? 'rt-fill' : '' }} {{ $tabs ? 'rt-tabbed' : '' }}" x-data="{ help: false }" @keydown.escape.window="help = false">
    {{ $slot }}

    @if($tabs)
        <nav class="rt-tabbar" aria-label="Coach console">
            <a href="{{ \App\Filament\Pages\CoachHome::getUrl() }}" wire:navigate class="rt-tab {{ $active === 'home' ? 'on' : '' }}" @if($active === 'home') aria-current="page" @endif>
                <svg viewBox="0 0 24 24"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>
                Home
            </a>
            <a href="{{ \App\Filament\Pages\RunTraining::getUrl() }}" wire:navigate class="rt-tab {{ $active === 'training' ? 'on' : '' }}" @if($active === 'training') aria-current="page" @endif>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3l2.5 3.5M12 3L9.5 6.5M21 12l-3.8 1M3 12l3.8 1M7.5 20l1.3-3.6M16.5 20l-1.3-3.6M12 9l3 2-1.2 3.5h-3.6L9 11z"/></svg>
                Training
            </a>
            <a href="{{ \App\Filament\Pages\Students::getUrl() }}" wire:navigate class="rt-tab {{ $active === 'students' ? 'on' : '' }}" @if($active === 'students') aria-current="page" @endif>
                <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0111 0M16 5.5a3 3 0 010 5.6M18 20a5.5 5.5 0 00-3-4.9"/></svg>
                Students
            </a>
        </nav>
    @endif
</div>
