// ===== FRAME: COMMON RENDERING LOGIC =====

function getNodeEl(id) {
  return document.getElementById('node-' + id);
}

function pctX(pct, containerW) {
  return (pct / 100) * containerW;
}
function pctY(pct, containerH) {
  return (pct / 100) * containerH;
}
function pctW(pct, containerW) {
  return Math.max(120, (pct / 100) * containerW);
}
function pctH(containerH) {
  return Math.max(50, containerH * 0.09);
}

function renderNodes(DATA) {
  const container = document.getElementById('graphArea');
  const cw = container.clientWidth;
  const ch = container.clientHeight;
  const wrapper = document.getElementById('nodesContainer');
  wrapper.innerHTML = '';
  for (const pkg of DATA.packages) {
    const div = document.createElement('div');
    div.className = `node cat-${pkg.cat}`;
    div.id = 'node-' + pkg.id;
    const px = pctX(pkg.x, cw);
    const py = pctY(pkg.y, ch);
    const pw = pctW(pkg.w || 16, cw);
    const ph = pctH(ch);
    div.style.left = px + 'px';
    div.style.top = py + 'px';
    div.style.width = pw + 'px';
    div.dataset.pkg = pkg.id;
    div.dataset.x = pkg.x;
    div.dataset.y = pkg.y;
    div.dataset.w = pkg.w || 16;
    div.innerHTML = `
      <div class="node-inner">
        <div class="node-name"><span class="emoji">${pkg.emoji}</span>${pkg.name}</div>
        <div class="node-desc">${pkg.desc}</div>
        ${pkg.tags ? `<div class="node-tags">${pkg.tags.map(t => `<span class="node-tag">${t}</span>`).join('')}</div>` : ''}
      </div>`;
    wrapper.appendChild(div);
  }
  window._nodePixels = {};
  for (const pkg of DATA.packages) {
    const el = getNodeEl(pkg.id);
    if (el) {
      window._nodePixels[pkg.id] = {
        x: parseFloat(el.style.left),
        y: parseFloat(el.style.top),
        w: el.offsetWidth,
        h: el.offsetHeight
      };
    }
  }
}

function getNodeCenter(id) {
  const p = window._nodePixels && window._nodePixels[id];
  if (!p) return { x: 0, y: 0 };
  return { x: p.x + p.w / 2, y: p.y + p.h / 2 };
}

function getEdgePoint(fromId, toId) {
  if (fromId === toId) {
    const p = window._nodePixels && window._nodePixels[fromId];
    if (!p) return { x1: 0, y1: 0, x2: 0, y2: 0, isSelf: true };
    return {
      x1: p.x + p.w * 0.25, y1: p.y,
      x2: p.x + p.w * 0.75, y2: p.y,
      cx1: p.x + p.w * 0.1, cy1: p.y - 28,
      cx2: p.x + p.w * 0.9, cy2: p.y - 28,
      isSelf: true
    };
  }
  const fp = window._nodePixels && window._nodePixels[fromId];
  const tp = window._nodePixels && window._nodePixels[toId];
  if (!fp || !tp) return { x1: 0, y1: 0, x2: 0, y2: 0, isSelf: false };
  const cx1 = fp.x + fp.w / 2, cy1 = fp.y + fp.h / 2;
  const cx2 = tp.x + tp.w / 2, cy2 = tp.y + tp.h / 2;
  const dx = cx2 - cx1, dy = cy2 - cy1;
  const angle = Math.atan2(dy, dx);
  const padH = fp.w / 2 + 5, padV = fp.h / 2 + 5;
  let ex1, ey1, ex2, ey2;
  if (Math.abs(dx) > Math.abs(dy) * 0.8) {
    ex1 = cx1 + (dx > 0 ? padH : -padH);
    ey1 = cy1 + Math.tan(angle) * (dx > 0 ? padH : -padH);
    ey1 = Math.max(fp.y + 3, Math.min(fp.y + fp.h - 3, ey1));
    ex2 = cx2 + (dx > 0 ? -padH : padH);
    ey2 = cy2 - Math.tan(angle) * (dx > 0 ? padH : -padH);
    ey2 = Math.max(tp.y + 3, Math.min(tp.y + tp.h - 3, ey2));
  } else {
    ey1 = cy1 + (dy > 0 ? padV : -padV);
    ex1 = cx1 + (dx / Math.abs(dy || 1)) * (dy > 0 ? padV : -padV);
    ex1 = Math.max(fp.x + 3, Math.min(fp.x + fp.w - 3, ex1));
    ey2 = cy2 + (dy > 0 ? -padV : padV);
    ex2 = cx2 - (dx / Math.abs(dy || 1)) * (dy > 0 ? padV : -padV);
    ex2 = Math.max(tp.x + 3, Math.min(tp.x + tp.w - 3, ex2));
  }
  return { x1: ex1, y1: ey1, x2: ex2, y2: ey2, isSelf: false };
}

