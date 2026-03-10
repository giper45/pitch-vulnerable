from __future__ import annotations

import contextlib
import http.cookiejar
import os
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parents[1]
APP_DIR = ROOT_DIR / 'wasm-vulnerable-app'
DEFAULT_EXTERNAL_BASE_URL = 'http://127.0.0.1:9999'


@dataclass
class HttpResponse:
    status: int
    body: str
    headers: dict


class WebClient:
    def __init__(self, base_url: str):
        self.base_url = base_url.rstrip('/')
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path: str, method: str = 'GET', data: dict | None = None, timeout: float = 8.0) -> HttpResponse:
        url = f"{self.base_url}{path}"
        headers = {'User-Agent': 'pytest-pitch-vulnerable'}

        payload = None
        if data is not None:
            payload = urllib.parse.urlencode(data).encode('utf-8')
            headers['Content-Type'] = 'application/x-www-form-urlencoded'

        req = urllib.request.Request(url, data=payload, headers=headers, method=method)

        try:
            with self.opener.open(req, timeout=timeout) as resp:
                raw = resp.read()
                return HttpResponse(
                    status=resp.getcode(),
                    body=raw.decode('utf-8', errors='ignore'),
                    headers=dict(resp.headers.items()),
                )
        except urllib.error.HTTPError as e:
            raw = e.read()
            return HttpResponse(
                status=e.code,
                body=raw.decode('utf-8', errors='ignore'),
                headers=dict(e.headers.items()) if e.headers else {},
            )


def _pick_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(('127.0.0.1', 0))
        return int(s.getsockname()[1])


def _is_server_up(base_url: str, timeout: float = 1.5) -> bool:
    try:
        with urllib.request.urlopen(f"{base_url}/index.php", timeout=timeout) as resp:
            return resp.getcode() in (200, 302)
    except Exception:
        return False


def _wait_server_ready(base_url: str, timeout_s: float = 20.0) -> None:
    deadline = time.time() + timeout_s
    while time.time() < deadline:
        if _is_server_up(base_url):
            return
        time.sleep(0.2)
    raise TimeoutError(f"Server not ready after {timeout_s} seconds: {base_url}")


@contextlib.contextmanager
def run_app_server() -> str:
    external = os.environ.get('PV_BASE_URL', '').strip() or DEFAULT_EXTERNAL_BASE_URL

    # Prefer already running Wasmer app (make run)
    if _is_server_up(external):
        yield external
        return

    # Fallback: run local PHP dev server in project app dir
    port = _pick_free_port()
    base_url = f"http://127.0.0.1:{port}"

    cmd = ['php', '-t', 'app', '-S', f'127.0.0.1:{port}', 'app/bootstrap.php']
    proc = subprocess.Popen(
        cmd,
        cwd=str(APP_DIR),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        env=os.environ.copy(),
    )

    try:
        _wait_server_ready(base_url)
        yield base_url
    finally:
        if proc.poll() is None:
            proc.terminate()
            try:
                proc.wait(timeout=4)
            except subprocess.TimeoutExpired:
                proc.kill()
                proc.wait(timeout=4)
