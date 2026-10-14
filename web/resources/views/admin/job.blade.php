@extends('layouts.app')
@section('title', 'Tiến trình trực tiếp · MovieLab')
@section('content')
<div class="space-y-6">
<a class="text-xs text-rose-300" href="{{ route('admin.jobs') }}">← Trung tâm điều hành</a>
<section class="hero-cinema p-7 md:p-10">
<p class="eyebrow">Pipeline / Live execution</p>
<div class="flex flex-wrap justify-between items-center gap-4 mt-4"><h1 class="text-3xl font-bold">{{ ['retrain'=>'Huấn luyện lại ALS','snapshot'=>'Snapshot đánh giá','benchmark'=>'Benchmark H0 / H1 / H2','activate'=>'Cập nhật model','rollback'=>'Khôi phục model'][$job['kind']] ?? $job['kind'] }}</h1><span id="status" class="status-pill">{{ $job['status'] }}</span></div>
<p class="small-code mt-4">{{ $job['id'] }}</p><p id="stage" class="mt-6 text-slate-300">{{ $job['stage'] ?? 'Đang chờ worker tiếp nhận' }}</p>
<p id="poll-state" class="text-[11px] mt-3 text-slate-500" role="status">Tự cập nhật mỗi 5 giây</p>
</section>
<div class="grid lg:grid-cols-[1fr_1.2fr] gap-6 items-start">
<section class="glass-panel rounded-3xl p-7"><h2 class="font-bold text-lg mb-6">Các bước đã thực hiện</h2><div id="steps" class="stage-list"></div><p id="error" class="mt-5 text-sm text-amber-300" role="alert">{{ $job['error'] ?? '' }}</p><pre id="result" class="text-xs leading-6 overflow-auto mt-5 text-emerald-200"></pre></section>
<details class="glass-panel rounded-3xl p-6" open><summary class="font-semibold text-sm cursor-pointer">Nhật ký kỹ thuật</summary><pre id="log" class="bg-black/30 rounded-xl p-4 mt-5 text-[10px] leading-relaxed overflow-auto max-h-[60vh] text-slate-400">{{ $job['log'] }}</pre><p class="mt-4 text-[10px] text-slate-500">Hiển thị 50 KB cuối. Nhật ký đầy đủ được giữ trên máy B. Các bước chỉ xuất hiện khi worker thực sự chạy.</p></details>
</div></div>
@endsection
@push('scripts')
<script>
(()=>{
 const initial=@json($job);
 const names={pending:'Đang chờ',running:'Đang chạy',succeeded:'Hoàn tất',failed:'Thất bại'};
 function render(j){
  document.getElementById('status').textContent=names[j.status]||j.status;
  document.getElementById('stage').textContent=j.stage||(j.status==='pending'?'Đang chờ worker tiếp nhận':'Tác vụ từ phiên bản worker trước');
  const steps=document.getElementById('steps');steps.replaceChildren();
  if(!j.steps?.length){const p=document.createElement('p');p.className='text-sm text-slate-500';p.textContent='Chưa có dữ liệu từng bước cho tác vụ này.';steps.append(p)}
  for(const [index,step] of (j.steps||[]).entries()){
   const row=document.createElement('div');row.className='stage-item';row.dataset.state=step.status;
   const bullet=document.createElement('span');bullet.className='stage-bullet';bullet.textContent=step.status==='succeeded'?'✓':step.status==='failed'?'!':String(index+1);
   const content=document.createElement('div');const title=document.createElement('p');title.className='text-sm text-slate-200';title.textContent=step.label;
   const note=document.createElement('p');note.className='text-[10px] text-slate-500 mt-1';note.textContent=(names[step.status]||step.status)+' · '+new Date(step.startedAt).toLocaleString('vi-VN');
   content.append(title,note);row.append(bullet,content);steps.append(row);
  }
  document.getElementById('log').textContent=j.log||'';
  document.getElementById('error').textContent=j.error||'';
  document.getElementById('result').textContent=j.result?JSON.stringify(j.result,null,2):'';
 }
 render(initial);
 async function poll(){
  try{
   const response=await fetch(@json(route('admin.jobs.show', $job['id'])),{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});
   if(!response.ok)throw new Error();
   const job=await response.json();render(job);
   document.getElementById('poll-state').textContent='Đã đồng bộ lúc '+new Date().toLocaleTimeString('vi-VN');
   if(['pending','running'].includes(job.status))setTimeout(poll,5000);
  }catch(error){document.getElementById('poll-state').textContent='Mất kết nối cập nhật · đang thử lại';setTimeout(poll,10000)}
 }
 poll();
})();
</script>
@endpush
