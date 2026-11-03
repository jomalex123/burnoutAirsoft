// Solo local: comprueba contadores reales con varios procesos PHP y limpia su fila ficticia.
const { spawn } = require('node:child_process');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const php = process.env.BURNOUT_TEST_PHP || 'D:/xampp/php/php.exe';
const cwd = path.join(__dirname, '..');
const identifier = 'audit-' + crypto.randomBytes(12).toString('hex');
const bootstrap = `<?php
require_once 'config/hq.php';
$config = burnout_database_config();
$environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true)) exit(2);
$identifier = '${identifier}';
`;
function run(code) {
    return new Promise((resolve, reject) => {
        const child = spawn(php, [], { cwd, stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true });
        let output = '', errors = '';
        child.stdout.on('data', part => { output += part; });
        child.stderr.on('data', part => { errors += part; });
        child.on('error', reject);
        child.on('close', code => code === 0 ? resolve(output.trim()) : reject(new Error('Fallo en comprobacion PHP local: ' + errors)));
        child.stdin.on('error', () => {});
        child.stdin.end(bootstrap + code);
    });
}
async function check() {
    try {
        const results = await Promise.allSettled(Array.from({ length: 8 }, () => run(
            `for ($i = 0; $i < 10; $i++) burnout_rate_limit_hit('hq_rate_audit', $identifier, 1000, 60, 60);`
        )));
        for (const result of results) if (result.status === 'rejected') throw result.reason;
        const count = await run(`$query = burnout_pdo()->prepare('SELECT attempts FROM rate_limits WHERE scope = ? AND identifier_hash = ?');
            $query->execute(['hq_rate_audit', burnout_rate_limit_hash($identifier)]); echo $query->fetchColumn();`);
        assert.equal(Number(count), 80);
        console.log('Rate limit: 80 intentos en 8 procesos, sin perder incrementos.');
    } finally {
        await run(`burnout_rate_limit_clear('hq_rate_audit', $identifier);`);
    }
}
check().catch(error => { console.error(error.message); process.exitCode = 1; });
