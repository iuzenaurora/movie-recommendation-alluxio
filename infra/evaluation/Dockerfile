FROM python:3.11-slim
WORKDIR /workspace
ENV PYTHONPATH=/workspace/src OPENBLAS_NUM_THREADS=1 OMP_NUM_THREADS=1
COPY requirements-experiments.txt /tmp/requirements-experiments.txt
RUN pip install --no-cache-dir -r /tmp/requirements-experiments.txt
CMD ["python", "scripts/evaluate_recommendations.py", "--help"]
