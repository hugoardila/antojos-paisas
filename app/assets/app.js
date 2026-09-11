const fmt=new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0});
let cart=[];

function selectedAddons(){
  return Array.from(document.querySelectorAll('.addon-choice.active')).map(function(btn){
    return {id:Number(btn.dataset.id),name:btn.dataset.name,price:Number(btn.dataset.price)};
  });
}

function addonKey(addons){
  return addons.map(function(addon){return addon.id;}).sort(function(a,b){return a-b;}).join(',');
}

function clearSelectedAddons(){
  document.querySelectorAll('.addon-choice.active').forEach(function(btn){btn.classList.remove('active');});
}

function escapeHtml(value){
  return String(value).replace(/[&<>"']/g,function(ch){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
  });
}

function renderCart(){
  const body=document.getElementById('cartBody');
  const totalEl=document.getElementById('cartTotal');
  const json=document.getElementById('itemsJson');
  if(!body)return;
  body.innerHTML='';
  let subtotal=0;
  if(cart.length===0){
    body.innerHTML='<tr><td colspan="4" class="text-muted text-center">Agrega productos</td></tr>';
  }
  cart.forEach(function(item,i){
    const addonsTotal=item.addons.reduce(function(sum,addon){return sum+addon.price;},0);
    const line=item.qty*(item.price+addonsTotal);
    subtotal+=line;
    const addonsText=item.addons.length ? '<small class="cart-addons">+ '+item.addons.map(function(a){return escapeHtml(a.name);}).join(', ')+'</small>' : '';
    body.insertAdjacentHTML('beforeend','<tr><td><strong>'+escapeHtml(item.name)+'</strong>'+addonsText+'</td><td><button type="button" class="qty-btn" onclick="decItem('+i+')">-</button> <strong>'+item.qty+'</strong> <button type="button" class="qty-btn" onclick="incItem('+i+')">+</button></td><td class="text-end">'+fmt.format(line)+'</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem('+i+')">x</button></td></tr>');
  });
  const discount=Number((document.getElementById('discount')||{}).value||0);
  if(totalEl)totalEl.textContent=fmt.format(Math.max(0,subtotal-discount));
  if(json)json.value=JSON.stringify(cart.map(function(x){return{id:x.id,qty:x.qty,addons:x.addons.map(function(a){return a.id;})};}));
}

function addItem(btn){
  const id=Number(btn.dataset.id);
  const addons=selectedAddons();
  const key=addonKey(addons);
  const found=cart.find(function(x){return x.id===id&&x.addonKey===key;});
  if(found)found.qty++;
  else cart.push({id:id,name:btn.dataset.name,price:Number(btn.dataset.price),qty:1,addons:addons,addonKey:key});
  clearSelectedAddons();
  renderCart();
}

function incItem(i){cart[i].qty++;renderCart();}
function decItem(i){cart[i].qty--;if(cart[i].qty<=0)cart.splice(i,1);renderCart();}
function removeItem(i){cart.splice(i,1);renderCart();}

