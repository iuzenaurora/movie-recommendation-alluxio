(()=>{
 const root=document.getElementById('operations');if(!root)return;
 const labels={pending:'Đang chờ',running:'Đang chạy',succeeded:'Hoàn tất',failed:'Thất bại'};
 const kinds={snapshot:'Snapshot đánh giá',retrain:'Huấn luyện lại ALS',benchmark:'Benchmark H0 / H1 / H2',activate:'Cập nhật model',rollback:'Khôi phục model'};
 const time=value=>value ? new Date(typeof value==='number'?value*1000:value).toLocaleString('vi-VN') : 'Chưa ghi nhận';
 const set=(id,value)=>{document.getElementById(id).textContent=value};
 function render(data){
  const config=data.automation||{},state=data.scheduler||{},jobs=data.jobs||[];
  const pill=document.getElementById('worker-state');
  pill.className='status-pill '+(data.worker?'is-live':'is-warn');
  pill.replaceChildren();
  const dot=document.createElement('span');dot.className='status-dot';
  pill.append(dot,document.createTextNode(data.worker?'Tự động hóa đang chạy':'Worker chưa kết nối'));
  const pending=Math.max(0,(state.currentRevision||0)-(state.trainedRevision||0));
  set('pending-ratings',state.currentRevision===undefined?'—':pending);
  set('threshold-note',config.retrainRatings?'Ngưỡng '+config.retrainRatings+' thay đổi để học lại':'Lịch retrain đang tắt');
  const hours=Number(config.benchmarkHours||0);
  set('next-benchmark',hours?(state.benchmarkAt?time(state.benchmarkAt+hours*3600):'Đang khởi tạo'):'Đã tắt');
  set('active-model',data.model?.modelVersion||'Chưa có model');
  set('activation-mode',config.autoActivate?'Tự động':'Admin duyệt');
  set('last-check','Worker kiểm tra: '+time(state.checkedAt));
  set('scheduler-error',state.error||'');
  const active=jobs.find(j=>['pending','running'].includes(j.status));
  set('pipeline-message',!data.worker?'Tiến trình nền chưa kết nối. Mở MovieLab.pyw để khởi động.':active?(active.stage||'Tác vụ đang chờ worker tiếp nhận'):'Đang lắng nghe đánh giá mới và chờ lịch tự động.');
  const list=document.getElementById('job-list');list.replaceChildren();
  if(!jobs.length){const p=document.createElement('p');p.className='py-8 text-sm text-slate-500';p.textContent='Chưa có tác vụ. Tiến trình sẽ tự xuất hiện khi đến lịch hoặc đủ đánh giá.';list.append(p)}
  for(const job of jobs){
   const a=document.createElement('a');a.className='job-row';
   a.href=root.dataset.jobUrl+'/'+encodeURIComponent(job.id);
   const head=document.createElement('div');head.className='flex justify-between gap-3 items-center';
   const title=document.createElement('p');title.className='job-title text-sm font-semibold';title.textContent=kinds[job.kind]||job.kind;
   const badge=document.createElement('span');badge.className='status-pill '+(job.status==='succeeded'?'is-live':job.status==='failed'?'is-warn':'');
   badge.textContent=labels[job.status]||job.status;head.append(title,badge);a.append(head);
   const detail=document.createElement('p');detail.className='text-xs text-slate-400 mt-3';detail.textContent=job.stage||(job.status==='pending'?'Đợi worker tiếp nhận':time(job.createdAt));a.append(detail);
   if(job.error){const p=document.createElement('p');p.className='text-xs text-amber-300 mt-2';p.textContent=job.error;a.append(p)}
   const info=document.createElement('p');info.className='text-[10px] text-slate-500 mt-3';info.textContent=(job.requestedBy==='scheduler'?'Lịch tự động':'Admin yêu cầu')+' · '+time(job.createdAt)+' · Xem từng bước ↗';a.append(info);
   list.append(a);
  }
 }
 if(window.pipelineInitial)render(window.pipelineInitial);
 async function poll(){
  if(document.hidden){setTimeout(poll,5000);return}
  try{
   const response=await fetch(root.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});
   if(!response.ok)throw new Error('Không đọc được trạng thái ('+response.status+')');
   render(await response.json());set('poll-state','Đã đồng bộ lúc '+new Date().toLocaleTimeString('vi-VN'));
  }catch(error){set('poll-state','Mất kết nối cập nhật · sẽ tự thử lại')}
  setTimeout(poll,5000);
 }
 poll();
})();
