<?php

return [
    'runtime_dir' => env('RUNTIME_DIR', '/runtime'),
    'model_dir' => env('MODEL_DIR', base_path('../models/current')),
    'experiment_dir' => env('EXPERIMENT_DIR', base_path('../experiments')),
    'benchmark_report' => env('BENCHMARK_REPORT', 'als-benchmark/ml32m-vpn-als-20261012-205245/metrics-summary-v1/metrics-summary.json'),
];
