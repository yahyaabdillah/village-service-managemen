import express from 'express';
import fs from 'fs';
import path from 'path';
import makeWASocket, {
  Browsers,
  DisconnectReason,
  useMultiFileAuthState,
} from 'baileys';
import pino from 'pino';
import QRCode from 'qrcode';
import qrcodeTerminal from 'qrcode-terminal';

const app = express();
const port = Number(process.env.WA_BRIDGE_PORT || 3100);
const token = process.env.WHATSAPP_BRIDGE_TOKEN || '';
const storageDir = path.resolve(
  process.env.WA_BRIDGE_STORAGE || 'storage/app/private/whatsapp',
);
const authDir = path.join(storageDir, 'session-baileys');
const statusFile = path.join(storageDir, 'status.json');
const qrFile = path.join(storageDir, 'qr.txt');
const qrImageFile = path.join(storageDir, 'qr.png');
const logger = pino({ level: process.env.WA_BRIDGE_LOG_LEVEL || 'silent' });
const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;

fs.mkdirSync(storageDir, { recursive: true });

let socket = null;
let ready = false;
let connecting = null;
let reconnectTimer = null;
let shuttingDown = false;

const writeStatus = (payload) => {
  const current = fs.existsSync(statusFile)
    ? JSON.parse(fs.readFileSync(statusFile, 'utf8'))
    : {};

  fs.writeFileSync(
    statusFile,
    JSON.stringify(
      {
        ...current,
        ...payload,
        provider: 'baileys',
        updated_at: new Date().toISOString(),
      },
      null,
      2,
    ),
  );
};

const clearQr = () => {
  for (const file of [qrFile, qrImageFile]) {
    if (fs.existsSync(file)) fs.unlinkSync(file);
  }
};

const disconnectCode = (error) =>
  error?.output?.statusCode || error?.data?.statusCode || error?.statusCode;

const scheduleReconnect = () => {
  if (shuttingDown || reconnectTimer) return;

  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    connect().catch((error) => {
      writeStatus({
        ready: false,
        state: 'error',
        error: error instanceof Error ? error.message : String(error),
      });
      scheduleReconnect();
    });
  }, 3000);
};

async function connect() {
  if (connecting) return connecting;

  connecting = (async () => {
    writeStatus({ ready: false, state: 'starting', error: null });
    const { state, saveCreds } = await useMultiFileAuthState(authDir);

    const nextSocket = makeWASocket({
      auth: state,
      browser: Browsers.ubuntu('Layanan Desa'),
      logger,
      markOnlineOnConnect: false,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
    });

    socket = nextSocket;
    nextSocket.ev.on('creds.update', saveCreds);
    nextSocket.ev.on(
      'connection.update',
      async ({ connection, lastDisconnect, qr }) => {
        if (socket !== nextSocket) return;

        if (qr) {
          ready = false;
          fs.writeFileSync(qrFile, qr);
          await QRCode.toFile(qrImageFile, qr, {
            errorCorrectionLevel: 'L',
            margin: 2,
            width: 320,
          });
          writeStatus({ ready: false, state: 'qr', error: null });
          qrcodeTerminal.generate(qr, { small: true });
        }

        if (connection === 'open') {
          ready = true;
          clearQr();
          writeStatus({ ready: true, state: 'ready', error: null });
        }

        if (connection === 'close') {
          ready = false;
          const code = disconnectCode(lastDisconnect?.error);
          const loggedOut = code === DisconnectReason.loggedOut;
          socket = null;
          writeStatus({
            ready: false,
            state: loggedOut ? 'logged_out' : 'disconnected',
            reason: code || 'unknown',
          });

          if (loggedOut) {
            clearQr();
          } else {
            scheduleReconnect();
          }
        }
      },
    );
  })();

  try {
    await connecting;
  } finally {
    connecting = null;
  }
}

const normalizePhone = (phone) => {
  let normalized = String(phone || '').replace(/\D/g, '');
  if (normalized.startsWith('0')) normalized = `62${normalized.slice(1)}`;
  if (!normalized || normalized.length < 8 || normalized.length > 18) {
    throw new Error('Nomor WhatsApp tidak valid.');
  }

  return normalized;
};

app.use(express.json({ limit: '8mb' }));
app.use((req, res, next) => {
  if (token && req.headers.authorization !== `Bearer ${token}`) {
    return res.status(401).json({ ok: false, error: 'Unauthorized' });
  }
  return next();
});

app.get('/status', (_req, res) => {
  const status = fs.existsSync(statusFile)
    ? JSON.parse(fs.readFileSync(statusFile, 'utf8'))
    : { ready: false, state: 'not_started', provider: 'baileys' };

  res.json({ ...status, running: true });
});