document.querySelectorAll('.product-btn').forEach(function(btn){btn.addEventListener('click',function(){addItem(btn);});});
document.querySelectorAll('.addon-choice').forEach(function(btn){btn.addEventListener('click',function(){btn.classList.toggle('active');});});
const discount=document.getElementById('discount');
if(discount)discount.addEventListener('input',renderCart);
const form=document.getElementById('orderForm');
if(form)form.addEventListener('submit',function(e){renderCart();if(cart.length===0){e.preventDefault();alert('Agrega productos al pedido.');}});
function syncOrderDestination(){
  const selectedType=document.querySelector('input[name="order_type"]:checked');
  const tableField=document.getElementById('tableNameField');
  const tableSelect=document.getElementById('tableNameSelect');
  const destinationHelp=document.getElementById('orderDestinationHelp');
  if(!selectedType||!tableField||!tableSelect)return;
  const orderType=selectedType.value;
  const usesTable=orderType==='mesa';
  tableField.hidden=!usesTable;
  tableSelect.disabled=!usesTable;
  if(destinationHelp)destinationHelp.textContent=usesTable?'El ticket imprimira la mesa seleccionada.':(orderType==='domicilio'?'El ticket se marcara como Domicilio.':'El ticket se marcara como Recoger.');
}
document.querySelectorAll('input[name="order_type"]').forEach(function(radio){radio.addEventListener('change',syncOrderDestination);});
syncOrderDestination();
function moneyTick(v){return fmt.format(v);}
function chartColors(){return {red:'#dc2626',yellow:'#facc15',green:'#16a34a',dark:'#111827',orange:'#fb923c',blue:'#2563eb',muted:'#78716c'};}
function makeBarLineChart(id, rows, labels, datasets){
  const el=document.getElementById(id);
  if(!el||!rows||!rows.length)return;
  ensureChartBox(el);
  new Chart(el,{type:'bar',data:{labels:labels(rows),datasets:datasets(rows)},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:12,usePointStyle:true}}},scales:{x:{grid:{display:false}},y:{ticks:{callback:moneyTick}}}}});
}
function makeDoughnut(id, rows, labelKey, valueKey){
  const el=document.getElementById(id);
  if(!el||!rows||!rows.length)return;
  ensureChartBox(el);
  const c=chartColors();
  new Chart(el,{type:'doughnut',data:{labels:rows.map(function(r){return r[labelKey]||r.method||r.label||'Sin dato';}),datasets:[{data:rows.map(function(r){return Number(r[valueKey]||r.total||0);}),backgroundColor:[c.red,c.yellow,c.green,c.orange,c.blue,'#7c3aed','#0f766e','#be123c'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'62%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,usePointStyle:true}}}}});
}
function makeHorizontalBars(id, rows, labelKey, valueKey){
  const el=document.getElementById(id);
  if(!el||!rows||!rows.length)return;
  ensureChartBox(el);
  const c=chartColors();
  new Chart(el,{type:'bar',data:{labels:rows.map(function(r){return r[labelKey];}),datasets:[{label:'Total',data:rows.map(function(r){return Number(r[valueKey]||0);}),backgroundColor:c.yellow,borderColor:c.red,borderWidth:1}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{callback:function(v){return valueKey==='qty'?v:moneyTick(v);}}},y:{grid:{display:false}}}}});
}
function ensureChartBox(canvas){
  if(canvas.parentElement&&canvas.parentElement.classList.contains('chart-box'))return;
  const box=document.createElement('div');
  box.className='chart-box';
  canvas.parentNode.insertBefore(box,canvas);
  box.appendChild(canvas);
  canvas.removeAttribute('height');
  canvas.removeAttribute('width');
  canvas.style.height='100%';
  canvas.style.width='100%';
}
const analyticsPayloadEl=document.getElementById('analyticsPayload');
if(analyticsPayloadEl){
  try{
    const payload=JSON.parse(analyticsPayloadEl.textContent||'{}');
    Object.keys(payload).forEach(function(key){window[key]=payload[key];});
  }catch(e){console.warn('No se pudieron cargar datos de analitica',e);}
}
const dailyRows=window.dailyRows||[];
const salesChartRows=window.salesChartRows||[];
const salesPaymentRows=window.salesPaymentRows||[];
const reportChartRows=window.reportChartRows||[];
const reportPaymentRows=window.reportPaymentRows||[];
const reportProductRows=window.reportProductRows||[];
const reportCostRows=window.reportCostRows||[];
const averageProductRows=window.averageProductRows||[];
const averageDailyRows=window.averageDailyRows||[];
const averageHourRows=window.averageHourRows||[];
if(dailyRows.length&&document.getElementById('dailyChart')){makeBarLineChart('dailyChart',dailyRows,function(rows){return rows.map(function(r){return r.day});},function(rows){const c=chartColors();return[{label:'Ventas',data:rows.map(function(r){return Number(r.sales)}),backgroundColor:c.green},{label:'Gastos + compras',data:rows.map(function(r){return Number(r.expenses)+Number(r.purchases)}),backgroundColor:c.red}];});}
if(salesChartRows.length){makeBarLineChart('salesTrendChart',salesChartRows,function(rows){return rows.map(function(r){return r.day});},function(rows){const c=chartColors();return[{label:'Ventas',data:rows.map(function(r){return Number(r.sales)}),backgroundColor:c.green},{label:'Margen',type:'line',data:rows.map(function(r){return Number(r.margin)}),borderColor:c.yellow,backgroundColor:c.yellow,tension:.35,pointRadius:3}];});}
if(salesPaymentRows.length){makeDoughnut('salesPaymentChart',salesPaymentRows,'method','total');}
if(reportChartRows.length){makeBarLineChart('reportTrendChart',reportChartRows,function(rows){return rows.map(function(r){return r.day});},function(rows){const c=chartColors();return[{label:'Ventas',data:rows.map(function(r){return Number(r.sales)}),backgroundColor:c.green},{label:'Egresos',data:rows.map(function(r){return Number(r.expenses)+Number(r.purchases)}),backgroundColor:c.red},{label:'Utilidad',type:'line',data:rows.map(function(r){return Number(r.profit)}),borderColor:c.yellow,backgroundColor:c.yellow,tension:.35,pointRadius:3}];});}
if(reportPaymentRows.length){makeDoughnut('reportPaymentChart',reportPaymentRows,'payment_method','total');}
if(reportProductRows.length){makeHorizontalBars('reportProductChart',reportProductRows,'product_name','total');}
if(reportCostRows.length){makeDoughnut('reportCostChart',reportCostRows,'label','total');}
if(averageProductRows.length){makeHorizontalBars('averageProductsChart',averageProductRows,'product_name','qty');}
if(averageDailyRows.length){makeBarLineChart('averageDailyChart',averageDailyRows,function(rows){return rows.map(function(r){return r.day});},function(rows){const c=chartColors();return[{label:'Ventas',data:rows.map(function(r){return Number(r.sales)}),backgroundColor:c.green},{label:'Pedidos x 1000',type:'line',data:rows.map(function(r){return Number(r.orders)*1000}),borderColor:c.yellow,backgroundColor:c.yellow,tension:.35,pointRadius:3}];});}
if(averageHourRows.length){const rows=averageHourRows.map(function(r){return{hour:String(r.hour).padStart(2,'0')+':00',qty:Number(r.qty)}});makeHorizontalBars('averageHourChart',rows,'hour','qty');}
