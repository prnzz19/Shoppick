document.addEventListener('DOMContentLoaded', async () => {
    const root=document.querySelector('[data-seller-dashboard]');if(!root)return;
    root.querySelector('[data-seller-range]')?.addEventListener('change',event=>event.target.form.requestSubmit());
    const data=JSON.parse(root.querySelector('[data-seller-chart-data]').textContent);
    const {default:Chart}=await import('chart.js/auto');
    const money=value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value);
    const compact=value=>'\u20b1'+new Intl.NumberFormat('en-PH',{notation:'compact',maximumFractionDigits:1}).format(value);
    const series=[{label:'Current net sales',data:data.trend.map(p=>p.current),borderColor:'#0d9488',backgroundColor:'#0d9488',borderWidth:2,pointRadius:2,pointHoverRadius:5,tension:.2}];
    if(data.previous)series.push({label:'Previous net sales',data:data.trend.map(p=>p.previous),borderColor:'#94a9c4',backgroundColor:'#94a9c4',borderWidth:2,pointRadius:1,pointHoverRadius:4,tension:.2});
    const sales=root.querySelector('[data-seller-chart="sales"]');
    new Chart(sales, {
        type: 'line',
        data: {labels: data.trend.map(p=>p.axisLabel||p.label), datasets: series},
        options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            interaction: {mode:'index', intersect:false},
            plugins: {
                legend: {display:false},
                tooltip: {
                    backgroundColor:'#15233f', padding:10,
                    callbacks: {
                        title: items=>data.trend[items[0].dataIndex].label,
                        label: ctx=>ctx.dataset.label+': '+money(ctx.parsed.y),
                        afterBody: items=>{
                            const p=data.trend[items[0].dataIndex];
                            return ['Completed orders: '+(p.currentOrders??0),
                                ...(p.previousLabel?['Previous period: '+p.previousLabel]:[]),
                                ...(p.growth!==null?['Change: '+p.growth.toFixed(1)+'%']:[])];
                        }
                    }
                }
            },
            scales: {
                x: {grid:{display:false}, ticks:{color:'#64748b',font:{size:10},maxRotation:0,maxTicksLimit:8}},
                y: {beginAtZero:true, ...(!data.trend.some(p=>p.current||p.previous)?{suggestedMax:1000}:{}),
                    grid:{color:'#eef2f6'},ticks:{color:'#64748b',font:{size:10},maxTicksLimit:5,callback:compact}}
            }
        }
    });
    sales.dataset.rendered='true';
    const colors={pending:'#f59e0b',confirmed:'#9cb8cc',processing:'#6686a3',packed:'#59788b',ready_to_ship:'#386e78',shipped:'#24a6b5',delivered:'#79b7ad',completed:'#0d9488',cancelled:'#c87950'};
    const total=data.statuses.reduce((sum,row)=>sum+row.value,0),status=root.querySelector('[data-seller-chart="status"]');
    new Chart(status,{type:'doughnut',data:{labels:total?data.statuses.map(r=>r.label):['No orders'],datasets:[{data:total?data.statuses.map(r=>r.value):[1],backgroundColor:total?data.statuses.map(r=>colors[r.status]||'#94a3b8'):['#edf1f5'],borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,animation:false,cutout:'76%',events:total?['mousemove','mouseout','click','touchstart','touchmove']:[],plugins:{legend:{display:false},tooltip:{enabled:total>0,callbacks:{label:ctx=>ctx.label+': '+ctx.raw+' orders'}}}},plugins:[{id:'sellerTotal',afterDraw(chart){const {ctx,chartArea:a}=chart;ctx.save();ctx.textAlign='center';ctx.fillStyle='#15233f';ctx.font='700 24px sans-serif';ctx.fillText(String(total),(a.left+a.right)/2,(a.top+a.bottom)/2);ctx.font='10px sans-serif';ctx.fillStyle='#64748b';ctx.fillText(total?'orders':'No orders yet',(a.left+a.right)/2,(a.top+a.bottom)/2+18);ctx.restore();}}]});status.dataset.rendered='true';
    root.querySelectorAll('[data-status]').forEach(el=>el.style.setProperty('--status-color',colors[el.dataset.status]||'#94a3b8'));
});
