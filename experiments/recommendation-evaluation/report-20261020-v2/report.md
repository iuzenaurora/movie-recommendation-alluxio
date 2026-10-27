# Kết quả đánh giá các cách gợi ý

Model: `als-4033f5cd6298`. Dataset: `ml-5b41ebbd9d02`.
Mẫu 500 người dùng. K=10. Lịch sử chỉ từ train + validation. Phim phù hợp: test >=4 sao.
Các phương pháp dùng cùng tập ứng viên và cùng mẫu. Không sử dụng profiles.json hoặc popularity toàn dataset.

## Lịch sử last-5

| Phương pháp | Người có positive test | Precision@10 | Recall@10 | NDCG@10 | Độ dài TB |
|---|---:|---:|---:|---:|---:|
| als_fold_in | 468 | 0.011325 | 0.023600 | 0.017315 | 10.00 |
| hybrid | 468 | 0.014530 | 0.030429 | 0.024287 | 10.00 |
| genre | 468 | 0.014316 | 0.030006 | 0.022195 | 10.00 |
| popularity | 468 | 0.017521 | 0.037910 | 0.026925 | 10.00 |

Chênh lệch NDCG so với ALS fold-in (bootstrap ghép cặp theo người, CI 95%):

- hybrid: +0.006972; [+0.000713, +0.013624].
- genre: +0.004880; [-0.000439, +0.010516].
- popularity: +0.009610; [+0.001506, +0.018072].

Fold-in RMSE=0.902992; MAE=0.687291. Movie mean RMSE=0.921457; MAE=0.704972. Cùng 8514/8527 cặp test có hỗ trợ.

## Lịch sử last-20

| Phương pháp | Người có positive test | Precision@10 | Recall@10 | NDCG@10 | Độ dài TB |
|---|---:|---:|---:|---:|---:|
| als_fold_in | 468 | 0.012179 | 0.025925 | 0.018537 | 10.00 |
| hybrid | 468 | 0.016239 | 0.032717 | 0.028613 | 10.00 |
| genre | 468 | 0.020085 | 0.043224 | 0.032953 | 10.00 |
| popularity | 468 | 0.018376 | 0.042254 | 0.029967 | 10.00 |

Chênh lệch NDCG so với ALS fold-in (bootstrap ghép cặp theo người, CI 95%):

- hybrid: +0.010075; [+0.003788, +0.016919].
- genre: +0.014416; [+0.008537, +0.021028].
- popularity: +0.011429; [+0.003801, +0.019540].

Fold-in RMSE=0.837764; MAE=0.646167. Movie mean RMSE=0.921457; MAE=0.704972. Cùng 8514/8527 cặp test có hỗ trợ.

## Lịch sử full

| Phương pháp | Người có positive test | Precision@10 | Recall@10 | NDCG@10 | Độ dài TB |
|---|---:|---:|---:|---:|---:|
| als_fold_in | 468 | 0.017308 | 0.030220 | 0.024201 | 10.00 |
| hybrid | 468 | 0.021795 | 0.039399 | 0.034895 | 10.00 |
| genre | 468 | 0.024145 | 0.040623 | 0.035640 | 10.00 |
| popularity | 468 | 0.033974 | 0.052902 | 0.051284 | 10.00 |

Chênh lệch NDCG so với ALS fold-in (bootstrap ghép cặp theo người, CI 95%):

- hybrid: +0.010694; [+0.004116, +0.017374].
- genre: +0.011439; [+0.005854, +0.017082].
- popularity: +0.027083; [+0.016427, +0.037859].

Fold-in RMSE=0.809874; MAE=0.618733. Movie mean RMSE=0.921457; MAE=0.704972. Cùng 8514/8527 cặp test có hỗ trợ.

## Phạm vi kết luận

- This is a sampled offline evaluation, not a satisfaction study.
- The cohort differs from the original Spark evaluation; do not compare scores as a before/after claim.
- Hybrid weight and genre policy were fixed before reading test results.
- Bootstrap intervals are exploratory and do not adjust for multiple comparisons.
- Hybrid and genre scores are ranking scores, not calibrated rating predictions.
- API latency is measured separately; offline runtime is not website response time.
