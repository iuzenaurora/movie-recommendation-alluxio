"""Local compute worker. Only fixed job kinds and validated model names are executable."""
import argparse, contextlib, datetime, json, os, re, shutil, subprocess, threading, time, uuid
from pathlib import Path
from urllib.request import Request, urlopen

ROOT=Path(__file__).resolve().parents[1]
RUNTIME=ROOT/'runtime'
JOBS=RUNTIME/'jobs'
COMPUTE=['docker','compose','--env-file','twohost/.env.compute','-f','twohost/compose.compute.yaml']
APP=['docker','compose','--profile','app']
DATASET='ml32m-full-v1'
KINDS={'snapshot','retrain','benchmark','activate','rollback'}

def save(path,value):
    path=Path(path)
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_name(path.name+'.'+uuid.uuid4().hex+'.tmp')
    tmp.write_text(json.dumps(value,indent=2,ensure_ascii=False),encoding='utf-8')
    tmp.replace(path)

def read(path,default=None):
    try:return json.loads(Path(path).read_text(encoding='utf-8'))
    except FileNotFoundError:return default

def enqueue(kind,model=None):
    if kind not in KINDS:raise ValueError('Unknown job kind')
    identifier=str(uuid.uuid4())
    row={'id':identifier,'kind':kind,'model':model,'status':'pending',
         'createdAt':datetime.datetime.now(datetime.timezone.utc).isoformat(),'requestedBy':'scheduler'}
    save(JOBS/(identifier+'.json'),row)
    return row

def activity():
    token=(RUNTIME/'service-token').read_text().strip()
    req=Request('http://127.0.0.1:8000/internal/activity',headers={'X-Service-Token':token})
    with urlopen(req,timeout=5) as response:return json.load(response)

