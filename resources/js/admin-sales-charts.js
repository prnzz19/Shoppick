// Loaded only on report dashboards; no global chart dependency on buyer/seller pages.
const instances = new Map();
let library;
const peso = value => new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(value);
const number = value => new Intl.NumberFormat('en-PH', {maximumFractionDigits:0}).format(value);
const shortPeso = value => '\u20b1' + new Intl.NumberFormat('en-PH',{notation:'compact',maximumFractionDigits:1}).format(value);
const percent = value => (value > 0 ? '+' : '') + value.toFixed(1) + '%';
const statusColors = {'Pending':'#94a3b8','Confirmed':'#94a9c4','Processing':'#6686a3','Packed':'#8a9eaf','Ready To Ship':'#334e68','Shipped':'#438e9b','Delivered':'#99c5bb','Completed':'#0d9488','Cancelled':'#bb8563'};

export function destroySalesCharts(root) {
    for (const [canvas, chart] of instances) {
        if (!canvas.isConnected || root.contains(canvas)) { chart.destroy(); instances.delete(canvas); }
    }
}

export async function setupSalesCharts(root = document) {
    const dashboards = [...root.querySelectorAll('[data-sales-chart-dashboard]')];
    if (!dashboards.length) return;
    const {default: Chart} = await (library ??= import('chart.js/auto'));
    for (const panel of dashboards) {
        if (!panel.isConnected || panel.dataset.enhanced) continue;
        panel.dataset.enhanced = 'true';
        const data = JSON.parse(panel.querySelector('[data-sales-chart-data]').textContent);
        const dashboard = panel.hasAttribute('data-dashboard-overview');
        const axisLabels = data.trend.map(p=>dashboard ? (p.axisLabel || p.label) : p.label);
        const base = {responsive:true,maintainAspectRatio:false,animation:false,
            plugins:{legend:{display:false},tooltip:{backgroundColor:'#15233f',padding:12,cornerRadius:8}},
            scales:{x:{grid:{display:false},ticks:{color:'#64748b',maxTicksLimit:9,maxRotation:0}},
                y:{beginAtZero:true,...(dashboard && !data.trend.some(p=>p.current || p.previous) ? {suggestedMax:1000} : {}),grid:{color:'#edf2f7'},ticks:{maxTicksLimit:5,color:'#64748b',callback:shortPeso}}}};
        function mount(kind, config) {
            const canvas = panel.querySelector('[data-report-chart="'+kind+'"]');
            if (!canvas) return;
            instances.get(canvas)?.destroy();
            if (dashboard) {
                config.plugins = [...(config.plugins || []), {id:'emptyOverlayPosition',afterLayout(chart){
                    const overlay=canvas.parentElement.querySelector('.overview-chart-empty');
                    if(!overlay)return;
                    const a=chart.chartArea;
                    overlay.style.left=((a.left+a.right)/2)+'px';
                    overlay.style.top=((a.top+a.bottom)/2)+'px';
                    overlay.style.maxWidth=Math.max(100,a.right-a.left-12)+'px';
                }}];
            }
            const chart = new Chart(canvas,config);
            instances.set(canvas,chart);
            canvas.dataset.rendered = 'true';
        }
        const datasets = [{type:'bar',label:'Current Sales',data:data.trend.map(p=>p.current),backgroundColor:'#0d9488',borderRadius:4,maxBarThickness:34,order:2}];
        if (data.previous) {
            datasets.unshift({type:'bar',label:'Previous Sales',data:data.trend.map(p=>p.previous),backgroundColor:'#94a9c4',borderRadius:4,maxBarThickness:34,order:2});
            datasets.push({type:'line',label:'Growth',data:data.trend.map(p=>p.growth),borderColor:'#d97706',backgroundColor:'#d97706',borderWidth:2,pointRadius:3,pointHoverRadius:5,yAxisID:'growth',spanGaps:false,order:0});
        }
        mount('trend',{type:'bar',data:{labels:data.trend.map(p=>p.label),datasets},options:{...base,
            interaction:{mode:'index',intersect:false},
            scales:{...base.scales,...(data.previous ? {growth:{position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Growth %'},ticks:{callback:v=>v+'%',color:'#a16207'}}}:{})},
            plugins:{...base.plugins,tooltip:{...base.plugins.tooltip,callbacks:{
                title:items=>{const p=data.trend[items[0].dataIndex];return [p.label,...(p.previousLabel?['Previous: '+p.previousLabel]:[])];},
                label:ctx=>ctx.dataset.label+': '+(ctx.dataset.yAxisID==='growth'?percent(ctx.parsed.y):peso(ctx.parsed.y)),
                footer:items=>data.previous && data.trend[items[0].dataIndex].growth===null?'Growth: not comparable (zero prior sales or unmatched interval)':''
            }}}}});
        mount('period',{type:'bar',data:{labels:axisLabels,datasets:[{label:'Completed Sales',data:data.trend.map(p=>p.current),backgroundColor:'#0d9488',borderRadius:4,maxBarThickness:38}]},options:{...base,plugins:{...base.plugins,tooltip:{...base.plugins.tooltip,callbacks:{title:items=>data.trend[items[0].dataIndex].label,label:ctx=>peso(ctx.parsed.y),footer:items=>number(data.trend[items[0].dataIndex].currentOrders ?? 0)+' completed orders'}}}}});
        const comparison = [{label:'Current Sales',data:data.trend.map(p=>p.current),borderColor:'#0d9488',backgroundColor:'#0d9488',borderWidth:2,pointRadius:3,pointHoverRadius:5,spanGaps:false}];
        if(data.previous)comparison.push({label:'Previous Sales',data:data.trend.map(p=>p.previous),borderColor:'#94a9c4',backgroundColor:'#94a9c4',borderWidth:2,pointRadius:2,pointHoverRadius:4,spanGaps:false});
        mount('comparison',{type:'line',data:{labels:axisLabels,datasets:comparison},options:{...base,interaction:{mode:'index',intersect:false},plugins:{...base.plugins,tooltip:{...base.plugins.tooltip,callbacks:{title:items=>{const p=data.trend[items[0].dataIndex];return [p.label,...(p.previousLabel?['Previous: '+p.previousLabel]:[])];},label:ctx=>ctx.dataset.label+': '+peso(ctx.parsed.y)}}}}});
        const total = data.statuses.reduce((sum,row)=>sum+row.value,0);
        mount('statuses',{type:'doughnut',data:{labels:data.statuses.map(r=>r.label),datasets:[{data:data.statuses.map(r=>r.value),backgroundColor:data.statuses.map(r=>statusColors[r.label] || '#64748b'),borderWidth:3,borderColor:'#fff',hoverOffset:3}]},options:{responsive:true,maintainAspectRatio:false,animation:false,cutout:'73%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>ctx.label+': '+number(ctx.raw)+' ('+(ctx.raw/total*100).toFixed(1)+'%)'}}}},plugins:[{id:'reportCenter',afterDraw(chart){const {ctx,chartArea:a}=chart;if(!a)return;ctx.save();ctx.textAlign='center';ctx.fillStyle='#15233f';ctx.font='700 26px sans-serif';ctx.fillText(number(total),(a.left+a.right)/2,(a.top+a.bottom)/2);ctx.fillStyle='#64748b';ctx.font='12px sans-serif';ctx.fillText('seller orders',(a.left+a.right)/2,(a.top+a.bottom)/2+22);ctx.restore();}}]});
        function ranking(kind,rows,units=false) {
            const labels = {id:'categoryValues',afterDatasetsDraw(chart){
                if(kind!=='categories')return;
                const {ctx,chartArea}=chart;ctx.save();ctx.font='600 11px sans-serif';ctx.textBaseline='middle';
                chart.getDatasetMeta(0).data.forEach((bar,i)=>{
                    if(!rows[i]?.value)return;
                    const label=shortPeso(rows[i].value),width=ctx.measureText(label).width;
                    const inside=bar.x+width+9>chartArea.right;
                    ctx.fillStyle=inside?'#fff':'#0f766e';ctx.textAlign=inside?'right':'left';
                    ctx.fillText(label,bar.x+(inside?-8:8),bar.y);
                });ctx.restore();
            }};
            mount(kind,{type:'bar',plugins:[labels],data:{labels:rows.map(r=>r.label),datasets:[{data:rows.map(r=>r.value),backgroundColor:['shops','categories'].includes(kind)?'#0d9488':'#6686a3',borderRadius:5,maxBarThickness:25}]},options:{...base,indexAxis:'y',scales:{x:{...base.scales.y,suggestedMax:dashboard && !rows.some(r=>r.value) ? 1000 : undefined,ticks:{maxTicksLimit:5,callback:units?number:shortPeso}},y:{grid:{display:false},ticks:{color:'#475569',callback:function(value){const s=this.getLabelForValue(value);return s.length>24?s.slice(0,22)+'...':s;}}}},plugins:{...base.plugins,tooltip:{...base.plugins.tooltip,callbacks:{label:ctx=>units?number(ctx.parsed.x)+' units':peso(ctx.parsed.x)}}}}});
        }
        if (data.categories) ranking('categories',data.categories.length ? data.categories : (data.categoryLabels || []).map(label=>({label,value:0})));
        if (data.topShops) ranking('shops',data.topShops);
        ranking('products',data.products.sales);
        panel.querySelector('[data-product-ranking]')?.addEventListener('change',event=>{
            const units=event.target.value==='units'; const rows=data.products[units?'units':'sales'];
            ranking('products',rows,units);
            const list=panel.querySelector('[data-product-values]');
            if (list) list.replaceChildren(...rows.map(row=>{const li=document.createElement('li'),name=document.createElement('span'),value=document.createElement('strong');name.textContent=row.label;value.textContent=units?number(row.value)+' units':peso(row.value);li.append(name,value);return li;}));
        });
    }
}

document.addEventListener('DOMContentLoaded',()=>{void setupSalesCharts();});