app.post('/disconnect', async (_req, res) => {
  shuttingDown = true;
  ready = false;
  if (reconnectTimer) clearTimeout(reconnectTimer);

  try {
    const activeSocket = socket;
    if (activeSocket) {
      await activeSocket.logout();
      activeSocket.ev.removeAllListeners('creds.update');
    }

    socket = null;
    clearQr();
    fs.rmSync(authDir, { recursive: true, force: true });
    writeStatus({
      ready: false,
      state: 'disconnected',
      error: null,
      reason: null,
    });
    res.json({ ok: true });

    setImmediate(() => {
      server.close(() => process.exit(0));
      setTimeout(() => process.exit(0), 3000).unref();
    });
  } catch (error) {
    shuttingDown = false;
    const message = error instanceof Error ? error.message : String(error);
    writeStatus({ ready: false, state: 'error', error: message });
    res.status(500).json({ ok: false, error: message });
  }
});

app.post('/send-message', async (req, res) => {
  try {
    const { phone, message } = req.body || {};
    if (!phone || typeof message !== 'string' || !message.trim()) {
      return res
        .status(422)
        .json({ ok: false, error: 'phone and message are required' });
    }
    if (!ready || !socket) {
      return res
        .status(503)
        .json({ ok: false, error: 'WhatsApp belum terhubung.' });
    }

    const jid = `${normalizePhone(phone)}@s.whatsapp.net`;
    await socket.sendMessage(jid, { text: message.trim() });

    return res.json({ ok: true });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    writeStatus({ error: message });
    return res.status(500).json({ ok: false, error: message });
  }
});

app.post('/send-document', async (req, res) => {
  try {
    const {
      phone,
      filename,
      mime_type: mimeType,
      document,
      caption,
    } = req.body || {};

    if (
      !phone ||
      typeof filename !== 'string' ||
      !filename.trim() ||
      filename.length > 255 ||
      typeof mimeType !== 'string' ||
      !mimeType.trim() ||
      typeof document !== 'string' ||
      !document.trim()
    ) {
      return res.status(422).json({
        ok: false,
        error: 'phone, filename, mime_type, and document are required',
      });
    }
    if (!ready || !socket) {
      return res
        .status(503)
        .json({ ok: false, error: 'WhatsApp belum terhubung.' });
    }
    if (
      document.length > Math.ceil((MAX_DOCUMENT_BYTES * 4) / 3) + 4 ||
      !/^[A-Za-z0-9+/]*={0,2}$/.test(document)
    ) {
      return res.status(413).json({ ok: false, error: 'Dokumen terlalu besar atau tidak valid.' });
    }

    const contents = Buffer.from(document, 'base64');
    if (!contents.length || contents.length > MAX_DOCUMENT_BYTES) {
      return res.status(413).json({ ok: false, error: 'Dokumen terlalu besar atau tidak valid.' });
    }

    const safeFilename = path
      .basename(filename.trim())
      .replace(/[\r\n\0]/g, '')
      .slice(0, 255);
    if (!safeFilename) {
      return res.status(422).json({ ok: false, error: 'Nama dokumen tidak valid.' });
    }

    const jid = `${normalizePhone(phone)}@s.whatsapp.net`;
    await socket.sendMessage(jid, {
      document: contents,
      fileName: safeFilename,
      mimetype: mimeType.trim(),
      caption: typeof caption === 'string' ? caption.trim().slice(0, 1024) : '',
    });

    return res.json({ ok: true });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    writeStatus({ error: message });
    return res.status(500).json({ ok: false, error: message });
  }
});

app.use((error, _req, res, next) => {
  if (error?.type === 'entity.too.large') {
    return res.status(413).json({ ok: false, error: 'Dokumen terlalu besar.' });
  }
  if (error instanceof SyntaxError && 'body' in error) {
    return res.status(400).json({ ok: false, error: 'JSON body tidak valid.' });
  }

  return next(error);
});

const host = process.env.WA_BRIDGE_HOST || '0.0.0.0';
const server = app.listen(port, host, () => {
  console.log(`Baileys bridge listening on http://${host}:${port}`);
  connect().catch((error) => {
    writeStatus({
      ready: false,
      state: 'error',
      error: error instanceof Error ? error.message : String(error),
    });
    scheduleReconnect();
  });
});

const shutdown = async () => {
  if (shuttingDown) return;
  shuttingDown = true;
  ready = false;
  if (reconnectTimer) clearTimeout(reconnectTimer);
  writeStatus({ ready: false, state: 'stopped' });
  socket?.end?.(new Error('Bridge stopped'));
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(0), 5000).unref();
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
process.on('uncaughtException', (error) => {
  writeStatus({ ready: false, state: 'error', error: error.message });
  console.error(error);
});
process.on('unhandledRejection', (error) => {
  const message = error instanceof Error ? error.message : String(error);
  writeStatus({ ready: false, state: 'error', error: message });
  console.error(error);
});