class Worker:
    def __init__(self,root=ROOT):
        self.root=Path(root).resolve()
        self.log=None
        self.job_path=None
    def stage(self,key,label):
        if not self.job_path:return
        job=read(self.job_path,{})
        steps=job.setdefault('steps',[])
        now=datetime.datetime.now(datetime.timezone.utc).isoformat()
        for step in steps:
            if step['status']=='running':step.update(status='succeeded',finishedAt=now)
        steps.append({'key':key,'label':label,'status':'running','startedAt':now})
        job.update(stage=label,steps=steps,updatedAt=now)
        save(self.job_path,job)
    def run(self,args,capture=False):
        self.log.write('\n$ '+' '.join(map(str,args))+'\n');self.log.flush()
        if capture:
            p=subprocess.run(args,cwd=self.root,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,encoding='utf-8',errors='replace')
            self.log.write(p.stdout);self.log.flush()
            if p.returncode:raise RuntimeError('Command failed ('+str(p.returncode)+'): '+' '.join(args[:6]))
            return p.stdout
        p=subprocess.run(args,cwd=self.root,stdout=self.log,stderr=subprocess.STDOUT)
        if p.returncode:raise RuntimeError('Command failed ('+str(p.returncode)+'): '+' '.join(args[:6]))
        return ''
    def spark(self,script,*args):
        return self.run(COMPUTE+['--profile','jobs','run','--rm','spark','bash','scripts/spark.sh',script,*map(str,args)])
    def ensure_compute(self):
        self.stage('storage','Kiểm tra máy A và kết nối HDFS / Alluxio')
        active=self.run(['docker','ps','--filter','label=com.docker.compose.service=spark','--format','{{.Names}}'],True)
        if active.strip():raise RuntimeError('Another Spark container is running; wait for it to finish')
        self.run(COMPUTE+['up','-d','alluxio-master','alluxio-worker'])
        self.spark('twohost/check_storage.py')
    def snapshot(self,identifier,keep_stopped=False):
        self.stage('snapshot','Chụp dữ liệu đánh giá từ MongoDB')
        relative='runtime/app-snapshot-'+identifier+'.jsonl'
        self.run(APP+['stop','api'])
        try:
            self.run(APP+['run','--rm','--no-deps','api','python','snapshot.py','--out','/'+relative])
        finally:
            if not keep_stopped:self.run(APP+['up','-d','--wait','--wait-timeout','240','api'])
        path=self.root/relative
        rows=0
        with path.open() as source:
            for line in source:
                if line.strip():json.loads(line);rows+=1
        if rows==0:raise ValueError('Snapshot is empty. Add ratings before retraining.')
        return relative
    def activate(self,model,rollback=False):
        self.stage('verify-model','Kiểm tra gói model trước khi kích hoạt')
        if not re.fullmatch(r'als-[A-Za-z0-9_-]+',model or '') and not rollback:
            raise ValueError('Invalid model folder name')
        root=(self.root/'models').resolve()
        source=(root/('previous' if rollback else model)).resolve()
        if source.parent!=root or not source.is_dir():raise ValueError('Model is missing or outside models directory')
        identifier=uuid.uuid4().hex
        stage=root/('staging-'+identifier)
        shutil.copytree(source,stage)
        self.run(['docker','compose','--profile','jobs','run','--rm','spark','python3','scripts/validate_package.py','--model','models/'+stage.name])
        expected=read(stage/'manifest.json')['modelVersion']
        backup=root/('before-'+identifier)
        current=root/'current'
        self.stage('activate','Chuyển model và kiểm tra sức khỏe API')
        self.run(APP+['stop','api'])
        moved=False
        try:
            if current.exists():current.rename(backup);moved=True
            stage.rename(current)
            self.run(APP+['up','-d','--wait','--wait-timeout','240','api'])
            with urlopen('http://127.0.0.1:8000/health',timeout=10) as response:health=json.load(response)
            if health['modelVersion']!=expected:raise RuntimeError('API loaded an unexpected model')
        except Exception:
            if current.exists():current.rename(root/('failed-'+identifier))
            if moved:backup.rename(current)
            self.run(APP+['up','-d','--wait','--wait-timeout','240','api'])
            raise
        if moved:
            previous=root/'previous'
            if previous.exists():previous.rename(root/('previous-archive-'+identifier))
            backup.rename(previous)
        return {'modelVersion':expected,'folder':model,'rollback':rollback}
    def retrain(self,identifier):
        self.ensure_compute()
        try:
            snapshot=self.snapshot(identifier,keep_stopped=True)
            dataset='retrain-'+identifier
            output='data/processed/'+dataset
            self.stage('merge','Ghép đánh giá mới với dataset MovieLens')
            self.spark('src/merge_snapshot.py','--base','data/processed/'+DATASET,'--snapshot',snapshot,'--out',output)
            self.stage('upload','Đưa dataset mới lên HDFS máy A')
            self.spark('scripts/upload_dataset.py','--data',output,'--destination','hdfs://hdfs-storage:9000/datasets/'+dataset)
            model='als-retrain-'+identifier
            self.stage('train','Huấn luyện Spark ALS qua Alluxio')
            self.spark('src/retrain.py','--data','alluxio://alluxio-master:19998/datasets/'+dataset,
                       '--manifest',output+'/manifest.json','--previous','models/current','--out','models/'+model,'--num-blocks','16')
            self.stage('validate','Xác minh manifest và checksum model')
            self.run(COMPUTE+['--profile','jobs','run','--rm','spark','python3','scripts/validate_package.py','--model','models/'+model])
            result={'snapshot':snapshot,'dataset':dataset,'model':model,'activation':'awaiting admin approval'}
            if read(self.root/'runtime/automation.json',{}).get('autoActivate',False):
                result.update(activation='automatic',deployment=self.activate(model))
            return result
        finally:self.run(APP+['up','-d','--wait','--wait-timeout','240','api'])
    def benchmark(self,identifier):
        self.ensure_compute()
        relative='experiments/als-benchmark/auto-'+identifier
        folder=self.root/relative
        folder.mkdir(parents=True,exist_ok=False)
        manifest=self.root/'data/processed'/DATASET/'manifest.json'
        shutil.copy2(manifest,folder/'dataset-manifest.json')
        save(folder/'settings.json',{'repeats':5,'source':'admin worker; current machine A','dataset':DATASET})
        (folder/'compose-effective.txt').write_text(self.run(COMPUTE+['config'],True),encoding='utf-8')
        (folder/'compose-version.txt').write_text(self.run(['docker','compose','version'],True),encoding='utf-8')
        train='/datasets/'+DATASET+'/split/split=train'
        def alluxio(*args):
            return self.run(COMPUTE+['exec','-T','alluxio-master','alluxio',*args],True)
        def check_cache(warm):
            output=alluxio('fs','du','-s',train)
            matches=re.findall(r'^\s*(\d+)\s+(\d+)\s+\(',output,re.M)
            if len(matches)!=1:raise RuntimeError('Cannot verify Alluxio cache state')
            size,cached=map(int,matches[0])
            if size<=0 or (warm and cached!=size) or (not warm and cached!=0):
                raise RuntimeError('Alluxio cache state does not match benchmark label')
        def state(prefix):
            time.sleep(5)
            (folder/(prefix+'-cache.txt')).write_text(alluxio('fs','du','-s',train),encoding='utf-8')
            (folder/(prefix+'-metrics.txt')).write_text(alluxio('fsadmin','report','metrics'),encoding='utf-8')
        for repeat in range(1,6):
            labels=['H0','H1','H2'] if repeat%2 else ['H1','H2','H0']
            for label in labels:
                self.stage('benchmark-'+label+'-'+str(repeat),'Đo '+label+' · lượt '+str(repeat)+'/5')
                if label=='H1':alluxio('fs','free','-f',train);check_cache(False)
                if label=='H2':alluxio('fs','load',train);check_cache(True)
                prefix=label+'-'+str(repeat)
                if label!='H0':state(prefix+'-before')
                scheme='hdfs://hdfs-storage:9000' if label=='H0' else 'alluxio://alluxio-master:19998'
                self.spark('src/benchmark_als.py','--data',scheme+'/datasets/'+DATASET,
                    '--manifest','data/processed/'+DATASET+'/manifest.json','--label',label,'--repeat',repeat,
                    '--results',relative,'--rank','20','--reg','0.1','--max-iter','10','--num-blocks','16')
                if label!='H0':state(prefix+'-after')
        self.stage('report','Tổng hợp 15 lượt đo và công bố báo cáo')
        self.run(COMPUTE+['--profile','jobs','run','--rm','spark','python3','scripts/summarize_als.py','--results',relative])
        self.run(COMPUTE+['--profile','jobs','run','--rm','spark','python3','scripts/summarize_alluxio_metrics.py','--results',relative,'--repeats','5'])
        report=relative.removeprefix('experiments/')+'/metrics-summary-v1/metrics-summary.json'
        save(RUNTIME/'active-benchmark.json',{'path':report})
        return {'report':report,'runs':15,'repeatsPerLabel':5}
    def execute(self,job):
        if job.get('kind') not in KINDS:raise ValueError('Unknown job kind')
        identifier=str(uuid.UUID(job['id']))
        if job['kind']=='snapshot':return {'snapshot':self.snapshot(identifier)}
        if job['kind']=='retrain':return self.retrain(identifier)
        if job['kind']=='benchmark':
            self.run(APP+['stop','api'])
            try:return self.benchmark(identifier)
            finally:self.run(APP+['up','-d','--wait','--wait-timeout','240','api'])
        if job['kind']=='activate':return self.activate(job.get('model'))
        return self.activate('previous',rollback=True)

