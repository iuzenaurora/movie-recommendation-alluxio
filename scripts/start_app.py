"""Start Movie Lab and its local job worker without PowerShell policy changes."""
import argparse,json,os,subprocess,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def worker_running(path):
    path.parent.mkdir(parents=True,exist_ok=True)
    with path.open('a+b') as lock:
        if path.stat().st_size == 0:
            lock.write(b'0');lock.flush()
        lock.seek(0)
        if os.name=='nt':
            import msvcrt
            try:msvcrt.locking(lock.fileno(),msvcrt.LK_NBLCK,1)
            except OSError:return True
            msvcrt.locking(lock.fileno(),msvcrt.LK_UNLCK,1)
        else:
            import fcntl
            try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            except OSError:return True
            fcntl.flock(lock,fcntl.LOCK_UN)
    return False

def main():
    p=argparse.ArgumentParser();p.add_argument('--build',action='store_true');a=p.parse_args()
    command=['docker','compose','--profile','app','up','-d','--wait','--wait-timeout','240']
    if a.build:command+=['--build']
    subprocess.run(command+['mongo','api','web'],cwd=ROOT,check=True)
    runtime=ROOT/'runtime';runtime.mkdir(exist_ok=True)
    settings=runtime/'automation.json'
    if not settings.exists():settings.write_text(json.dumps({'benchmarkHours':24,'retrainRatings':20,'autoActivate':True}),encoding='utf-8')
    if not worker_running(runtime/'jobs/worker.lock'):
        with (runtime/'worker.stdout.log').open('a') as stdout,(runtime/'worker.stderr.log').open('a') as stderr:
            kwargs={'creationflags':subprocess.CREATE_NO_WINDOW} if os.name=='nt' else {'start_new_session':True}
            subprocess.Popen([sys.executable,str(ROOT/'scripts/job_worker.py')],cwd=ROOT,
                             stdin=subprocess.DEVNULL,stdout=stdout,stderr=stderr,**kwargs)
        print('Background worker started.')
    else:print('Background worker already running.')
    print('Movie Lab: http://localhost:8080')
    print('Admin credentials: runtime/admin-credentials.json (kept local; never committed)')
if __name__=='__main__':main()
