import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import sharp from 'sharp';
import { functionalRequirements } from './functional-requirements.mjs';

const root = process.cwd();
const outputDir = path.join(root, 'thesis-assets', 'diagrams', 'functional');
fs.mkdirSync(outputDir, { recursive: true });

const escapeXml = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;');

function wrapText(text, max = 32) {
  const words = text.split(/\s+/);
  const lines = [];
  let line = '';
  for (const word of words) {
    if (`${line} ${word}`.trim().length > max && line) {
      lines.push(line);
      line = word;
    } else {
      line = `${line} ${word}`.trim();
    }
  }
  if (line) lines.push(line);
  return lines;
}

function textBlock(text, x, y, max = 32, options = {}) {
  const lines = wrapText(text, max);
  const lineHeight = options.lineHeight ?? 27;
  const startY = y - ((lines.length - 1) * lineHeight) / 2;
  return `<text x="${x}" y="${startY}" text-anchor="middle" font-family="Times New Roman, serif" font-size="${options.size ?? 22}" font-weight="${options.bold ? '700' : '400'}" fill="#111827">${lines.map((line, index) => `<tspan x="${x}" dy="${index === 0 ? 0 : lineHeight}">${escapeXml(line)}</tspan>`).join('')}</text>`;
}

function activitySvg(requirement) {
  const width = 1200;
  const stepGap = 135;
  const firstY = 245;
  const endY = firstY + requirement.steps.length * stepGap + 40;
  const height = endY + 110;
  const centers = { actor: 300, system: 900 };
  const nodes = requirement.steps.map((step, index) => ({ ...step, number: index + 1, x: centers[step.lane], y: firstY + index * stepGap }));
  const arrow = (from, to, label = '') => {
    const fromY = from.y + (from.decision ? 55 : 48);
    const toY = to.y - (to.decision ? 55 : 48);
    const midY = Math.round((fromY + toY) / 2);
    const points = from.x === to.x
      ? `${from.x},${fromY} ${to.x},${toY}`
      : `${from.x},${fromY} ${from.x},${midY} ${to.x},${midY} ${to.x},${toY}`;
    return `<polyline points="${points}" fill="none" stroke="#111827" stroke-width="2.5" marker-end="url(#arrow)"/>${label ? `<text x="${to.x + 18}" y="${midY - 7}" font-family="Times New Roman, serif" font-size="18">${label}</text>` : ''}`;
  };

  const elements = [];
  elements.push(`<circle cx="${nodes[0].x}" cy="165" r="20" fill="#111827"/>`);
  elements.push(`<line x1="${nodes[0].x}" y1="185" x2="${nodes[0].x}" y2="${nodes[0].y - 48}" stroke="#111827" stroke-width="2.5" marker-end="url(#arrow)"/>`);

  nodes.forEach((node) => {
    if (node.decision) {
      elements.push(`<polygon points="${node.x},${node.y - 58} ${node.x + 118},${node.y} ${node.x},${node.y + 58} ${node.x - 118},${node.y}" fill="#fff" stroke="#111827" stroke-width="2.2"/>`);
      elements.push(textBlock(node.text, node.x, node.y, 25, { size: 20, lineHeight: 23 }));
    } else {
      elements.push(`<rect x="${node.x - 205}" y="${node.y - 48}" width="410" height="96" fill="#fff" stroke="#111827" stroke-width="2.2"/>`);
      elements.push(textBlock(node.text, node.x, node.y, 34, { size: 21, lineHeight: 25 }));
    }
    elements.push(`<circle cx="${node.x - 190}" cy="${node.y - 35}" r="14" fill="#e8f1e8" stroke="#315b42"/><text x="${node.x - 190}" y="${node.y - 29}" text-anchor="middle" font-family="Arial" font-size="15" font-weight="700">${node.number}</text>`);
  });

  for (let index = 0; index < nodes.length - 1; index += 1) {
    elements.push(arrow(nodes[index], nodes[index + 1], nodes[index].decision ? 'Ya' : ''));
  }

  nodes.filter((node) => node.decision && node.loopTo).forEach((node) => {
    const target = nodes[node.loopTo - 1];
    const outerX = node.lane === 'system' ? 1170 : 30;
    const startX = node.lane === 'system' ? node.x + 118 : node.x - 118;
    const targetX = target.lane === 'system' ? target.x + 205 : target.x - 205;
    const path = `M ${startX} ${node.y} L ${outerX} ${node.y} L ${outerX} ${target.y} L ${targetX} ${target.y}`;
    elements.push(`<path d="${path}" fill="none" stroke="#8b1e1e" stroke-width="2.2" stroke-dasharray="8 6" marker-end="url(#arrowRed)"/>`);
    elements.push(`<text x="${outerX + (node.lane === 'system' ? -18 : 18)}" y="${node.y - 10}" text-anchor="${node.lane === 'system' ? 'end' : 'start'}" font-family="Times New Roman, serif" font-size="18" fill="#8b1e1e">Tidak</text>`);
  });

  const last = nodes.at(-1);
  const lastBottom = last.y + (last.decision ? 58 : 48);
  elements.push(`<line x1="${last.x}" y1="${lastBottom}" x2="${last.x}" y2="${endY - 25}" stroke="#111827" stroke-width="2.5" marker-end="url(#arrow)"/>`);
  elements.push(`<circle cx="${last.x}" cy="${endY}" r="24" fill="#fff" stroke="#111827" stroke-width="3"/><circle cx="${last.x}" cy="${endY}" r="16" fill="#111827"/>`);

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
    <defs>
      <marker id="arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#111827"/></marker>
      <marker id="arrowRed" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#8b1e1e"/></marker>
    </defs>
    <rect width="1200" height="${height}" fill="#fff"/>
    <rect x="20" y="20" width="1160" height="${height - 40}" fill="#fff" stroke="#111827" stroke-width="2.5"/>
    <line x1="20" y1="80" x2="1180" y2="80" stroke="#111827" stroke-width="2"/>
    <line x1="20" y1="135" x2="1180" y2="135" stroke="#111827" stroke-width="2"/>
    <line x1="600" y1="80" x2="600" y2="${height - 20}" stroke="#111827" stroke-width="2"/>
    ${textBlock(`${requirement.code} — ${requirement.title}`, 600, 57, 80, { size: 25 })}
    ${textBlock(requirement.actor, 300, 115, 40, { size: 22, bold: true })}
    ${textBlock('Sistem', 900, 115, 30, { size: 22, bold: true })}
    ${elements.join('\n')}
  </svg>`;
}

function sequenceMermaid(requirement) {
  const participantLines = requirement.participants.map(([type, id, label]) => `${type} ${id} as ${label}`);
  const messageLines = requirement.messages.map(([from, to, text], index) => `${from}${index >= requirement.messages.length - 2 ? '-->>' : '->>'}${to}: ${text}`);
  return [
    'sequenceDiagram',
    'autonumber',
    ...participantLines,
    ...messageLines,
  ].join('\n');
}

async function renderMermaid(code, outputPath) {
  const payload = { code, mermaid: { theme: 'neutral' } };
  const encoded = zlib.deflateSync(JSON.stringify(payload), { level: 9 }).toString('base64url');
  const url = `https://mermaid.ink/img/pako:${encoded}?type=png&width=2400`;
  const response = await fetch(url, { headers: { 'User-Agent': 'Mozilla/5.0' } });
  if (!response.ok) throw new Error(`Mermaid render failed: ${response.status} ${await response.text()}`);
  const buffer = Buffer.from(await response.arrayBuffer());
  if (!buffer.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) {
    throw new Error(`Mermaid output is not PNG: ${outputPath}`);
  }
  fs.writeFileSync(outputPath, buffer);
}