def scheduler():
    config=read(RUNTIME/'automation.json',{}) or {}
    state=read(RUNTIME/'automation-state.json',{}) or {}
    hours=int(config.get('benchmarkHours',0))
    threshold=int(config.get('retrainRatings',0))
    now=time.time()
    state['checkedAt']=now
    state['error']=None
    pending=state.get('retrainJob')
    if pending:
        completed=read(JOBS/(pending+'.json'),{})
        if completed.get('status')=='succeeded':
            state['trainedRevision']=state.pop('queuedRevision',state.get('trainedRevision',0))
            state['retrainJob']=None
        elif completed.get('status')=='failed':
            state['retrainJob']=None
            state['error']='Lần retrain trước thất bại. Hệ thống sẽ thử lại sau thời gian chờ.'
    if hours and now-state.get('benchmarkAt',now)>=hours*3600:
        enqueue('benchmark');state['benchmarkAt']=now;save(RUNTIME/'automation-state.json',state);return
    state.setdefault('benchmarkAt',now)
    if threshold:
        revision=activity()['revision']
        state['currentRevision']=revision
        if 'trainedRevision' not in state:state['trainedRevision']=revision
        if not state.get('retrainJob') and revision-state['trainedRevision']>=threshold and now-state.get('retrainAt',0)>3600:
            job=enqueue('retrain');state.update(retrainJob=job['id'],queuedRevision=revision,retrainAt=now)
    save(RUNTIME/'automation-state.json',state)

