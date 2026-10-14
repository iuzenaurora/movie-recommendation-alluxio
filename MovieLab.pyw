"""Double-click to open MovieLab without a command prompt."""
import os
import subprocess
import sys
import threading
import webbrowser
from pathlib import Path
import tkinter as tk
from tkinter import messagebox
ROOT=Path(__file__).resolve().parent
window=tk.Tk()
window.title("MovieLab")
window.geometry("460x250")
window.configure(bg="#11131f")
tk.Label(window,text="MOVIELAB",fg="#fda4af",bg="#11131f",font=("Segoe UI",24,"bold")).pack(pady=(35,15))
status=tk.StringVar(value="Đang khởi động ứng dụng và tiến trình tự động…")
tk.Label(window,textvariable=status,fg="#d5d1e2",bg="#11131f",wraplength=400,font=("Segoe UI",11)).pack(padx=25)
tk.Label(window,text="Docker Desktop cần đang hoạt động.",fg="#817b96",bg="#11131f",font=("Segoe UI",10)).pack(pady=18)
def start():
    runtime=ROOT/"runtime";runtime.mkdir(exist_ok=True)
    python=Path(sys.executable).with_name("python.exe") if os.name=="nt" else Path(sys.executable)
    command=[str(python),str(ROOT/"scripts/start_app.py")]
    flags={"creationflags":subprocess.CREATE_NO_WINDOW} if os.name=="nt" else {}
    with (runtime/"launcher.log").open("a",encoding="utf-8") as log:
        result=subprocess.run(command,cwd=ROOT,stdout=log,stderr=subprocess.STDOUT,**flags)
    if result.returncode:
        window.after(0,lambda:messagebox.showerror("MovieLab","Không khởi động được. Hãy mở Docker Desktop rồi thử lại. Chi tiết: runtime/launcher.log"))
        window.after(0,lambda:status.set("Khởi động chưa thành công. Có thể đóng và mở lại."))
    else:
        webbrowser.open("http://localhost:8080")
        window.after(0,window.destroy)
threading.Thread(target=start,daemon=True).start()
window.mainloop()
