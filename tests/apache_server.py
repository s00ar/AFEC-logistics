"""Local Apache + FastCGI test harness. No changes to the installed server configuration."""
from pathlib import Path
import os
import socket
import subprocess


def start_apache(root, apache_root, php, port, env, private, output, flags):
    apache_root = Path(apache_root).resolve()
    executable = apache_root / ('bin/httpd.exe' if os.name == 'nt' else 'bin/httpd')
    cgi = Path(php).with_name('php-cgi.exe' if os.name == 'nt' else 'php-cgi')
    if not executable.is_file() or not cgi.is_file():
        raise RuntimeError('Apache and PHP FastCGI executables are required for repository-root tests.')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        cgi_port = sock.getsockname()[1]
    modules = ['authz_core', 'authz_host', 'dir', 'mime', 'rewrite', 'proxy', 'proxy_fcgi']
    config = private / 'apache-test.conf'
    loads = '\n'.join(f'LoadModule {name}_module "{apache_root.as_posix()}/modules/mod_{name}.so"' for name in modules)
    config.write_text(f'''ServerRoot "{apache_root.as_posix()}"
Listen 127.0.0.1:{port}
ServerName 127.0.0.1
PidFile "{private.as_posix()}/apache.pid"
ErrorLog "{private.as_posix()}/apache-error.log"
{loads}
TypesConfig "{apache_root.as_posix()}/conf/mime.types"
ProxyFCGIBackendType GENERIC
ProxyFCGISetEnvIf "reqenv('SCRIPT_FILENAME') =~ m|^/([A-Za-z]:/.*)$|" SCRIPT_FILENAME "$1"
DocumentRoot "{root.as_posix()}"
<Directory />
    Require all denied
</Directory>
<Directory "{root.as_posix()}">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    <FilesMatch "\\.php$">
        SetHandler "proxy:fcgi://127.0.0.1:{cgi_port}/"
    </FilesMatch>
</Directory>
''', encoding='utf-8')
    checked = subprocess.run([str(executable), '-t', '-f', str(config)], env=env, capture_output=True, text=True, creationflags=flags)
    if checked.returncode:
        raise RuntimeError(checked.stdout + checked.stderr)
    processes = []
    try:
        processes.append(subprocess.Popen([str(cgi), '-b', f'127.0.0.1:{cgi_port}'], cwd=root, env=env, stdout=output, stderr=output, creationflags=flags))
        processes.append(subprocess.Popen([str(executable), '-X', '-f', str(config)], cwd=root, env=env, stdout=output, stderr=output, creationflags=flags))
        return processes
    except Exception:
        stop_processes(processes)
        raise


def stop_processes(processes):
    for process in reversed(processes):
        if process.poll() is None:
            process.terminate()
        process.wait(timeout=10)