def main():
    p=argparse.ArgumentParser();p.add_argument('--once',action='store_true');a=p.parse_args()
    JOBS.mkdir(parents=True,exist_ok=True)
    lock=(JOBS/'worker.lock').open('a+b')
    if (JOBS/'worker.lock').stat().st_size == 0:
        lock.write(b'0');lock.flush()
    lock.seek(0)
    if os.name=='nt':
        import msvcrt
        msvcrt.locking(lock.fileno(),msvcrt.LK_NBLCK,1)
    else:
        import fcntl
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
    def heartbeat():
        while True:
            (JOBS/'heartbeat').touch()
            time.sleep(15)
    threading.Thread(target=heartbeat,daemon=True).start()
    worker=Worker()
    # Interrupted jobs remain visible and are never silently retried.
    for path in JOBS.glob('*.json'):
        job=read(path,{})
        if job.get('status')=='running':
            job.update(status='failed',error='Worker was interrupted. Inspect active containers before retrying.')
            save(path,job)
    while True:
        pending=[]
        for path in JOBS.glob('*.json'):
            row=read(path,{})
            if row.get('status')=='pending':pending.append((path,row))
        pending.sort(key=lambda item:item[1].get('createdAt',''))
        if pending:
            path,job=pending[0]
            worker.job_path=path
            job.update(status='running',startedAt=datetime.datetime.now(datetime.timezone.utc).isoformat());save(path,job)
            with (JOBS/(str(uuid.UUID(job['id']))+'.log')).open('a',encoding='utf-8') as output:
                worker.log=output
                try:
                    result=worker.execute(job)
                    job=read(path,job)
                    job.update(result=result,status='succeeded',stage='Hoàn tất')
                except Exception as exc:
                    job=read(path,job)
                    job.update(status='failed',error=str(exc))
                    output.write('\nERROR: '+str(exc)+'\n')
                for step in job.get('steps',[]):
                    if step['status']=='running':step.update(status=job['status'],finishedAt=datetime.datetime.now(datetime.timezone.utc).isoformat())
            job['finishedAt']=datetime.datetime.now(datetime.timezone.utc).isoformat();save(path,job)
        elif not a.once:
            try:scheduler()
            except Exception as exc:
                state=read(RUNTIME/'automation-state.json',{})
                state.update(error='Không đọc được trạng thái dịch vụ: '+str(exc),checkedAt=time.time())
                save(RUNTIME/'automation-state.json',state)
        if a.once:return
        if (JOBS/'stop').exists():
            (JOBS/'stop').unlink()
            return
        time.sleep(5)
if __name__=='__main__':main()
