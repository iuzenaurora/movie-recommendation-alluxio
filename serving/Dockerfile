FROM python:3.11-slim
WORKDIR /app
COPY serving/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY src/recommend_core.py /app/recommend_core.py
COPY serving/api.py /app/api.py
COPY serving/snapshot.py /app/snapshot.py
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000"]