function buildUniqueEdges(DATA) {
  const edges = new Map();
  for (const flow of DATA.flows) {
    for (const step of flow.steps) {
      if (step.from !== step.to) {
        const key = step.from + '->' + step.to;
        edges.set(key, { from: step.from, to: step.to });
      }
    }
  }
  return edges;
}

function renderConnections(DATA, activeFlowId) {
  const svg = document.getElementById('connectionsSvg');
  const container = document.getElementById('graphArea');
  svg.setAttribute('width', container.clientWidth);
  svg.setAttribute('height', container.clientHeight);
  svg.innerHTML = '';

  const activeEdges = new Map();
  if (activeFlowId) {
    const flow = DATA.flows.find(f => f.id === activeFlowId);
    if (flow) {
      flow.steps.forEach((step, i) => {
        if (step.from !== step.to) {
          const key = step.from + '->' + step.to;
          const colors = ['active', 'active-amber', 'active-emerald', 'active-purple', 'active-rose', 'active-cyan'];
          if (!activeEdges.has(key)) activeEdges.set(key, colors[i % colors.length]);
        }
      });
    }
  }

  const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
  svg.appendChild(defs);
  const mBase = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
  mBase.setAttribute('id', 'arrowhead');
  mBase.setAttribute('markerWidth', '7');
  mBase.setAttribute('markerHeight', '5');
  mBase.setAttribute('refX', '7');
  mBase.setAttribute('refY', '2.5');
  mBase.setAttribute('orient', 'auto');
  const pBase = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
  pBase.setAttribute('points', '0 0, 7 2.5, 0 5');
  pBase.setAttribute('class', 'arrow');
  mBase.appendChild(pBase);
  defs.appendChild(mBase);
  for (const cls of ['active','active-amber','active-emerald','active-purple','active-rose','active-cyan']) {
    const m = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
    m.setAttribute('id', 'arrowhead-' + cls);
    m.setAttribute('markerWidth', '7');
    m.setAttribute('markerHeight', '5');
    m.setAttribute('refX', '7');
    m.setAttribute('refY', '2.5');
    m.setAttribute('orient', 'auto');
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    p.setAttribute('points', '0 0, 7 2.5, 0 5');
    p.setAttribute('class', 'arrow ' + cls);
    m.appendChild(p);
    defs.appendChild(m);
  }

  const allEdges = buildUniqueEdges(DATA);
  for (const [key, edge] of allEdges) {
    const ep = getEdgePoint(edge.from, edge.to);
    const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    let path;
    if (ep.isSelf) {
      path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${ep.x1} ${ep.y1} C ${ep.cx1} ${ep.cy1}, ${ep.cx2} ${ep.cy2}, ${ep.x2} ${ep.y2}`);
    } else {
      const dx = ep.x2 - ep.x1, dy = ep.y2 - ep.y1;
      const cpx = (ep.x1 + ep.x2) / 2;
      const cpy = (ep.y1 + ep.y2) / 2;
      const dist = Math.sqrt(dx * dx + dy * dy);
      const bend = Math.min(25, dist * 0.12);
      const nx = -dy / (dist || 1) * bend;
      const ny = dx / (dist || 1) * bend;
      path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${ep.x1} ${ep.y1} Q ${cpx + nx} ${cpy + ny}, ${ep.x2} ${ep.y2}`);
    }
    path.setAttribute('class', 'connection');
    path.setAttribute('data-edge', key);
    if (activeEdges.has(key)) {
      path.classList.add(activeEdges.get(key));
      path.classList.add('animated');
      path.setAttribute('marker-end', 'url(#arrowhead-' + activeEdges.get(key) + ')');
    } else {
      path.setAttribute('marker-end', 'url(#arrowhead)');
      path.classList.add('dimmed');
    }
    g.appendChild(path);

    if (activeEdges.has(key)) {
      const flow = DATA.flows.find(f => f.id === activeFlowId);
      const matchingSteps = flow.steps.filter(s => s.from + '->' + s.to === key);
      if (matchingSteps.length > 0) {
        const labels = matchingSteps.map(s => s.label.length > 24 ? s.label.slice(0,22) + '…' : s.label);
        const dx = ep.x2 - ep.x1, dy = ep.y2 - ep.y1;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const bend = Math.min(25, dist * 0.12);
        const nx = -dy / (dist || 1) * bend;
        const ny = dx / (dist || 1) * bend;
        const mx = (ep.x1 + ep.x2) / 2 + nx;
        const my = (ep.y1 + ep.y2) / 2 + ny - 9;
        const textLen = Math.max(...labels.map(l => l.length));
        const tw = textLen * 5.5 + 8;
        const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bg.setAttribute('class', 'edge-label-bg');
        bg.setAttribute('x', mx - tw / 2);
        bg.setAttribute('y', my - 7);
        bg.setAttribute('width', tw);
        bg.setAttribute('height', '16');
        bg.setAttribute('rx', '3');
        bg.style.fill = 'rgba(11,11,18,0.85)';
        bg.style.stroke = 'rgba(59,130,246,0.3)';
        bg.style.strokeWidth = '0.5';
        g.appendChild(bg);
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('class', 'edge-label active');
        text.setAttribute('x', mx);
        text.setAttribute('y', my + 4);
        text.textContent = labels.join(' / ');
        g.appendChild(text);
      }
    }

    svg.appendChild(g);
  }
}

