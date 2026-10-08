<style>
.sales-workspace{color:#334155;font-size:14px}
.sales-workspace [hidden]{display:none!important}
.sales-workspace .report-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;flex-wrap:wrap}
.sales-workspace .report-eyebrow{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin-bottom:6px;font-weight:600}
.sales-workspace h1{font-size:28px;line-height:1.2;font-weight:800;letter-spacing:-.025em;color:#15233f}
.sales-workspace .report-subtitle{font-size:13px;color:#64748b;margin-top:6px}
.sales-workspace .report-card{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:20px;box-shadow:0 2px 5px #15233f05;min-width:0}
.sales-workspace .report-section-title{font-weight:700;font-size:16px;color:#15233f;margin-bottom:4px}
.sales-workspace .report-note{font-size:12px;color:#64748b;line-height:1.6}
.sales-workspace .report-filters{margin-bottom:20px}
.sales-workspace .report-filter-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}
.sales-workspace .report-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sales-workspace .report-filter-grid label{display:block;font-size:12px;font-weight:600;color:#475569;min-width:0}
.sales-workspace .report-filter-grid .input{margin-top:6px;width:100%;height:42px;border-radius:10px;background:#f8fafc;font-size:13px;font-weight:400;min-width:0}
.sales-workspace .report-search{grid-column:span 3}
.sales-workspace .report-custom{grid-column:1/-1;display:flex;gap:14px;flex-wrap:wrap}
.sales-workspace .report-custom label{flex:1;min-width:140px}
.sales-workspace .report-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sales-workspace .report-metric{padding:18px;overflow:hidden}
.sales-workspace .report-metric:first-child{border-top:3px solid #14b8a6;padding-top:16px}
.sales-workspace .report-metric-top{display:flex;align-items:center;gap:9px;margin-bottom:16px}
.sales-workspace .report-metric-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:#64748b}
.sales-workspace .report-icon-tile{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;background:#f0fdfa;color:#0f766e;flex-shrink:0}
.sales-workspace .report-metric:nth-child(2) .report-icon-tile{background:#fff7ed;color:#c2410c}
.sales-workspace .report-metric:nth-child(3) .report-icon-tile{background:#eff6ff;color:#1d4ed8}
.sales-workspace .report-value{font-size:clamp(20px,2.1vw,28px);font-weight:800;color:#15233f;letter-spacing:-.02em;line-height:1.2;overflow-wrap:anywhere;font-variant-numeric:tabular-nums;margin-bottom:9px}
.sales-workspace .report-badge{display:inline-flex;align-items:center;gap:5px;border-radius:7px;padding:4px 8px;font-size:11px;line-height:1.3;font-weight:600;white-space:nowrap}
.sales-workspace .tone-teal{background:#ecfdf5;color:#047857}
.sales-workspace .tone-blue{background:#eff6ff;color:#1d4ed8}
.sales-workspace .tone-red{background:#fff1f2;color:#be123c}
.sales-workspace .tone-purple{background:#f5f3ff;color:#6d28d9}
.sales-workspace .tone-gray{background:#f1f5f9;color:#64748b}
.sales-workspace .report-strip{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:18px 0;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#ffffffa6}
.sales-workspace .report-strip-badges{display:flex;flex-wrap:wrap;gap:6px}
.sales-workspace .report-table-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:14px;background:white}
.sales-workspace .report-table{width:100%;text-align:left;border-collapse:collapse;font-size:12px}
.sales-workspace .report-table th{background:#f1f7f7;color:#526577;font-weight:700;font-size:10px;letter-spacing:.05em;text-transform:uppercase;padding:13px 14px;white-space:nowrap}
.sales-workspace .report-table td{padding:16px 14px;border-top:1px solid #f1f5f9;vertical-align:middle}
.sales-workspace .report-table tbody tr:hover{background:#f8fdfc}
.sales-workspace .report-number{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.sales-workspace .report-strong{color:#15233f;font-weight:700}
.sales-workspace .report-identity{display:flex;align-items:center;gap:10px;min-width:150px}
.sales-workspace .report-avatar{width:38px;height:38px;border-radius:11px;background:#f0fdfa;border:1px solid #ccfbf1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;overflow:hidden}
.sales-workspace .report-avatar img{width:100%;height:100%;object-fit:cover}
.sales-workspace .report-avatar.large{width:58px;height:58px;border-radius:16px;font-size:22px}
.sales-workspace .report-action{display:inline-flex;align-items:center;gap:6px;border:1px solid #ccfbf1;border-radius:9px;padding:7px 10px;color:#0f766e;background:#f0fdfa;font-weight:600;white-space:nowrap}
.sales-workspace .report-action:hover{background:#ccfbf1}
.sales-workspace a:focus-visible,.sales-workspace button:focus-visible,.sales-workspace summary:focus-visible{outline:3px solid #14b8a6;outline-offset:3px}
.sales-workspace .report-trend{font-size:12px;font-weight:700;white-space:nowrap}
.sales-workspace .report-trend.up{color:#047857}
.sales-workspace .report-trend.down{color:#be123c}
.sales-workspace .report-export{position:relative}
.sales-workspace .report-export summary{display:flex;gap:8px;align-items:center;list-style:none;cursor:pointer;background:#0f766e;color:white;border-radius:11px;padding:11px 16px;font-weight:600;font-size:13px;box-shadow:0 3px 8px #0f766e15}
.sales-workspace .report-export summary::-webkit-details-marker{display:none}
.sales-workspace .report-export summary:hover{background:#115e59}
.sales-workspace .report-export-menu{position:absolute;right:0;top:calc(100% + 8px);z-index:20;background:white;border:1px solid #e2e8f0;border-radius:13px;padding:6px;width:190px;box-shadow:0 10px 28px #15233f15}
.sales-workspace .report-export-menu a{display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;font-size:13px;color:#334155}
.sales-workspace .report-export-menu a:hover{background:#f0fdfa;color:#0f766e}
.sales-workspace .report-sections{display:grid;gap:24px;margin-top:24px}
.sales-workspace .report-two-columns{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:24px}
.sales-workspace .report-progress{height:5px;border-radius:99px;background:#eaf2f2;overflow:hidden;margin-top:8px}
.sales-workspace .report-progress>span{display:block;height:100%;background:#14b8a6;border-radius:99px}
.sales-workspace .report-status-row{margin-top:18px}
.sales-workspace .report-status-heading{display:flex;justify-content:space-between;align-items:center;gap:8px}
.sales-workspace .report-performance-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
.sales-workspace .report-performance-grid>div{padding:14px;border-radius:12px;background:#f8fafc}
.sales-workspace .report-empty{padding:30px 15px;text-align:center;color:#64748b}
.sales-workspace .report-inventory{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:16px}
.sales-workspace .report-inventory>div{padding:16px;border-radius:12px;background:#f8fafc}
.sales-workspace .report-top-rank{color:#0f766e;font-size:11px;background:#f0fdfa;border-radius:5px;padding:2px 5px}
.sales-workspace .report-source{margin-top:12px;font-size:11px;color:#64748b}
.sales-workspace .report-shop-filters{grid-template-columns:1fr 1fr 2fr auto}
.sales-workspace svg{flex-shrink:0}

@media(max-width:1200px){.sales-workspace .report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
.sales-workspace .report-two-columns{grid-template-columns:1fr}
.sales-workspace .report-shop-filters{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:640px){.sales-workspace h1{font-size:24px}
.sales-workspace .report-card{padding:16px}
.sales-workspace .report-filter-grid{grid-template-columns:1fr}
.sales-workspace .report-search{grid-column:auto}
.sales-workspace .report-metrics{grid-template-columns:1fr}
.sales-workspace .report-performance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.sales-workspace .report-header{align-items:flex-start}
.sales-workspace .report-inventory{grid-template-columns:1fr}
.sales-workspace .report-value{font-size:28px}
.sales-workspace .report-strip{align-items:flex-start}
.sales-workspace .report-custom{grid-column:auto}
.sales-workspace .report-table td{padding:13px 12px}
}

.sales-workspace [data-sales-period]{width:100%}
.sales-workspace .report-strip{margin:0}
.sales-workspace .report-table th.report-number{text-align:right}

.sales-workspace .report-pagination{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.sales-workspace .report-page-links{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.sales-workspace .report-page-links a,.sales-workspace .report-page-links span{padding:7px 10px;border-radius:8px;font-size:12px}.sales-workspace .report-page-links a{background:white;border:1px solid #e2e8f0;color:#475569}.sales-workspace .report-page-links a:hover{border-color:#99f6e4;color:#0f766e}.sales-workspace .report-page-links .current{background:#0f766e;color:white}.sales-workspace .report-page-links [aria-disabled]{color:#94a3b8}
</style>