for (const requirement of functionalRequirements) {
  const suffix = requirement.code.toLowerCase();
  const activityPath = path.join(outputDir, `activity-${suffix}.png`);
  await sharp(Buffer.from(activitySvg(requirement))).png().toFile(activityPath);

  const mermaid = sequenceMermaid(requirement);
  fs.writeFileSync(path.join(outputDir, `sequence-${suffix}.mmd`), mermaid, 'utf8');
  await renderMermaid(mermaid, path.join(outputDir, `sequence-${suffix}.png`));
  process.stdout.write(`${requirement.code} `);
}

async function contactSheet(kind) {
  const files = functionalRequirements.map((requirement) => path.join(outputDir, `${kind}-${requirement.code.toLowerCase()}.png`));
  const thumbWidth = 420;
  const thumbHeight = 360;
  const columns = 3;
  const rows = Math.ceil(files.length / columns);
  const composites = [];
  for (let index = 0; index < files.length; index += 1) {
    const input = await sharp(files[index]).resize(thumbWidth - 20, thumbHeight - 40, { fit: 'contain', background: '#ffffff' }).png().toBuffer();
    composites.push({ input, left: (index % columns) * thumbWidth + 10, top: Math.floor(index / columns) * thumbHeight + 30 });
  }
  await sharp({ create: { width: columns * thumbWidth, height: rows * thumbHeight, channels: 3, background: '#f3f4f6' } })
    .composite(composites)
    .png()
    .toFile(path.join(outputDir, `contact-${kind}.png`));
}

await contactSheet('activity');
await contactSheet('sequence');
console.log(`\nGenerated ${functionalRequirements.length * 2} diagrams in ${outputDir}`);