function renderFlows(DATA, activeFlowId, filter) {
  const list = document.getElementById('flowList');
  list.innerHTML = '';
  const cats = {};
  for (const flow of DATA.flows) {
    if (filter) {
      const lf = flow.name.toLowerCase() + ' ' + flow.desc.toLowerCase();
      if (!lf.includes(filter.toLowerCase())) continue;
    }
    if (!cats[flow.cat]) cats[flow.cat] = [];
    cats[flow.cat].push(flow);
  }
  for (const [cat, flows] of Object.entries(cats)) {
    const hdr = document.createElement('div');
    hdr.className = 'flow-category';
    hdr.textContent = cat;
    list.appendChild(hdr);
    flows.forEach((flow, idx) => {
      const item = document.createElement('div');
      item.className = 'flow-item' + (activeFlowId === flow.id ? ' active' : '');
      item.dataset.flowId = flow.id;
      item.onclick = () => window.selectFlow(flow.id);
      const dot = document.createElement('span');
      dot.className = 'dot';
      const colors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#f43f5e','#06b6d4'];
      dot.style.background = colors[idx % colors.length];
      item.appendChild(dot);
      const name = document.createElement('span');
      name.textContent = flow.name;
      item.appendChild(name);
      const badge = document.createElement('span');
      badge.className = 'badge';
      badge.textContent = flow.steps.length + ' steps';
      item.appendChild(badge);
      list.appendChild(item);
    });
  }
  document.getElementById('statsDisplay').textContent =
    `${DATA.packages.length} packages · ${DATA.flows.length} workflows`;
}

function filterFlows(DATA, activeFlowId, val) {
  renderFlows(DATA, activeFlowId, val);
  if (activeFlowId) {
    const el = document.querySelector(`.flow-item[data-flow-id="${activeFlowId}"]`);
    if (el) el.classList.add('active');
  }
}

function highlightFlow(DATA, flowId) {
  const flow = DATA.flows.find(f => f.id === flowId);
  if (!flow) return;
  const activeNodes = new Set();
  flow.steps.forEach(s => { activeNodes.add(s.from); activeNodes.add(s.to); });
  document.querySelectorAll('.node').forEach(el => el.classList.add('dimmed'));
  for (const id of activeNodes) {
    const el = getNodeEl(id);
    if (el) { el.classList.remove('dimmed'); el.classList.add('active', 'pulse'); }
  }
}

function clearFlowHighlight() {
  document.querySelectorAll('.node').forEach(el => el.classList.remove('dimmed', 'active', 'pulse'));
  document.querySelectorAll('.connection').forEach(el => {
    el.classList.remove('active', 'active-amber', 'active-emerald', 'active-purple', 'active-rose', 'active-cyan', 'animated', 'dimmed');
  });
}
