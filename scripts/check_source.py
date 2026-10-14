"""Audit Git publication candidates; never delete local data or print secret values."""
import argparse
import json
import re
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MAX_SOURCE_BYTES = 5 * 1024 * 1024
SECRET_PATTERNS = {
    "GitHub access token": re.compile(r"\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{40,})\b"),
    "OpenAI-style secret": re.compile(r"\bsk-(?:proj-|svcacct-)?[A-Za-z0-9_-]{32,}\b"),
    "AWS access key": re.compile(r"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b"),
    "private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "database URL with credentials": re.compile(r"(?:mongodb(?:\+srv)?|postgres(?:ql)?|mysql)://[^/\s:@]+:[^/\s@]+@"),
}


def candidate_paths(root=ROOT):
    result = subprocess.run(
        ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"],
        cwd=root, check=True, capture_output=True,
    )
    return sorted(set(result.stdout.decode("utf-8").split("\0")) - {""})


def inspect_file(root, name):
    """Return only file/line/rule findings, without copying matching secrets."""
    path = root / name
    if not path.exists():
        return []
    findings = []
    try:
        path.resolve().relative_to(root.resolve())
    except ValueError:
        return [{"path": name, "rule": "path outside repository"}]
    if path.is_symlink():
        return [{"path": name, "rule": "symlink requires review before publication"}]
    parts = Path(name).parts
    is_example = path.name.endswith(".example")
    private_name = (
        (path.name.startswith(".env") and not is_example)
        or path.name in {"auth.json", "service-token", "application-key", "admin-credentials.json", "user-mapping.json"}
        or path.name.startswith("app-snapshot-")
    )
    generated = (
        parts[0] == "runtime"
        or tuple(parts[:2]) in {("data", "raw"), ("data", "processed"), ("web", "vendor"), ("web", "node_modules")}
        or (parts[0] in {"models", "experiments"} and name not in {"models/README.md", "models/.gitkeep", "experiments/README.md"})
    )
    if private_name or generated:
        findings.append({"path": name, "rule": "local configuration or generated artifact"})
    if path.suffix.lower() in {".npz", ".parquet", ".csv", ".sqlite", ".sqlite3", ".db", ".zip", ".rar", ".tar", ".pem", ".key"}:
        findings.append({"path": name, "rule": "binary data, archive or private key"})
    if path.stat().st_size > MAX_SOURCE_BYTES:
        return findings + [{"path": name, "rule": "source file exceeds 5 MiB"}]
    try:
        text = path.read_text(encoding="utf-8-sig")
    except (UnicodeError, OSError):
        return findings
    for number, line in enumerate(text.splitlines(), 1):
        for label, pattern in SECRET_PATTERNS.items():
            if pattern.search(line):
                findings.append({"path": name, "line": number, "rule": label})
    if path.suffix == ".ipynb":
        try:
            notebook = json.loads(text)
            if any(cell.get("outputs") or cell.get("execution_count") is not None
                   for cell in notebook.get("cells", []) if cell.get("cell_type") == "code"):
                findings.append({"path": name, "rule": "notebook contains execution outputs"})
        except ValueError:
            findings.append({"path": name, "rule": "invalid notebook JSON"})
    return findings


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--archive", type=Path, help="Optional new ZIP path inside runtime/")
    args = parser.parse_args()
    paths = [name for name in candidate_paths() if (ROOT / name).is_file()]
    findings = [finding for name in paths for finding in inspect_file(ROOT, name)]
    if findings:
        print(json.dumps({"ok": False, "findings": findings}, indent=2))
        return 1
    total = sum((ROOT / name).stat().st_size for name in paths)
    print(json.dumps({"ok": True, "files": len(paths), "bytes": total,
                      "notice": "Heuristic scan; review Git diff before publishing."}))
    if args.archive:
        destination = args.archive.resolve()
        try:
            destination.relative_to((ROOT / "runtime").resolve())
        except ValueError:
            parser.error("--archive must be inside runtime/ so the ZIP cannot be published accidentally")
        destination.parent.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(destination, "x", compression=zipfile.ZIP_DEFLATED) as archive:
            for name in paths:
                archive.write(ROOT / name, arcname=name)
        print("Source archive:", destination)
    return 0


if __name__ == "__main__":
    sys.exit(main())

