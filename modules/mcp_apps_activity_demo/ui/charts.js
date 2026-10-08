import Chart from 'chart.js/auto';

// Normalize theme CSS colours (including oklch) for Chart.js colour helpers.
function rgba(value, alpha = 1) {
  const context = document.createElement('canvas').getContext('2d');
  context.fillStyle = value;
  context.fillRect(0, 0, 1, 1);
  const [r, g, b] = context.getImageData(0, 0, 1, 1).data;
  return `rgba(${r},${g},${b},${alpha})`;
}
function attach(context = document) {
  context.querySelectorAll('[data-pulse-chart], [data-pulse-spark]').forEach(canvas => {
    if (Chart.getChart(canvas)) return;
    const style = getComputedStyle(canvas);
    const primary = style.getPropertyValue('--pulse-primary').trim() || '#087d67';
    const muted = style.getPropertyValue('--pulse-muted').trim() || '#737e83';
    const line = style.getPropertyValue('--pulse-line').trim() || '#edf0ef';
    const card = style.getPropertyValue('--pulse-card').trim() || '#fff';
    const text = style.color;
    const font = {family:style.fontFamily,size:11};
    if (canvas.hasAttribute('data-pulse-spark')) {
      new Chart(canvas, {type:'line',data:{labels:JSON.parse(canvas.dataset.pulseSpark).map((_, i)=>i),datasets:[{data:JSON.parse(canvas.dataset.pulseSpark),borderColor:rgba(primary),borderWidth:2,pointRadius:0,tension:.4}]},options:{animation:false,responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}}}});
      return;
    }
    const values = JSON.parse(canvas.dataset.pulseChart);
    const chart = new Chart(canvas, {
      type:'line',
      data:{labels:values.labels,datasets:[
        {label:'Selected period',data:values.current,borderColor:rgba(primary),backgroundColor:rgba(primary,.12),borderWidth:2.5,fill:true,pointRadius:0,pointHoverRadius:5,tension:.35},
        {label:'Previous period',data:values.previous,borderColor:rgba(muted),backgroundColor:rgba(muted,.12),borderWidth:2,borderDash:[5,5],pointRadius:0,pointHoverRadius:4,tension:.35}
      ]},
      options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:rgba(card),titleColor:rgba(text),bodyColor:rgba(text),borderColor:rgba(line),borderWidth:1,padding:12,cornerRadius:8}},scales:{x:{grid:{display:false},border:{display:false},ticks:{maxTicksLimit:7,color:rgba(muted),font}},y:{beginAtZero:true,grid:{color:rgba(line)},border:{display:false},ticks:{maxTicksLimit:5,color:rgba(muted),font}}}}
    });
    canvas.closest('.pulse-readership').querySelector('[data-pulse-style]').addEventListener('change',event=>{
      const bars = event.target.value === 'bar';
      chart.config.type = event.target.value;
      chart.data.datasets[0].fill = !bars;
      chart.data.datasets[0].backgroundColor = rgba(primary,bars?1:.12);
      chart.data.datasets[1].backgroundColor = rgba(muted,bars?.4:.12);
      chart.update();
    });
  });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',()=>attach()); else attach();
if (window.Drupal) Drupal.behaviors.mcpAppsEditorialCharts = {attach};
