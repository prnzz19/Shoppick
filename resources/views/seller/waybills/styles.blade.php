<style>
.seller-waybills{--wb-teal:#078f84;--wb-navy:#1d2e44;--wb-muted:#7c8999;--wb-border:#e4eaf0;color:var(--wb-navy);max-width:1440px;margin-inline:auto}
.waybills-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:20px}
.waybills-eyebrow{color:var(--wb-teal);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.waybills-header h1{margin:4px 0 5px;font-size:27px;font-weight:800;letter-spacing:-.6px}
.waybills-header p{color:var(--wb-muted);font-size:12px}
.waybills-card{min-width:0;border:1px solid var(--wb-border);border-radius:14px;background:#fff;padding:18px;box-shadow:0 3px 12px rgba(24,44,65,.045)}
.waybills-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.waybills-metric{padding:15px 17px}
.waybills-metric span{display:block;color:var(--wb-muted);font-size:11px;font-weight:600}
.waybills-metric strong{display:block;margin-top:8px;font-size:24px;font-weight:800;letter-spacing:-.6px}
.waybills-filter{display:flex;align-items:end;flex-wrap:wrap;gap:10px;margin-bottom:15px}
.waybills-filter label{display:grid;gap:5px;min-width:135px;color:#68778a;font-size:10px;font-weight:700}
.waybills-filter label:first-child{flex:1 1 230px}
.waybills-filter input,.waybills-filter select{height:36px;min-height:36px;border:1px solid #e1e7ed;border-radius:8px;background:#fff;padding:0 10px;color:var(--wb-navy);font-size:11px}
.waybills-filter button{height:36px;min-height:36px;padding:0 14px;border-radius:8px;background:var(--wb-teal);font-size:11px;font-weight:700}
.waybills-table-wrap{overflow-x:auto;border:1px solid #edf0f3;border-radius:9px}
.waybills-table{width:100%;min-width:850px;border-collapse:collapse;text-align:left;font-size:11px}
.waybills-table th{padding:10px 11px;border-bottom:1px solid #e9edf1;background:#f7f9fb;color:#738195;font-size:9px;font-weight:750;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
.waybills-table td{padding:11px;border-bottom:1px solid #eff2f5;color:#526276;vertical-align:middle}
.waybills-table tbody tr:last-child td{border-bottom:0}
.waybills-table tbody tr:hover{background:#f7fbfa}
.waybill-order-link{color:var(--wb-teal);font-weight:750}
.waybill-ref{color:var(--wb-navy);font-weight:750;white-space:nowrap}
.waybill-subtext{display:block;margin-top:3px;color:#8995a3;font-size:9px}
.waybill-pill{display:inline-flex;align-items:center;min-height:22px;padding:3px 8px;border:1px solid #e8edf1;border-radius:999px;background:#f7f9fa;color:#667588;font-size:9px;font-weight:700;white-space:nowrap}
.waybill-pill-ready{border-color:#d8efeb;background:#eff9f7;color:#087c73}
.waybill-pill-generated{border-color:#d8efeb;background:#eff9f7;color:#087c73}
.waybill-pill-pending{border-color:#f3e7d8;background:#fff8ef;color:#aa6b20}
.waybill-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}
.waybill-action{display:inline-flex;align-items:center;justify-content:center;min-height:29px;padding:0 8px;border:1px solid #dfe7ec;border-radius:7px;background:#fff;color:#516176;font-size:9px;font-weight:750;text-decoration:none}
.waybill-action:hover{border-color:#b7dcd6;background:#f5fbfa;color:var(--wb-teal)}
.waybill-action-primary{border-color:var(--wb-teal);background:var(--wb-teal);color:#fff}
.waybill-action-primary:hover{border-color:#06776e;background:#06776e;color:#fff}
.waybill-empty{text-align:center;padding:44px 18px}
.waybill-empty h2{font-size:16px;font-weight:750}
.waybill-empty p{margin:7px 0 15px;color:var(--wb-muted);font-size:12px}
.waybill-alert{margin-bottom:13px;border-radius:8px;padding:10px 12px;font-size:12px}
.waybill-alert-success{background:#eff9f5;color:#18704f}.waybill-alert-error{background:#fff1f0;color:#a33631}
.waybill-pagination{margin-top:15px}
.waybill-document{max-width:900px;margin:0 auto;border:1px solid var(--wb-border);border-radius:14px;background:#fff;padding:30px;box-shadow:0 4px 16px rgba(24,44,65,.06);color:#1e2d42}
.waybill-doc-head{display:flex;justify-content:space-between;gap:20px;padding-bottom:20px;border-bottom:2px solid #138f84}
.waybill-brand{font-size:16px;font-weight:900;letter-spacing:.06em;color:#078f84}
.waybill-doc-title{margin-top:5px;font-size:22px;font-weight:800;letter-spacing:-.4px}
.waybill-doc-info{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:8px;min-width:0}
.waybill-doc-number{text-align:right}.waybill-doc-number span,.waybill-label{display:block;color:#7d8998;font-size:9px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}
.waybill-doc-number strong{display:block;margin-top:5px;font-size:16px;font-weight:800}
.waybill-doc-note{margin-top:4px;color:#8793a1;font-size:9px}
.waybill-barcode{display:flex;flex-direction:column;align-items:center;gap:4px;margin:13px 0;padding:8px;border:1px solid #e7ecef;border-radius:8px;background:#fff}
.waybill-doc-info .waybill-barcode{margin:0;padding:4px}
.waybill-barcode svg{display:block;width:min(100%,390px);height:auto;max-height:58px}
.waybill-barcode strong{font-size:12px;letter-spacing:.08em}
.waybill-parties{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:18px 0}
.waybill-party{border:1px solid #e7ecef;border-radius:10px;padding:14px}
.waybill-party h2,.waybill-section-title{margin:0 0 8px;color:#728094;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.waybill-party strong{display:block;font-size:13px;font-weight:750}
.waybill-party p{margin-top:5px;color:#536276;font-size:11px;line-height:1.55;overflow-wrap:anywhere}
.waybill-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}
.waybill-fact{padding:11px;border:1px solid #edf0f3;border-radius:9px}
.waybill-fact strong{display:block;margin-top:5px;font-size:11px}
.waybill-items{width:100%;border-collapse:collapse;font-size:10px}
.waybill-items th,.waybill-items td{padding:9px 8px;border-bottom:1px solid #e9edf1;text-align:left;vertical-align:top}
.waybill-items th{background:#f7f9fb;color:#718094;font-size:9px;letter-spacing:.05em;text-transform:uppercase}
.waybill-items td:last-child,.waybill-items th:last-child{text-align:right}
.waybill-payment{display:flex;justify-content:space-between;gap:15px;margin-top:16px;padding:13px;border:1px solid #e7ecef;border-radius:9px;background:#fbfcfd}
.waybill-payment strong{font-size:11px}
.waybill-payment p{margin-top:4px;color:#7c8998;font-size:10px}
.waybill-actions-bar{display:flex;justify-content:flex-end;gap:8px;margin:0 auto 14px;max-width:900px}
.waybill-back{margin-right:auto}
.waybill-document .waybill-no-items{padding:16px;text-align:center;color:#7c8998}
.waybill-pdf .waybill-document{max-width:none;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}
.waybill-pdf .waybill-doc-head{gap:8px;padding-bottom:5px;border-bottom-width:1px}
.waybill-pdf .waybill-doc-info{display:table;width:100%;table-layout:fixed}
.waybill-pdf .waybill-doc-number,.waybill-pdf .waybill-doc-info .waybill-barcode{display:table-cell;width:50%;vertical-align:middle}
.waybill-pdf .waybill-brand{margin:0;font-size:13px}
.waybill-pdf .waybill-doc-title{margin:1px 0 0;font-size:14px}
.waybill-pdf .waybill-doc-number span,.waybill-pdf .waybill-label{font-size:7px}
.waybill-pdf .waybill-doc-number strong{margin-top:1px;font-size:10px}
.waybill-pdf .waybill-doc-note{margin-top:1px;font-size:7px}
.waybill-pdf .waybill-barcode{gap:0;margin:0;padding:1px;border-radius:3px}
.waybill-pdf .waybill-barcode svg{width:90%;height:auto;max-height:22px}
.waybill-pdf .waybill-barcode strong{font-size:7px}
.waybill-pdf .waybill-parties{display:table;width:100%;table-layout:fixed;margin:2px 0}
.waybill-pdf .waybill-party{display:table-cell;width:50%;vertical-align:top;padding:3px;border-radius:4px}
.waybill-pdf .waybill-party h2,.waybill-pdf .waybill-section-title{margin-bottom:1px;font-size:7px}
.waybill-pdf .waybill-party strong{font-size:9px}
.waybill-pdf .waybill-party p{margin:0;font-size:8px;line-height:1.05;overflow-wrap:anywhere}
.waybill-pdf .waybill-facts{display:table;width:100%;table-layout:fixed;margin-bottom:1px}
.waybill-pdf .waybill-fact{display:table-cell;vertical-align:top}
.waybill-pdf .waybill-fact{padding:2px;border-radius:3px}
.waybill-pdf .waybill-fact strong{margin-top:1px;font-size:7px;line-height:1.05;overflow-wrap:anywhere}
.waybill-pdf .waybill-section-title{display:none}
.waybill-pdf .waybill-items{font-size:8px;line-height:1.1}
.waybill-pdf .waybill-items th,.waybill-pdf .waybill-items td{padding:2px 3px}
.waybill-pdf .waybill-items th{font-size:7px}
.waybill-pdf .waybill-payment{display:table;width:100%;table-layout:fixed;margin-top:2px;padding:2px;border-radius:3px}
.waybill-pdf .waybill-payment>div{display:table-cell;width:33%;vertical-align:top}
.waybill-pdf .waybill-payment strong{font-size:8px;line-height:1.05;overflow-wrap:anywhere}
.waybill-pdf .waybill-payment p{margin:0;font-size:7px;line-height:1}
@media(max-width:900px){.waybills-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.waybills-header{flex-wrap:wrap}.waybill-document{padding:22px}}
@media(max-width:640px){.waybills-metrics{gap:9px}.waybills-metric{padding:12px}.waybills-metric strong{font-size:21px}.waybills-card{padding:13px}.waybills-filter{gap:8px}.waybills-filter label{flex:1 1 135px}.waybill-document{padding:16px;border-radius:11px}.waybill-doc-head{flex-direction:column;gap:12px}.waybill-doc-number{text-align:left}.waybill-parties{grid-template-columns:1fr}.waybill-facts{grid-template-columns:repeat(2,minmax(0,1fr))}.waybill-actions-bar{flex-wrap:wrap;justify-content:flex-start}.waybill-back{flex-basis:100%}}
@page{size:A4 landscape;margin:8mm}
@media print{
    html,body{width:auto!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}
    body{print-color-adjust:exact;-webkit-print-color-adjust:exact}
    .seller-waybills,.seller-waybills *{box-sizing:border-box}
    .seller-waybills{width:100%!important;max-width:none!important;margin:0!important}
    .seller-waybills .waybill-actions-bar{display:none!important}
    .waybill-document{width:100%;max-width:none;margin:0;padding:6mm;border:1px solid var(--wb-border);border-radius:0;box-shadow:none}
    .waybill-doc-head{align-items:center;gap:8mm;padding-bottom:4mm;border-bottom-width:2px}
    .waybill-brand{font-size:20px}
    .waybill-doc-title{margin-top:3px;font-size:28px}
    .waybill-doc-info{flex:0 1 155mm;grid-template-columns:minmax(42mm,auto) minmax(0,1fr);gap:5mm}
    .waybill-doc-number span,.waybill-label{font-size:9px}
    .waybill-doc-number strong{margin-top:3px;font-size:16px}
    .waybill-doc-note{margin-top:3px;font-size:9px}
    .waybill-barcode{gap:2px;margin:0;padding:3mm;border-color:#cfd6dc;border-radius:5px}
    .waybill-barcode svg{width:min(100%,520px);height:auto;max-height:20mm}
    .waybill-barcode strong{font-size:11px}
    .waybill-parties{gap:4mm;margin:4mm 0}
    .waybill-party{padding:3mm;border-radius:5px}
    .waybill-party h2,.waybill-section-title{margin-bottom:3mm;font-size:10px}
    .waybill-party strong{font-size:14px}
    .waybill-party p{margin-top:2mm;font-size:11px;line-height:1.3}
    .waybill-facts{grid-template-columns:repeat(3,minmax(0,1fr));gap:2mm;margin-bottom:3mm}
    .waybill-fact{padding:2mm;border-radius:4px}
    .waybill-fact strong{margin-top:1mm;font-size:11px;line-height:1.25}
    .waybill-section-title{display:block}
    .waybill-items{font-size:11px;line-height:1.25}
    .waybill-items th,.waybill-items td{padding:2mm}
    .waybill-items th{font-size:9px}
    .waybill-payment{gap:6mm;margin-top:3mm;padding:3mm;border-radius:5px}
    .waybill-payment strong{font-size:12px;line-height:1.25}
    .waybill-payment p{margin-top:2mm;font-size:10px;line-height:1.25}
    .waybill-parties,.waybill-barcode,.waybill-fact,.waybill-payment,.waybill-items tr{break-inside:avoid;page-break-inside:avoid}
}
</style>
