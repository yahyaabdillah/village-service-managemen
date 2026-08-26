import { spawn } from 'node:child_process';
import fs from 'node:fs';

const {
  DETACHED_COMMAND_JSON,
  DETACHED_WORKING_DIRECTORY,
  DETACHED_STDOUT,
  DETACHED_STDERR,
  ...childEnvironment
} = process.env;

if (
  !DETACHED_COMMAND_JSON ||
  !DETACHED_WORKING_DIRECTORY ||
  !DETACHED_STDOUT ||
  !DETACHED_STDERR
) {
  throw new Error('Konfigurasi detached process tidak lengkap.');
}

const command = JSON.parse(DETACHED_COMMAND_JSON);
if (!Array.isArray(command) || !command.length) {
  throw new Error('Perintah detached process tidak valid.');
}

const stdout = fs.openSync(DETACHED_STDOUT, 'a');
const stderr = fs.openSync(DETACHED_STDERR, 'a');
const child = spawn(command[0], command.slice(1), {
  cwd: DETACHED_WORKING_DIRECTORY,
  env: childEnvironment,
  detached: true,
  windowsHide: true,
  stdio: ['ignore', stdout, stderr],
});

await new Promise((resolve, reject) => {
  child.once('spawn', resolve);
  child.once('error', reject);
});

child.unref();
fs.closeSync(stdout);
fs.closeSync(stderr);
process.stdout.write(String(child.pid));
